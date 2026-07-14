<?php
/**
 * Plugin Name: Port Libs Document Importer
 * Description: Imports uploaded documents into WordPress block markup, with browser-assisted PDF figure rendering.
 * Version: 0.3.0
 */

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\PandocMediaExtractor;
use PortLibs\Pandoc\ZipPackage;

if (!defined('ABSPATH')) {
    exit;
}

const PLPC_MAX_COLLECTION_FILES = 200;
const PLPC_MAX_COLLECTION_TOTAL_BYTES = 90000000;
const PLPC_MAX_COLLECTION_FILE_BYTES = 25000000;
const PLPC_MAX_STAGED_UPLOAD_BYTES = 90000000;
const PLPC_MAX_LEGACY_JSON_SOURCE_BYTES = PLPC_MAX_STAGED_UPLOAD_BYTES;
const PLPC_STAGED_UPLOAD_DIRECTORY = '/tmp/port-libs-converter';
const PLPC_MAX_PDF_RASTER_IMAGES = 96;
const PLPC_MAX_PDF_RASTER_BYTES = 24000000;
const PLPC_MAX_PDF_RASTER_IMAGE_BYTES = 16777216;
const PLPC_IMPORT_JOB_OPTION_PREFIX = 'plpc_import_job_';
const PLPC_IMPORT_JOB_DIRECTORY = 'port-libs-import-jobs';
const PLPC_IMPORT_JOB_MAX_EVENTS = 80;
const PLPC_IMPORT_JOB_MAX_FORM_RENDERS = 48;
const PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES = 24000000;
const PLPC_IMPORT_JOB_MAX_FORM_RENDER_IMAGE_BYTES = 16777216;
const PLPC_IMPORT_JOB_MAX_FORM_RENDER_PIXELS = 48000000;
const PLPC_IMPORT_JOB_MAX_RENDER_SOURCE_BYTES = 25000000;
const PLPC_IMPORT_JOB_VERSION = 1;
// Leave enough time to write the durable job option and JSON response before
// a normal shared host's max_execution_time terminates the PHP worker.
const PLPC_IMPORT_REQUEST_DEFAULT_RESERVE_SECONDS = 3.0;
// A whole-document reader is not resumable yet.  Retrying it indefinitely
// after a deadline or a hard worker termination would just spin the browser
// and can create duplicate side effects in later conversion phases.
const PLPC_IMPORT_JOB_MAX_DEADLINE_YIELDS_PER_DOCUMENT = 3;
const PLPC_IMPORT_JOB_MAX_INTERRUPTED_RETRIES_PER_DOCUMENT = 2;

/**
 * Used only for an intentional, already-persisted request handoff.  It must
 * not be treated as an import failure: the browser will issue the next
 * /advance request and the job snapshot explains what happened.
 */
final class PlpcImportCheckpointYield extends RuntimeException
{
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'PortLibs\\Pandoc\\' => __DIR__ . '/lanes/pandoc/src/',
        'PortLibs\\MarkerPDF\\' => __DIR__ . '/lanes/markerpdf/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

add_filter('upload_mimes', 'plpc_upload_mimes');

function plpc_upload_mimes(array $mimes): array
{
    $mimes['webp'] = 'image/webp';
    if (plpc_svg_import_allowed()) {
        $mimes['svg'] = 'image/svg+xml';
    }

    return $mimes;
}

/**
 * SVG is executable markup in several WordPress/browser deployment shapes.
 * Keep the permission used for direct uploads and extracted document media
 * identical: wp_upload_bits() intentionally bypasses the usual upload MIME
 * filter, so the media-import path must enforce this itself.
 */
function plpc_svg_import_allowed(): bool
{
    return plpc_is_playground_environment()
        || (function_exists('current_user_can') && current_user_can('unfiltered_html'));
}

add_action('rest_api_init', static function (): void {
    register_rest_route('port-libs/v1', '/convert', [
        'methods' => 'POST',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_convert_uploaded_document',
    ]);
    register_rest_route('port-libs/v1', '/imports', [
        'methods' => 'POST',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_create_import_job',
    ]);
    register_rest_route('port-libs/v1', '/imports/(?P<jobId>[A-Za-z0-9_-]+)', [
        'methods' => 'GET',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_import_job_status',
    ]);
    register_rest_route('port-libs/v1', '/imports/(?P<jobId>[A-Za-z0-9_-]+)/advance', [
        'methods' => 'POST',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_advance_import_job',
    ]);
    register_rest_route('port-libs/v1', '/imports/(?P<jobId>[A-Za-z0-9_-]+)/rendered-media', [
        'methods' => 'POST',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_submit_import_rendered_media',
    ]);
    register_rest_route('port-libs/v1', '/imports/(?P<jobId>[A-Za-z0-9_-]+)/render-source/(?P<requestId>form-[a-f0-9]+)', [
        'methods' => 'GET',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_import_job_render_source',
    ]);
});

add_action('admin_menu', 'plpc_register_importer_admin_page');
add_action('admin_enqueue_scripts', 'plpc_enqueue_importer_admin_assets');
add_filter('script_loader_tag', 'plpc_importer_module_script_tag', 10, 3);

function plpc_convert_permission(): bool|WP_Error
{
    if (plpc_is_playground_environment()) {
        return true;
    }

    if (
        function_exists('current_user_can')
        && current_user_can('upload_files')
        && current_user_can('edit_pages')
    ) {
        return true;
    }

    if (class_exists('WP_Error')) {
        return new WP_Error(
            'rest_forbidden',
            'Document conversion requires WordPress Playground or an authenticated user who can upload files and edit pages.',
            ['status' => 403]
        );
    }

    return false;
}

/**
 * Respect the host's PHP resource policy by default. Earlier versions forced
 * every import request to 512 MiB / 120 seconds, which made a normal server's
 * hard limits impossible to exercise and undermined the durable-job recovery
 * path. Site owners who explicitly provision more headroom can opt in from
 * wp-config.php or a filter; PHP-admin limits still remain authoritative.
 */
function plpc_import_apply_runtime_limits(): void
{
    $memoryLimit = defined('PLPC_IMPORT_MEMORY_LIMIT') ? constant('PLPC_IMPORT_MEMORY_LIMIT') : null;
    $timeLimit = defined('PLPC_IMPORT_TIME_LIMIT') ? constant('PLPC_IMPORT_TIME_LIMIT') : null;
    if (function_exists('apply_filters')) {
        $memoryLimit = apply_filters('plpc_import_memory_limit', $memoryLimit);
        $timeLimit = apply_filters('plpc_import_time_limit', $timeLimit);
    }
    if (is_string($memoryLimit)) {
        $memoryLimit = trim($memoryLimit);
        if (preg_match('/\A(?:-1|\d+[KMG]?)\z/i', $memoryLimit) === 1) {
            @ini_set('memory_limit', $memoryLimit);
        }
    }
    if (is_int($timeLimit) || (is_string($timeLimit) && ctype_digit($timeLimit))) {
        @set_time_limit(max(0, (int) $timeLimit));
    }
}

/**
 * Return the time available to an individual REST request. A value of zero
 * has PHP's usual "unlimited" meaning, so no voluntary deadline is imposed
 * in that case. Hosts with an outer proxy limit can provide their known
 * value with the filter without weakening PHP's own limit.
 */
function plpc_import_request_time_limit_seconds(): ?float
{
    $raw = function_exists('ini_get') ? ini_get('max_execution_time') : false;
    $limit = is_string($raw) || is_int($raw) || is_float($raw) ? (float) $raw : 0.0;
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_import_request_time_limit_seconds', $limit > 0.0 ? $limit : null);
        if ($filtered === null || $filtered === false || $filtered === '') {
            $limit = 0.0;
        } elseif (is_numeric($filtered)) {
            $limit = (float) $filtered;
        }
    }

    return is_finite($limit) && $limit > 0.0 ? $limit : null;
}

/**
 * Keep a small response-writing reserve. This is deliberately based on the
 * host's current policy rather than extending it: a durable importer must be
 * able to work with the restrictive limits people actually deploy.
 */
function plpc_import_request_reserve_seconds(float $timeLimit): float
{
    $reserve = min(
        PLPC_IMPORT_REQUEST_DEFAULT_RESERVE_SECONDS,
        max(0.25, $timeLimit / 4.0)
    );
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_import_request_reserve_seconds', $reserve, $timeLimit);
        if (is_numeric($filtered)) {
            $reserve = (float) $filtered;
        }
    }

    return max(0.25, min(max(0.25, $timeLimit - 0.25), $reserve));
}

/**
 * @return float|null Unix timestamp with microsecond precision
 */
function plpc_import_request_deadline(): ?float
{
    $timeLimit = plpc_import_request_time_limit_seconds();
    $deadline = $timeLimit === null
        ? null
        : microtime(true) + max(0.25, $timeLimit - plpc_import_request_reserve_seconds($timeLimit));
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_import_request_deadline', $deadline, $timeLimit);
        if ($filtered === null || $filtered === false || $filtered === '') {
            return null;
        }
        if (is_numeric($filtered) && is_finite((float) $filtered)) {
            return (float) $filtered;
        }
    }

    return $deadline;
}

function plpc_import_request_deadline_reached(?float $deadline): bool
{
    return $deadline !== null && microtime(true) >= $deadline;
}

function plpc_is_playground_environment(): bool
{
    foreach (['WP_PLAYGROUND', 'WORDPRESS_PLAYGROUND', 'IS_WORDPRESS_PLAYGROUND'] as $constant) {
        if (defined($constant) && constant($constant)) {
            return true;
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = preg_replace('/:\d+\z/', '', $host) ?? $host;
    if ($host !== 'playground.wordpress.net' && !str_ends_with($host, '.playground.wordpress.net')) {
        return false;
    }

    // Do not trust Referer: a normal WordPress installation must not become
    // anonymously writable just because a caller supplied a forged header.
    // On a real WordPress install, also require the configured site URL to
    // agree with the request host. The host-only fallback keeps the isolated
    // Playground test harness working where WordPress URL functions do not
    // exist, while actual Playground installations normally define one of
    // the constants checked above.
    if (!function_exists('home_url')) {
        return true;
    }
    $siteUrl = home_url('/');
    $siteHost = is_string($siteUrl) ? strtolower((string) wp_parse_url($siteUrl, PHP_URL_HOST)) : '';

    return $siteHost !== '' && hash_equals($siteHost, $host);
}

function plpc_register_importer_admin_page(): void
{
    if (!function_exists('add_management_page')) {
        return;
    }
    add_management_page(
        'Import a document',
        'Port Libs Import',
        'edit_pages',
        'port-libs-importer',
        'plpc_render_importer_admin_page'
    );
}

function plpc_enqueue_importer_admin_assets(string $hookSuffix = ''): void
{
    if ($hookSuffix !== 'tools_page_port-libs-importer' || !function_exists('wp_enqueue_script')) {
        return;
    }
    $baseUrl = function_exists('plugin_dir_url')
        ? plugin_dir_url(__FILE__) . 'assets/'
        : '';
    if (function_exists('wp_enqueue_style')) {
        wp_enqueue_style('port-libs-importer', $baseUrl . 'admin-importer.css', [], '0.3.0');
    }
    if (function_exists('wp_enqueue_script_module')) {
        wp_enqueue_script_module('port-libs-importer', $baseUrl . 'admin-importer.mjs', [], '0.3.0');

        return;
    }

    // WordPress versions before native Script Modules support need a classic
    // enqueue plus the filter below to mark this static-ESM asset as a module.
    wp_enqueue_script('port-libs-importer', $baseUrl . 'admin-importer.mjs', [], '0.3.0', true);
    if (function_exists('wp_script_add_data')) {
        wp_script_add_data('port-libs-importer', 'type', 'module');
    }
}

/**
 * Print configuration in the page before the deferred module runs. Keeping it
 * independent of a classic script handle also works with wp_enqueue_script_module().
 */
function plpc_print_importer_configuration_script(): void
{
    $baseUrl = function_exists('plugin_dir_url')
        ? plugin_dir_url(__FILE__) . 'assets/'
        : '';
    $configuration = [
        'restRoot' => function_exists('rest_url') ? rest_url('port-libs/v1/') : '/wp-json/port-libs/v1/',
        'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '',
        'pdfjsModuleUrl' => $baseUrl . 'vendor/pdfjs/pdf.min.mjs',
        'pdfjsWorkerUrl' => $baseUrl . 'vendor/pdfjs/pdf.worker.min.mjs',
        'pdfjsWasmUrl' => $baseUrl . 'vendor/pdfjs/wasm/',
        'pdfjsCMapUrl' => $baseUrl . 'vendor/pdfjs/cmaps/',
        'pdfjsStandardFontDataUrl' => $baseUrl . 'vendor/pdfjs/standard_fonts/',
    ];
    $encoded = function_exists('wp_json_encode')
        ? wp_json_encode($configuration, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        : json_encode($configuration, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        return;
    }

    $script = 'window.PortLibsImporterConfig = ' . $encoded . ';';
    if (function_exists('wp_print_inline_script_tag')) {
        wp_print_inline_script_tag($script);

        return;
    }

    echo '<script>' . $script . '</script>';
}

/**
 * WordPress versions that predate native script-module support still emit
 * enqueued scripts as classic scripts. The importer has a static ESM import,
 * so guarantee the module type instead of relying on a version-specific
 * wp_script_add_data() implementation.
 */
function plpc_importer_module_script_tag(string $tag, string $handle, string $source): string
{
    if ($handle !== 'port-libs-importer') {
        return $tag;
    }

    $withoutExistingType = preg_replace('/\s+type\s*=\s*(["\'])[^"\']*\1/i', '', $tag) ?? $tag;

    return preg_replace('/<script\b/i', '<script type="module"', $withoutExistingType, 1) ?? $withoutExistingType;
}

function plpc_render_importer_admin_page(): void
{
    if (function_exists('current_user_can') && (!current_user_can('upload_files') || !current_user_can('edit_pages'))) {
        if (function_exists('wp_die')) {
            wp_die('You do not have permission to import documents.');
        }

        return;
    }
    ?>
    <div class="wrap plpc-importer" id="plpc-importer">
        <?php plpc_print_importer_configuration_script(); ?>
        <h1>Import a document</h1>
        <p class="description">Choose a file and WordPress will detect its type. PDF charts and composite figures are rendered privately in this browser before they are added to the media library.</p>
        <form class="plpc-importer__form" novalidate>
            <label class="plpc-importer__dropzone" for="plpc-import-file">
                <span class="plpc-importer__dropzone-title">Choose a document</span>
                <span class="plpc-importer__dropzone-help">or drop a file, folder, or ZIP here</span>
                <input id="plpc-import-file" type="file" multiple>
            </label>
            <p class="plpc-importer__selection" aria-live="polite" data-plpc-selection>No file selected.</p>
            <fieldset class="plpc-importer__options">
                <legend>Images</legend>
                <label><input type="radio" name="plpc-image-mode" value="important" checked> Important images</label>
                <label><input type="radio" name="plpc-image-mode" value="all"> All images</label>
                <label><input type="radio" name="plpc-image-mode" value="none"> No images</label>
            </fieldset>
            <fieldset class="plpc-importer__options" data-plpc-pdf-options hidden>
                <legend>PDF reconstruction</legend>
                <label><input type="radio" name="plpc-pdf-mode" value="layout" checked> Layout-aware</label>
                <label><input type="radio" name="plpc-pdf-mode" value="text"> Text-only</label>
            </fieldset>
            <p><button type="submit" class="button button-primary" disabled data-plpc-submit>Import into WordPress</button></p>
        </form>
        <section class="plpc-importer__progress" aria-live="polite" hidden data-plpc-progress>
            <strong data-plpc-progress-label>Preparing import…</strong>
            <progress max="1" value="0" data-plpc-progress-bar></progress>
            <p data-plpc-progress-detail></p>
        </section>
        <section class="plpc-importer__result" hidden data-plpc-result></section>
        <ol class="plpc-importer__events" aria-live="polite" data-plpc-events></ol>
    </div>
    <?php
}

/**
 * Create a persisted import job. The original file is written below the
 * WordPress uploads directory instead of being retained in an option, while
 * the option holds only compact, user-owned job metadata.
 */
function plpc_create_import_job(WP_REST_Request $request): WP_REST_Response
{
    try {
        $payload = plpc_import_job_request_payload($request);
        $jobId = plpc_import_job_new_id();
        $jobDirectory = plpc_import_job_create_directory($jobId);
        $filename = sanitize_file_name((string) ($payload['filename'] ?? 'upload'));
        if ($filename === '') {
            $filename = 'upload';
        }
        $title = sanitize_text_field((string) ($payload['title'] ?? ''));
        if ($title === '') {
            $title = plpc_title_from_filename($filename);
        }

        $job = [
            'version' => PLPC_IMPORT_JOB_VERSION,
            'id' => $jobId,
            'ownerId' => plpc_import_job_owner_id(),
            'createdAt' => time(),
            'updatedAt' => time(),
            'status' => 'queued',
            'stage' => 'queued',
            'progress' => [
                'completed' => 0,
                'total' => 1,
                'label' => 'Upload saved. Waiting to inspect the document.',
            ],
            'events' => [],
            'title' => $title,
            'imageMode' => plpc_normalize_image_mode($payload['imageMode'] ?? 'important'),
            'pdfMode' => plpc_normalize_pdf_mode($payload['pdfMode'] ?? 'layout'),
            'sourceKind' => plpc_import_job_payload_is_collection($payload) ? 'collection' : 'single',
            'sourceLabel' => $filename,
            'sourceFiles' => [],
            'pdfRasters' => [],
            'documents' => [],
            'nextDocument' => 0,
            'renderRequests' => [],
            'renderedForms' => [],
            'checkpoint' => null,
            'results' => [],
            'result' => null,
            'error' => null,
        ];

        plpc_import_job_store_payload($job, $payload, $jobDirectory);
        plpc_import_job_add_event($job, 'queued', 'The source document was saved securely.');
        plpc_import_job_save($job);

        return plpc_import_job_response($job, 201);
    } catch (Throwable $error) {
        return plpc_import_job_error_response($error->getMessage(), 400);
    }
}

/**
 * Return a safe snapshot for browser polling. Source bytes and disk paths are
 * deliberately not returned: PDF.js already has the selected local File.
 */
function plpc_import_job_status(WP_REST_Request $request): WP_REST_Response
{
    try {
        return plpc_import_job_response(plpc_import_job_from_request($request));
    } catch (Throwable $error) {
        return plpc_import_job_error_response($error->getMessage(), 404);
    }
}

/**
 * Return one outstanding PDF render source to the browser. This is needed
 * when a ZIP was expanded on the server: the browser has the ZIP File but
 * not its inner PDF. Normal job snapshots intentionally never contain source
 * bytes, and this endpoint is both owner-scoped and tied to an outstanding
 * opaque renderer request.
 */
function plpc_import_job_render_source(WP_REST_Request $request): WP_REST_Response
{
    try {
        $job = plpc_import_job_from_request($request);
        $requestId = '';
        if (method_exists($request, 'get_url_params')) {
            $urlParams = $request->get_url_params();
            if (is_array($urlParams)) {
                $requestId = (string) ($urlParams['requestId'] ?? '');
            }
        }
        if ($requestId === '' && method_exists($request, 'get_param')) {
            $requestId = (string) $request->get_param('requestId');
        }
        $requestIndex = plpc_import_job_render_request_index($job, $requestId);
        if ($requestIndex === null) {
            throw new RuntimeException('This PDF source is not awaiting browser rendering.');
        }
        $renderRequest = $job['renderRequests'][$requestIndex] ?? null;
        if (!is_array($renderRequest)) {
            throw new RuntimeException('The outstanding browser-render request was malformed.');
        }
        $path = (string) ($renderRequest['path'] ?? '');
        $storage = '';
        foreach ($job['documents'] ?? [] as $document) {
            if (is_array($document) && (string) ($document['path'] ?? '') === $path) {
                $storage = (string) ($document['storage'] ?? '');
                break;
            }
        }
        if ($storage === '') {
            throw new RuntimeException('The requested PDF source is no longer available.');
        }
        $bytes = plpc_import_job_read_file($job, $storage);
        if (strlen($bytes) > PLPC_IMPORT_JOB_MAX_RENDER_SOURCE_BYTES) {
            throw new RuntimeException('This PDF is too large to send to the browser. Please select the original file again.');
        }
        if (!str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('The requested render source is not a PDF.');
        }

        $response = new WP_REST_Response([
            'ok' => true,
            'jobId' => (string) ($job['id'] ?? ''),
            'requestId' => $requestId,
            'path' => $path,
            'bytes' => base64_encode($bytes),
        ]);
        if (method_exists($response, 'header')) {
            $response->header('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    } catch (Throwable $error) {
        return plpc_import_job_error_response($error->getMessage(), 404);
    }
}

/**
 * Advance exactly one durable unit of the import state machine. For a batch,
 * one document is converted per call; the browser can therefore poll and make
 * the next request without guessing whether the previous request completed.
 */
function plpc_advance_import_job(WP_REST_Request $request): WP_REST_Response
{
    plpc_import_apply_runtime_limits();
    $deadline = plpc_import_request_deadline();

    $lock = null;
    try {
        // Verify ownership before touching the job directory, then reload the
        // option after the lock is held so a second tab/retry never acts on a
        // stale copy and creates duplicate pages or attachments.
        $job = plpc_import_job_from_request($request);
        $lock = plpc_import_job_acquire_lock($job);
        $job = plpc_import_job_from_request($request);
        if (in_array($job['status'] ?? '', ['complete', 'failed'], true)) {
            return plpc_import_job_response($job);
        }
        if (($job['status'] ?? '') === 'awaiting_renderer') {
            plpc_import_job_set_progress(
                $job,
                'awaiting_renderer',
                (int) ($job['progress']['completed'] ?? 0),
                max(1, (int) ($job['progress']['total'] ?? 1)),
                'Waiting for this browser to render the PDF figure' . (count($job['renderRequests'] ?? []) === 1 ? '.' : 's.')
            );
            plpc_import_job_save($job);

            return plpc_import_job_response($job);
        }

        if (($job['status'] ?? '') === 'queued') {
            plpc_import_job_prepare($job);
            plpc_import_job_save($job);

            return plpc_import_job_response($job);
        }

        if (($job['status'] ?? '') === 'converting') {
            // A PHP worker can disappear between progress checkpoints (for
            // example at a host execution limit). The lock is released by
            // the operating system, and the next browser request can safely
            // restart inspection or replay the current durable document unit
            // instead of declaring the whole import irrecoverable.
            if (($job['stage'] ?? '') === 'inspecting') {
                $job['status'] = 'queued';
                $job['stage'] = 'queued';
                plpc_import_job_add_event($job, 'resuming', 'The previous request ended while inspecting. Restarting document inspection.');
                plpc_import_job_prepare($job);
            } else {
                if (!plpc_import_job_recover_interrupted_document($job)) {
                    plpc_import_job_save($job);

                    return plpc_import_job_response($job);
                }
                plpc_import_job_convert_next_document($job, $deadline);
            }
            plpc_import_job_save($job);

            return plpc_import_job_response($job);
        }

        if (($job['status'] ?? '') === 'ready_to_convert') {
            plpc_import_job_convert_next_document($job, $deadline);
            plpc_import_job_save($job);

            return plpc_import_job_response($job);
        }

        throw new RuntimeException('The import job is in an unknown state. Please start a new import.');
    } catch (PlpcImportCheckpointYield) {
        // The checkpoint function already persisted a coherent snapshot. A
        // successful REST response lets the browser make the next bounded
        // request instead of mistaking an intentional handoff for a fatal.
        return plpc_import_job_response($job);
    } catch (Throwable $error) {
        if ($error->getCode() === 409) {
            return plpc_import_job_error_response($error->getMessage(), 409);
        }
        try {
            if ($lock !== null && isset($job) && is_array($job)) {
                plpc_import_job_fail($job, $error->getMessage());
                plpc_import_job_save($job);

                return plpc_import_job_response($job, 500);
            }
        } catch (Throwable) {
            // Preserve the original failure response below.
        }

        return plpc_import_job_error_response($error->getMessage(), 500);
    } finally {
        plpc_import_job_release_lock($lock);
    }
}

/**
 * Accept the PNG/WebP/AVIF crop rendered by PDF.js for an outstanding Form
 * XObject request. A browser may instead report a rendering failure; that
 * lets an import continue with a visible diagnostic rather than stall.
 */
function plpc_submit_import_rendered_media(WP_REST_Request $request): WP_REST_Response
{
    $lock = null;
    try {
        $job = plpc_import_job_from_request($request);
        $lock = plpc_import_job_acquire_lock($job);
        $job = plpc_import_job_from_request($request);
        if (($job['status'] ?? '') !== 'awaiting_renderer') {
            throw new RuntimeException('This import is not currently waiting for a browser-rendered PDF figure.');
        }
        $payload = plpc_import_job_request_payload($request);
        $requestId = (string) ($payload['requestId'] ?? '');
        $requestIndex = plpc_import_job_render_request_index($job, $requestId);
        if ($requestIndex === null) {
            throw new RuntimeException('This browser-rendered figure does not match an outstanding import request.');
        }
        $renderRequest = $job['renderRequests'][$requestIndex];
        if (!is_array($renderRequest)) {
            throw new RuntimeException('The outstanding browser-render request was malformed.');
        }

        $renderError = trim((string) ($payload['error'] ?? ''));
        if ($renderError !== '') {
            $job['renderedForms'][$requestId] = [
                'requestId' => $requestId,
                'path' => (string) ($renderRequest['path'] ?? ''),
                'error' => substr($renderError, 0, 300),
            ];
            array_splice($job['renderRequests'], $requestIndex, 1);
            plpc_import_job_add_event($job, 'renderer', 'The browser could not render one PDF figure; the text import will continue.');
        } else {
            $rendered = isset($payload['uploadedRender']) && is_array($payload['uploadedRender'])
                ? plpc_import_job_rendered_image_from_uploaded_file($payload['uploadedRender'], $payload)
                : plpc_import_job_rendered_image_from_payload($payload);
            if (plpc_import_job_rendered_form_total_bytes($job) + strlen($rendered['contents']) > PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES) {
                throw new RuntimeException('The browser-rendered PDF figures exceed the per-import media safety limit.');
            }
            $stored = plpc_import_job_store_rendered_form(
                plpc_import_job_directory($job),
                $requestId,
                $renderRequest,
                $rendered
            );
            $job['renderedForms'][$requestId] = $stored;
            array_splice($job['renderRequests'], $requestIndex, 1);
            plpc_import_job_add_event($job, 'renderer', 'Received a browser-rendered PDF figure.');
        }

        if (($job['renderRequests'] ?? []) === []) {
            plpc_import_job_set_progress(
                $job,
                'ready_to_convert',
                2,
                plpc_import_job_progress_total($job),
                'PDF figures are ready. Preparing WordPress blocks.'
            );
        } else {
            plpc_import_job_set_progress(
                $job,
                'awaiting_renderer',
                1,
                plpc_import_job_progress_total($job),
                'Waiting for ' . count($job['renderRequests']) . ' more PDF figure' . (count($job['renderRequests']) === 1 ? '.' : 's.')
            );
        }
        plpc_import_job_save($job);

        return plpc_import_job_response($job);
    } catch (Throwable $error) {
        return plpc_import_job_error_response($error->getMessage(), $error->getCode() === 409 ? 409 : 422);
    } finally {
        plpc_import_job_release_lock($lock);
    }
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_prepare(array &$job): void
{
    plpc_import_job_set_progress($job, 'inspecting', 0, 1, 'Inspecting the document and detecting its format.');
    plpc_import_job_add_event($job, 'inspecting', 'Detecting the document type from its contents.');
    plpc_import_job_save($job);

    $sourceFiles = plpc_import_job_prepare_source_files($job);
    if ($sourceFiles === []) {
        throw new RuntimeException('The saved source upload is no longer available. Please select it again.');
    }
    $documents = [];
    $collection = null;
    if (($job['sourceKind'] ?? '') === 'collection') {
        $collection = [
            'label' => (string) ($job['sourceLabel'] ?? $job['title'] ?? 'Import'),
            'files' => $sourceFiles,
        ];
        $documents = plpc_convertible_collection_files($collection);
    } else {
        $source = $sourceFiles[0];
        $format = (string) ($source['format'] ?? plpc_infer_document_format((string) $source['path'], (string) $source['bytes']));
        if ($format === '') {
            throw new RuntimeException('Could not infer a supported document type from the uploaded filename or contents.');
        }
        if (plpc_should_expand_zip_upload($format, (string) $source['bytes'])) {
            $expanded = plpc_collection_from_zip((string) $source['bytes'], (string) $source['path'], (string) ($job['title'] ?? ''));
            $collection = plpc_import_job_store_expanded_collection($job, $expanded);
            $sourceFiles = plpc_import_job_prepare_source_files($job);
            $collection['files'] = $sourceFiles;
            $job['sourceKind'] = 'collection';
            $job['sourceLabel'] = $collection['label'];
            $documents = plpc_convertible_collection_files($collection);
        } else {
            if (!PandocConverter::canRead($format)) {
                throw new RuntimeException('The inferred document type is not supported by this importer.');
            }
            $documents = [[
                'path' => (string) $source['path'],
                'bytes' => (string) $source['bytes'],
                'format' => $format,
            ]];
        }
    }
    if ($documents === []) {
        throw new RuntimeException('No supported document files were found in this upload.');
    }

    $storageByPath = [];
    foreach (plpc_import_job_source_file_records($job) as $source) {
        $storageByPath[(string) $source['path']] = (string) $source['storage'];
    }
    $job['documents'] = [];
    foreach ($documents as $document) {
        $path = (string) $document['path'];
        $storage = $storageByPath[$path] ?? '';
        if ($storage === '') {
            continue;
        }
        $job['documents'][] = [
            'path' => $path,
            'storage' => $storage,
            'format' => (string) $document['format'],
        ];
    }
    if (($job['documents'] ?? []) === []) {
        throw new RuntimeException('The saved document files could not be prepared for import.');
    }

    $job['nextDocument'] = 0;
    $job['results'] = [];
    $job['renderRequests'] = plpc_import_job_collect_form_render_requests($job);
    $total = plpc_import_job_progress_total($job);
    if (($job['renderRequests'] ?? []) !== []) {
        plpc_import_job_set_progress(
            $job,
            'awaiting_renderer',
            1,
            $total,
            'Found ' . count($job['renderRequests']) . ' PDF figure' . (count($job['renderRequests']) === 1 ? ' to render in this browser.' : 's to render in this browser.')
        );
        plpc_import_job_add_event($job, 'renderer', 'Sending PDF figure crop requests to the browser.');

        return;
    }

    plpc_import_job_set_progress($job, 'ready_to_convert', 1, $total, 'Document inspected. Ready to create WordPress blocks.');
    plpc_import_job_add_event($job, 'ready_to_convert', 'The importer knows what to do next.');
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_convert_next_document(array &$job, ?float $deadline = null): void
{
    $documents = is_array($job['documents'] ?? null) ? $job['documents'] : [];
    $index = max(0, (int) ($job['nextDocument'] ?? 0));
    if (!isset($documents[$index]) || !is_array($documents[$index])) {
        plpc_import_job_finish($job);

        return;
    }
    $document = $documents[$index];
    $path = (string) ($document['path'] ?? 'document');
    $format = (string) ($document['format'] ?? '');
    $canonicalFormat = $format === '' ? '' : PandocConverter::canonicalInputFormat($format);
    $sourcePath = '';
    $bytes = '';
    if ($canonicalFormat === 'epub') {
        // Persisted EPUBs already live in a private job directory. Keep that
        // trusted path internal and let Pandoc's file-backed EPUB APIs open
        // it directly instead of retaining another full ZIP string here.
        $sourcePath = plpc_import_job_storage_path($job, (string) ($document['storage'] ?? ''));
    } else {
        $bytes = plpc_import_job_read_file($job, (string) ($document['storage'] ?? ''));
        $format = $format !== '' ? $format : plpc_infer_document_format($path, $bytes);
        $canonicalFormat = $format === '' ? '' : PandocConverter::canonicalInputFormat($format);
    }
    $total = plpc_import_job_progress_total($job);
    $phaseOffsets = [
        'reading' => 2,
        'extracting_media' => 3,
        'writing_blocks' => 4,
        'uploading_media' => 5,
        'creating_page' => 6,
    ];
    $reportProgress = static function (string $stage, string $label) use (&$job, $deadline, $index, $total, $phaseOffsets): void {
        $completed = min($total - 1, ($index * 6) + ($phaseOffsets[$stage] ?? 2));
        plpc_import_job_set_progress($job, 'converting', $completed, $total, $label);
        plpc_import_job_add_event($job, $stage, $label, false);
        plpc_import_job_save($job);
        plpc_import_job_checkpoint_for_deadline($job, $deadline, $index, $stage, $label);
    };
    $reportProgress('reading', 'Reading ' . basename($path) . ' (' . ($index + 1) . ' of ' . count($documents) . ').');

    $collection = ($job['sourceKind'] ?? '') === 'collection' && $canonicalFormat !== 'epub'
        ? ['label' => (string) ($job['sourceLabel'] ?? $job['title'] ?? 'Import'), 'files' => plpc_import_job_load_source_files($job)]
        : null;
    $file = [
        'path' => $path,
        'bytes' => $bytes,
        'format' => $format,
        'pdfRasterImages' => plpc_import_job_load_pdf_rasters($job, $path),
        'pdfFormRenders' => plpc_import_job_load_rendered_forms($job, $path),
    ];
    if ($sourcePath !== '') {
        $file['sourcePath'] = $sourcePath;
    }
    $result = plpc_convert_collection_file_to_page($file, $collection, count($documents) === 1 ? (string) ($job['title'] ?? '') : null, (string) ($job['imageMode'] ?? 'important'), (string) ($job['pdfMode'] ?? 'layout'), $reportProgress);
    $job['results'][] = $result;
    plpc_import_job_clear_document_checkpoint($job, $index);
    $job['nextDocument'] = $index + 1;
    if ($job['nextDocument'] >= count($documents)) {
        plpc_import_job_finish($job);

        return;
    }

    plpc_import_job_set_progress(
        $job,
        'ready_to_convert',
        min($total - 1, ($job['nextDocument'] * 6) + 1),
        $total,
        'Finished ' . basename($path) . '. Ready for the next document.'
    );
    plpc_import_job_add_event($job, 'ready_to_convert', 'One document is complete; the batch will continue on the next request.');
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_finish(array &$job): void
{
    $results = is_array($job['results'] ?? null) ? $job['results'] : [];
    if ($results === []) {
        throw new RuntimeException('The import completed without creating a page.');
    }
    if (count($results) === 1) {
        $job['result'] = $results[0];
    } else {
        $diagnostics = [];
        $imageTagCount = 0;
        $imagesImported = 0;
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }
            $imageTagCount += (int) ($result['imageTagCount'] ?? 0);
            $imagesImported += (int) ($result['imagesImported'] ?? 0);
            foreach ($result['diagnostics'] ?? [] as $diagnostic) {
                $diagnostics[] = (string) ($result['path'] ?? 'document') . ':' . (string) $diagnostic;
            }
        }
        $indexTitle = (string) ($job['title'] ?? $job['sourceLabel'] ?? 'Imported documents');
        $indexPostId = wp_insert_post([
            'post_type' => 'page',
            'post_title' => $indexTitle,
            'post_status' => 'publish',
            'post_content' => plpc_collection_index_blocks($indexTitle, $results, $diagnostics),
        ], true);
        if (is_wp_error($indexPostId)) {
            throw new RuntimeException($indexPostId->get_error_message());
        }
        $job['result'] = [
            'batch' => true,
            'postCount' => count($results),
            'posts' => $results,
            'postId' => (int) $indexPostId,
            'pageUrl' => get_permalink((int) $indexPostId),
            'editUrl' => get_edit_post_link((int) $indexPostId, 'raw'),
            'title' => get_the_title((int) $indexPostId),
            'imageTagCount' => $imageTagCount,
            'imagesImported' => $imagesImported,
            'diagnostics' => $diagnostics,
            'quality' => plpc_import_quality_report('', $diagnostics, $imageTagCount, $imagesImported),
        ];
    }
    $total = plpc_import_job_progress_total($job);
    plpc_import_job_set_progress($job, 'complete', $total, $total, 'Import complete. Your WordPress page is ready.');
    plpc_import_job_add_event($job, 'complete', 'Created the WordPress page' . (count($results) > 1 ? 's and collection index.' : '.'));
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_fail(array &$job, string $message): void
{
    $job['status'] = 'failed';
    $job['stage'] = 'failed';
    $job['error'] = $message;
    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    $job['progress'] = [
        'completed' => max(0, (int) ($progress['completed'] ?? 0)),
        'total' => max(1, (int) ($progress['total'] ?? 1)),
        'label' => 'Import stopped: ' . $message,
    ];
    plpc_import_job_add_event($job, 'failed', 'Import stopped: ' . $message);
}

/**
 * @return array<string, mixed>
 */
function plpc_import_job_request_payload(WP_REST_Request $request): array
{
    $fileParams = method_exists($request, 'get_file_params') ? $request->get_file_params() : [];
    if (is_array($fileParams) && $fileParams !== []) {
        return plpc_import_job_multipart_payload($request, $fileParams);
    }
    $body = trim((string) $request->get_body());
    if ($body === '') {
        return [];
    }
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid import request payload.');
    }

    return $payload;
}

/**
 * Convert a native WordPress multipart request into the same internal shape
 * as the JSON protocol. Regular wp-admin uploads must not base64 a 90 MB PDF
 * into a REST body: that both exceeds common PHP limits and briefly doubles
 * its memory use. Only the server-populated PHP upload fields are accepted.
 *
 * @param array<string, mixed> $fileParams
 * @return array<string, mixed>
 */
function plpc_import_job_multipart_payload(WP_REST_Request $request, array $fileParams): array
{
    if (isset($fileParams['plpc_rendered'])) {
        $rendered = plpc_import_job_uploaded_file_descriptor($fileParams['plpc_rendered']);

        return [
            'requestId' => (string) plpc_import_job_form_value($request, 'requestId'),
            'mimeType' => (string) plpc_import_job_form_value($request, 'mimeType'),
            'width' => plpc_import_job_form_value($request, 'width'),
            'height' => plpc_import_job_form_value($request, 'height'),
            'uploadedRender' => $rendered,
        ];
    }

    $metadataRaw = plpc_import_job_form_value($request, 'metadata');
    $metadata = is_string($metadataRaw) ? json_decode($metadataRaw, true) : null;
    if (!is_array($metadata)) {
        throw new RuntimeException('The multipart import metadata was invalid.');
    }
    $metadataEntries = is_array($metadata['entries'] ?? null) ? array_values($metadata['entries']) : [];
    $uploads = [];
    foreach ($fileParams as $key => $file) {
        if (!is_string($key) || preg_match('/\Aplpc_file_(\d+)\z/', $key, $match) !== 1) {
            continue;
        }
        $index = (int) $match[1];
        $descriptor = plpc_import_job_uploaded_file_descriptor($file);
        $entry = $metadataEntries[$index] ?? [];
        $path = is_array($entry) ? (string) ($entry['path'] ?? '') : '';
        if ($path === '') {
            $path = (string) ($descriptor['name'] ?? 'upload');
        }
        $path = plpc_normalize_collection_path($path);
        if ($path === '') {
            throw new RuntimeException('One uploaded file did not have a valid path.');
        }
        $uploads[$index] = $descriptor + ['path' => $path];
    }
    if ($uploads === []) {
        throw new RuntimeException('No readable files were found in the multipart upload.');
    }
    ksort($uploads, SORT_NUMERIC);
    $uploads = array_values($uploads);
    $seenPaths = [];
    foreach ($uploads as $upload) {
        $path = strtolower((string) $upload['path']);
        if (isset($seenPaths[$path])) {
            throw new RuntimeException('The multipart upload contains the same file path more than once.');
        }
        $seenPaths[$path] = true;
    }

    $first = $uploads[0];
    $filename = (string) ($metadata['filename'] ?? ($first['name'] ?? 'upload'));
    if ($filename === '') {
        $filename = (string) ($first['name'] ?? 'upload');
    }

    $uploadedPdfRasters = [];
    foreach ($metadata['pdfRasterImages'] ?? [] as $descriptor) {
        if (!is_array($descriptor)) {
            continue;
        }
        $field = (string) ($descriptor['field'] ?? '');
        if (preg_match('/\Aplpc_raster_\d+\z/', $field) !== 1 || !isset($fileParams[$field])) {
            throw new RuntimeException('A browser-rendered PDF image was missing from the multipart upload.');
        }
        $uploadedPdfRasters[] = [
            'path' => plpc_normalize_collection_path((string) ($descriptor['path'] ?? '')),
            'object' => (string) ($descriptor['object'] ?? ''),
            'mimeType' => strtolower(trim((string) ($descriptor['mimeType'] ?? ''))),
            'width' => (int) ($descriptor['width'] ?? 0),
            'height' => (int) ($descriptor['height'] ?? 0),
            'upload' => plpc_import_job_uploaded_file_descriptor($fileParams[$field]),
        ];
    }

    return [
        'filename' => $filename,
        'title' => (string) ($metadata['title'] ?? ''),
        'imageMode' => $metadata['imageMode'] ?? 'important',
        'pdfMode' => $metadata['pdfMode'] ?? 'layout',
        'uploadedFiles' => $uploads,
        'uploadedPdfRasters' => $uploadedPdfRasters,
    ];
}

/** @return string|int|float|bool|null */
function plpc_import_job_form_value(WP_REST_Request $request, string $key): string|int|float|bool|null
{
    if (!method_exists($request, 'get_param')) {
        return null;
    }
    $value = $request->get_param($key);

    return is_string($value) || is_int($value) || is_float($value) || is_bool($value) ? $value : null;
}

/**
 * @param mixed $file
 * @return array{name:string,tmpName:string,size:int}
 */
function plpc_import_job_uploaded_file_descriptor(mixed $file): array
{
    if (!is_array($file) || is_array($file['name'] ?? null) || is_array($file['tmp_name'] ?? null)) {
        throw new RuntimeException('The multipart upload file was malformed.');
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('WordPress could not receive one of the uploaded files (upload error ' . $error . ').');
    }
    $name = (string) ($file['name'] ?? 'upload');
    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('The multipart upload file was unavailable.');
    }
    $size = filesize($tmpName);
    if (!is_int($size) || $size <= 0) {
        throw new RuntimeException('The multipart upload file was empty or could not be read.');
    }

    return compact('name', 'tmpName', 'size');
}

/** @param array<string, mixed> $payload */
function plpc_import_job_payload_is_collection(array $payload): bool
{
    if (isset($payload['files']) && is_array($payload['files'])) {
        return true;
    }
    $stagedFiles = $payload['stagedFiles'] ?? null;
    if (is_array($stagedFiles) && $stagedFiles !== []) {
        if (count($stagedFiles) > 1) {
            return true;
        }
        $first = $stagedFiles[0] ?? [];

        return is_array($first) && str_contains((string) ($first['path'] ?? ''), '/');
    }
    $uploads = $payload['uploadedFiles'] ?? null;
    if (!is_array($uploads) || $uploads === []) {
        return false;
    }

    return count($uploads) > 1 || str_contains((string) ($uploads[0]['path'] ?? ''), '/');
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_save(array &$job): void
{
    $job['updatedAt'] = time();
    update_option(PLPC_IMPORT_JOB_OPTION_PREFIX . (string) $job['id'], $job, false);
}

/**
 * @return array<string, mixed>
 */
function plpc_import_job_from_request(WP_REST_Request $request): array
{
    $jobId = '';
    if (method_exists($request, 'get_url_params')) {
        $urlParams = $request->get_url_params();
        if (is_array($urlParams)) {
            $jobId = (string) ($urlParams['jobId'] ?? '');
        }
    }
    if ($jobId === '' && method_exists($request, 'get_param')) {
        $jobId = (string) $request->get_param('jobId');
    }
    if (preg_match('/\A[A-Za-z0-9_-]{12,128}\z/', $jobId) !== 1) {
        throw new RuntimeException('The import job id is invalid.');
    }
    $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, null);
    if (!is_array($job) || (string) ($job['id'] ?? '') !== $jobId) {
        throw new RuntimeException('This import job was not found.');
    }
    if (!plpc_is_playground_environment() && (int) ($job['ownerId'] ?? -1) !== plpc_import_job_owner_id()) {
        throw new RuntimeException('This import job belongs to another WordPress user.');
    }

    return $job;
}

/**
 * Keep mutable import transitions serial per job. A file lock releases
 * automatically if PHP dies at its execution limit, unlike an option-based
 * mutex that could strand a resumable job after a fatal error.
 *
 * @return resource
 */
function plpc_import_job_acquire_lock(array $job)
{
    $directory = plpc_import_job_directory($job);
    if (!is_dir($directory) && !wp_mkdir_p($directory)) {
        throw new RuntimeException('WordPress could not prepare this import for an update.');
    }
    $path = $directory . DIRECTORY_SEPARATOR . '.import.lock';
    $handle = @fopen($path, 'c');
    if (!is_resource($handle)) {
        throw new RuntimeException('WordPress could not lock this import for an update.');
    }
    @chmod($path, 0600);
    if (!@flock($handle, LOCK_EX | LOCK_NB)) {
        @fclose($handle);
        throw new RuntimeException('This import is already being updated. Please wait a moment and try again.', 409);
    }

    return $handle;
}

/** @param resource|null $lock */
function plpc_import_job_release_lock(mixed $lock): void
{
    if (!is_resource($lock)) {
        return;
    }
    @flock($lock, LOCK_UN);
    @fclose($lock);
}

function plpc_import_job_new_id(): string
{
    if (function_exists('wp_generate_uuid4')) {
        $uuid = strtolower((string) wp_generate_uuid4());
        $id = str_replace('-', '', $uuid);
        if (preg_match('/\A[a-z0-9]{12,128}\z/', $id) === 1) {
            return $id;
        }
    }

    return bin2hex(random_bytes(16));
}

function plpc_import_job_owner_id(): int
{
    return function_exists('get_current_user_id') ? max(0, (int) get_current_user_id()) : 0;
}

function plpc_import_job_directory(array|string $job): string
{
    $jobId = is_array($job) ? (string) ($job['id'] ?? '') : $job;
    if (preg_match('/\A[A-Za-z0-9_-]{12,128}\z/', $jobId) !== 1) {
        throw new RuntimeException('The import job storage key is invalid.');
    }
    $uploads = wp_upload_dir();
    if (!is_array($uploads) || !empty($uploads['error']) || !is_string($uploads['basedir'] ?? null) || $uploads['basedir'] === '') {
        throw new RuntimeException('WordPress could not prepare upload storage for this import.');
    }

    return rtrim($uploads['basedir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . PLPC_IMPORT_JOB_DIRECTORY . DIRECTORY_SEPARATOR . $jobId;
}

function plpc_import_job_create_directory(string $jobId): string
{
    $directory = plpc_import_job_directory($jobId);
    $storageRoot = dirname($directory);
    if (!wp_mkdir_p($storageRoot)) {
        throw new RuntimeException('WordPress could not create import storage.');
    }
    plpc_import_job_harden_storage_root($storageRoot);
    if (!wp_mkdir_p($directory)) {
        throw new RuntimeException('WordPress could not create temporary storage for this import.');
    }
    @chmod($directory, 0700);

    return $directory;
}

/**
 * Import sources live below uploads so WordPress can persist a job between
 * requests. They are never linked in REST responses; these server rules add
 * defense in depth for Apache and IIS installations where uploads are public.
 */
function plpc_import_job_harden_storage_root(string $storageRoot): void
{
    $files = [
        'index.php' => "<?php\n// Silence is golden.\n",
        '.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
    ];
    foreach ($files as $filename => $contents) {
        $path = $storageRoot . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            continue;
        }
        $written = @file_put_contents($path, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            // File permissions still make the random per-job directories
            // private on common PHP-FPM deployments. Do not fail an import
            // solely because a server disallows an optional web rule file.
            continue;
        }
        // These are access-control directives rather than source documents;
        // let the web server read them even when PHP-FPM uses another user.
        @chmod($path, 0644);
    }
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_set_progress(array &$job, string $stage, int $completed, int $total, string $label): void
{
    $total = max(1, $total);
    $completed = max(0, min($total, $completed));
    $job['stage'] = $stage;
    $job['status'] = match ($stage) {
        'awaiting_renderer' => 'awaiting_renderer',
        'ready_to_convert' => 'ready_to_convert',
        'complete' => 'complete',
        'failed' => 'failed',
        default => 'converting',
    };
    $job['progress'] = [
        'completed' => $completed,
        'total' => $total,
        'label' => $label,
    ];
}

/**
 * Persist an intentional handoff before PHP reaches its execution deadline.
 * This is a phase boundary, not a parser cursor: the next request restarts
 * the current durable document unit. Keeping the count on the job prevents a
 * document that cannot fit in this host's limit from making the browser loop
 * forever.
 *
 * @param array<string, mixed> $job
 */
function plpc_import_job_checkpoint_for_deadline(array &$job, ?float $deadline, int $documentIndex, string $stage, string $label): void
{
    if (!plpc_import_request_deadline_reached($deadline)) {
        return;
    }

    $previous = is_array($job['checkpoint'] ?? null) ? $job['checkpoint'] : [];
    $sameDocument = (int) ($previous['documentIndex'] ?? -1) === $documentIndex;
    $deadlineYields = ($sameDocument ? max(0, (int) ($previous['deadlineYields'] ?? 0)) : 0) + 1;
    $checkpoint = [
        'documentIndex' => $documentIndex,
        'stage' => $stage,
        'label' => $label,
        'deadlineYields' => $deadlineYields,
        'interruptedRetries' => $sameDocument ? max(0, (int) ($previous['interruptedRetries'] ?? 0)) : 0,
        'updatedAt' => time(),
    ];
    $job['checkpoint'] = $checkpoint;

    if ($deadlineYields >= PLPC_IMPORT_JOB_MAX_DEADLINE_YIELDS_PER_DOCUMENT) {
        $message = 'This document reached the server execution-time safety checkpoint '
            . $deadlineYields . ' times. To avoid retrying forever, the import stopped before creating duplicate work. '
            . 'Increase this site\'s PHP execution limit or split the source document, then start a new import.';
        plpc_import_job_fail($job, $message);
        plpc_import_job_save($job);

        throw new PlpcImportCheckpointYield($message);
    }

    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    plpc_import_job_set_progress(
        $job,
        'ready_to_convert',
        max(1, (int) ($progress['completed'] ?? 1)),
        max(1, (int) ($progress['total'] ?? 1)),
        'Pausing before this server reaches its execution limit. The browser will continue with a fresh request.'
    );
    plpc_import_job_add_event(
        $job,
        'checkpoint',
        'Saved a progress checkpoint before the server execution limit; the next request will retry the current document safely.'
    );
    plpc_import_job_save($job);

    throw new PlpcImportCheckpointYield('The import yielded at a saved server-time checkpoint.');
}

/**
 * A hard timeout cannot execute the graceful checkpoint code above. When a
 * later browser request finds the durable job still marked converting, cap
 * these recovery attempts too. That gives the person a useful next step
 * instead of an invisible endless replay of the same whole-document reader.
 *
 * @param array<string, mixed> $job
 */
function plpc_import_job_recover_interrupted_document(array &$job): bool
{
    $documentIndex = max(0, (int) ($job['nextDocument'] ?? 0));
    $previous = is_array($job['checkpoint'] ?? null) ? $job['checkpoint'] : [];
    $sameDocument = (int) ($previous['documentIndex'] ?? -1) === $documentIndex;
    $interruptedRetries = ($sameDocument ? max(0, (int) ($previous['interruptedRetries'] ?? 0)) : 0) + 1;
    $job['checkpoint'] = [
        'documentIndex' => $documentIndex,
        'stage' => (string) ($job['stage'] ?? 'converting'),
        'label' => (string) (($job['progress']['label'] ?? '') ?: 'The previous request ended while converting.'),
        'deadlineYields' => $sameDocument ? max(0, (int) ($previous['deadlineYields'] ?? 0)) : 0,
        'interruptedRetries' => $interruptedRetries,
        'updatedAt' => time(),
    ];
    if ($interruptedRetries >= PLPC_IMPORT_JOB_MAX_INTERRUPTED_RETRIES_PER_DOCUMENT) {
        plpc_import_job_fail(
            $job,
            'The server ended this document conversion more than once before it reached a saved checkpoint. '
            . 'The importer stopped to avoid a retry loop. Increase this site\'s PHP execution limit or split the source document, then start a new import.'
        );

        return false;
    }

    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    plpc_import_job_set_progress(
        $job,
        'ready_to_convert',
        max(1, (int) ($progress['completed'] ?? 1)),
        max(1, (int) ($progress['total'] ?? 1)),
        'The previous request ended mid-document. Retrying once from the saved import state.'
    );
    plpc_import_job_add_event(
        $job,
        'resuming',
        'The previous request ended mid-document. Retrying the current durable document unit.'
    );

    return true;
}

/** @param array<string, mixed> $job */
function plpc_import_job_clear_document_checkpoint(array &$job, int $documentIndex): void
{
    if ((int) (($job['checkpoint']['documentIndex'] ?? -1)) === $documentIndex) {
        unset($job['checkpoint']);
    }
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_add_event(array &$job, string $stage, string $message, bool $force = true): void
{
    $message = trim($message);
    if ($message === '') {
        return;
    }
    $events = is_array($job['events'] ?? null) ? $job['events'] : [];
    $last = $events === [] ? null : $events[count($events) - 1];
    if (!$force && is_array($last) && ($last['stage'] ?? '') === $stage && ($last['message'] ?? '') === $message) {
        return;
    }
    $events[] = [
        'stage' => $stage,
        'message' => $message,
        'time' => time(),
    ];
    if (count($events) > PLPC_IMPORT_JOB_MAX_EVENTS) {
        $events = array_slice($events, -PLPC_IMPORT_JOB_MAX_EVENTS);
    }
    $job['events'] = array_values($events);
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_progress_total(array $job): int
{
    $documents = is_array($job['documents'] ?? null) ? count($job['documents']) : 0;

    return max(6, max(1, $documents) * 6);
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_response(array $job, int $status = 200): WP_REST_Response
{
    $requests = [];
    foreach ($job['renderRequests'] ?? [] as $request) {
        if (!is_array($request)) {
            continue;
        }
        $bbox = plpc_import_job_normalize_bbox($request['bbox'] ?? null);
        if ($bbox === null) {
            continue;
        }
        $requests[] = [
            'id' => (string) ($request['id'] ?? ''),
            'path' => (string) ($request['path'] ?? ''),
            'page' => max(1, (int) ($request['page'] ?? 1)),
            'bbox' => $bbox,
            'label' => (string) ($request['label'] ?? 'PDF figure'),
        ];
    }
    $events = [];
    foreach ($job['events'] ?? [] as $event) {
        if (!is_array($event)) {
            continue;
        }
        $events[] = [
            'stage' => (string) ($event['stage'] ?? 'progress'),
            'message' => (string) ($event['message'] ?? 'Import is continuing.'),
            'time' => (int) ($event['time'] ?? 0),
        ];
    }
    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    $snapshot = [
        'ok' => ($job['status'] ?? '') !== 'failed',
        'jobId' => (string) ($job['id'] ?? ''),
        'status' => (string) ($job['status'] ?? 'queued'),
        'stage' => (string) ($job['stage'] ?? 'queued'),
        'progress' => [
            'completed' => max(0, (int) ($progress['completed'] ?? 0)),
            'total' => max(1, (int) ($progress['total'] ?? 1)),
            'label' => (string) ($progress['label'] ?? 'Preparing import.'),
        ],
        'events' => $events,
        'renderRequests' => $requests,
    ];
    if (is_array($job['result'] ?? null)) {
        $snapshot['result'] = $job['result'];
    }
    if (($job['status'] ?? '') === 'failed') {
        $snapshot['message'] = (string) ($job['error'] ?? 'The import failed.');
    }

    return new WP_REST_Response($snapshot, $status);
}

function plpc_import_job_error_response(string $message, int $status): WP_REST_Response
{
    return new WP_REST_Response([
        'ok' => false,
        'message' => $message,
    ], $status);
}

/**
 * @param array<string, mixed> $job
 * @param array<string, mixed> $payload
 */
function plpc_import_job_store_payload(array &$job, array $payload, string $directory): void
{
    $sourceFiles = [];
    $pdfRastersByPath = [];
    $storedPdfRasters = null;
    if (isset($payload['stagedFiles']) && is_array($payload['stagedFiles'])
        && (array_key_exists('files', $payload) || array_key_exists('bytes', $payload) || array_key_exists('stagedPath', $payload))) {
        throw new RuntimeException('An import must use either a staged-file manifest or encoded source bytes, not both.');
    }
    if (isset($payload['uploadedFiles']) && is_array($payload['uploadedFiles'])) {
        $sourceFiles = plpc_import_job_store_uploaded_source_files($directory, $payload['uploadedFiles'], (string) ($job['sourceKind'] ?? 'single'));
        $storedPdfRasters = plpc_import_job_store_uploaded_pdf_rasters(
            $directory,
            is_array($payload['uploadedPdfRasters'] ?? null) ? $payload['uploadedPdfRasters'] : [],
            $sourceFiles
        );
    } elseif (isset($payload['stagedFiles']) && is_array($payload['stagedFiles'])) {
        $sourceFiles = plpc_import_job_store_staged_source_files($directory, $payload['stagedFiles'], (string) ($job['sourceKind'] ?? 'single'));
        if (($job['sourceKind'] ?? 'single') === 'collection') {
            $job['sourceLabel'] = sanitize_text_field((string) ($payload['filename'] ?? $job['title'] ?? 'Import')) ?: (string) ($job['title'] ?? 'Import');
        }
        $pdfRastersByPath = plpc_pdf_raster_images_by_path($payload['pdfRasterImages'] ?? []);
    } elseif (array_key_exists('stagedPath', $payload)) {
        $filename = sanitize_file_name((string) ($payload['filename'] ?? 'upload'));
        if ($filename === '') {
            $filename = 'upload';
        }
        $sourceFiles = plpc_import_job_store_staged_source_files($directory, [[
            'path' => $filename,
            'stagedPath' => $payload['stagedPath'],
        ]], 'single');
        $pdfRastersByPath[$filename] = plpc_pdf_raster_images_from_payload($payload['pdfRasterImages'] ?? []);
    } elseif (isset($payload['files']) && is_array($payload['files'])) {
        $collection = plpc_collection_from_payload($payload, (string) $job['title']);
        $job['sourceKind'] = 'collection';
        $job['sourceLabel'] = (string) $collection['label'];
        $pdfRastersByPath = plpc_pdf_raster_images_by_path($payload['pdfRasterImages'] ?? []);
        foreach ($collection['files'] as $index => $file) {
            $path = (string) $file['path'];
            $storage = plpc_import_job_store_source_bytes($directory, $path, (string) $file['bytes'], $index, 'source');
            $sourceFiles[] = [
                'path' => $path,
                'storage' => $storage,
                'size' => strlen((string) $file['bytes']),
            ];
        }
    } else {
        $filename = sanitize_file_name((string) ($payload['filename'] ?? 'upload'));
        if ($filename === '') {
            $filename = 'upload';
        }
        $bytes = plpc_uploaded_document_bytes($payload);
        if (strlen($bytes) > PLPC_MAX_STAGED_UPLOAD_BYTES) {
            throw new RuntimeException('The selected file is too large to import.');
        }
        $storage = plpc_import_job_store_source_bytes($directory, $filename, $bytes, 0, 'source');
        $sourceFiles[] = [
            'path' => $filename,
            'storage' => $storage,
            'size' => strlen($bytes),
        ];
        $pdfRastersByPath[$filename] = plpc_pdf_raster_images_from_payload($payload['pdfRasterImages'] ?? []);
    }
    if ($sourceFiles === []) {
        throw new RuntimeException('No readable files were found in the selected upload.');
    }
    $job['sourceFiles'] = $sourceFiles;
    $job['pdfRasters'] = is_array($storedPdfRasters)
        ? $storedPdfRasters
        : plpc_import_job_store_pdf_rasters($directory, $pdfRastersByPath);
    $rasterCount = 0;
    foreach ($job['pdfRasters'] as $rasters) {
        if (is_array($rasters)) {
            $rasterCount += count($rasters);
        }
    }
    if ($rasterCount > 0) {
        plpc_import_job_add_event(
            $job,
            'browser_media',
            'Saved ' . $rasterCount . ' browser-decoded PDF image' . ($rasterCount === 1 ? '.' : 's.')
        );
    }
}

/**
 * @param list<array{name:string,tmpName:string,size:int,path:string}> $uploads
 * @return list<array{path:string,storage:string,size:int}>
 */
function plpc_import_job_store_uploaded_source_files(string $directory, array $uploads, string $sourceKind): array
{
    if (count($uploads) > PLPC_MAX_COLLECTION_FILES) {
        throw new RuntimeException('Too many files were selected for one import.');
    }
    $sourceFiles = [];
    $totalBytes = 0;
    $isCollection = $sourceKind === 'collection';
    foreach ($uploads as $index => $upload) {
        if (!is_array($upload)) {
            continue;
        }
        $path = plpc_normalize_collection_path((string) ($upload['path'] ?? $upload['name'] ?? ''));
        $tmpName = (string) ($upload['tmpName'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($path === '' || $tmpName === '' || $size <= 0 || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('A multipart upload file was unavailable. Please choose the files again.');
        }
        $perFileLimit = $isCollection ? PLPC_MAX_COLLECTION_FILE_BYTES : PLPC_MAX_STAGED_UPLOAD_BYTES;
        if ($size > $perFileLimit) {
            throw new RuntimeException('One selected file is too large to import.');
        }
        $totalBytes += $size;
        if ($totalBytes > ($isCollection ? PLPC_MAX_COLLECTION_TOTAL_BYTES : PLPC_MAX_STAGED_UPLOAD_BYTES)) {
            throw new RuntimeException('The selected files are too large to import together.');
        }
        $storage = plpc_import_job_store_uploaded_source_file($directory, $path, $tmpName, $index, 'source');
        $sourceFiles[] = [
            'path' => $path,
            'storage' => $storage,
            'size' => $size,
        ];
    }

    if ($sourceFiles === []) {
        throw new RuntimeException('No readable files were found in the multipart upload.');
    }

    return $sourceFiles;
}

/**
 * Move files that the Playground browser has already written to its private
 * temporary filesystem into durable job storage. This is deliberately a
 * manifest of paths rather than base64 document bodies: a 90 MB upload must
 * not become a 120 MB JSON string plus several PHP copies before conversion
 * even begins.
 *
 * @param list<array<string,mixed>> $stagedFiles
 * @return list<array{path:string,storage:string,size:int}>
 */
function plpc_import_job_store_staged_source_files(string $directory, array $stagedFiles, string $sourceKind): array
{
    if (!plpc_is_playground_environment()) {
        throw new RuntimeException('Staged uploads are only available inside WordPress Playground.');
    }
    if (count($stagedFiles) === 0) {
        throw new RuntimeException('No readable files were found in the selected upload.');
    }
    if (count($stagedFiles) > PLPC_MAX_COLLECTION_FILES) {
        throw new RuntimeException('Too many files were selected for one import.');
    }

    $sourceFiles = [];
    $seenPaths = [];
    $totalBytes = 0;
    $isCollection = $sourceKind === 'collection';
    foreach ($stagedFiles as $index => $staged) {
        if (!is_array($staged)) {
            throw new RuntimeException('One staged file was invalid. Please choose the files again.');
        }
        $path = plpc_normalize_collection_path((string) ($staged['path'] ?? $staged['filename'] ?? ''));
        if ($path === '') {
            throw new RuntimeException('One staged file did not have a valid path.');
        }
        $pathKey = strtolower($path);
        if (isset($seenPaths[$pathKey])) {
            throw new RuntimeException('The staged upload contains the same file path more than once.');
        }
        $seenPaths[$pathKey] = true;
        $stagedPath = plpc_staged_upload_path($staged['stagedPath'] ?? null);
        $size = filesize($stagedPath);
        if (!is_int($size) || $size <= 0) {
            throw new RuntimeException('A staged file was empty or could not be read. Please choose the files again.');
        }
        $perFileLimit = $isCollection ? PLPC_MAX_COLLECTION_FILE_BYTES : PLPC_MAX_STAGED_UPLOAD_BYTES;
        if ($size > $perFileLimit) {
            throw new RuntimeException('One selected file is too large to import.');
        }
        $totalBytes += $size;
        if ($totalBytes > ($isCollection ? PLPC_MAX_COLLECTION_TOTAL_BYTES : PLPC_MAX_STAGED_UPLOAD_BYTES)) {
            throw new RuntimeException('The selected files are too large to import together.');
        }

        $storage = plpc_import_job_move_staged_file($directory, $path, $stagedPath, (int) $index, 'source');
        $sourceFiles[] = [
            'path' => $path,
            'storage' => $storage,
            'size' => $size,
        ];
    }

    return $sourceFiles;
}

function plpc_import_job_move_staged_file(string $directory, string $path, string $stagedPath, int $index, string $bucket): string
{
    $safeBucket = $bucket === 'expanded' ? 'expanded' : 'source';
    $relative = $safeBucket . '/' . sprintf('%04d', max(0, $index)) . '-' . substr(sha1($path), 0, 20) . '.bin';
    $target = plpc_import_job_storage_target($directory, $relative);
    if (!wp_mkdir_p(dirname($target))) {
        throw new RuntimeException('WordPress could not prepare temporary import storage.');
    }
    if (!@rename($stagedPath, $target)) {
        // A Playground filesystem mount may not permit rename() across its
        // temporary and uploads roots. copy() stays stream-backed in PHP and
        // therefore avoids constructing a second document-sized PHP string.
        if (!@copy($stagedPath, $target) || !@unlink($stagedPath)) {
            @unlink($target);
            throw new RuntimeException('WordPress could not save the staged upload.');
        }
    }
    @chmod($target, 0600);

    return $relative;
}

function plpc_import_job_store_uploaded_source_file(string $directory, string $path, string $tmpName, int $index, string $bucket): string
{
    if (!is_uploaded_file($tmpName)) {
        throw new RuntimeException('The multipart upload file was unavailable.');
    }
    $safeBucket = $bucket === 'expanded' ? 'expanded' : 'source';
    $relative = $safeBucket . '/' . sprintf('%04d', max(0, $index)) . '-' . substr(sha1($path), 0, 20) . '.bin';
    $target = plpc_import_job_storage_target($directory, $relative);
    if (!wp_mkdir_p(dirname($target))) {
        throw new RuntimeException('WordPress could not prepare temporary import storage.');
    }
    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('WordPress could not save the multipart upload.');
    }
    @chmod($target, 0600);

    return $relative;
}

/**
 * Persist the bounded browser raster fallbacks supplied alongside a multipart
 * admin upload. The metadata is only a descriptor; the PNG bytes themselves
 * stay in PHP's normal upload channel instead of inflating a JSON request.
 *
 * @param list<array<string,mixed>> $uploads
 * @param list<array{path:string,storage:string,size:int}> $sourceFiles
 * @return array<string, list<array{object:string,storage:string,mimeType:string,width:int,height:int}>>
 */
function plpc_import_job_store_uploaded_pdf_rasters(string $directory, array $uploads, array $sourceFiles): array
{
    $sourcePaths = [];
    foreach ($sourceFiles as $source) {
        $sourcePaths[(string) $source['path']] = true;
    }
    $storedByPath = [];
    $totalBytes = 0;
    $rasterCount = 0;
    foreach ($uploads as $index => $raster) {
        if (!is_array($raster)) {
            break;
        }
        if ($rasterCount >= PLPC_MAX_PDF_RASTER_IMAGES) {
            throw new RuntimeException('Too many browser-rendered PDF images were supplied for this import.');
        }
        $path = plpc_normalize_collection_path((string) ($raster['path'] ?? ''));
        $object = (string) ($raster['object'] ?? '');
        $mimeType = strtolower(trim((string) ($raster['mimeType'] ?? '')));
        $width = (int) ($raster['width'] ?? 0);
        $height = (int) ($raster['height'] ?? 0);
        $upload = is_array($raster['upload'] ?? null) ? $raster['upload'] : [];
        $tmpName = (string) ($upload['tmpName'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($path === '' || !isset($sourcePaths[$path]) || preg_match('/^\d+$/', $object) !== 1
            || $mimeType !== 'image/png' || $width <= 0 || $height <= 0
            || $width * $height > PLPC_IMPORT_JOB_MAX_FORM_RENDER_PIXELS
            || $tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0 || $size > PLPC_MAX_PDF_RASTER_IMAGE_BYTES) {
            throw new RuntimeException('A browser-rendered PDF image was invalid.');
        }
        $totalBytes += $size;
        if ($totalBytes > PLPC_MAX_PDF_RASTER_BYTES) {
            throw new RuntimeException('The browser-rendered PDF images exceed the per-import media safety limit.');
        }
        $contents = file_get_contents($tmpName);
        if (!is_string($contents) || strlen($contents) !== $size || !str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('A browser-rendered PDF image could not be read safely.');
        }
        $dimensions = function_exists('getimagesizefromstring') ? @getimagesizefromstring($contents) : false;
        if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) !== $width || (int) ($dimensions[1] ?? 0) !== $height
            || strtolower((string) ($dimensions['mime'] ?? '')) !== 'image/png') {
            throw new RuntimeException('A browser-rendered PDF image dimensions did not match its image data.');
        }
        $relative = 'rasters/' . substr(sha1($path . "\0" . $object . "\0" . $index), 0, 28) . '.png';
        plpc_import_job_write_file($directory, $relative, $contents);
        $storedByPath[$path] ??= [];
        $storedByPath[$path][] = [
            'object' => $object,
            'storage' => $relative,
            'mimeType' => $mimeType,
            'width' => $width,
            'height' => $height,
        ];
        $rasterCount++;
    }

    return $storedByPath;
}

function plpc_import_job_store_source_bytes(string $directory, string $path, string $bytes, int $index, string $bucket): string
{
    if ($bytes === '') {
        throw new RuntimeException('The uploaded file was empty.');
    }
    $safeBucket = $bucket === 'expanded' ? 'expanded' : 'source';
    $relative = $safeBucket . '/' . sprintf('%04d', max(0, $index)) . '-' . substr(sha1($path), 0, 20) . '.bin';
    plpc_import_job_write_file($directory, $relative, $bytes);

    return $relative;
}

function plpc_import_job_write_file(string $directory, string $relative, string $bytes): void
{
    $target = plpc_import_job_storage_target($directory, $relative);
    if (!wp_mkdir_p(dirname($target))) {
        throw new RuntimeException('WordPress could not prepare temporary import storage.');
    }
    $written = file_put_contents($target, $bytes, LOCK_EX);
    if (!is_int($written) || $written !== strlen($bytes)) {
        throw new RuntimeException('WordPress could not save the uploaded import file.');
    }
    @chmod($target, 0600);
}

function plpc_import_job_storage_target(string $directory, string $relative): string
{
    if (!preg_match('#\A(?:source|expanded|rasters|rendered)/[A-Za-z0-9._-]+\z#', $relative)) {
        throw new RuntimeException('The import storage path was invalid.');
    }

    return $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

/**
 * Return the trusted, private file backing one persisted job record without
 * reading it into PHP memory. Callers must obtain $relative from job metadata;
 * request payloads never get to choose this path.
 *
 * @param array<string, mixed> $job
 */
function plpc_import_job_storage_path(array $job, string $relative): string
{
    if (!preg_match('#\A(?:source|expanded|rasters|rendered)/[A-Za-z0-9._-]+\z#', $relative)) {
        throw new RuntimeException('The saved import file path was invalid.');
    }
    $path = plpc_import_job_directory($job) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('A saved import file is no longer available. Please select it again.');
    }

    return $path;
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_read_file(array $job, string $relative): string
{
    $path = plpc_import_job_storage_path($job, $relative);
    $bytes = file_get_contents($path);
    if (!is_string($bytes)) {
        throw new RuntimeException('WordPress could not read a saved import file.');
    }

    return $bytes;
}

/**
 * @param array<string, mixed> $job
 * @return list<array{path:string,storage:string,size:int}>
 */
function plpc_import_job_source_file_records(array $job): array
{
    $files = [];
    foreach ($job['sourceFiles'] ?? [] as $file) {
        if (!is_array($file)) {
            continue;
        }
        $path = (string) ($file['path'] ?? '');
        $storage = (string) ($file['storage'] ?? '');
        if ($path === '' || $storage === '') {
            continue;
        }
        // Validate the durable record now, without loading its bytes.
        plpc_import_job_storage_path($job, $storage);
        $files[] = [
            'path' => $path,
            'storage' => $storage,
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    return $files;
}

/**
 * Detect a normal EPUB package from its small, mandatory mimetype entry.
 * This keeps content-based inference for a renamed DOCX/ZIP while allowing a
 * genuine .epub source to remain file-backed throughout preparation.
 */
function plpc_import_job_file_is_epub(string $path): bool
{
    if (!class_exists(ZipArchive::class)) {
        return false;
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        return false;
    }
    try {
        if ($zip->getNameIndex(0) !== 'mimetype') {
            return false;
        }
        $stat = $zip->statIndex(0);
        if (!is_array($stat) || (int) ($stat['size'] ?? 0) > 64) {
            return false;
        }
        $stream = $zip->getStream('mimetype');
        if (!is_resource($stream)) {
            return false;
        }
        try {
            $mimetype = stream_get_contents($stream, 65);
        } finally {
            fclose($stream);
        }

        return $mimetype === 'application/epub+zip';
    } finally {
        $zip->close();
    }
}

/**
 * @param array<string, mixed> $job
 * @param array{path:string,storage:string,size:int} $source
 */
function plpc_import_job_source_format(array $job, array $source): string
{
    $path = (string) $source['path'];
    $fromFilename = PandocFormatRegistry::inferDocumentTypeFromFilename($path);
    if ($fromFilename !== null && PandocConverter::canonicalInputFormat($fromFilename) === 'epub') {
        $storagePath = plpc_import_job_storage_path($job, (string) $source['storage']);
        if (plpc_import_job_file_is_epub($storagePath)) {
            return 'epub';
        }
    }

    return plpc_infer_document_format($path, plpc_import_job_read_file($job, (string) $source['storage']));
}

/**
 * Read only sources that cannot be established as EPUBs from their bounded
 * package metadata. EPUB records retain an empty byte field plus their
 * inferred format; the converter later receives the trusted storage path.
 *
 * @param array<string, mixed> $job
 * @return list<array{path:string,storage:string,size:int,bytes:string,format:string}>
 */
function plpc_import_job_prepare_source_files(array $job): array
{
    $files = [];
    foreach (plpc_import_job_source_file_records($job) as $source) {
        $format = plpc_import_job_source_format($job, $source);
        $files[] = $source + [
            'bytes' => $format === 'epub' ? '' : plpc_import_job_read_file($job, $source['storage']),
            'format' => $format,
        ];
    }

    return $files;
}

/**
 * @param array<string, mixed> $job
 * @return list<array{path:string,storage:string,size?:int,bytes:string}>
 */
function plpc_import_job_load_source_files(array $job): array
{
    $files = [];
    foreach ($job['sourceFiles'] ?? [] as $file) {
        if (!is_array($file)) {
            continue;
        }
        $path = (string) ($file['path'] ?? '');
        $storage = (string) ($file['storage'] ?? '');
        if ($path === '' || $storage === '') {
            continue;
        }
        $files[] = [
            'path' => $path,
            'storage' => $storage,
            'size' => (int) ($file['size'] ?? 0),
            'bytes' => plpc_import_job_read_file($job, $storage),
        ];
    }

    return $files;
}

/**
 * @param array<string, list<array{object:string,contents:string,mimeType:string,width:int,height:int}>> $rastersByPath
 * @return array<string, list<array{object:string,storage:string,mimeType:string,width:int,height:int}>>
 */
function plpc_import_job_store_pdf_rasters(string $directory, array $rastersByPath): array
{
    $storedByPath = [];
    foreach ($rastersByPath as $path => $rasters) {
        if (!is_array($rasters)) {
            continue;
        }
        $stored = [];
        foreach ($rasters as $index => $raster) {
            if (!is_array($raster)) {
                continue;
            }
            $contents = (string) ($raster['contents'] ?? '');
            $object = (string) ($raster['object'] ?? '');
            $mimeType = (string) ($raster['mimeType'] ?? '');
            $width = (int) ($raster['width'] ?? 0);
            $height = (int) ($raster['height'] ?? 0);
            if ($contents === '' || $object === '' || $mimeType !== 'image/png' || $width <= 0 || $height <= 0) {
                continue;
            }
            $relative = 'rasters/' . substr(sha1((string) $path . '\0' . $object . '\0' . $index), 0, 28) . '.png';
            plpc_import_job_write_file($directory, $relative, $contents);
            $stored[] = [
                'object' => $object,
                'storage' => $relative,
                'mimeType' => $mimeType,
                'width' => $width,
                'height' => $height,
            ];
        }
        if ($stored !== []) {
            $storedByPath[(string) $path] = $stored;
        }
    }

    return $storedByPath;
}

/**
 * @param array<string, mixed> $job
 * @return list<array{object:string,contents:string,mimeType:string,width:int,height:int}>
 */
function plpc_import_job_load_pdf_rasters(array $job, string $path): array
{
    $rasters = [];
    $records = is_array($job['pdfRasters'] ?? null) ? ($job['pdfRasters'][$path] ?? []) : [];
    if (!is_array($records)) {
        return [];
    }
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        try {
            $contents = plpc_import_job_read_file($job, (string) ($record['storage'] ?? ''));
        } catch (Throwable) {
            continue;
        }
        $rasters[] = [
            'object' => (string) ($record['object'] ?? ''),
            'contents' => $contents,
            'mimeType' => (string) ($record['mimeType'] ?? 'image/png'),
            'width' => (int) ($record['width'] ?? 0),
            'height' => (int) ($record['height'] ?? 0),
        ];
    }

    return $rasters;
}

/**
 * @param array<string, mixed> $job
 * @param array{label:string,files:list<array{path:string,bytes:string}>} $collection
 * @return array{label:string,files:list<array{path:string,bytes:string}>}
 */
function plpc_import_job_store_expanded_collection(array &$job, array $collection): array
{
    $directory = plpc_import_job_directory($job);
    $sourceFiles = [];
    foreach ($collection['files'] as $index => $file) {
        $path = (string) $file['path'];
        $bytes = (string) $file['bytes'];
        $storage = plpc_import_job_store_source_bytes($directory, $path, $bytes, $index, 'expanded');
        $sourceFiles[] = [
            'path' => $path,
            'storage' => $storage,
            'size' => strlen($bytes),
        ];
    }
    $job['sourceFiles'] = $sourceFiles;
    $job['pdfRasters'] = [];

    return [
        'label' => (string) $collection['label'],
        'files' => $collection['files'],
    ];
}

/**
 * @param array<string, mixed> $job
 * @return list<array<string, mixed>>
 */
function plpc_import_job_collect_form_render_requests(array $job): array
{
    if (($job['imageMode'] ?? 'important') === 'none'
        || !class_exists('PortLibs\\MarkerPDF\\PdfTextExtractor')) {
        return [];
    }
    $requests = [];
    foreach ($job['documents'] ?? [] as $document) {
        if (!is_array($document) || PandocConverter::canonicalInputFormat((string) ($document['format'] ?? '')) !== 'pdf') {
            continue;
        }
        $path = (string) ($document['path'] ?? '');
        if ($path === '') {
            continue;
        }
        try {
            $bytes = plpc_import_job_read_file($job, (string) ($document['storage'] ?? ''));
            $placements = (new \PortLibs\MarkerPDF\PdfTextExtractor())->extractFormXObjectPlacements($bytes);
        } catch (Throwable) {
            continue;
        }
        foreach ($placements as $placement) {
            if (!is_array($placement)
                || ($placement['visible'] ?? false) !== true
                || ($placement['placementEligible'] ?? false) !== true) {
                continue;
            }
            $bbox = plpc_import_job_normalize_bbox($placement['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }
            $width = $bbox['x2'] - $bbox['x1'];
            $height = $bbox['y2'] - $bbox['y1'];
            // This intentionally leaves small decorative Forms alone while
            // retaining charts, diagrams, and other page-layout figures.
            if ($width < 12.0 || $height < 12.0 || $width > 10000.0 || $height > 10000.0) {
                continue;
            }
            $formId = (string) ($placement['id'] ?? 'form');
            $requestId = 'form-' . substr(hash('sha256', $path . "\0" . $formId), 0, 28);
            $requests[] = [
                'id' => $requestId,
                'path' => $path,
                'page' => max(1, (int) ($placement['page'] ?? 1)),
                'bbox' => $bbox,
                'formId' => $formId,
                'object' => (int) ($placement['object'] ?? 0),
                'paintOrder' => (int) ($placement['paintOrder'] ?? 0),
                'precedingText' => is_string($placement['precedingText'] ?? null) ? $placement['precedingText'] : null,
                'followingText' => is_string($placement['followingText'] ?? null) ? $placement['followingText'] : null,
                'label' => 'PDF figure on page ' . max(1, (int) ($placement['page'] ?? 1)),
            ];
            if (count($requests) >= PLPC_IMPORT_JOB_MAX_FORM_RENDERS) {
                break 2;
            }
        }
    }

    return $requests;
}

/**
 * @return array{x1:float,y1:float,x2:float,y2:float}|null
 */
function plpc_import_job_normalize_bbox(mixed $bbox): ?array
{
    if (!is_array($bbox)) {
        return null;
    }
    foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
        if (!is_numeric($bbox[$coordinate] ?? null) || !is_finite((float) $bbox[$coordinate])) {
            return null;
        }
    }
    $x1 = (float) $bbox['x1'];
    $y1 = (float) $bbox['y1'];
    $x2 = (float) $bbox['x2'];
    $y2 = (float) $bbox['y2'];
    if ($x2 <= $x1 || $y2 <= $y1) {
        return null;
    }

    return compact('x1', 'y1', 'x2', 'y2');
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_render_request_index(array $job, string $requestId): ?int
{
    if (preg_match('/\Aform-[a-f0-9]{16,64}\z/', $requestId) !== 1) {
        return null;
    }
    foreach ($job['renderRequests'] ?? [] as $index => $renderRequest) {
        if (is_array($renderRequest) && hash_equals((string) ($renderRequest['id'] ?? ''), $requestId)) {
            return (int) $index;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $payload
 * @return array{contents:string,mimeType:string,width:int,height:int}
 */
function plpc_import_job_rendered_image_from_payload(array $payload): array
{
    $contents = base64_decode((string) ($payload['bytes'] ?? ''), true);
    $mimeType = strtolower(trim((string) ($payload['mimeType'] ?? '')));
    $width = (int) ($payload['width'] ?? 0);
    $height = (int) ($payload['height'] ?? 0);

    if (!is_string($contents)) {
        throw new RuntimeException('The browser-rendered PDF figure was invalid or exceeded the import safety limit.');
    }

    return plpc_import_job_validate_rendered_image($contents, $mimeType, $width, $height);
}

/**
 * @param array{name:string,tmpName:string,size:int} $upload
 * @param array<string, mixed> $payload
 * @return array{contents:string,mimeType:string,width:int,height:int}
 */
function plpc_import_job_rendered_image_from_uploaded_file(array $upload, array $payload): array
{
    $tmpName = (string) ($upload['tmpName'] ?? '');
    $size = (int) ($upload['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0 || $size > PLPC_IMPORT_JOB_MAX_FORM_RENDER_IMAGE_BYTES) {
        throw new RuntimeException('The browser-rendered PDF figure was invalid or exceeded the import safety limit.');
    }
    $contents = file_get_contents($tmpName);
    if (!is_string($contents) || strlen($contents) !== $size) {
        throw new RuntimeException('The browser-rendered PDF figure could not be read.');
    }

    return plpc_import_job_validate_rendered_image(
        $contents,
        strtolower(trim((string) ($payload['mimeType'] ?? ''))),
        (int) ($payload['width'] ?? 0),
        (int) ($payload['height'] ?? 0)
    );
}

/** @return array{contents:string,mimeType:string,width:int,height:int} */
function plpc_import_job_validate_rendered_image(string $contents, string $mimeType, int $width, int $height): array
{
    if (!is_string($contents) || $contents === '' || strlen($contents) > PLPC_IMPORT_JOB_MAX_FORM_RENDER_IMAGE_BYTES
        || !in_array($mimeType, ['image/png', 'image/webp', 'image/avif'], true)
        || $width <= 0 || $height <= 0 || $width * $height > PLPC_IMPORT_JOB_MAX_FORM_RENDER_PIXELS
        || !plpc_import_job_rendered_image_has_expected_signature($contents, $mimeType)) {
        throw new RuntimeException('The browser-rendered PDF figure was invalid or exceeded the import safety limit.');
    }

    // Do not trust dimensions declared by the browser. A tiny crafted image
    // can claim a harmless size while advertising enormous pixels to the
    // WordPress image metadata generator. getimagesizefromstring() is core
    // PHP (not GD) and supports the PNG output used by our PDF.js renderer.
    $actual = function_exists('getimagesizefromstring') ? @getimagesizefromstring($contents) : false;
    if (!is_array($actual) || !isset($actual[0], $actual[1])) {
        throw new RuntimeException('The browser-rendered PDF figure could not be inspected safely.');
    }
    $actualWidth = (int) $actual[0];
    $actualHeight = (int) $actual[1];
    $actualMimeType = strtolower((string) ($actual['mime'] ?? ''));
    if ($actualWidth !== $width || $actualHeight !== $height || $actualMimeType !== $mimeType
        || $actualWidth <= 0 || $actualHeight <= 0 || $actualWidth * $actualHeight > PLPC_IMPORT_JOB_MAX_FORM_RENDER_PIXELS) {
        throw new RuntimeException('The browser-rendered PDF figure dimensions did not match its image data.');
    }

    return compact('contents', 'mimeType', 'width', 'height');
}

function plpc_import_job_rendered_image_has_expected_signature(string $contents, string $mimeType): bool
{
    return match ($mimeType) {
        'image/png' => str_starts_with($contents, "\x89PNG\r\n\x1a\n"),
        'image/webp' => strlen($contents) >= 12 && substr($contents, 0, 4) === 'RIFF' && substr($contents, 8, 4) === 'WEBP',
        'image/avif' => strlen($contents) >= 12 && substr($contents, 4, 4) === 'ftyp' && str_contains(substr($contents, 8, 24), 'avif'),
        default => false,
    };
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_rendered_form_total_bytes(array $job): int
{
    $total = 0;
    foreach ($job['renderedForms'] ?? [] as $rendered) {
        if (!is_array($rendered) || !isset($rendered['storage'])) {
            continue;
        }
        try {
            $bytes = plpc_import_job_read_file($job, (string) $rendered['storage']);
            $total += strlen($bytes);
        } catch (Throwable) {
            continue;
        }
    }

    return $total;
}

/**
 * @param array<string, mixed> $renderRequest
 * @param array{contents:string,mimeType:string,width:int,height:int} $rendered
 * @return array<string, mixed>
 */
function plpc_import_job_store_rendered_form(string $directory, string $requestId, array $renderRequest, array $rendered): array
{
    $extension = match ($rendered['mimeType']) {
        'image/webp' => '.webp',
        'image/avif' => '.avif',
        default => '.png',
    };
    $relative = 'rendered/' . $requestId . $extension;
    plpc_import_job_write_file($directory, $relative, $rendered['contents']);

    return [
        'requestId' => $requestId,
        'formId' => (string) ($renderRequest['formId'] ?? ''),
        'path' => (string) ($renderRequest['path'] ?? ''),
        'storage' => $relative,
        'mimeType' => $rendered['mimeType'],
        'width' => $rendered['width'],
        'height' => $rendered['height'],
        'page' => max(1, (int) ($renderRequest['page'] ?? 1)),
        'bbox' => $renderRequest['bbox'] ?? [],
        'paintOrder' => (int) ($renderRequest['paintOrder'] ?? 0),
        'precedingText' => $renderRequest['precedingText'] ?? null,
        'followingText' => $renderRequest['followingText'] ?? null,
    ];
}

/**
 * @param array<string, mixed> $job
 * @return list<array<string, mixed>>
 */
function plpc_import_job_load_rendered_forms(array $job, string $path): array
{
    $forms = [];
    foreach ($job['renderedForms'] ?? [] as $rendered) {
        if (!is_array($rendered) || (string) ($rendered['path'] ?? '') !== $path || !isset($rendered['storage'])) {
            continue;
        }
        try {
            $contents = plpc_import_job_read_file($job, (string) $rendered['storage']);
        } catch (Throwable) {
            continue;
        }
        $forms[] = [
            'id' => (string) ($rendered['requestId'] ?? ''),
            'formId' => (string) ($rendered['formId'] ?? ''),
            'contents' => $contents,
            'mimeType' => (string) ($rendered['mimeType'] ?? 'image/png'),
            'width' => (int) ($rendered['width'] ?? 0),
            'height' => (int) ($rendered['height'] ?? 0),
            'page' => max(1, (int) ($rendered['page'] ?? 1)),
            'bbox' => $rendered['bbox'] ?? [],
            'paintOrder' => (int) ($rendered['paintOrder'] ?? 0),
            'precedingText' => $rendered['precedingText'] ?? null,
            'followingText' => $rendered['followingText'] ?? null,
        ];
    }

    return $forms;
}

function plpc_convert_uploaded_document(WP_REST_Request $request): WP_REST_Response
{
    plpc_import_apply_runtime_limits();

    try {
        $payload = json_decode((string) $request->get_body(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid request payload.');
        }

        $filename = sanitize_file_name((string) ($payload['filename'] ?? 'upload'));
        $title = sanitize_text_field((string) ($payload['title'] ?? 'Converted document'));
        if ($title === '') {
            $title = plpc_title_from_filename($filename);
        }
        $imageMode = plpc_normalize_image_mode($payload['imageMode'] ?? 'important');
        $pdfMode = plpc_normalize_pdf_mode($payload['pdfMode'] ?? 'layout');

        if (isset($payload['files']) && is_array($payload['files'])) {
            return plpc_collection_response(plpc_collection_from_payload($payload, $title), $title, $imageMode, $pdfMode);
        }

        $bytes = plpc_uploaded_document_bytes($payload);

        $format = plpc_infer_document_format($filename, $bytes);
        if ($format === '') {
            throw new RuntimeException('Could not infer a supported document type from the uploaded filename or contents.');
        }

        if (plpc_should_expand_zip_upload($format, $bytes)) {
            return plpc_collection_response(plpc_collection_from_zip($bytes, $filename, $title), $title, $imageMode, $pdfMode);
        }

        $result = plpc_convert_collection_file_to_page([
            'path' => $filename,
            'bytes' => $bytes,
            'pdfRasterImages' => plpc_pdf_raster_images_from_payload($payload['pdfRasterImages'] ?? []),
        ], null, $title, $imageMode, $pdfMode);

        return new WP_REST_Response([
            'ok' => true,
            'postId' => $result['postId'],
            'pageUrl' => $result['pageUrl'],
            'editUrl' => $result['editUrl'],
            'format' => $result['format'],
            'title' => $result['title'],
            'path' => $result['path'],
            'imageTagCount' => $result['imageTagCount'],
            'imagesImported' => $result['imagesImported'],
            'diagnostics' => $result['diagnostics'],
            'quality' => $result['quality'],
        ]);
    } catch (Throwable $error) {
        return new WP_REST_Response([
            'ok' => false,
            'message' => $error->getMessage(),
        ], 500);
    }
}

/**
 * Read a document supplied by the Playground client. Browser uploads are
 * staged in Playground's local /tmp filesystem before the small REST request
 * is made, avoiding base64 expansion and PHP request-body limits. Keep the
 * legacy base64 field for existing trusted integrations.
 *
 * @param array<string, mixed> $payload
 */
function plpc_uploaded_document_bytes(array $payload): string
{
    $stagedPath = plpc_staged_upload_path_from_payload($payload);
    if ($stagedPath !== null) {
        try {
            $size = filesize($stagedPath);
            if (!is_int($size) || $size <= 0) {
                throw new RuntimeException('The staged upload was empty or could not be read. Please choose the file again.');
            }
            if ($size > PLPC_MAX_STAGED_UPLOAD_BYTES) {
                throw new RuntimeException('The selected file is too large to import.');
            }

            $bytes = file_get_contents($stagedPath);
            if (!is_string($bytes) || $bytes === '') {
                throw new RuntimeException('The staged upload was empty or could not be read. Please choose the file again.');
            }

            return $bytes;
        } finally {
            // The source has been copied into memory for conversion. Do not
            // leave an unbounded sequence of browser uploads in Playground's
            // temporary filesystem.
            @unlink($stagedPath);
        }
    }

    $base64 = (string) ($payload['bytes'] ?? '');
    if (strlen($base64) > plpc_max_base64_length(PLPC_MAX_LEGACY_JSON_SOURCE_BYTES)) {
        throw new RuntimeException('The uploaded file is too large for the legacy encoded upload API. Use a staged or multipart upload.');
    }
    $bytes = base64_decode($base64, true);
    if (!is_string($bytes) || $bytes === '' || strlen($bytes) > PLPC_MAX_LEGACY_JSON_SOURCE_BYTES) {
        throw new RuntimeException('The uploaded file was empty or could not be decoded.');
    }

    return $bytes;
}

function plpc_max_base64_length(int $decodedBytes): int
{
    return max(4, (int) (4 * ceil(max(0, $decodedBytes) / 3)) + 4);
}

/**
 * Only accept a direct, regular file in the dedicated Playground staging
 * namespace. The REST payload is otherwise untrusted and must never select an
 * arbitrary local path.
 *
 * @param array<string, mixed> $payload
 */
function plpc_staged_upload_path_from_payload(array $payload): ?string
{
    if (!array_key_exists('stagedPath', $payload)) {
        return null;
    }
    if (array_key_exists('bytes', $payload)) {
        throw new RuntimeException('An upload must use either staged bytes or encoded bytes, not both.');
    }
    return plpc_staged_upload_path($payload['stagedPath']);
}

/**
 * Validate one browser-staged path. The same root validation is shared by a
 * single document and a staged collection manifest.
 */
function plpc_staged_upload_path(mixed $stagedPath): string
{
    if (!plpc_is_playground_environment()) {
        throw new RuntimeException('Staged uploads are only available inside WordPress Playground.');
    }
    if (!is_string($stagedPath)) {
        throw new RuntimeException('The staged upload path was invalid. Please choose the file again.');
    }

    $path = $stagedPath;
    $root = realpath(PLPC_STAGED_UPLOAD_DIRECTORY);
    $resolvedPath = realpath($path);
    $rootPrefix = is_string($root) ? rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
    if (
        $path === ''
        || str_contains($path, "\0")
        || !str_starts_with($path, PLPC_STAGED_UPLOAD_DIRECTORY . '/')
        || !is_string($root)
        || !is_string($resolvedPath)
        || !str_starts_with($resolvedPath, $rootPrefix)
        || !is_file($resolvedPath)
        || is_link($path)
    ) {
        throw new RuntimeException('The staged upload is unavailable. Please choose the file again.');
    }

    return $resolvedPath;
}

function plpc_should_expand_zip_upload(string $format, string $bytes): bool
{
    return $format === 'zip' && plpc_zip_package($bytes) !== null;
}

/**
 * @param array<string, mixed> $payload
 * @return array{label: string, files: list<array{path: string, bytes: string}>}
 */
function plpc_collection_from_payload(array $payload, string $fallbackTitle): array
{
    $files = [];
    $totalBytes = 0;
    $rasterImagesByPath = plpc_pdf_raster_images_by_path($payload['pdfRasterImages'] ?? []);
    foreach ($payload['files'] ?? [] as $index => $file) {
        if (!is_array($file)) {
            continue;
        }
        $path = plpc_normalize_collection_path((string) ($file['path'] ?? $file['filename'] ?? 'file-' . $index));
        if ($path === '' || plpc_collection_path_is_ignored($path)) {
            continue;
        }
        $encoded = (string) ($file['bytes'] ?? '');
        if (strlen($encoded) > plpc_max_base64_length(PLPC_MAX_COLLECTION_FILE_BYTES)) {
            throw new RuntimeException('One selected file is too large to import.');
        }
        $bytes = base64_decode($encoded, true);
        if (!is_string($bytes) || $bytes === '') {
            continue;
        }
        $size = strlen($bytes);
        if ($size > PLPC_MAX_COLLECTION_FILE_BYTES) {
            continue;
        }
        $totalBytes += $size;
        if ($totalBytes > PLPC_MAX_COLLECTION_TOTAL_BYTES) {
            throw new RuntimeException('The selected files are too large to import together.');
        }
        $entry = [
            'path' => $path,
            'bytes' => $bytes,
        ];
        if (isset($rasterImagesByPath[$path])) {
            $entry['pdfRasterImages'] = $rasterImagesByPath[$path];
        }
        $files[] = $entry;
        if (count($files) >= PLPC_MAX_COLLECTION_FILES) {
            break;
        }
    }

    if ($files === []) {
        throw new RuntimeException('No readable files were found in the selected folder.');
    }

    return [
        'label' => sanitize_text_field((string) ($payload['filename'] ?? $fallbackTitle)) ?: $fallbackTitle,
        'files' => plpc_sort_collection_files($files),
    ];
}

/**
 * @return array{label: string, files: list<array{path: string, bytes: string}>}
 */
function plpc_collection_from_zip(string $bytes, string $filename, string $fallbackTitle = ''): array
{
    $package = plpc_zip_package($bytes);
    if ($package === null) {
        throw new RuntimeException('The ZIP file could not be read.');
    }

    $files = [];
    $totalBytes = 0;
    foreach ($package->entries() as $entry) {
        if ($entry->isDirectory()) {
            continue;
        }
        $path = plpc_normalize_collection_path($entry->name);
        if ($path === '' || plpc_collection_path_is_ignored($path)) {
            continue;
        }
        if ($entry->uncompressedSize > PLPC_MAX_COLLECTION_FILE_BYTES) {
            continue;
        }
        $totalBytes += $entry->uncompressedSize;
        if ($totalBytes > PLPC_MAX_COLLECTION_TOTAL_BYTES) {
            throw new RuntimeException('The ZIP file is too large to import in one batch.');
        }
        try {
            $entryBytes = $package->read($entry->name, PLPC_MAX_COLLECTION_FILE_BYTES);
        } catch (Throwable) {
            continue;
        }
        if ($entryBytes === '') {
            continue;
        }
        $files[] = [
            'path' => $path,
            'bytes' => $entryBytes,
        ];
        if (count($files) >= PLPC_MAX_COLLECTION_FILES) {
            break;
        }
    }

    if ($files === []) {
        throw new RuntimeException('No readable files were found in the ZIP file.');
    }

    return [
        'label' => $fallbackTitle !== '' ? $fallbackTitle : plpc_title_from_filename($filename),
        'files' => plpc_sort_collection_files($files),
    ];
}

/**
 * @param list<array{path: string, bytes: string, pdfRasterImages?: list<array{object:string,contents:string,mimeType:string,width:int,height:int}>}> $files
 * @return list<array{path: string, bytes: string, pdfRasterImages?: list<array{object:string,contents:string,mimeType:string,width:int,height:int}>}>
 */
function plpc_sort_collection_files(array $files): array
{
    usort($files, static fn (array $left, array $right): int => strnatcasecmp($left['path'], $right['path']));

    return array_values($files);
}

function plpc_normalize_collection_path(string $path, string $baseDir = ''): string
{
    $path = html_entity_decode($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $path = rawurldecode($path);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    if ($baseDir !== '' && !str_starts_with($path, '/')) {
        $path = rtrim($baseDir, '/') . '/' . $path;
    }
    $path = ltrim($path, '/');

    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
}

function plpc_collection_path_is_ignored(string $path): bool
{
    $basename = basename($path);

    return $basename === ''
        || $basename === '.DS_Store'
        || str_starts_with($basename, '.')
        || str_starts_with($path, '__MACOSX/')
        || str_ends_with($path, '/');
}

/**
 * @param array{label: string, files: list<array{path: string, bytes: string, pdfRasterImages?: list<array{object:string,contents:string,mimeType:string,width:int,height:int}>}>} $collection
 */
function plpc_collection_response(array $collection, string $title, string $imageMode = 'important', string $pdfMode = 'layout'): WP_REST_Response
{
    $documents = plpc_convertible_collection_files($collection);
    if ($documents === []) {
        throw new RuntimeException('No supported document files were found.');
    }

    $posts = [];
    $diagnostics = [];
    $imageTagCount = 0;
    $imagesImported = 0;
    foreach ($documents as $file) {
        try {
            $result = plpc_convert_collection_file_to_page($file, $collection, null, $imageMode, $pdfMode);
            $posts[] = $result;
            $imageTagCount += $result['imageTagCount'];
            $imagesImported += $result['imagesImported'];
            foreach ($result['diagnostics'] as $diagnostic) {
                $diagnostics[] = $file['path'] . ':' . $diagnostic;
            }
        } catch (Throwable $error) {
            $diagnostics[] = $file['path'] . ':document-failed:' . $error->getMessage();
        }
    }

    if ($posts === []) {
        throw new RuntimeException('None of the supported files could be converted.');
    }

    if (count($posts) === 1) {
        $post = $posts[0];

        return new WP_REST_Response([
            'ok' => true,
            'batch' => true,
            'postCount' => 1,
            'posts' => $posts,
            'postId' => $post['postId'],
            'pageUrl' => $post['pageUrl'],
            'editUrl' => $post['editUrl'],
            'format' => $post['format'],
            'title' => $post['title'],
            'path' => $post['path'],
            'imageTagCount' => $imageTagCount,
            'imagesImported' => $imagesImported,
            'diagnostics' => $diagnostics,
            'quality' => plpc_import_quality_report($post['format'], $diagnostics, $imageTagCount, $imagesImported),
        ]);
    }

    $indexTitle = $title !== '' ? $title : (string) $collection['label'];
    $indexBlocks = plpc_collection_index_blocks($indexTitle, $posts, $diagnostics);
    $indexPostId = wp_insert_post([
        'post_type' => 'page',
        'post_title' => $indexTitle,
        'post_status' => 'publish',
        'post_content' => $indexBlocks,
    ], true);
    if (is_wp_error($indexPostId)) {
        throw new RuntimeException($indexPostId->get_error_message());
    }

    return new WP_REST_Response([
        'ok' => true,
        'batch' => true,
        'postCount' => count($posts),
        'posts' => $posts,
        'postId' => (int) $indexPostId,
        'pageUrl' => get_permalink((int) $indexPostId),
        'editUrl' => get_edit_post_link((int) $indexPostId, 'raw'),
        'title' => get_the_title((int) $indexPostId),
        'imageTagCount' => $imageTagCount,
        'imagesImported' => $imagesImported,
        'diagnostics' => $diagnostics,
        'quality' => plpc_import_quality_report('', $diagnostics, $imageTagCount, $imagesImported),
    ]);
}

/**
 * @param array{label: string, files: list<array{path: string, bytes: string, pdfRasterImages?: list<array{object:string,contents:string,mimeType:string,width:int,height:int}>}>} $collection
 * @return list<array{path: string, bytes: string, format: string, pdfRasterImages?: list<array{object:string,contents:string,mimeType:string,width:int,height:int}>}>
 */
function plpc_convertible_collection_files(array $collection): array
{
    $documents = [];
    foreach ($collection['files'] as $file) {
        $path = $file['path'];
        if (plpc_path_is_image($path)) {
            continue;
        }
        $format = (string) ($file['format'] ?? '');
        if ($format === '') {
            $format = plpc_infer_document_format($path, $file['bytes']);
        }
        if ($format === '' || $format === 'zip') {
            continue;
        }
        try {
            if (!PandocConverter::canRead($format)) {
                continue;
            }
        } catch (Throwable) {
            continue;
        }
        $document = [
            'path' => $path,
            'bytes' => $file['bytes'],
            'format' => $format,
        ];
        if (isset($file['pdfRasterImages']) && is_array($file['pdfRasterImages'])) {
            $document['pdfRasterImages'] = $file['pdfRasterImages'];
        }
        $documents[] = $document;
    }

    return $documents;
}

/**
 * @param array{path: string, bytes?: string, format?: string, sourcePath?: string, pdfRasterImages?: list<array{object:string,contents:string,mimeType:string,width:int,height:int}>, pdfFormRenders?: list<array<string,mixed>>} $file
 * @param array{label: string, files: list<array{path: string, bytes: string}>}|null $collection
 * @return array{postId: int, pageUrl: string, editUrl: string, format: string, title: string, path: string, imageTagCount: int, imagesImported: int, diagnostics: list<string>, quality: array{status:string, flags:list<string>, warnings:list<string>}}
 */
function plpc_convert_collection_file_to_page(array $file, ?array $collection = null, ?string $title = null, string $imageMode = 'important', string $pdfMode = 'layout', ?callable $reportProgress = null): array
{
    $path = (string) ($file['path'] ?? 'document');
    $bytes = isset($file['bytes']) && is_string($file['bytes']) ? $file['bytes'] : '';
    $format = (string) ($file['format'] ?? '');
    if ($format === '') {
        $format = plpc_infer_document_format($path, $bytes);
    }
    if ($format === '' || $format === 'zip' || !PandocConverter::canRead($format)) {
        throw new RuntimeException('Could not infer a supported document type for ' . basename($path) . '.');
    }
    $canonicalFormat = PandocConverter::canonicalInputFormat($format);
    $sourcePath = isset($file['sourcePath']) && is_string($file['sourcePath']) ? $file['sourcePath'] : '';
    $fileBackedEpub = $canonicalFormat === 'epub' && $sourcePath !== '' && is_file($sourcePath) && is_readable($sourcePath);
    $postTitle = $title !== null && $title !== '' ? $title : plpc_title_from_filename($path);
    $options = plpc_converter_options($format, $pdfMode);
    plpc_conversion_progress($reportProgress, 'reading', 'Reading document structure and text.');
    if ($fileBackedEpub) {
        $options['readerOptions']['sourcePath'] = $sourcePath;
        $document = PandocConverter::readFile($sourcePath, $format, $options['readerOptions']);
    } else {
        $document = PandocConverter::read($bytes, $format, $options['readerOptions']);
    }
    if ($canonicalFormat === 'pdf' && ($file['pdfFormRenders'] ?? []) !== []) {
        plpc_conversion_progress($reportProgress, 'extracting_media', 'Placing browser-rendered PDF figures near their text.');
        $document = plpc_document_with_browser_pdf_form_renders($document, $file['pdfFormRenders']);
    }
    plpc_conversion_progress($reportProgress, 'extracting_media', 'Extracting images and other document media.');
    $mediaOptions = [
        'destination' => 'media',
        'imageMode' => $imageMode,
        'pdfRasterImages' => is_array($file['pdfRasterImages'] ?? null) ? $file['pdfRasterImages'] : [],
    ];
    if ($fileBackedEpub) {
        $mediaOptions['sourcePath'] = $sourcePath;
    }
    $extractor = new PandocMediaExtractor();
    $media = $fileBackedEpub
        ? $extractor->extractFile($document, $sourcePath, $format, $mediaOptions)
        : $extractor->extract($document, $bytes, $format, $mediaOptions);
    $document = $media['document'];
    plpc_conversion_progress($reportProgress, 'writing_blocks', 'Writing WordPress block markup.');
    $blocks = PandocConverter::write($document, 'wordpress', $options['writerOptions']);

    $imageSources = plpc_rendered_image_sources($blocks);
    plpc_conversion_progress($reportProgress, 'uploading_media', 'Uploading extracted media to the WordPress media library.');
    $mediaResult = plpc_import_extracted_media_entries($blocks, $imageSources, $media['entries']);
    $remainingSources = array_values(array_filter($imageSources, static fn (string $source): bool => !in_array($source, $mediaResult['sources'], true)));
    if ($remainingSources === []) {
        $fallbackMediaResult = [
            'blocks' => $mediaResult['blocks'],
            'imported' => 0,
            'diagnostics' => [],
        ];
    } elseif ($fileBackedEpub) {
        // extractFile() already inspected the EPUB archive through its
        // file-backed reader. Re-reading the whole ZIP only to retry an
        // unresolved source would defeat that memory boundary; retain the
        // source in the block and make the limitation visible instead.
        $fallbackMediaResult = [
            'blocks' => $mediaResult['blocks'],
            'imported' => 0,
            'diagnostics' => array_map(static fn (string $source): string => 'image-not-resolved:' . $source, $remainingSources),
        ];
    } else {
        $fallbackMediaResult = plpc_import_rendered_images($mediaResult['blocks'], $remainingSources, $bytes, basename($path), $collection, $path);
    }
    $blocks = $mediaResult['blocks'];
    $blocks = $fallbackMediaResult['blocks'];

    $diagnostics = array_values(array_merge(
        plpc_document_diagnostics($document, $format),
        $media['diagnostics'],
        $mediaResult['diagnostics'],
        $fallbackMediaResult['diagnostics']
    ));
    $imageTagCount = count($imageSources);
    $imagesImported = $mediaResult['imported'] + $fallbackMediaResult['imported'];
    $quality = plpc_import_quality_report($format, $diagnostics, $imageTagCount, $imagesImported);

    // By the time the blocks and diagnostics exist, the reader AST, ZIP
    // handles, extracted-media bag, and source bytes are no longer needed.
    // Keeping them alive through wp_insert_post() needlessly overlaps their
    // peak with WordPress's database serialization (which copies the block
    // string). That is particularly costly for a long EPUB on a normal
    // 128 MiB PHP worker. Release the conversion phase before creating the
    // page; the durable job still retains its private source file if a later
    // request needs to retry.
    unset(
        $document,
        $media,
        $mediaOptions,
        $mediaResult,
        $fallbackMediaResult,
        $imageSources,
        $remainingSources,
        $file,
        $bytes,
        $collection,
        $options
    );
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    $blocks = plpc_prepend_conversion_warning_blocks($blocks, $format, $diagnostics);
    $blocks = plpc_prepend_import_quality_blocks($blocks, $quality);

    plpc_conversion_progress($reportProgress, 'creating_page', 'Creating the WordPress page.');
    $postId = wp_insert_post([
        'post_type' => 'page',
        'post_title' => $postTitle,
        'post_status' => 'publish',
        'post_content' => $blocks,
    ], true);
    if (is_wp_error($postId)) {
        throw new RuntimeException($postId->get_error_message());
    }

    return [
        'postId' => (int) $postId,
        'pageUrl' => get_permalink((int) $postId),
        'editUrl' => get_edit_post_link((int) $postId, 'raw'),
        'format' => $format,
        'title' => get_the_title((int) $postId),
        'path' => $path,
        'imageTagCount' => $imageTagCount,
        'imagesImported' => $imagesImported,
        'diagnostics' => $diagnostics,
        'quality' => $quality,
    ];
}

function plpc_conversion_progress(?callable $reportProgress, string $stage, string $label): void
{
    if ($reportProgress !== null) {
        $reportProgress($stage, $label);
    }
}

/**
 * Browser PDF.js rendering is used only for full Form-XObject page crops. The
 * returned image enters the ordinary media extractor as a data URI, so it is
 * uploaded, deduplicated, and turned into a native WordPress image exactly
 * like media from DOCX/EPUB/HTML imports.
 *
 * @param list<array<string, mixed>> $renders
 */
function plpc_document_with_browser_pdf_form_renders(object $document, array $renders): object
{
    if (!$document instanceof \PortLibs\Pandoc\AstNode || $renders === []) {
        return $document;
    }
    $metadata = $document->attr('meta', []);
    $placementsById = [];
    if (is_array($metadata) && is_array($metadata['pdfFormXObjectPlacements'] ?? null)) {
        foreach ($metadata['pdfFormXObjectPlacements'] as $placement) {
            if (is_array($placement) && is_string($placement['id'] ?? null)) {
                $placementsById[$placement['id']] = $placement;
            }
        }
    }
    $before = [];
    $after = [];
    $trailing = [];
    $children = $document->children;
    foreach ($renders as $render) {
        if (!is_array($render) || !is_string($render['contents'] ?? null) || $render['contents'] === '') {
            continue;
        }
        $placement = $placementsById[(string) ($render['formId'] ?? '')] ?? [];
        if (is_array($placement)) {
            foreach (['page', 'bbox', 'paintOrder', 'precedingText', 'followingText'] as $key) {
                if (array_key_exists($key, $placement)) {
                    $render[$key] = $placement[$key];
                }
            }
        }
        $block = plpc_browser_pdf_form_render_block($render);
        $following = plpc_browser_pdf_form_anchor_index($children, $render['followingText'] ?? null);
        $preceding = plpc_browser_pdf_form_anchor_index($children, $render['precedingText'] ?? null);
        if ($following !== null && ($preceding === null || $preceding < $following)) {
            $before[$following][] = $render + ['block' => $block];
        } elseif ($preceding !== null) {
            $after[$preceding][] = $render + ['block' => $block];
        } else {
            $trailing[] = $render + ['block' => $block];
        }
    }
    if ($before === [] && $after === [] && $trailing === []) {
        return $document;
    }
    $sort = static function (array &$group): void {
        usort($group, static fn (array $left, array $right): int => ((int) ($left['paintOrder'] ?? 0)) <=> ((int) ($right['paintOrder'] ?? 0)));
    };
    foreach ($before as &$group) {
        $sort($group);
    }
    unset($group);
    foreach ($after as &$group) {
        $sort($group);
    }
    unset($group);
    $sort($trailing);

    $newChildren = [];
    foreach ($children as $index => $child) {
        foreach ($before[$index] ?? [] as $render) {
            $newChildren[] = $render['block'];
        }
        $newChildren[] = $child;
        foreach ($after[$index] ?? [] as $render) {
            $newChildren[] = $render['block'];
        }
    }
    foreach ($trailing as $render) {
        $newChildren[] = $render['block'];
    }

    return new \PortLibs\Pandoc\AstNode($document->type, $document->attrs, $newChildren);
}

/**
 * @param list<\PortLibs\Pandoc\AstNode> $blocks
 */
function plpc_browser_pdf_form_anchor_index(array $blocks, mixed $anchor): ?int
{
    if (!is_string($anchor)) {
        return null;
    }
    $anchor = preg_replace('/\s+/u', ' ', trim($anchor)) ?? '';
    $anchorLength = function_exists('mb_strlen') ? mb_strlen($anchor, 'UTF-8') : strlen($anchor);
    if ($anchorLength < 3) {
        return null;
    }
    $found = null;
    foreach ($blocks as $index => $block) {
        if (!$block instanceof \PortLibs\Pandoc\AstNode || !in_array($block->type, ['paragraph', 'heading', 'plain'], true)) {
            continue;
        }
        $text = preg_replace('/\s+/u', ' ', trim((string) $block->attr('text', ''))) ?? '';
        if ($text === '' || !str_contains($text, $anchor)) {
            continue;
        }
        if ($found !== null) {
            return null;
        }
        $found = $index;
    }

    return $found;
}

/**
 * @param array<string, mixed> $render
 */
function plpc_browser_pdf_form_render_block(array $render): \PortLibs\Pandoc\AstNode
{
    $mimeType = (string) ($render['mimeType'] ?? 'image/png');
    $dataUri = 'data:' . $mimeType . ';base64,' . base64_encode((string) $render['contents']);
    $attributes = [
        'data-pandoc-pdf-form-id' => (string) ($render['id'] ?? ''),
        'data-pandoc-pdf-form-rendered' => 'browser-pdfjs',
        'data-pandoc-pdf-page' => (string) max(1, (int) ($render['page'] ?? 1)),
    ];
    $bbox = plpc_import_job_normalize_bbox($render['bbox'] ?? null);
    if ($bbox !== null) {
        foreach ($bbox as $coordinate => $value) {
            $attributes['data-pandoc-pdf-form-' . $coordinate] = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
        }
    }
    $imageAttributes = [
        'url' => $dataUri,
        'title' => 'Browser-rendered PDF figure',
        'attributes' => $attributes,
    ];
    if ($bbox !== null) {
        $imageAttributes['width'] = rtrim(rtrim(sprintf('%.4F', $bbox['x2'] - $bbox['x1']), '0'), '.') . 'pt';
        $imageAttributes['height'] = rtrim(rtrim(sprintf('%.4F', $bbox['y2'] - $bbox['y1']), '0'), '.') . 'pt';
    }

    return new \PortLibs\Pandoc\AstNode('paragraph', [
        'classes' => ['pandoc-pdf-form-figure', 'pandoc-pdf-form-rendered'],
        'attributes' => $attributes,
    ], [
        new \PortLibs\Pandoc\AstNode('image', $imageAttributes, [
            new \PortLibs\Pandoc\AstNode('text', ['text' => 'PDF figure']),
        ]),
    ]);
}

/**
 * @param list<array{postId: int, pageUrl: string, editUrl: string, format: string, title: string, path: string, imageTagCount: int, imagesImported: int, diagnostics: list<string>}> $posts
 * @param list<string> $diagnostics
 */
function plpc_collection_index_blocks(string $title, array $posts, array $diagnostics = []): string
{
    $items = '';
    foreach ($posts as $post) {
        $items .= '<li><a href="' . esc_url($post['pageUrl']) . '">' . esc_html($post['title']) . '</a>'
            . ' <code>' . esc_html($post['path']) . '</code></li>';
    }

    $blocks = '<!-- wp:heading {"level":1} -->'
        . "\n" . '<h1 class="wp-block-heading">' . esc_html($title) . '</h1>'
        . "\n" . '<!-- /wp:heading -->'
        . "\n\n" . '<!-- wp:list -->'
        . "\n" . '<ul class="wp-block-list">' . $items . '</ul>'
        . "\n" . '<!-- /wp:list -->';

    $quality = plpc_import_quality_report('', $diagnostics);
    $blocks = plpc_prepend_conversion_warning_blocks($blocks, '', $diagnostics);

    return plpc_prepend_import_quality_blocks($blocks, $quality);
}

/**
 * @return list<string>
 */
function plpc_document_diagnostics(object $document, string $format): array
{
    $diagnostics = [];
    $canonical = PandocConverter::canonicalInputFormat($format);
    if ($canonical !== 'pdf' || !method_exists($document, 'attr')) {
        return $diagnostics;
    }

    $meta = $document->attr('meta', []);
    if (!is_array($meta)) {
        return $diagnostics;
    }

    if (($meta['pdfTextLimited'] ?? false) === true) {
        $diagnostics[] = 'document-truncated:pdf-text-limit';
    }
    if (($meta['pdfFastTextOnly'] ?? false) === true) {
        $diagnostics[] = 'pdf-fast-text-only';
    }
    if (($meta['pdfTextLines'] ?? 0) === 0 && (($meta['pdfEstimatedPages'] ?? 0) > 0 || ($meta['pdfPageCount'] ?? 0) > 0)) {
        $diagnostics[] = 'pdf-scanned-or-image-only';
    }
    if (($meta['pdfTableReconstruction'] ?? '') === 'geometry') {
        $diagnostics[] = 'pdf-layout-uncertain:geometry-tables';
    }
    if (($meta['pdfTableReconstruction'] ?? '') === 'text') {
        $diagnostics[] = 'pdf-layout-uncertain:text-reconstruction';
    }

    return $diagnostics;
}

/**
 * @param list<string> $diagnostics
 * @return array{status:string, flags:list<string>, warnings:list<string>}
 */
function plpc_import_quality_report(string $format, array $diagnostics, int $imageTagCount = 0, int $imagesImported = 0): array
{
    $flags = [];
    $warnings = plpc_conversion_warning_messages($format, $diagnostics);
    $canonical = $format === '' ? '' : PandocConverter::canonicalInputFormat($format);

    if ($canonical === 'pdf') {
        $flags[] = 'best_effort';
        $flags[] = 'layout_uncertain';
    }
    if ($imageTagCount > $imagesImported) {
        $flags[] = 'media_missing';
    }
    foreach ($diagnostics as $diagnostic) {
        $diagnostic = trim((string) $diagnostic);
        $unscoped = plpc_unscoped_diagnostic($diagnostic);
        if ($unscoped === '') {
            continue;
        }
        if (str_starts_with($unscoped, 'document-truncated:')) {
            $flags[] = 'truncated';
            $flags[] = 'partial';
        } elseif ($unscoped === 'pdf-fast-text-only' || str_starts_with($unscoped, 'pdf-layout-uncertain:')) {
            $flags[] = 'layout_uncertain';
            $flags[] = 'best_effort';
        } elseif ($unscoped === 'pdf-scanned-or-image-only') {
            $flags[] = 'ocr_needed';
            $flags[] = 'partial';
            $flags[] = 'layout_uncertain';
        } elseif (str_starts_with($unscoped, 'image-not-resolved:') || str_starts_with($unscoped, 'image-upload-failed:')) {
            $flags[] = 'media_missing';
        } elseif (str_starts_with($unscoped, 'document-failed:')) {
            $flags[] = 'partial';
        }
    }

    $flags = array_values(array_unique($flags));
    $rank = ['truncated', 'ocr_needed', 'partial', 'media_missing', 'layout_uncertain', 'best_effort'];
    $status = 'complete';
    foreach ($rank as $candidate) {
        if (in_array($candidate, $flags, true)) {
            $status = $candidate;
            break;
        }
    }

    return [
        'status' => $status,
        'flags' => $flags,
        'warnings' => $warnings,
    ];
}

/**
 * @param list<string> $diagnostics
 */
function plpc_prepend_conversion_warning_blocks(string $blocks, string $format, array $diagnostics): string
{
    $warnings = plpc_conversion_warning_messages($format, $diagnostics);
    if ($warnings === []) {
        return $blocks;
    }

    return plpc_prepend_block_markup($blocks, plpc_conversion_warning_blocks($warnings));
}

/**
 * @param array{status:string, flags:list<string>, warnings:list<string>} $quality
 */
function plpc_prepend_import_quality_blocks(string $blocks, array $quality): string
{
    $status = (string) ($quality['status'] ?? 'complete');
    $flags = is_array($quality['flags'] ?? null) ? array_values(array_map('strval', $quality['flags'])) : [];

    return plpc_prepend_block_markup($blocks, plpc_import_quality_blocks($status, $flags));
}

function plpc_prepend_block_markup(string $blocks, string $prefixBlocks): string
{
    if (function_exists('parse_blocks') && function_exists('serialize_blocks')) {
        $parsedPrefix = parse_blocks($prefixBlocks);
        $parsedBody = parse_blocks($blocks);
        if (is_array($parsedPrefix) && is_array($parsedBody)) {
            return serialize_blocks(array_merge(
                plpc_filter_meaningful_parsed_blocks($parsedPrefix),
                plpc_filter_meaningful_parsed_blocks($parsedBody)
            ));
        }
    }

    return $prefixBlocks . "\n\n" . $blocks;
}

/**
 * @param list<array<string, mixed>> $blocks
 * @return list<array<string, mixed>>
 */
function plpc_filter_meaningful_parsed_blocks(array $blocks): array
{
    $filtered = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $blockName = $block['blockName'] ?? null;
        $innerHTML = (string) ($block['innerHTML'] ?? '');
        $innerBlocks = is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
        if ($blockName === null && trim($innerHTML) === '' && $innerBlocks === []) {
            continue;
        }
        $filtered[] = $block;
    }

    return $filtered;
}

/**
 * @param list<string> $flags
 */
function plpc_import_quality_blocks(string $status, array $flags): string
{
    $summary = plpc_import_quality_summary($status, $flags);
    $details = plpc_import_quality_detail_items($status, $flags);
    $items = '';
    foreach ($details as $detail) {
        $items .= '<li>' . esc_html($detail) . '</li>';
    }

    $classes = 'port-libs-import-quality port-libs-import-quality-' . plpc_html_class_suffix($status === '' ? 'complete' : $status);

    return '<!-- wp:group {"className":"' . esc_attr($classes) . '"} -->'
        . "\n" . '<div class="wp-block-group ' . esc_attr($classes) . '">'
        . "\n" . '<!-- wp:paragraph -->'
        . "\n" . '<p><strong>Import quality:</strong> ' . esc_html($summary) . '</p>'
        . "\n" . '<!-- /wp:paragraph -->'
        . ($items === '' ? '' : "\n\n" . '<!-- wp:list -->'
            . "\n" . '<ul class="wp-block-list">' . $items . '</ul>'
            . "\n" . '<!-- /wp:list -->')
        . "\n" . '</div>'
        . "\n" . '<!-- /wp:group -->';
}

function plpc_html_class_suffix(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? $value;
    $value = trim($value, '-_');

    return $value === '' ? 'complete' : $value;
}

/**
 * @param list<string> $flags
 */
function plpc_import_quality_summary(string $status, array $flags = []): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'truncated' => 'Only part of this document was imported.',
        'ocr_needed' => 'This PDF likely needs OCR before import.',
        'partial' => 'Some content could not be imported automatically.',
        'media_missing' => 'The content imported, but some images or media files are missing.',
        'layout_uncertain' => 'The content imported, but the layout needs review.',
        'best_effort' => 'The document was imported using best-effort reconstruction.',
        default => 'The document imported successfully.',
    };
}

/**
 * @param list<string> $flags
 * @return list<string>
 */
function plpc_import_quality_detail_items(string $status, array $flags): array
{
    $details = [];
    $flags = array_values(array_unique(array_map(static fn (string $flag): string => strtolower(trim($flag)), $flags)));

    if (in_array('truncated', $flags, true)) {
        $details[] = 'The browser safety limit was reached, so later content may be missing.';
    }
    if (in_array('ocr_needed', $flags, true)) {
        $details[] = 'This PDF appears to have little or no extractable text. Run OCR first, then import the searchable PDF.';
    }
    if (in_array('partial', $flags, true)) {
        $details[] = 'Review the page before publishing; at least one part of the import was incomplete.';
    }
    if (in_array('media_missing', $flags, true)) {
        $details[] = 'Try importing again with all images, or upload the source folder/ZIP that contains the missing media.';
    }
    if (in_array('layout_uncertain', $flags, true)) {
        $details[] = 'Check headings, reading order, columns, tables, and image placement.';
    }
    if (in_array('best_effort', $flags, true)) {
        $details[] = 'This format may not preserve the original document layout exactly.';
    }

    return $details;
}

/**
 * @param list<string> $diagnostics
 * @return list<string>
 */
function plpc_conversion_warning_messages(string $format, array $diagnostics): array
{
    $warnings = [];
    if (PandocConverter::canonicalInputFormat($format) === 'pdf') {
        $warnings[] = 'PDF layout was reconstructed from page geometry. Reading order, columns, tables, and image placement may need review.';
    }

    foreach ($diagnostics as $diagnostic) {
        $diagnostic = trim((string) $diagnostic);
        if ($diagnostic === '') {
            continue;
        }

        $message = plpc_conversion_warning_message($diagnostic);
        if ($message !== '') {
            $warnings[] = $message;
        }
    }

    return array_values(array_unique($warnings));
}

function plpc_conversion_warning_message(string $diagnostic): string
{
    $unscoped = plpc_unscoped_diagnostic($diagnostic);

    if (str_starts_with($unscoped, 'document-failed:')) {
        return 'One document in the upload could not be converted: ' . substr($unscoped, strlen('document-failed:'));
    }
    if (str_starts_with($unscoped, 'document-truncated:')) {
        return 'Only part of the document text was imported because the browser importer reached its safety limit.';
    }
    if ($unscoped === 'pdf-fast-text-only') {
        return 'This large PDF was imported in bounded text-only mode, so detailed layout reconstruction was skipped.';
    }
    if ($unscoped === 'pdf-scanned-or-image-only') {
        return 'This PDF appears to contain little or no extractable text. Scanned pages may need OCR before import.';
    }
    if (str_starts_with($unscoped, 'pdf-layout-uncertain:')) {
        return 'Some PDF layout was inferred from geometry and may need review before publishing.';
    }
    if (str_starts_with($unscoped, 'image-not-resolved:')) {
        return 'An image reference could not be found in the uploaded file or folder: ' . substr($unscoped, strlen('image-not-resolved:'));
    }
    if (str_starts_with($unscoped, 'image-upload-failed:')) {
        return 'An extracted image could not be imported into the WordPress media library: ' . substr($unscoped, strlen('image-upload-failed:'));
    }
    if ($unscoped === 'extract-media-package-unreadable') {
        return 'Embedded media could not be read from the uploaded package.';
    }
    if (str_starts_with($unscoped, 'extract-media-package-read-failed:')) {
        return 'One embedded media file could not be read from the uploaded package.';
    }
    if ($unscoped === 'extract-media-data-uri-invalid') {
        return 'One embedded data URI image was invalid and was not imported.';
    }
    if ($unscoped === 'extract-media-pdf-scan-skipped:too-large') {
        return 'PDF image extraction was skipped because the file was too large for the browser importer.';
    }
    if ($unscoped === 'extract-media-pdf-image-limit') {
        return 'PDF image extraction stopped after the importer reached its image limit.';
    }
    if (str_starts_with($unscoped, 'extract-media-pdf-image-skipped:')) {
        return 'Some PDF image streams could not be embedded directly and were skipped.';
    }

    return '';
}

function plpc_unscoped_diagnostic(string $diagnostic): string
{
    $diagnostic = trim($diagnostic);
    foreach ([
        'extract-media-',
        'image-',
        'document-',
        'pdf-',
    ] as $prefix) {
        if (str_starts_with($diagnostic, $prefix)) {
            return $diagnostic;
        }
    }

    return preg_replace('/\A[^:]+:(?=extract-media-|image-|document-|pdf-)/', '', $diagnostic) ?? $diagnostic;
}

/**
 * @param list<string> $warnings
 */
function plpc_conversion_warning_blocks(array $warnings): string
{
    $items = '';
    foreach ($warnings as $warning) {
        $items .= '<li>' . esc_html($warning) . '</li>';
    }

    return '<!-- wp:group {"className":"port-libs-conversion-notice"} -->'
        . "\n" . '<div class="wp-block-group port-libs-conversion-notice">'
        . "\n" . '<!-- wp:heading {"level":2} -->'
        . "\n" . '<h2 class="wp-block-heading">Conversion notes</h2>'
        . "\n" . '<!-- /wp:heading -->'
        . "\n\n" . '<!-- wp:list -->'
        . "\n" . '<ul class="wp-block-list">' . $items . '</ul>'
        . "\n" . '<!-- /wp:list -->'
        . "\n" . '</div>'
        . "\n" . '<!-- /wp:group -->';
}

/**
 * @return array{readerOptions: array<string, mixed>, writerOptions: array<string, mixed>}
 */
function plpc_converter_options(string $format, string $pdfMode = 'layout'): array
{
    $readerOptions = [];
    $canonicalFormat = PandocConverter::canonicalInputFormat($format);
    if ($canonicalFormat === 'pdf') {
        $pdfMode = plpc_normalize_pdf_mode($pdfMode);
        $readerOptions['maxTextBytes'] = 80000;
        $readerOptions['pdfGeometryTables'] = $pdfMode === 'layout';
        $readerOptions['pdfRepairProseText'] = true;
        // The Playground invokes the reader and media extractor separately,
        // so it must explicitly request the compact placement map that lets
        // the media pass attach painted PDF images to nearby text.
        $readerOptions['pdfCollectImagePlacements'] = true;
        // Form XObjects carry charts, diagrams, and composite illustrations.
        // Their safe rasterization happens in the browser, but the final
        // reader pass supplies text anchors so the returned figure can be put
        // back near the relevant paragraph rather than appended as a gallery.
        $readerOptions['pdfCollectFormXObjectPlacements'] = true;
    }
    if ($canonicalFormat === 'docx') {
        $readerOptions['preserveRunStyles'] = true;
        $readerOptions['preserveImportStyles'] = true;
    }
    if ($canonicalFormat === 'csv' || $canonicalFormat === 'tsv') {
        $readerOptions['allowBlankRecords'] = true;
    }

    return [
        'readerOptions' => $readerOptions,
        'writerOptions' => [
            'writerHTMLMathMethod' => 'mathml',
        ],
    ];
}

/**
 * Choose the reader from the document itself and its filename. The client
 * never needs to present a format picker or supply a trusted format hint.
 */
function plpc_infer_document_format(string $filename, string $bytes = ''): string
{
    // A verified file signature wins over a filename. This avoids routing a
    // renamed PDF, RTF, or Office package through a text reader.
    if ($bytes !== '') {
        if (str_starts_with($bytes, '%PDF-')) {
            return 'pdf';
        }

        if (preg_match('/^\s*\{\\\\rtf\d*/i', substr($bytes, 0, 256)) === 1) {
            return 'rtf';
        }

        if (str_starts_with($bytes, "PK\x03\x04")) {
            $package = plpc_zip_package($bytes);
            if ($package !== null) {
                return plpc_document_type_from_zip($package);
            }
        }
    }

    $fromFilename = PandocFormatRegistry::inferDocumentTypeFromFilename($filename);
    if ($fromFilename !== null) {
        return PandocConverter::canonicalInputFormat($fromFilename);
    }

    if ($bytes === '') {
        return '';
    }

    // Type detection should stay cheap even for a large upload. The file
    // reader receives the original bytes after this bounded inspection.
    $probe = ltrim(substr($bytes, 0, 65536), "\xEF\xBB\xBF \t\r\n");
    if ($probe === '') {
        return '';
    }

    $markupFormat = plpc_document_type_from_markup($probe);
    if ($markupFormat !== null) {
        return $markupFormat;
    }

    $decoded = json_decode($probe, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (
            is_array($decoded)
            && isset($decoded['nbformat'], $decoded['cells'])
            && is_int($decoded['nbformat'])
            && is_array($decoded['cells'])
        ) {
            return 'ipynb';
        }

        return 'json';
    }

    return plpc_is_utf8_text_document($probe) ? 'markdown' : '';
}

function plpc_document_type_from_markup(string $probe): ?string
{
    $root = preg_replace('/^<\?xml\b[^?]*\?>\s*/i', '', $probe);
    if (!is_string($root)) {
        return null;
    }

    if (preg_match('/^(?:<!doctype\s+html\b|<html\b)/i', $root) === 1) {
        return 'html';
    }
    if (preg_match('/^<opml\b/i', $root) === 1) {
        return 'opml';
    }
    if (preg_match('/^<(?:article|aside|body|blockquote|div|figure|footer|h[1-6]|head|header|li|main|nav|ol|p|pre|section|table|tbody|td|th|thead|tr|ul)\b/i', $root) === 1) {
        return 'html';
    }
    if (preg_match('/^<[a-z_][a-z0-9_.:-]*\b/i', $root) === 1) {
        return 'xml';
    }

    return null;
}

function plpc_document_type_from_zip(ZipPackage $package): string
{
    if ($package->has('mimetype')) {
        try {
            $mimetype = trim($package->read('mimetype', 256));
            if ($mimetype === 'application/epub+zip') {
                return 'epub';
            }
            if ($mimetype === 'application/vnd.oasis.opendocument.text') {
                return 'odt';
            }
        } catch (Throwable) {
            // The archive remains a collection upload when its mimetype entry
            // cannot be safely read.
        }
    }

    $isOpenPackagingConvention = $package->has('[Content_Types].xml');
    if ($isOpenPackagingConvention && $package->has('word/document.xml')) {
        return 'docx';
    }
    if ($isOpenPackagingConvention && $package->has('ppt/presentation.xml')) {
        return 'pptx';
    }
    if ($isOpenPackagingConvention && $package->has('xl/workbook.xml')) {
        return 'xlsx';
    }

    return 'zip';
}

function plpc_is_utf8_text_document(string $bytes): bool
{
    $sample = substr($bytes, 0, 65536);

    return !str_contains($sample, "\0")
        && preg_match('//u', $sample) === 1;
}

/**
 * @deprecated 0.1.0 Upload handlers use plpc_infer_document_format() and
 *             never trust a caller-supplied format.
 */
function plpc_normalize_format(string $format, string $filename): string
{
    $inferred = plpc_infer_document_format($filename);
    if ($inferred !== '') {
        return $inferred;
    }

    $format = PandocConverter::canonicalInputFormat($format);

    return PandocConverter::canRead($format) ? $format : '';
}

function plpc_normalize_image_mode(mixed $mode): string
{
    if (is_bool($mode)) {
        return $mode ? 'all' : 'none';
    }

    $mode = strtolower(str_replace(['_', ' '], '-', trim((string) $mode)));

    return match ($mode) {
        'none', 'no', 'off', 'false', '0', 'no-images', 'without-images' => 'none',
        'all', 'yes', 'on', 'true', '1', 'all-images' => 'all',
        default => 'important',
    };
}

/**
 * Decode browser-produced PNG rasters for PDF image objects. The core media
 * extractor validates their PNG headers and PDF dimensions before use.
 *
 * @return list<array{object:string,contents:string,mimeType:string,width:int,height:int}>
 */
function plpc_pdf_raster_images_from_payload(mixed $images): array
{
    if (!is_array($images)) {
        return [];
    }

    $rasters = [];
    $totalBytes = 0;
    foreach ($images as $key => $image) {
        if (!is_array($image) || count($rasters) >= PLPC_MAX_PDF_RASTER_IMAGES) {
            continue;
        }
        $object = (string) ($image['object'] ?? $key);
        if (preg_match('/^\d+$/', $object) !== 1) {
            continue;
        }
        $contents = base64_decode((string) ($image['bytes'] ?? ''), true);
        $mimeType = strtolower(trim((string) ($image['mimeType'] ?? '')));
        $width = $image['width'] ?? null;
        $height = $image['height'] ?? null;
        if (!is_string($contents) || $contents === '' || strlen($contents) > PLPC_MAX_PDF_RASTER_IMAGE_BYTES
            || $mimeType !== 'image/png' || !is_numeric($width) || !is_numeric($height)) {
            continue;
        }
        $width = (int) $width;
        $height = (int) $height;
        if ($width <= 0 || $height <= 0) {
            continue;
        }
        $totalBytes += strlen($contents);
        if ($totalBytes > PLPC_MAX_PDF_RASTER_BYTES) {
            break;
        }

        $rasters[] = [
            'object' => $object,
            'contents' => $contents,
            'mimeType' => $mimeType,
            'width' => $width,
            'height' => $height,
        ];
    }

    return $rasters;
}

/**
 * @return array<string,list<array{object:string,contents:string,mimeType:string,width:int,height:int}>>
 */
function plpc_pdf_raster_images_by_path(mixed $images): array
{
    if (!is_array($images)) {
        return [];
    }

    $byPath = [];
    foreach ($images as $path => $records) {
        if (!is_string($path)) {
            continue;
        }
        $path = plpc_normalize_collection_path($path);
        if ($path === '') {
            continue;
        }
        $decoded = plpc_pdf_raster_images_from_payload($records);
        if ($decoded !== []) {
            $byPath[$path] = $decoded;
        }
    }

    return $byPath;
}

function plpc_normalize_pdf_mode(mixed $mode): string
{
    $mode = strtolower(str_replace(['_', ' '], '-', trim((string) $mode)));

    return match ($mode) {
        'text', 'text-only', 'plain-text', 'fast-text', 'no-layout', 'without-layout' => 'text',
        default => 'layout',
    };
}

function plpc_title_from_filename(string $filename): string
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/[-_]+/', ' ', $name) ?? $name;
    $name = trim($name);

    return $name === '' ? 'Converted document' : ucwords($name);
}

/**
 * @return list<string>
 */
function plpc_rendered_image_sources(string $blocks): array
{
    $sources = [];
    if (function_exists('parse_blocks')) {
        $parsed = parse_blocks($blocks);
        if (is_array($parsed)) {
            plpc_collect_image_sources_from_blocks($parsed, $sources);

            return array_keys($sources);
        }
    }

    plpc_collect_image_sources_from_html($blocks, $sources);

    return array_keys($sources);
}

/**
 * @param list<array<string, mixed>> $blocks
 * @param array<string, true> $sources
 */
function plpc_collect_image_sources_from_blocks(array $blocks, array &$sources): void
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $innerHtml = $block['innerHTML'] ?? '';
        if (is_string($innerHtml) && $innerHtml !== '') {
            plpc_collect_image_sources_from_html($innerHtml, $sources);
        }
        $innerBlocks = $block['innerBlocks'] ?? [];
        if (is_array($innerBlocks) && $innerBlocks !== []) {
            plpc_collect_image_sources_from_blocks($innerBlocks, $sources);
        }
    }
}

/**
 * @param array<string, true> $sources
 */
function plpc_collect_image_sources_from_html(string $html, array &$sources): void
{
    if (trim($html) === '' || !class_exists('DOMDocument')) {
        return;
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML(
            '<!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        return;
    }

    foreach ($dom->getElementsByTagName('img') as $image) {
        if (!$image instanceof DOMElement) {
            continue;
        }
        $source = html_entity_decode(trim($image->getAttribute('src')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($source !== '') {
            $sources[$source] = true;
        }
    }

    // A JPEG 2000 PDF image that could not be rasterized is represented by a
    // marked download link rather than a broken <img>. Treat only that
    // dedicated link as extracted media; ordinary document links must never
    // be uploaded or rewritten as attachments.
    foreach ($dom->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement || $link->getAttribute('data-pandoc-pdf-image-original') !== 'true') {
            continue;
        }
        $source = html_entity_decode(trim($link->getAttribute('href')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($source !== '') {
            $sources[$source] = true;
        }
    }
}

/**
 * @param list<string> $imageSources
 * @param array{label: string, files: list<array{path: string, bytes: string}>}|null $collection
 * @return array{blocks: string, imported: int, diagnostics: list<string>}
 */
function plpc_import_rendered_images(string $blocks, array $imageSources, string $uploadedBytes, string $filename, ?array $collection = null, string $documentPath = ''): array
{
    $diagnostics = [];
    $imported = 0;
    $package = plpc_zip_package($uploadedBytes);
    foreach ($imageSources as $source) {
        $resolved = plpc_resolve_image_source($source, $uploadedBytes, $package, $collection, $documentPath);
        if ($resolved === null) {
            $diagnostics[] = 'image-not-resolved:' . $source;
            continue;
        }

        $attachment = plpc_insert_media_attachment($resolved['bytes'], $resolved['filename'], $resolved['mimeType']);
        if ($attachment === null) {
            $diagnostics[] = 'image-upload-failed:' . $source;
            continue;
        }

        $blocks = plpc_replace_image_source($blocks, $source, $attachment['url'], $attachment['id']);
        $imported++;
        $diagnostics[] = 'image-imported:' . $source . '=>' . $attachment['id'];
    }

    return [
        'blocks' => $blocks,
        'imported' => $imported,
        'diagnostics' => $diagnostics,
    ];
}

/**
 * @param list<string> $imageSources
 * @param list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, extractionPathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string}> $entries
 * @return array{blocks: string, imported: int, sources: list<string>, diagnostics: list<string>}
 */
function plpc_import_extracted_media_entries(string $blocks, array $imageSources, array $entries): array
{
    if ($entries === [] || $imageSources === []) {
        return [
            'blocks' => $blocks,
            'imported' => 0,
            'sources' => [],
            'diagnostics' => [],
        ];
    }

    $entriesByPath = [];
    foreach ($entries as $entry) {
        $keys = array_values(array_unique(array_filter([
            plpc_media_path_from_url((string) ($entry['path'] ?? '')),
            plpc_media_path_from_url((string) ($entry['mediaPath'] ?? '')),
            plpc_media_path_from_url((string) ($entry['source'] ?? '')),
        ], static fn (string $key): bool => $key !== '')));
        foreach ($keys as $key) {
            $entriesByPath[$key] = $entry;
        }
    }

    $imported = 0;
    $importedSources = [];
    $diagnostics = [];
    foreach ($imageSources as $source) {
        $path = plpc_media_path_from_url($source);
        $entry = $entriesByPath[$path] ?? null;
        if ($entry === null) {
            continue;
        }

        $contents = (string) ($entry['contents'] ?? '');
        $mimeType = (string) ($entry['mimeType'] ?? 'application/octet-stream');
        $filename = sanitize_file_name(basename($path));
        if ($filename === '') {
            $filename = (string) ($entry['sha1'] ?? sha1($contents)) . plpc_extension_for_mime($mimeType);
        }

        $attachment = plpc_insert_media_attachment($contents, $filename, $mimeType);
        if ($attachment === null) {
            $diagnostics[] = 'image-upload-failed:' . $source;
            continue;
        }

        $blocks = plpc_replace_image_source($blocks, $source, $attachment['url'], $attachment['id']);
        $imported++;
        $importedSources[] = $source;
        $diagnostics[] = 'image-imported:' . $source . '=>' . $attachment['id'];
    }

    return [
        'blocks' => $blocks,
        'imported' => $imported,
        'sources' => array_values(array_unique($importedSources)),
        'diagnostics' => $diagnostics,
    ];
}

function plpc_replace_image_source(string $blocks, string $source, string $url, ?int $attachmentId = null): string
{
    if (function_exists('parse_blocks') && function_exists('serialize_blocks')) {
        $parsed = parse_blocks($blocks);
        if (is_array($parsed)) {
            $changed = false;
            plpc_replace_image_source_in_blocks($parsed, $source, $url, $attachmentId, $changed);
            if ($changed) {
                return serialize_blocks($parsed);
            }
        }
    }

    return plpc_replace_image_source_in_html($blocks, $source, $url, null);
}

/**
 * @param list<array<string, mixed>> $blocks
 */
function plpc_replace_image_source_in_blocks(array &$blocks, string $source, string $url, ?int $attachmentId, bool &$changed): void
{
    foreach ($blocks as &$block) {
        if (!is_array($block)) {
            continue;
        }
        $blockName = (string) ($block['blockName'] ?? '');
        $isImageBlock = $blockName === 'core/image';
        $blockMatched = false;
        $classAttachmentId = $isImageBlock && $attachmentId !== null && $attachmentId > 0 ? $attachmentId : null;

        if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
            $matched = false;
            $rewritten = plpc_replace_image_source_in_html($block['innerHTML'], $source, $url, $classAttachmentId, $matched);
            if ($matched) {
                $block['innerHTML'] = $rewritten;
                $blockMatched = true;
                $changed = true;
            }
        }

        if (isset($block['innerContent']) && is_array($block['innerContent'])) {
            foreach ($block['innerContent'] as &$content) {
                if (!is_string($content) || $content === '') {
                    continue;
                }
                $matched = false;
                $rewritten = plpc_replace_image_source_in_html($content, $source, $url, $classAttachmentId, $matched);
                if ($matched) {
                    $content = $rewritten;
                    $blockMatched = true;
                    $changed = true;
                }
            }
            unset($content);
        }

        if ($blockMatched && $isImageBlock && $attachmentId !== null && $attachmentId > 0) {
            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
            $block['attrs'] = ['id' => $attachmentId] + array_diff_key($attrs, ['id' => true]);
        }

        if (isset($block['innerBlocks']) && is_array($block['innerBlocks']) && $block['innerBlocks'] !== []) {
            plpc_replace_image_source_in_blocks($block['innerBlocks'], $source, $url, $attachmentId, $changed);
        }
    }
    unset($block);
}

function plpc_replace_image_source_in_html(string $html, string $source, string $url, ?int $attachmentId = null, ?bool &$matched = null): string
{
    $matched = false;
    if (trim($html) === '' || !class_exists('DOMDocument')) {
        return $html;
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML(
            '<!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        return $html;
    }

    foreach ($dom->getElementsByTagName('img') as $image) {
        if (!$image instanceof DOMElement) {
            continue;
        }
        $currentSource = html_entity_decode(trim($image->getAttribute('src')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($currentSource !== $source) {
            continue;
        }
        $image->setAttribute('src', $url);
        if ($attachmentId !== null && $attachmentId > 0) {
            plpc_add_dom_element_class($image, 'wp-image-' . $attachmentId);
        }
        $matched = true;
    }
    foreach ($dom->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement || $link->getAttribute('data-pandoc-pdf-image-original') !== 'true') {
            continue;
        }
        $currentSource = html_entity_decode(trim($link->getAttribute('href')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($currentSource !== $source) {
            continue;
        }
        $link->setAttribute('href', $url);
        $matched = true;
    }
    if (!$matched) {
        return $html;
    }

    return plpc_dom_body_inner_html($dom);
}

function plpc_add_dom_element_class(DOMElement $element, string $className): void
{
    $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
    $classes = array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
    if (!in_array($className, $classes, true)) {
        $classes[] = $className;
    }
    $element->setAttribute('class', implode(' ', $classes));
}

function plpc_dom_body_inner_html(DOMDocument $dom): string
{
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        $html = $dom->saveHTML();

        return is_string($html) ? $html : '';
    }

    $html = '';
    foreach ($body->childNodes as $child) {
        $chunk = $dom->saveHTML($child);
        if (is_string($chunk)) {
            $html .= $chunk;
        }
    }

    return $html;
}

function plpc_zip_package(string $bytes): ?ZipPackage
{
    if (!str_starts_with($bytes, "PK\x03\x04")) {
        return null;
    }

    try {
        return ZipPackage::fromString($bytes);
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array{label: string, files: list<array{path: string, bytes: string}>}|null $collection
 * @return array{bytes: string, filename: string, mimeType: string}|null
 */
function plpc_resolve_image_source(string $source, string $uploadedBytes, ?ZipPackage $package, ?array $collection = null, string $documentPath = ''): ?array
{
    if (str_starts_with($source, 'data:')) {
        $decoded = plpc_decode_data_uri($source);

        return $decoded === null ? null : [
            'bytes' => $decoded['bytes'],
            'filename' => sha1($decoded['bytes']) . plpc_extension_for_mime($decoded['mimeType']),
            'mimeType' => $decoded['mimeType'],
        ];
    }

    if ($collection !== null && !plpc_source_is_remote($source)) {
        $resolved = plpc_find_collection_image($collection, $source, $documentPath);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    if ($package === null) {
        return null;
    }

    $path = plpc_media_path_from_url($source);
    if ($path === '') {
        return null;
    }

    $entry = plpc_find_package_image_entry($package, $path);
    if ($entry === null) {
        return null;
    }

    try {
        $bytes = $package->read($entry, 25000000);
    } catch (Throwable) {
        return null;
    }

    return [
        'bytes' => $bytes,
        'filename' => sanitize_file_name(basename($entry)),
        'mimeType' => plpc_mime_for_filename($entry),
    ];
}

function plpc_source_is_remote(string $source): bool
{
    $parts = wp_parse_url($source);
    if (!is_array($parts)) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));

    return $scheme !== '' && !in_array($scheme, ['file'], true);
}

/**
 * @param array{label: string, files: list<array{path: string, bytes: string}>} $collection
 * @return array{bytes: string, filename: string, mimeType: string}|null
 */
function plpc_find_collection_image(array $collection, string $source, string $documentPath): ?array
{
    $path = plpc_media_path_from_url($source);
    if ($path === '') {
        return null;
    }

    $documentDir = trim(dirname($documentPath), '.\\/');
    $candidates = [$path];
    if ($documentDir !== '') {
        $candidates[] = plpc_normalize_collection_path($source, $documentDir);
        $candidates[] = plpc_normalize_collection_path($path, $documentDir);
    }
    $candidates[] = basename($path);
    $candidates = array_values(array_unique(array_filter($candidates, static fn (string $candidate): bool => $candidate !== '')));

    $filesByLower = [];
    foreach ($collection['files'] as $file) {
        if (!plpc_path_is_image($file['path'])) {
            continue;
        }
        $filesByLower[strtolower($file['path'])] = $file;
    }

    foreach ($candidates as $candidate) {
        $file = $filesByLower[strtolower($candidate)] ?? null;
        if (is_array($file)) {
            return [
                'bytes' => $file['bytes'],
                'filename' => sanitize_file_name(basename($file['path'])),
                'mimeType' => plpc_mime_for_filename($file['path']),
            ];
        }
    }

    $basename = strtolower(basename($path));
    foreach ($filesByLower as $file) {
        if (strtolower(basename($file['path'])) === $basename) {
            return [
                'bytes' => $file['bytes'],
                'filename' => sanitize_file_name(basename($file['path'])),
                'mimeType' => plpc_mime_for_filename($file['path']),
            ];
        }
    }

    return null;
}

/**
 * @return array{bytes: string, mimeType: string}|null
 */
function plpc_decode_data_uri(string $source): ?array
{
    if (!preg_match('/\Adata:([^,]*),(.*)\z/s', $source, $match)) {
        return null;
    }

    $metadata = $match[1];
    $payload = $match[2];
    $parts = $metadata === '' ? [] : explode(';', $metadata);
    $mimeType = strtolower($parts[0] ?? 'text/plain');
    if ($mimeType === '') {
        $mimeType = 'text/plain';
    }

    if (in_array('base64', array_map('strtolower', $parts), true)) {
        $bytes = base64_decode(preg_replace('/\s+/', '', $payload) ?? $payload, true);

        return is_string($bytes) ? ['bytes' => $bytes, 'mimeType' => $mimeType] : null;
    }

    return ['bytes' => rawurldecode($payload), 'mimeType' => $mimeType];
}

function plpc_media_path_from_url(string $source): string
{
    $source = html_entity_decode($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $parts = wp_parse_url($source);
    if (is_array($parts) && isset($parts['path'])) {
        $source = (string) $parts['path'];
    }
    $source = rawurldecode($source);
    $source = str_replace('\\', '/', $source);
    $source = preg_replace('#/+#', '/', $source) ?? $source;
    $source = ltrim($source, '/');
    $segments = [];
    foreach (explode('/', $source) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
}

function plpc_find_package_image_entry(ZipPackage $package, string $path): ?string
{
    $entries = [];
    foreach ($package->entries() as $entry) {
        if ($entry->isDirectory()) {
            continue;
        }
        $entries[] = $entry->name;
    }

    $candidates = array_values(array_unique([
        $path,
        'word/' . $path,
        'ppt/' . $path,
        'xl/' . $path,
        'Pictures/' . basename($path),
        'Thumbnails/' . basename($path),
        'Media/' . basename($path),
    ]));

    $entriesByLower = [];
    foreach ($entries as $entry) {
        $entriesByLower[strtolower($entry)] = $entry;
    }

    foreach ($candidates as $candidate) {
        $match = $entriesByLower[strtolower($candidate)] ?? null;
        if (is_string($match) && plpc_path_is_image($match)) {
            return $match;
        }
    }

    $pathLower = strtolower($path);
    foreach ($entries as $entry) {
        if (plpc_path_is_image($entry) && str_ends_with(strtolower($entry), $pathLower)) {
            return $entry;
        }
    }

    $basename = strtolower(basename($path));
    foreach ($entries as $entry) {
        if (plpc_path_is_image($entry) && strtolower(basename($entry)) === $basename) {
            return $entry;
        }
    }

    return null;
}

function plpc_path_is_image(string $path): bool
{
    return preg_match('/\.(?:avif|bmp|gif|jpe?g|jp2|jpx|png|svg|tiff?|webp)$/i', $path) === 1;
}

/**
 * @return array{id: int, url: string}|null
 */
function plpc_insert_media_attachment(string $bytes, string $filename, string $mimeType): ?array
{
    if ($bytes === '') {
        return null;
    }
    $mimeType = strtolower(trim($mimeType));
    if ($mimeType === 'image/svg+xml' && !plpc_svg_import_allowed()) {
        return null;
    }
    $hash = sha1($bytes);
    $cacheKey = strtolower($mimeType) . ':' . $hash;
    if (isset($GLOBALS['plpc_imported_media_by_hash'][$cacheKey]) && is_array($GLOBALS['plpc_imported_media_by_hash'][$cacheKey])) {
        return $GLOBALS['plpc_imported_media_by_hash'][$cacheKey];
    }

    $fileApi = ABSPATH . 'wp-admin/includes/file.php';
    if (is_file($fileApi)) {
        require_once $fileApi;
    }
    $imageApi = ABSPATH . 'wp-admin/includes/image.php';
    if (is_file($imageApi)) {
        require_once $imageApi;
    }

    $filename = sanitize_file_name($filename);
    if ($filename === '') {
        $filename = sha1($bytes) . plpc_extension_for_mime($mimeType);
    }

    $upload = wp_upload_bits($filename, null, $bytes);
    if (!empty($upload['error']) || empty($upload['file'])) {
        return null;
    }

    $attachmentId = wp_insert_attachment([
        'post_mime_type' => $mimeType,
        'post_title' => preg_replace('/\.[^.]+$/', '', $filename) ?: $filename,
        'post_content' => '',
        'post_status' => 'inherit',
    ], $upload['file']);
    if (is_wp_error($attachmentId) || (int) $attachmentId <= 0) {
        return null;
    }

    $metadata = wp_generate_attachment_metadata((int) $attachmentId, $upload['file']);
    if (is_array($metadata)) {
        wp_update_attachment_metadata((int) $attachmentId, $metadata);
    }

    $url = wp_get_attachment_url((int) $attachmentId);

    if (!is_string($url)) {
        return null;
    }

    $attachment = ['id' => (int) $attachmentId, 'url' => $url];
    $GLOBALS['plpc_imported_media_by_hash'][$cacheKey] = $attachment;

    return $attachment;
}

function plpc_mime_for_filename(string $filename): string
{
    $mime = wp_check_filetype($filename)['type'] ?? '';
    if (is_string($mime) && $mime !== '') {
        return $mime;
    }

    return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        'avif' => 'image/avif',
        'bmp' => 'image/bmp',
        'gif' => 'image/gif',
        'jpg', 'jpeg' => 'image/jpeg',
        'jp2', 'jpx' => 'image/jp2',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'tif', 'tiff' => 'image/tiff',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
}

function plpc_extension_for_mime(string $mimeType): string
{
    return match (strtolower($mimeType)) {
        'image/avif' => '.avif',
        'image/bmp' => '.bmp',
        'image/gif' => '.gif',
        'image/jpeg' => '.jpg',
        'image/jp2', 'image/jpx' => '.jp2',
        'image/png' => '.png',
        'image/svg+xml' => '.svg',
        'image/tiff' => '.tiff',
        'image/webp' => '.webp',
        default => '.bin',
    };
}
