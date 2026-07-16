<?php
/**
 * Plugin Name: Port Libs Document Importer
 * Description: Imports uploaded documents into WordPress block markup, with optional browser-assisted PDF facts and figure rendering.
 * Version: 0.6.0
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
const PLPC_MAX_PDF_BROWSER_FACTS_BYTES = 4194304;
const PLPC_MAX_PDF_BROWSER_FACTS_TOTAL_BYTES = 8388608;
const PLPC_IMPORT_JOB_OPTION_PREFIX = 'plpc_import_job_';
const PLPC_IMPORT_JOB_INDEX_OPTION = 'plpc_import_job_index';
const PLPC_IMPORT_JOB_DIRECTORY = 'port-libs-import-jobs';
const PLPC_IMPORT_JOB_MAX_EVENTS = 80;
const PLPC_IMPORT_JOB_STATUS_MAX_EVENTS = 24;
const PLPC_IMPORT_JOB_STATUS_MAX_RENDER_REQUESTS = 24;
const PLPC_IMPORT_JOB_STATUS_MAX_RESULT_ITEMS = 40;
const PLPC_IMPORT_JOB_MAX_STATUS_BYTES = 65536;
const PLPC_IMPORT_JOB_MAX_OPTION_BYTES = 65536;
const PLPC_IMPORT_JOB_STATE_BLOB_MIN_BYTES = 8192;
const PLPC_IMPORT_JOB_RETENTION_COMPLETE_SECONDS = 172800;
const PLPC_IMPORT_JOB_RETENTION_FAILED_SECONDS = 604800;
const PLPC_IMPORT_JOB_RETENTION_ACTIVE_SECONDS = 1209600;
const PLPC_IMPORT_JOB_INDEX_LOCK_TIMEOUT_MS = 250;
// This is an abuse bound, not a document-quality cap. Requests are exposed to
// the browser in small status pages and acknowledged one at a time, so a PDF
// with 49+ legitimate figures no longer loses its tail at an arbitrary 48.
const PLPC_IMPORT_JOB_MAX_FORM_RENDERS = 4096;
// Keep the durable visual ledger bounded independently from render requests.
// One terminal inspection issue is reserved so reaching the bound is visible
// and machine-readable instead of silently making the inventory incomplete.
const PLPC_IMPORT_JOB_MAX_VISUAL_OCCURRENCES = 8192;
const PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES = 24000000;
const PLPC_IMPORT_JOB_MAX_FORM_RENDER_IMAGE_BYTES = 16777216;
const PLPC_IMPORT_JOB_MAX_FORM_RENDER_PIXELS = 48000000;
const PLPC_IMPORT_JOB_MAX_RENDER_SOURCE_BYTES = 25000000;
// A Form covering nearly the whole visible page is a page-layout wrapper,
// background, or full-page composition—not an inline chart. Rasterizing it
// duplicates the entire page and can make magazine/slide PDFs request dozens
// of large images before text conversion starts.
const PLPC_IMPORT_JOB_PAGE_LIKE_FORM_COVERAGE = 0.82;
const PLPC_IMPORT_JOB_VERSION = 3;
const PLPC_PDF_SINGLE_PAGE_HARD_LIMIT_BYTES = 8388608;
// A PDF's serialized page facts can be many times larger than its compressed
// upload. Keep each document-semantics pass comfortably below both the memory
// and execution time left by WordPress, the source PDF, and media handling in
// a conventional 128 MiB / 30 second worker. Normal PDFs still group many
// pages; unusually dense page facts split earlier without document-specific
// rules.
const PLPC_IMPORT_JOB_PDF_SEGMENT_MAX_FACT_BYTES = 4194304;
// Extraction requests may contract to one page under pressure, but semantic
// interpretation always uses this deterministic, document-independent grain.
// That keeps furniture, lists, columns, and table decisions invariant to the
// number of HTTP requests while retaining a hard memory bound.
const PLPC_IMPORT_JOB_PDF_SEGMENT_MAX_PAGES = 8;
// Unlimited development runtimes are useful for diagnostics, but a normal
// import request must still have a finite failure boundary. These defaults
// only tighten an unlimited or more permissive host; an explicit constant or
// filter may intentionally opt out or select another site-specific value.
const PLPC_IMPORT_DEFAULT_MEMORY_LIMIT = '512M';
const PLPC_IMPORT_DEFAULT_MEMORY_LIMIT_BYTES = 536870912;
const PLPC_IMPORT_DEFAULT_TIME_LIMIT_SECONDS = 45;
// Leave enough time to write the durable job option and JSON response before
// a normal shared host's max_execution_time terminates the PHP worker.
const PLPC_IMPORT_REQUEST_DEFAULT_RESERVE_SECONDS = 3.0;
// Non-PDF readers still retry a document unit after an interrupted worker.
// PDFs use durable page chunks below, so this cap is only a final safety net
// for work that ends between two page-boundary checkpoints.
const PLPC_IMPORT_JOB_MAX_DEADLINE_YIELDS_PER_DOCUMENT = 3;
const PLPC_IMPORT_JOB_MAX_INTERRUPTED_RETRIES_PER_DOCUMENT = 2;
const PLPC_IMPORT_JOB_MAX_RECOVERABLE_FAILURE_RETRIES = 3;

/**
 * Used only for an intentional, already-persisted request handoff.  It must
 * not be treated as an import failure: the browser will issue the next
 * /advance request and the job snapshot explains what happened.
 */
final class PlpcImportCheckpointYield extends RuntimeException
{
}

/**
 * A stable, machine-readable import failure. The REST response keeps the
 * plain-language message for people while clients and tests can distinguish
 * malformed input, exhausted resources, and a recoverable publication
 * round-trip without scraping prose.
 */
final class PlpcImportFailure extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $message,
        public readonly bool $recoverable = false,
        public readonly string $failureStage = 'converting'
    ) {
        parent::__construct($message);
    }
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
    register_rest_route('port-libs/v1', '/imports/(?P<jobId>[A-Za-z0-9_-]+)/output-mode', [
        'methods' => 'POST',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_switch_import_output_mode',
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
add_action('init', 'plpc_import_job_schedule_cleanup');
add_action('plpc_cleanup_import_jobs', 'plpc_cleanup_import_jobs');

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

/** Normalize an explicit PHP memory_limit override, or null to opt out. */
function plpc_import_normalize_memory_limit_override(mixed $value): ?string
{
    if ($value === null || $value === false || $value === '') {
        return null;
    }
    if (is_int($value)) {
        return $value >= -1 ? (string) $value : null;
    }
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if (preg_match('/\A(?:-1|\d+[KMG]?)\z/i', $value) !== 1) {
        return null;
    }

    return strtoupper($value);
}

/**
 * Select the memory limit to apply to this request.
 *
 * With no explicit override, an unlimited or >512 MiB host is tightened to
 * 512 MiB and a lower host is left untouched. An explicit constant/filter
 * may return a valid PHP limit (including -1) to override deliberately, or
 * false/null/an empty string to leave the host policy unchanged.
 */
function plpc_import_memory_limit_policy(
    mixed $hostLimit,
    mixed $override = null,
    bool $hasExplicitOverride = false
): ?string {
    if ($hasExplicitOverride) {
        if ($override === null || $override === false || $override === '') {
            return null;
        }
        $normalized = plpc_import_normalize_memory_limit_override($override);

        return $normalized ?? plpc_import_memory_limit_policy($hostLimit);
    }
    $raw = is_string($hostLimit) || is_int($hostLimit) || is_float($hostLimit)
        ? trim((string) $hostLimit)
        : '';
    $hostBytes = plpc_php_ini_bytes($hostLimit);

    return $raw === '-1' || $hostBytes > PLPC_IMPORT_DEFAULT_MEMORY_LIMIT_BYTES
        ? PLPC_IMPORT_DEFAULT_MEMORY_LIMIT
        : null;
}

/** Normalize an explicit max_execution_time override, or null to opt out. */
function plpc_import_normalize_time_limit_override(mixed $value): ?int
{
    if ($value === null || $value === false || $value === '') {
        return null;
    }
    if (is_int($value)) {
        return $value >= 0 ? $value : null;
    }
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);

    return ctype_digit($value) ? (int) $value : null;
}

/**
 * Select the execution limit to apply to this request.
 *
 * With no explicit override, an unlimited/unknown or >45 second host is
 * tightened to 45 seconds and a lower host is never extended. An explicit
 * constant/filter may return a non-negative number (zero intentionally means
 * unlimited), or false/null/an empty string to leave the host unchanged.
 */
function plpc_import_time_limit_policy(
    mixed $hostLimit,
    mixed $override = null,
    bool $hasExplicitOverride = false
): ?int {
    if ($hasExplicitOverride) {
        if ($override === null || $override === false || $override === '') {
            return null;
        }
        $normalized = plpc_import_normalize_time_limit_override($override);

        return $normalized ?? plpc_import_time_limit_policy($hostLimit);
    }
    $hostSeconds = is_string($hostLimit) || is_int($hostLimit) || is_float($hostLimit)
        ? (float) $hostLimit
        : 0.0;

    return !is_finite($hostSeconds)
        || $hostSeconds <= 0.0
        || $hostSeconds > PLPC_IMPORT_DEFAULT_TIME_LIMIT_SECONDS
            ? PLPC_IMPORT_DEFAULT_TIME_LIMIT_SECONDS
            : null;
}

/**
 * Resolve constants and filters without mutating PHP ini state.
 *
 * Filters receive the safe default, the raw host value, and whether the
 * corresponding wp-config constant was defined. Returning false/null/'' is
 * an explicit opt-out; returning a valid limit is an intentional override.
 *
 * @return array{memoryLimit:?string,timeLimit:?int,effectiveTimeLimit:?float}
 */
function plpc_import_runtime_limit_policy(mixed $hostMemoryLimit, mixed $hostTimeLimit): array
{
    $hasMemoryConstant = defined('PLPC_IMPORT_MEMORY_LIMIT');
    $hasTimeConstant = defined('PLPC_IMPORT_TIME_LIMIT');
    $memoryLimit = plpc_import_memory_limit_policy(
        $hostMemoryLimit,
        $hasMemoryConstant ? constant('PLPC_IMPORT_MEMORY_LIMIT') : null,
        $hasMemoryConstant
    );
    $timeLimit = plpc_import_time_limit_policy(
        $hostTimeLimit,
        $hasTimeConstant ? constant('PLPC_IMPORT_TIME_LIMIT') : null,
        $hasTimeConstant
    );
    if (function_exists('apply_filters')) {
        $memoryLimit = apply_filters(
            'plpc_import_memory_limit',
            $memoryLimit,
            $hostMemoryLimit,
            $hasMemoryConstant
        );
        $timeLimit = apply_filters(
            'plpc_import_time_limit',
            $timeLimit,
            $hostTimeLimit,
            $hasTimeConstant
        );
    }
    $memoryLimit = plpc_import_memory_limit_policy($hostMemoryLimit, $memoryLimit, true);
    $timeLimit = plpc_import_time_limit_policy($hostTimeLimit, $timeLimit, true);

    return [
        'memoryLimit' => $memoryLimit,
        'timeLimit' => $timeLimit,
        'effectiveTimeLimit' => plpc_import_effective_time_limit_seconds($hostTimeLimit, $timeLimit),
    ];
}

/** Return the finite deadline budget even when ini/set_time_limit cannot report it. */
function plpc_import_effective_time_limit_seconds(mixed $hostLimit, ?int $appliedLimit): ?float
{
    if ($appliedLimit !== null) {
        return $appliedLimit > 0 ? (float) $appliedLimit : null;
    }
    $hostSeconds = is_string($hostLimit) || is_int($hostLimit) || is_float($hostLimit)
        ? (float) $hostLimit
        : 0.0;

    return is_finite($hostSeconds) && $hostSeconds > 0.0 ? $hostSeconds : null;
}

/**
 * Apply finite importer defaults without raising a lower host limit. Site
 * constants and filters retain an explicit, documented override/opt-out.
 */
function plpc_import_apply_runtime_limits(): void
{
    $hostMemoryLimit = function_exists('ini_get') ? ini_get('memory_limit') : false;
    $hostTimeLimit = function_exists('ini_get') ? ini_get('max_execution_time') : false;
    $policy = plpc_import_runtime_limit_policy($hostMemoryLimit, $hostTimeLimit);
    $GLOBALS['plpc_import_request_time_limit_fallback'] = $policy['effectiveTimeLimit'];
    if ($policy['memoryLimit'] !== null && function_exists('ini_set')) {
        @ini_set('memory_limit', $policy['memoryLimit']);
    }
    if ($policy['timeLimit'] !== null && function_exists('set_time_limit')) {
        @set_time_limit($policy['timeLimit']);
    }
}

/** Resolve the stricter finite value reported by PHP or retained by policy. */
function plpc_import_request_time_limit_from_observed(mixed $observed, mixed $fallback): ?float
{
    $observed = is_numeric($observed) && is_finite((float) $observed) && (float) $observed > 0.0
        ? (float) $observed
        : 0.0;
    $fallback = is_numeric($fallback) && is_finite((float) $fallback) && (float) $fallback > 0.0
        ? (float) $fallback
        : 0.0;
    $limit = $observed > 0.0 && $fallback > 0.0
        ? min($observed, $fallback)
        : max($observed, $fallback);

    return $limit > 0.0 ? $limit : null;
}

/**
 * Return the finite time available to this REST request. The policy fallback
 * keeps the voluntary deadline at 45 seconds even if a disabled ini setter or
 * SAPI continues reporting an unlimited max_execution_time. A stricter
 * observed host value always wins.
 */
function plpc_import_request_time_limit_seconds(): ?float
{
    $raw = function_exists('ini_get') ? ini_get('max_execution_time') : false;
    $limit = plpc_import_request_time_limit_from_observed(
        $raw,
        $GLOBALS['plpc_import_request_time_limit_fallback'] ?? null
    );
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_import_request_time_limit_seconds', $limit);
        if ($filtered === null || $filtered === false || $filtered === '') {
            $limit = null;
        } elseif (is_numeric($filtered)) {
            $limit = (float) $filtered;
        }
    }

    return is_numeric($limit) && is_finite((float) $limit) && (float) $limit > 0.0
        ? (float) $limit
        : null;
}

/**
 * Keep a small response-writing reserve. This is deliberately based on the
 * host's current policy rather than extending it: a durable importer must be
 * able to work with the restrictive limits people actually deploy.
 */
function plpc_import_request_reserve_seconds(float $timeLimit): float
{
    $reserve = max(
        $timeLimit * 0.20,
        min(PLPC_IMPORT_REQUEST_DEFAULT_RESERVE_SECONDS, max(0.25, $timeLimit / 4.0))
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
        wp_enqueue_style('port-libs-importer', $baseUrl . 'admin-importer.css', [], '0.6.0');
    }
    if (function_exists('wp_enqueue_script_module')) {
        wp_enqueue_script_module('port-libs-importer', $baseUrl . 'admin-importer.mjs', [], '0.6.0');

        return;
    }

    // WordPress versions before native Script Modules support need a classic
    // enqueue plus the filter below to mark this static-ESM asset as a module.
    wp_enqueue_script('port-libs-importer', $baseUrl . 'admin-importer.mjs', [], '0.6.0', true);
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
            <fieldset class="plpc-importer__options" data-plpc-pdf-output-options hidden>
                <legend>PDF pages</legend>
                <label><input type="radio" name="plpc-pdf-output-mode" value="single" checked> One WordPress page</label>
                <label><input type="radio" name="plpc-pdf-output-mode" value="pages"> One child page per PDF page</label>
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
 * Create a persisted import job. Source and render files normally live in a
 * private, site-scoped temporary directory outside public uploads. Hosts that
 * do not allow that directory may use an explicitly reported uploads fallback
 * only when this server can consume the installed directory-deny file. The
 * option holds only compact, user-owned job metadata.
 */
function plpc_create_import_job(WP_REST_Request $request): WP_REST_Response
{
    try {
        $payload = plpc_import_job_request_payload($request);
        $jobId = plpc_import_job_new_id();
        $storageSecurity = [];
        $jobDirectory = plpc_import_job_create_directory($jobId, $storageSecurity);
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
            'pdfOutputMode' => plpc_normalize_pdf_output_mode($payload['pdfOutputMode'] ?? 'single'),
            'singlePageLimitBytes' => plpc_pdf_single_page_limit_bytes(),
            'sourceKind' => plpc_import_job_payload_is_collection($payload) ? 'collection' : 'single',
            'sourceLabel' => $filename,
            'storageSecurity' => $storageSecurity,
            'sourceFiles' => [],
            'pdfRasters' => [],
            'browserFacts' => [],
            'documents' => [],
            'nextDocument' => 0,
            'renderRequests' => [],
            'renderedForms' => [],
            'renderedFormBytes' => 0,
            'checkpoint' => null,
            'results' => [],
            'documentResults' => [],
            'result' => null,
            'error' => null,
        ];

        plpc_import_job_store_payload($job, $payload, $jobDirectory);
        plpc_import_job_add_event(
            $job,
            'queued',
            ($storageSecurity['fallback'] ?? false) === true
                ? match ((string) ($storageSecurity['accessProtection'] ?? '')) {
                    'apache-htaccess-deny' => 'The source document was saved in the uploads fallback after installing an Apache .htaccess deny rule because private server storage was unavailable.',
                    'iis-web-config-deny' => 'The source document was saved in the uploads fallback after installing an IIS web.config deny rule because private server storage was unavailable.',
                    default => 'The source document was saved in an explicitly configured private fallback.',
                }
                : 'The source document was saved in private server storage outside public uploads.'
        );
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
 * Resume an oversized single-page PDF import as a page tree without asking
 * the user to upload or parse the source again. Only the publication shape is
 * reset; durable PDF page facts and browser-rendered media remain available.
 */
function plpc_switch_import_output_mode(WP_REST_Request $request): WP_REST_Response
{
    $lock = null;
    try {
        $job = plpc_import_job_from_request($request);
        $lock = plpc_import_job_acquire_lock($job);
        $job = plpc_import_job_from_request($request);
        $payload = plpc_import_job_request_payload($request);
        $requested = plpc_normalize_pdf_output_mode($payload['pdfOutputMode'] ?? '');
        if (($job['status'] ?? '') !== 'awaiting_output_mode'
            || (string) (($job['failure']['code'] ?? '')) !== 'pdf_single_page_too_large'
        ) {
            throw new RuntimeException('This import is not waiting for a PDF output choice.');
        }
        if ($requested !== 'pages') {
            throw new RuntimeException('The recoverable output choice must create one child page per PDF page.');
        }

        plpc_import_job_reset_pdf_publication($job);
        $job['pdfOutputMode'] = 'pages';
        unset($job['failure'], $job['error'], $job['assembledBytes'], $job['publishNextResult']);
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            max(1, (int) ($job['progress']['completed'] ?? 1)),
            plpc_import_job_progress_total($job),
            'The saved PDF is ready to continue as one WordPress child page per PDF page.'
        );
        plpc_import_job_add_event(
            $job,
            'output_mode',
            'Reusing the saved PDF facts to create a page tree; the source does not need to be uploaded again.'
        );
        plpc_import_job_save($job);

        return plpc_import_job_response($job);
    } catch (Throwable $error) {
        return plpc_import_job_error_response($error->getMessage(), $error->getCode() === 409 ? 409 : 422);
    } finally {
        plpc_import_job_release_lock($lock);
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
        if (in_array($job['status'] ?? '', ['complete', 'failed', 'awaiting_output_mode'], true)) {
            return plpc_import_job_response($job);
        }
        if (($job['status'] ?? '') === 'retryable_failure') {
            plpc_import_job_resume_retryable_failure($job);
            plpc_import_job_save($job);

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
                if (($job['checkpoint']['rangeContracted'] ?? false) === true) {
                    // Save and acknowledge the smaller range before doing any
                    // more parser work. If the next worker also dies, it will
                    // observe and halve this durable size instead of replaying
                    // the original expensive range forever.
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

        if (($job['status'] ?? '') === 'ready_to_publish') {
            plpc_import_job_publish_next_result($job);
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
                $recoverable = $error instanceof PlpcImportFailure && $error->recoverable;
                plpc_import_job_fail(
                    $job,
                    $error->getMessage(),
                    $error instanceof PlpcImportFailure ? [
                        'code' => $error->failureCode,
                        'stage' => $error->failureStage,
                        'recoverable' => $error->recoverable,
                    ] : null
                );
                plpc_import_job_save($job);

                return plpc_import_job_response($job, $recoverable ? 200 : 500);
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
            $visualId = (string) ($renderRequest['visualId'] ?? $renderRequest['formId'] ?? '');
            $job['renderedForms'][$requestId] = [
                'requestId' => $requestId,
                'formId' => (string) ($renderRequest['formId'] ?? ''),
                'visualId' => $visualId,
                'visualKind' => (string) ($renderRequest['visualKind'] ?? 'form-xobject'),
                'path' => (string) ($renderRequest['path'] ?? ''),
                'error' => substr($renderError, 0, 300),
            ];
            plpc_import_job_mark_pdf_visual_occurrence(
                $job,
                (string) ($renderRequest['path'] ?? ''),
                $visualId,
                'unresolved',
                'browser-render-failed'
            );
            array_splice($job['renderRequests'], $requestIndex, 1);
            plpc_import_job_add_event($job, 'renderer', 'The browser could not render one PDF figure; the text import will continue.');
        } else {
            $rendered = isset($payload['uploadedRender']) && is_array($payload['uploadedRender'])
                ? plpc_import_job_rendered_image_from_uploaded_file($payload['uploadedRender'], $payload)
                : plpc_import_job_rendered_image_from_payload($payload);
            $renderedBytes = strlen($rendered['contents']);
            $renderedFormBytes = plpc_import_job_rendered_form_total_bytes($job);
            if ($renderedBytes > PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES - $renderedFormBytes) {
                // Reaching an enhancement budget is not a document failure.
                // A magazine can contain dozens of legitimate vector Forms;
                // acknowledge the outstanding request with a diagnostic so
                // the durable text import continues instead of stranding the
                // job in awaiting_renderer after minutes of browser work.
                $visualId = (string) ($renderRequest['visualId'] ?? $renderRequest['formId'] ?? '');
                $job['renderedForms'][$requestId] = [
                    'requestId' => $requestId,
                    'formId' => (string) ($renderRequest['formId'] ?? ''),
                    'visualId' => $visualId,
                    'visualKind' => (string) ($renderRequest['visualKind'] ?? 'form-xobject'),
                    'path' => (string) ($renderRequest['path'] ?? ''),
                    'error' => 'The PDF figure media budget was reached.',
                ];
                plpc_import_job_mark_pdf_visual_occurrence(
                    $job,
                    (string) ($renderRequest['path'] ?? ''),
                    $visualId,
                    'unresolved',
                    'browser-render-media-budget-exceeded'
                );
                array_splice($job['renderRequests'], $requestIndex, 1);
                plpc_import_job_add_event(
                    $job,
                    'renderer',
                    'The PDF figure media budget was reached; remaining text and images will continue without this optional crop.'
                );
            } else {
                $stored = plpc_import_job_store_rendered_form(
                    plpc_import_job_directory($job),
                    $requestId,
                    $renderRequest,
                    $rendered
                );
                $job['renderedForms'][$requestId] = $stored;
                $job['renderedFormBytes'] = $renderedFormBytes + $renderedBytes;
                array_splice($job['renderRequests'], $requestIndex, 1);
                plpc_import_job_add_event($job, 'renderer', 'Received a browser-rendered PDF figure.');
            }
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
 * Persist pages independently even when one request extracts a wider range.
 * Extraction can therefore amortize the document-global PDF parse while the
 * later semantic planner can still make memory-bounded page groups instead
 * of treating one large extraction request as an indivisible final segment.
 *
 * @param array<string, mixed> $job
 * @param array{facts:\PortLibs\MarkerPDF\PdfDocumentFacts,startPage:int,endPage:int,pageNumbers:list<int>} $chunk
 * @return list<array{startPage:int,endPage:int,facts:string,sha256:string,bytes:int}>
 */
function plpc_import_job_store_pdf_chunk_pages(array $job, int $documentIndex, array $chunk): array
{
    $facts = $chunk['facts'] ?? null;
    if (!$facts instanceof \PortLibs\MarkerPDF\PdfDocumentFacts) {
        throw new RuntimeException('A PDF page chunk did not contain serializable facts.');
    }
    $documentData = $facts->toArray();
    $totalPages = max(1, (int) ($documentData['inventory']['totalPages'] ?? count($facts->pages())));
    $records = [];
    foreach ($facts->pages() as $page) {
        $pageNumber = $page->pageNumber();
        $pageData = $documentData;
        $pageData['pages'] = [$page->toArray()];
        $pageData['inventory'] = [
            'totalPages' => $totalPages,
            'startPage' => $pageNumber,
            'endPage' => $pageNumber,
            'pageNumbers' => [$pageNumber],
            'hasMorePages' => $pageNumber < $totalPages,
            'nextPage' => $pageNumber < $totalPages ? $pageNumber + 1 : null,
        ];
        $pageFacts = \PortLibs\MarkerPDF\PdfDocumentFacts::fromArray($pageData);
        $records[] = plpc_import_job_store_pdf_chunk($job, $documentIndex, [
            'facts' => $pageFacts,
            'startPage' => $pageNumber,
            'endPage' => $pageNumber,
            'pageNumbers' => [$pageNumber],
        ]);
    }
    if (array_column($records, 'startPage') !== ($chunk['pageNumbers'] ?? [])) {
        throw new RuntimeException('A PDF extraction range could not be persisted page by page.');
    }

    return $records;
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
        $record = [
            'path' => $path,
            'storage' => $storage,
            'format' => (string) $document['format'],
        ];
        if (PandocConverter::canonicalInputFormat((string) $document['format']) === 'pdf') {
            $pdfBytes = is_string($document['bytes'] ?? null)
                ? $document['bytes']
                : plpc_import_job_read_file($job, $storage);
            $pdfExtractor = new \PortLibs\MarkerPDF\PdfTextExtractor();
            $inventory = $pdfExtractor->extractPageInventory($pdfBytes);
            $pageCount = max(0, (int) ($inventory['totalPages'] ?? 0));
            if ($pageCount < 1) {
                throw new RuntimeException('The PDF page tree could not be resolved safely.');
            }
            $record['pdfPageCount'] = $pageCount;
            $record['pdfNextPage'] = 1;
            $record['pdfPagesPerRequest'] = plpc_pdf_pages_per_request($pageCount);
            $record['pdfChunks'] = [];
            // Visuals are inspected from the same bounded page facts that are
            // persisted below. Doing a second whole-document pass here made a
            // timed-out preparation request restart from page one and could
            // retain four unbounded visual collections at once.
            $record['pdfVisualInspectionCompleteThroughPage'] = 0;
            $record['pdfVisualInspectionNextPage'] = 1;
            $record['pdfVisualOccurrences'] = [];
            $record['pdfVisualInventoryComplete'] = ($job['imageMode'] ?? 'important') === 'none';
            if (class_exists('PortLibs\\MarkerPDF\\PdfMetadataExtractor')) {
                try {
                    $metadataExtractor = new \PortLibs\MarkerPDF\PdfMetadataExtractor();
                    $record['pdfReaderStructuralMetadata'] = $metadataExtractor->extractReaderStructuralMetadata($pdfBytes);
                    $record['pdfReaderMetadata'] = $metadataExtractor->extractReaderMetadata($pdfBytes);
                } catch (Throwable) {
                    // Reader metadata is optional. The final semantic pass can
                    // still derive it from the source if this cache is absent.
                }
            }
            $browserFacts = is_array($job['browserFacts'][$path] ?? null) ? $job['browserFacts'][$path] : null;
            if ($browserFacts !== null) {
                $record['pdfBrowserFacts'] = $browserFacts;
            }
        }
        $job['documents'][] = $record;
    }
    if (($job['documents'] ?? []) === []) {
        throw new RuntimeException('The saved document files could not be prepared for import.');
    }

    $job['nextDocument'] = 0;
    $job['results'] = [];
    $job['documentResults'] = [];
    $job['renderRequests'] = [];
    $total = plpc_import_job_progress_total($job);
    plpc_import_job_set_progress(
        $job,
        'ready_to_convert',
        1,
        $total,
        'Document type and page count are ready. The next bounded request starts with PDF page 1.'
    );
    plpc_import_job_add_event($job, 'ready_to_convert', 'PDF page facts and figures will be inspected in resumable page ranges.');
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_convert_next_document(array &$job, ?float $deadline = null): void
{
    $documents = is_array($job['documents'] ?? null) ? $job['documents'] : [];
    $index = max(0, (int) ($job['nextDocument'] ?? 0));
    while (isset($documents[$index]) && is_array($documents[$index]) && ($documents[$index]['completed'] ?? false) === true) {
        $index++;
    }
    $job['nextDocument'] = $index;
    if (!isset($documents[$index]) || !is_array($documents[$index])) {
        plpc_import_job_begin_publication($job);

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
    if ($canonicalFormat === 'pdf' && (int) ($document['pdfPageCount'] ?? 0) > 0) {
        plpc_import_job_convert_next_pdf_chunk($job, $index, $document, $bytes, $deadline);

        return;
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
    $result['kind'] = (string) ($result['kind'] ?? 'document');
    $result['postCount'] = max(1, (int) ($result['postCount'] ?? 1));
    $result['documentIndex'] = $index;
    plpc_import_job_append_publication_result($job, $result);
    plpc_import_job_clear_document_checkpoint($job, $index);
    plpc_import_job_complete_document($job, $index, $document, $result);
}

/**
 * Migrate page facts saved by an older job version without re-reading the PDF
 * source. At most one normal extraction-range worth of already-durable pages
 * is classified per request, and the cursor is saved before more work begins.
 *
 * @param array<string,mixed> $job
 * @param array<string,mixed> $document
 */
function plpc_import_job_migrate_saved_pdf_visuals(
    array &$job,
    int $documentIndex,
    array $document,
    int $pageCount,
    int $pagesPerRequest
): bool {
    if (($job['imageMode'] ?? 'important') === 'none') {
        if (!array_key_exists('pdfVisualInspectionCompleteThroughPage', $document)) {
            $document['pdfVisualInspectionCompleteThroughPage'] = $pageCount;
            $document['pdfVisualInspectionNextPage'] = $pageCount + 1;
            $document['pdfVisualOccurrences'] = [];
            $document['pdfVisualInventoryComplete'] = true;
            $job['documents'][$documentIndex] = $document;
        }

        return false;
    }
    if (($document['pdfVisualInventoryComplete'] ?? false) === true
        && !array_key_exists('pdfVisualInspectionCompleteThroughPage', $document)
    ) {
        // Version 3 jobs performed a complete visual scan during prepare.
        $document['pdfVisualInspectionCompleteThroughPage'] = $pageCount;
        $document['pdfVisualInspectionNextPage'] = $pageCount + 1;
        $job['documents'][$documentIndex] = $document;

        return false;
    }

    $completeThrough = max(0, (int) ($document['pdfVisualInspectionCompleteThroughPage'] ?? 0));
    $extractedThrough = min($pageCount, max(0, (int) ($document['pdfNextPage'] ?? 1) - 1));
    if ($completeThrough >= $extractedThrough) {
        if (!array_key_exists('pdfVisualInspectionCompleteThroughPage', $document)) {
            $document['pdfVisualInspectionCompleteThroughPage'] = $completeThrough;
            $document['pdfVisualInspectionNextPage'] = $completeThrough + 1;
            $document['pdfVisualOccurrences'] = is_array($document['pdfVisualOccurrences'] ?? null)
                ? $document['pdfVisualOccurrences']
                : [];
            $document['pdfVisualInventoryComplete'] = false;
            $job['documents'][$documentIndex] = $document;
        }

        return false;
    }

    $records = [];
    foreach ($document['pdfChunks'] ?? [] as $record) {
        if (is_array($record)) {
            $records[max(1, (int) ($record['startPage'] ?? 1))] = $record;
        }
    }
    $migrationEnd = min($extractedThrough, $completeThrough + max(1, $pagesPerRequest));
    $job['documents'][$documentIndex] = $document;
    for ($pageNumber = $completeThrough + 1; $pageNumber <= $migrationEnd; $pageNumber++) {
        $record = $records[$pageNumber] ?? null;
        if (!is_array($record) || (int) ($record['endPage'] ?? 0) !== $pageNumber) {
            throw new PlpcImportFailure(
                'pdf_visual_checkpoint_missing_facts',
                'A saved PDF page is missing the facts needed to resume visual inspection.',
                true,
                'inspecting_visuals'
            );
        }
        $chunk = plpc_import_job_load_pdf_chunk($job, $record);
        plpc_import_job_checkpoint_pdf_chunk_visuals(
            $job,
            $documentIndex,
            $chunk['facts'],
            $pageNumber,
            $pageNumber,
            $pageCount
        );
    }

    $total = plpc_import_job_progress_total($job);
    if (($job['renderRequests'] ?? []) !== []) {
        plpc_import_job_set_progress(
            $job,
            'awaiting_renderer',
            min($total - 1, $migrationEnd),
            $total,
            'Recovered saved visual facts through PDF page ' . $migrationEnd . '. Waiting for this browser to preserve the figures.'
        );
    } else {
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            min($total - 1, $migrationEnd),
            $total,
            'Recovered saved visual facts through PDF page ' . $migrationEnd . '. The next request will continue safely.'
        );
    }
    plpc_import_job_add_event(
        $job,
        'checkpoint_migrated',
        'Recovered an older import job from durable page facts without rescanning its source PDF.'
    );
    plpc_import_job_save($job);

    return true;
}

/**
 * Advance one PDF page range, or finalize the page after every range is
 * durable. No post or attachment is created while page chunks are pending.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 */
function plpc_import_job_convert_next_pdf_chunk(
    array &$job,
    int $index,
    array $document,
    string $bytes,
    ?float $deadline = null
): void {
    $documents = is_array($job['documents'] ?? null) ? $job['documents'] : [];
    $pageCount = max(1, (int) ($document['pdfPageCount'] ?? 0));
    $nextPage = max(1, (int) ($document['pdfNextPage'] ?? 1));
    $pagesPerRequest = max(1, (int) ($document['pdfPagesPerRequest'] ?? plpc_pdf_pages_per_request($pageCount)));
    $total = plpc_import_job_progress_total($job);
    $before = plpc_import_job_progress_before_document($job, $index);
    $path = (string) ($document['path'] ?? 'document.pdf');

    if (plpc_import_job_migrate_saved_pdf_visuals(
        $job,
        $index,
        $document,
        $pageCount,
        $pagesPerRequest
    )) {
        return;
    }
    $document = is_array($job['documents'][$index] ?? null) ? $job['documents'][$index] : $document;

    if ($nextPage <= $pageCount) {
        $endPage = min($pageCount, $nextPage + $pagesPerRequest - 1);
        $rangeLabel = $nextPage === $endPage
            ? 'page ' . $nextPage . ' of ' . $pageCount
            : 'pages ' . $nextPage . '–' . $endPage . ' of ' . $pageCount;
        plpc_import_job_set_progress(
            $job,
            'converting',
            min($total - 1, $before + $nextPage - 1),
            $total,
            'Reading PDF ' . $rangeLabel . '.'
        );
        plpc_import_job_add_event($job, 'reading', 'Reading PDF ' . $rangeLabel . '.', false);
        plpc_import_job_save($job);
        plpc_import_job_checkpoint_for_deadline($job, $deadline, $index, 'reading', 'Reading PDF ' . $rangeLabel . '.');

        $reportProgress = static function (string $stage, string $label) use (&$job, $index, $total, $before, $nextPage, $deadline): void {
            plpc_import_job_set_progress(
                $job,
                'converting',
                min($total - 1, $before + $nextPage - 1),
                $total,
                $label
            );
            plpc_import_job_add_event($job, $stage, $label, false);
            plpc_import_job_save($job);
            plpc_import_job_checkpoint_for_deadline($job, $deadline, $index, $stage, $label);
        };
        $file = [
            'path' => $path,
            'bytes' => $bytes,
            'format' => (string) ($document['format'] ?? 'pdf'),
            'pdfRasterImages' => plpc_import_job_load_pdf_rasters($job, $path),
            'pdfFormRenders' => plpc_import_job_load_rendered_forms($job, $path),
            'pdfBrowserFacts' => plpc_import_job_load_browser_facts($job, $path),
        ];
        $chunkStartedAt = microtime(true);
        $chunkStartMemory = memory_get_usage(true);
        $chunk = plpc_convert_pdf_page_chunk(
            $file,
            $nextPage,
            $endPage - $nextPage + 1,
            (string) ($job['imageMode'] ?? 'important'),
            (string) ($job['pdfMode'] ?? 'layout'),
            $reportProgress
        );
        $records = plpc_import_job_store_pdf_chunk_pages($job, $index, $chunk);
        $recordsByStart = [];
        foreach ($document['pdfChunks'] ?? [] as $existing) {
            if (is_array($existing)) {
                $recordsByStart[max(1, (int) ($existing['startPage'] ?? 1))] = $existing;
            }
        }
        foreach ($records as $record) {
            $recordsByStart[max(1, (int) ($record['startPage'] ?? 1))] = $record;
        }
        ksort($recordsByStart, SORT_NUMERIC);
        $document['pdfChunks'] = array_values($recordsByStart);
        $document['pdfNextPage'] = $endPage + 1;
        $factsBytes = array_sum(array_map(
            static fn (array $record): int => max(0, (int) ($record['bytes'] ?? 0)),
            $records
        ));
        $metric = [
            'startPage' => $nextPage,
            'endPage' => $endPage,
            'pages' => $endPage - $nextPage + 1,
            'factsBytes' => $factsBytes,
            'durationMs' => max(1, (int) round((microtime(true) - $chunkStartedAt) * 1000)),
            'peakBytes' => max(0, memory_get_peak_usage(true)),
            'workingBytes' => max(0, memory_get_peak_usage(true) - $chunkStartMemory),
        ];
        $metrics = is_array($document['pdfChunkMetrics'] ?? null) ? $document['pdfChunkMetrics'] : [];
        $metrics[] = $metric;
        $document['pdfChunkMetrics'] = array_slice($metrics, -50);
        $document['pdfPagesPerRequest'] = plpc_pdf_adaptive_pages_per_request(
            $pagesPerRequest,
            $metric,
            max(0, $pageCount - $endPage)
        );
        $job['documents'][$index] = $document;
        plpc_import_job_checkpoint_pdf_chunk_visuals(
            $job,
            $index,
            $chunk['facts'],
            $nextPage,
            $endPage,
            $pageCount
        );
        plpc_import_job_clear_document_checkpoint($job, $index);
        plpc_import_job_add_event($job, 'checkpoint', 'Saved a durable PDF checkpoint through page ' . $endPage . ' of ' . $pageCount . '.');
        if (($job['renderRequests'] ?? []) !== []) {
            plpc_import_job_set_progress(
                $job,
                'awaiting_renderer',
                min($total - 1, $before + $endPage),
                $total,
                'Saved PDF ' . $rangeLabel . '. Waiting for this browser to preserve '
                    . count($job['renderRequests']) . ' figure' . (count($job['renderRequests']) === 1 ? '.' : 's.')
            );
            plpc_import_job_add_event(
                $job,
                'renderer',
                'The saved page range produced ' . count($job['renderRequests'])
                    . ' browser-renderable PDF figure' . (count($job['renderRequests']) === 1 ? '.' : 's.')
            );
        } else {
            plpc_import_job_set_progress(
                $job,
                'ready_to_convert',
                min($total - 1, $before + $endPage),
                $total,
                $endPage >= $pageCount
                    ? 'Saved the final PDF ' . $rangeLabel . ' and completed the visual inventory.'
                    : 'Saved PDF ' . $rangeLabel . '. The next request will continue from page ' . ($endPage + 1) . '.'
            );
        }
        // Persist the facts cursor, visual cursor, and pending renderer queue
        // as one coherent checkpoint before returning control to the browser.
        plpc_import_job_save($job);

        return;
    }

    plpc_import_job_convert_next_pdf_segment($job, $index, $document, $bytes, $deadline);
}

/**
 * Resolve one bounded semantic segment at a time. Segments are internal
 * checkpoints: single-page mode assembles them into one post, while page-tree
 * mode deliberately uses one physical-page segment per child page.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 */
function plpc_import_job_convert_next_pdf_segment(
    array &$job,
    int $documentIndex,
    array $document,
    string $pdfBytes,
    ?float $deadline = null
): void {
    $outputMode = plpc_import_job_pdf_output_mode($job);
    $pageCount = max(1, (int) ($document['pdfPageCount'] ?? 0));
    $before = plpc_import_job_progress_before_document($job, $documentIndex);
    if (!is_array($document['pdfSegments'] ?? null) || $document['pdfSegments'] === []) {
        if (!is_array($document['pdfDocumentProfile'] ?? null)) {
            $document['pdfDocumentProfile'] = plpc_import_job_merge_pdf_document_profile(
                $job,
                $documentIndex,
                $document
            );
        }
        $document['pdfSegments'] = $outputMode === 'pages'
            ? plpc_import_job_plan_pdf_page_segments($document)
            : plpc_import_job_plan_pdf_segments($document);
        $document['pdfNextSegment'] = 0;
        if ($outputMode === 'pages') {
            $document['pdfRootPostId'] = plpc_import_job_get_or_create_pdf_root($job, $documentIndex, $document);
        }
        $job['documents'][$documentIndex] = $document;
        $total = plpc_import_job_progress_total($job);
        $segmentCount = count($document['pdfSegments']);
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            min($total - 1, $before + $pageCount),
            $total,
            $outputMode === 'pages'
                ? 'All PDF page facts are durable. Ready to create one child page per physical PDF page.'
                : ($segmentCount === 1
                ? 'All PDF page facts are durable. Ready to verify the complete document.'
                : 'All PDF page facts are durable. The document will be resolved in '
                    . $segmentCount . ' bounded internal passes before one WordPress page is created.')
        );
        plpc_import_job_add_event(
            $job,
            'segmentation',
            $outputMode === 'pages'
                ? 'Physical PDF pages will become resumable child pages below a private root draft.'
                : ($segmentCount === 1
                ? 'The saved facts fit one complete-document semantic pass.'
                : 'Bounded semantic passes will preserve completed work without exposing internal page ranges.')
        );
    }

    $segments = array_values($document['pdfSegments']);
    $segmentIndex = max(0, (int) ($document['pdfNextSegment'] ?? 0));
    if ($segmentIndex >= count($segments)) {
        if ($outputMode === 'single') {
            plpc_import_job_finalize_single_pdf_output($job, $documentIndex, $document, $pdfBytes, $deadline);

            return;
        }
        if ($outputMode === 'pages') {
            plpc_import_job_finalize_pdf_page_tree($job, $documentIndex, $document, $deadline);

            return;
        }
    }
    if (!isset($segments[$segmentIndex]) || !is_array($segments[$segmentIndex])) {
        throw new RuntimeException('The saved PDF segment cursor was invalid.');
    }
    $segment = $segments[$segmentIndex];
    $startPage = max(1, (int) ($segment['startPage'] ?? 1));
    $endPage = max($startPage, (int) ($segment['endPage'] ?? $startPage));
    $range = $startPage === $endPage ? 'page ' . $startPage : 'pages ' . $startPage . '–' . $endPage;
    $total = plpc_import_job_progress_total($job);
    $segmentBase = $before + $pageCount + ($segmentIndex * 3);

    if (!is_array($segment['facts'] ?? null)) {
        plpc_import_job_set_progress($job, 'merging_facts', min($total - 1, $segmentBase), $total, 'Verifying PDF ' . $range . '.');
        plpc_import_job_add_event($job, 'merging_facts', 'Merging the durable facts for PDF ' . $range . '.', false);
        plpc_import_job_save($job);
        plpc_import_job_checkpoint_for_deadline($job, $deadline, $documentIndex, 'merging_facts', 'Verifying PDF ' . $range . '.');
        $segment['facts'] = plpc_import_job_merge_pdf_segment_facts($job, $documentIndex, $document, $segmentIndex, $segment);
        $segments[$segmentIndex] = $segment;
        $document['pdfSegments'] = $segments;
        if (count($segments) === 1) {
            $document['pdfDocumentFacts'] = $segment['facts'];
        }
        $job['documents'][$documentIndex] = $document;
        plpc_import_job_clear_document_checkpoint($job, $documentIndex);
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            min($total - 1, $segmentBase + 1),
            $total,
            'PDF ' . $range . ' is verified. The next request will resolve its reading order and layout.'
        );
        plpc_import_job_add_event($job, 'checkpoint', 'Saved a verified facts snapshot for PDF ' . $range . '.');

        return;
    }

    if (!is_array($segment['finalBundle'] ?? null)) {
        plpc_import_job_set_progress(
            $job,
            'global_semantics',
            min($total - 1, $segmentBase + 1),
            $total,
            'Resolving reading order and document structure for PDF ' . $range . '.'
        );
        plpc_import_job_add_event($job, 'global_semantics', 'Running the bounded semantic pass for PDF ' . $range . '.', false);
        plpc_import_job_save($job);
        plpc_import_job_checkpoint_for_deadline($job, $deadline, $documentIndex, 'global_semantics', 'Resolving PDF ' . $range . '.');
        $semanticProgress = static function (string $stage, string $label) use (&$job, $total, $segmentBase): void {
            plpc_import_job_set_progress($job, $stage, min($total - 1, $segmentBase + 1), $total, $label);
            plpc_import_job_add_event($job, $stage, $label, false);
            plpc_import_job_save($job);
        };
        $segment['finalBundle'] = plpc_import_job_prepare_pdf_final_bundle(
            $job,
            $documentIndex,
            $document,
            $pdfBytes,
            (string) ($job['imageMode'] ?? 'important'),
            (string) ($job['pdfMode'] ?? 'layout'),
            $semanticProgress,
            $segment['facts'],
            $segmentIndex
        );
        $segments[$segmentIndex] = $segment;
        $document['pdfSegments'] = $segments;
        if (count($segments) === 1) {
            $document['pdfFinalBundle'] = $segment['finalBundle'];
        }
        $job['documents'][$documentIndex] = $document;
        plpc_import_job_clear_document_checkpoint($job, $documentIndex);
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            min($total - 1, $segmentBase + 2),
            $total,
            'The conversion for PDF ' . $range . ' is saved. The next request will prepare its publication bundle.'
        );
        plpc_import_job_add_event($job, 'checkpoint', 'Saved the private WordPress block and media bundle for PDF ' . $range . '.');

        return;
    }

    plpc_import_job_set_progress($job, 'finalizing', min($total - 1, $segmentBase + 2), $total, 'Preparing PDF ' . $range . ' for WordPress.');
    plpc_import_job_add_event($job, 'finalizing', 'The result for PDF ' . $range . ' is durable and ready for publication preparation.', false);
    plpc_import_job_save($job);
    plpc_import_job_checkpoint_for_deadline($job, $deadline, $documentIndex, 'finalizing', 'Publishing PDF ' . $range . '.');
    $collection = ($job['sourceKind'] ?? '') === 'collection'
        ? ['label' => (string) ($job['sourceLabel'] ?? $job['title'] ?? 'Import'), 'files' => plpc_import_job_load_source_files($job)]
        : null;
    $baseTitle = count($job['documents'] ?? []) === 1
        ? (string) ($job['title'] ?? '')
        : plpc_title_from_filename((string) ($document['path'] ?? 'document.pdf'));
    if ($baseTitle === '') {
        $baseTitle = plpc_title_from_filename((string) ($document['path'] ?? 'document.pdf'));
    }
    $finalProgress = static function (string $stage, string $label) use (&$job, $total, $segmentBase): void {
        plpc_import_job_set_progress($job, $stage, min($total - 1, $segmentBase + 2), $total, $label);
        plpc_import_job_add_event($job, $stage, $label, false);
        plpc_import_job_save($job);
    };

    if ($outputMode === 'single') {
        if (!is_array($segment['publicationBundle'] ?? null)) {
            $materialized = plpc_import_job_materialize_pdf_bundle(
                $job,
                $document,
                $pdfBytes,
                $collection,
                $segment['finalBundle'],
                $finalProgress
            );
            $segment['publicationBundle'] = plpc_import_job_store_pdf_publication_bundle(
                $job,
                $documentIndex,
                $segmentIndex,
                $materialized
            );
        }
        $segments[$segmentIndex] = $segment;
        $document['pdfSegments'] = $segments;
        $document['pdfNextSegment'] = $segmentIndex + 1;
        $job['documents'][$documentIndex] = $document;
        plpc_import_job_clear_document_checkpoint($job, $documentIndex);
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            min($total - 1, $segmentBase + 3),
            $total,
            $document['pdfNextSegment'] < count($segments)
                ? 'Saved PDF ' . $range . ' for final assembly. The next bounded segment is ready.'
                : 'Every PDF segment is ready. The next request will safely assemble one WordPress page.'
        );
        plpc_import_job_add_event($job, 'checkpoint', 'Saved post-ready blocks for PDF ' . $range . ' without creating a partial page.');

        return;
    }

    if ($outputMode === 'pages') {
        $rootPostId = max(1, (int) ($document['pdfRootPostId'] ?? plpc_import_job_get_or_create_pdf_root($job, $documentIndex, $document)));
        $materialized = plpc_import_job_materialize_pdf_bundle(
            $job,
            $document,
            $pdfBytes,
            $collection,
            $segment['finalBundle'],
            $finalProgress
        );
        $pageFingerprint = plpc_import_content_fingerprint((string) ($materialized['blocks'] ?? ''));
        $allowEmpty = (int) ($pageFingerprint['visibleTextBytes'] ?? 0) === 0
            && (int) ($pageFingerprint['imageCount'] ?? 0) === 0
            && plpc_import_job_pdf_page_is_certified_blank($job, $segment['facts']);
        $result = plpc_import_job_finalize_pdf_document(
            $job,
            $documentIndex,
            $document,
            $pdfBytes,
            $collection,
            $baseTitle . ' — Page ' . $startPage,
            $finalProgress,
            $segment['finalBundle'],
            $segmentIndex,
            ['startPage' => $startPage, 'endPage' => $startPage],
            ['post_parent' => $rootPostId, 'menu_order' => $startPage],
            'page',
            $materialized,
            $allowEmpty
        );
        $result['pageNumber'] = $startPage;
        $result['documentIndex'] = $documentIndex;
        $segment['result'] = $result;
        $segments[$segmentIndex] = $segment;
        $document['pdfSegments'] = $segments;
        $document['pdfNextSegment'] = $segmentIndex + 1;
        $document['pdfRootPostId'] = $rootPostId;
        $job['documents'][$documentIndex] = $document;
        plpc_import_job_append_publication_result($job, $result);
        plpc_import_job_clear_document_checkpoint($job, $documentIndex);
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            min($total - 1, $segmentBase + 3),
            $total,
            $document['pdfNextSegment'] < count($segments)
                ? 'Verified PDF page ' . $startPage . ' privately. The next physical page is ready.'
                : 'Every PDF child page is verified privately. The next request will finalize their root index.'
        );
        plpc_import_job_add_event($job, 'checkpoint', 'Saved and verified child page ' . $startPage . ' of ' . $pageCount . '.');

        return;
    }

    // Resume version-2 jobs with their original linked-range publication
    // contract. New imports never select this compatibility path.
    $postTitle = count($segments) === 1 ? $baseTitle : $baseTitle . ' — pages ' . $startPage . '–' . $endPage;
    $stableSegmentIndex = count($segments) > 1 ? $segmentIndex : null;
    $result = plpc_import_job_finalize_pdf_document(
        $job,
        $documentIndex,
        $document,
        $pdfBytes,
        $collection,
        $postTitle,
        $finalProgress,
        $segment['finalBundle'],
        $stableSegmentIndex,
        ['startPage' => $startPage, 'endPage' => $endPage]
    );
    $result['documentIndex'] = $documentIndex;
    $segment['result'] = $result;
    $segments[$segmentIndex] = $segment;
    $document['pdfSegments'] = $segments;
    $document['pdfNextSegment'] = $segmentIndex + 1;
    $job['documents'][$documentIndex] = $document;
    plpc_import_job_append_publication_result($job, $result);
    plpc_import_job_clear_document_checkpoint($job, $documentIndex);

    if ($document['pdfNextSegment'] < count($segments)) {
        plpc_import_job_set_progress(
            $job,
            'ready_to_convert',
            min($total - 1, $segmentBase + 3),
            $total,
            'Verified PDF ' . $range . ' privately. The next saved page range is ready.'
        );
        plpc_import_job_add_event($job, 'ready_to_convert', 'One bounded PDF page range is stored and verified privately; the import will continue.');

        return;
    }

    $legacyResult = count($segments) === 1 ? $result : [
        'kind' => 'legacy-pdf-ranges',
        'format' => 'pdf',
        'path' => (string) ($document['path'] ?? 'document.pdf'),
        'title' => $baseTitle,
        'posts' => array_values(array_map(static fn (array $item): array => $item['result'], $segments)),
    ];
    plpc_import_job_complete_document($job, $documentIndex, $document, $legacyResult);
}

/** @param array<string, mixed> $job @param array<string, mixed> $result */
function plpc_import_job_append_publication_result(array &$job, array $result): void
{
    $results = is_array($job['results'] ?? null) ? array_values($job['results']) : [];
    $postId = max(0, (int) ($result['postId'] ?? 0));
    $publicationRecord = plpc_import_job_publication_record($result);
    foreach ($results as $index => $existing) {
        if (is_array($existing) && $postId > 0 && (int) ($existing['postId'] ?? 0) === $postId) {
            $results[$index] = $publicationRecord;
            $job['results'] = $results;

            return;
        }
    }
    $results[] = $publicationRecord;
    $job['results'] = $results;
}

/**
 * The publication queue needs an identity and cursor, not another copy of a
 * page's diagnostics, quality report, children, and posts. Source-level
 * results remain once in documentResults and the durable post metadata.
 *
 * @param array<string, mixed> $result
 * @return array<string, mixed>
 */
function plpc_import_job_publication_record(array $result): array
{
    $record = [
        'postId' => max(0, (int) ($result['postId'] ?? 0)),
    ];
    foreach (['documentIndex', 'pageNumber', 'postCount', 'pageCount'] as $field) {
        if (isset($result[$field])) {
            $record[$field] = max(0, (int) $result[$field]);
        }
    }
    foreach (['kind', 'format', 'path', 'pageUrl', 'editUrl', 'title'] as $field) {
        if (isset($result[$field]) && is_scalar($result[$field])) {
            $record[$field] = (string) $result[$field];
        }
    }

    return $record;
}

/**
 * Mark one source document complete and move the durable cursor. The flat
 * results list is publication order; documentResults retains the source-level
 * shape used by PDF roots and collection indexes.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @param array<string, mixed> $result
 */
function plpc_import_job_complete_document(array &$job, int $documentIndex, array $document, array $result): void
{
    $document['completed'] = true;
    $documentResults = is_array($job['documentResults'] ?? null) ? $job['documentResults'] : [];
    $documentResults[$documentIndex] = $result;
    ksort($documentResults, SORT_NUMERIC);
    $job['documentResults'] = $documentResults;

    // Page facts, semantic bundles, and publication bundles are already
    // integrity-checked files. Once a source document has produced its sole
    // source-level result, keeping every descriptor and every child result in
    // the option only duplicates durable state and grows quadratically for a
    // page tree. Retention keeps the private files for recovery/audit.
    if ((int) ($document['pdfPageCount'] ?? 0) > 0) {
        unset(
            $document['pdfChunks'],
            $document['pdfChunkMetrics'],
            $document['pdfSegments'],
            $document['pdfDocumentFacts'],
            $document['pdfFinalBundle'],
            $document['pdfSingleResult'],
            $document['pdfTreeResult'],
            $document['result']
        );
        $document['durableConversionRetained'] = true;
    } else {
        unset($document['result']);
    }
    $job['documents'][$documentIndex] = $document;

    $documents = is_array($job['documents'] ?? null) ? $job['documents'] : [];
    $next = $documentIndex + 1;
    while (isset($documents[$next]) && is_array($documents[$next]) && ($documents[$next]['completed'] ?? false) === true) {
        $next++;
    }
    $job['nextDocument'] = $next;
    if ($next >= count($documents)) {
        plpc_import_job_begin_publication($job);

        return;
    }
    $total = plpc_import_job_progress_total($job);
    plpc_import_job_set_progress(
        $job,
        'ready_to_convert',
        min($total - 1, plpc_import_job_progress_before_document($job, $next)),
        $total,
        'Finished ' . basename((string) ($document['path'] ?? 'document')) . '. Ready for the next document.'
    );
    plpc_import_job_add_event($job, 'ready_to_convert', 'One document is complete; the batch will continue on the next request.');
}

/** @param array<string, mixed> $job @param array<string, mixed> $document */
function plpc_import_job_get_or_create_pdf_root(array $job, int $documentIndex, array $document): int
{
    $jobId = (string) ($job['id'] ?? '');
    if ($jobId !== '' && function_exists('get_posts')) {
        $ids = get_posts([
            'post_type' => 'page',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => [
                ['key' => '_plpc_import_job_id', 'value' => $jobId],
                ['key' => '_plpc_import_document_index', 'value' => (string) $documentIndex],
                ['key' => '_plpc_import_pdf_role', 'value' => 'root'],
            ],
        ]);
        $existing = max(0, (int) ($ids[0] ?? 0));
        if ($existing > 0) {
            return $existing;
        }
    }

    $title = count($job['documents'] ?? []) === 1
        ? (string) ($job['title'] ?? '')
        : plpc_title_from_filename((string) ($document['path'] ?? 'document.pdf'));
    if ($title === '') {
        $title = plpc_title_from_filename((string) ($document['path'] ?? 'document.pdf'));
    }
    $postId = wp_insert_post([
        'post_type' => 'page',
        'post_title' => $title,
        'post_status' => 'draft',
        'post_content' => '',
        'meta_input' => [
            '_plpc_import_job_id' => $jobId,
            '_plpc_import_document_index' => $documentIndex,
            '_plpc_import_pdf_role' => 'root',
        ],
    ], true);
    if (is_wp_error($postId) || (int) $postId < 1) {
        $message = is_wp_error($postId) && method_exists($postId, 'get_error_message')
            ? $postId->get_error_message()
            : 'WordPress could not create the private PDF root draft.';
        throw new RuntimeException($message);
    }
    $postId = (int) $postId;
    $status = plpc_import_job_post_status($postId);
    if ($status !== '' && $status !== 'draft') {
        if (function_exists('wp_delete_post')) {
            wp_delete_post($postId, true);
        }
        throw new PlpcImportFailure(
            'publication_draft_status_mismatch',
            'WordPress did not preserve the private PDF root draft status.',
            true,
            'preparing_publication'
        );
    }

    return $postId;
}

/** @param array<string, mixed> $post */
function plpc_import_update_verified_page(int $postId, array $post): void
{
    $blocks = plpc_import_sanitize_post_content((string) ($post['post_content'] ?? ''));
    $expected = plpc_import_content_fingerprint($blocks);
    plpc_import_assert_content_fingerprint($expected, $blocks);
    $storedFingerprint = $expected;
    unset($storedFingerprint['visibleText']);
    $post['ID'] = $postId;
    $post['post_content'] = $blocks;
    $post['post_status'] = 'draft';
    $post['meta_input'] = is_array($post['meta_input'] ?? null) ? $post['meta_input'] : [];
    $post['meta_input']['_plpc_import_content_fingerprint'] = $storedFingerprint;
    $updated = wp_update_post($post, true);
    if (is_wp_error($updated) || (int) $updated < 1) {
        $message = is_wp_error($updated) && method_exists($updated, 'get_error_message')
            ? $updated->get_error_message()
            : 'WordPress could not finalize the PDF root page.';
        throw new RuntimeException($message);
    }
    $storedBlocks = function_exists('get_post_field')
        ? (string) get_post_field('post_content', $postId, 'raw')
        : $blocks;
    plpc_import_assert_content_fingerprint($expected, $storedBlocks);
    $status = plpc_import_job_post_status($postId);
    if ($status !== '' && $status !== 'draft') {
        throw new PlpcImportFailure(
            'publication_draft_status_mismatch',
            'WordPress did not preserve the private PDF root draft status.',
            true,
            'preparing_publication'
        );
    }
}

/** @param list<array<string, mixed>> $children */
function plpc_pdf_page_tree_index_blocks(array $children): string
{
    $items = '';
    foreach ($children as $child) {
        $pageNumber = max(1, (int) ($child['pageNumber'] ?? 1));
        $items .= '<li><a href="' . esc_url((string) ($child['pageUrl'] ?? '')) . '">Page '
            . $pageNumber . '</a></li>';
    }

    return '<!-- wp:list {"ordered":true} -->' . "\n"
        . '<ol class="wp-block-list">' . $items . '</ol>' . "\n"
        . '<!-- /wp:list -->';
}

/** @param array<string, mixed> $job @param array<string, mixed> $document */
function plpc_import_job_finalize_single_pdf_output(
    array &$job,
    int $documentIndex,
    array $document,
    string $pdfBytes,
    ?float $deadline = null
): void {
    $segments = is_array($document['pdfSegments'] ?? null) ? array_values($document['pdfSegments']) : [];
    if ($segments === []) {
        throw new RuntimeException('The PDF has no durable publication segments.');
    }
    $records = [];
    $assembledBytes = 0;
    foreach ($segments as $segment) {
        $record = is_array($segment['publicationBundle'] ?? null) ? $segment['publicationBundle'] : null;
        if (!is_array($record)) {
            throw new RuntimeException('A PDF segment is not ready for single-page assembly.');
        }
        if ((int) ($record['blockBytes'] ?? 0) < 1) {
            continue;
        }
        if ($records !== []) {
            $assembledBytes += 2;
        }
        $assembledBytes += max(0, (int) ($record['blockBytes'] ?? 0));
        $records[] = $record;
    }
    $limit = plpc_pdf_single_page_limit_bytes();
    $job['singlePageLimitBytes'] = $limit;
    $job['assembledBytes'] = $assembledBytes;
    $document['pdfAssembledBytes'] = $assembledBytes;
    $job['documents'][$documentIndex] = $document;
    if ($assembledBytes > $limit) {
        $message = 'The converted PDF needs ' . number_format($assembledBytes)
            . ' bytes, above this server\'s safe ' . number_format($limit)
            . '-byte limit for one WordPress page. The completed conversion is preserved.';
        $job['status'] = 'awaiting_output_mode';
        $job['stage'] = 'awaiting_output_mode';
        $job['error'] = $message;
        $job['failure'] = [
            'code' => 'pdf_single_page_too_large',
            'stage' => 'assembling_pdf',
            'recoverable' => true,
            'actualBytes' => $assembledBytes,
            'allowedBytes' => $limit,
        ];
        $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
        $job['progress'] = [
            'completed' => max(1, (int) ($progress['completed'] ?? 1)),
            'total' => max(1, (int) ($progress['total'] ?? 1)),
            'label' => 'The conversion is safe, but it is too large for one page. Choose one child page per PDF page to continue.',
        ];
        plpc_import_job_add_event($job, 'awaiting_output_mode', 'Single-page assembly reached the safe server limit; no partial page was created.');

        return;
    }
    if ($records === []) {
        throw new PlpcImportFailure('semantic_output_empty', 'The converted PDF did not contain publishable text or media.', false, 'assembling_pdf');
    }

    plpc_import_job_checkpoint_for_deadline($job, $deadline, $documentIndex, 'assembling_pdf', 'Assembling one WordPress page.');
    $blocks = [];
    $diagnostics = [];
    $imageTagCount = 0;
    $imagesImported = 0;
    $mediaDispositions = [];
    foreach ($records as $record) {
        $bundle = plpc_import_job_load_pdf_publication_bundle($job, $record);
        $blocks[] = $bundle['blocks'];
        $diagnostics = array_merge($diagnostics, $bundle['diagnostics']);
        $imageTagCount += $bundle['imageTagCount'];
        $imagesImported += $bundle['imagesImported'];
        $mediaDispositions[] = is_array($bundle['mediaDisposition'] ?? null) ? $bundle['mediaDisposition'] : [];
    }
    $blockMarkup = implode("\n\n", $blocks);
    $diagnostics = array_values(array_unique($diagnostics));
    $title = count($job['documents'] ?? []) === 1
        ? (string) ($job['title'] ?? '')
        : plpc_title_from_filename((string) ($document['path'] ?? 'document.pdf'));
    $materialized = [
        'blocks' => $blockMarkup,
        'diagnostics' => $diagnostics,
        'quality' => plpc_import_quality_report('pdf', $diagnostics, $imageTagCount, $imagesImported),
        'imageTagCount' => $imageTagCount,
        'imagesImported' => $imagesImported,
        'mediaDisposition' => plpc_import_add_pdf_visual_dispositions(
            plpc_import_aggregate_media_dispositions($mediaDispositions),
            $job,
            (string) ($document['path'] ?? '')
        ),
        'format' => 'pdf',
    ];
    $result = plpc_import_job_finalize_pdf_document(
        $job,
        $documentIndex,
        $document,
        $pdfBytes,
        null,
        $title,
        null,
        null,
        null,
        null,
        [],
        'single',
        $materialized
    );
    $result['kind'] = 'single';
    $result['postCount'] = 1;
    $result['pageCount'] = max(1, (int) ($document['pdfPageCount'] ?? 1));
    $result['documentIndex'] = $documentIndex;
    plpc_import_job_append_publication_result($job, $result);
    $document['pdfSingleResult'] = $result;
    plpc_import_job_clear_document_checkpoint($job, $documentIndex);
    plpc_import_job_complete_document($job, $documentIndex, $document, $result);
}

/** @param array<string, mixed> $job @param array<string, mixed> $document */
function plpc_import_job_finalize_pdf_page_tree(
    array &$job,
    int $documentIndex,
    array $document,
    ?float $deadline = null
): void {
    $segments = is_array($document['pdfSegments'] ?? null) ? array_values($document['pdfSegments']) : [];
    $children = [];
    foreach ($segments as $segment) {
        if (!is_array($segment['result'] ?? null)) {
            throw new RuntimeException('A PDF child page is not ready for the root index.');
        }
        $children[] = $segment['result'];
    }
    usort($children, static fn (array $left, array $right): int => ((int) ($left['pageNumber'] ?? 0)) <=> ((int) ($right['pageNumber'] ?? 0)));
    $rootPostId = max(1, (int) ($document['pdfRootPostId'] ?? plpc_import_job_get_or_create_pdf_root($job, $documentIndex, $document)));
    plpc_import_job_checkpoint_for_deadline($job, $deadline, $documentIndex, 'finalizing_root', 'Finalizing the PDF page index.');
    $title = count($job['documents'] ?? []) === 1
        ? (string) ($job['title'] ?? '')
        : plpc_title_from_filename((string) ($document['path'] ?? 'document.pdf'));
    $diagnostics = [];
    $imageTagCount = 0;
    $imagesImported = 0;
    $mediaDispositions = [];
    foreach ($children as $child) {
        $diagnostics = array_merge($diagnostics, is_array($child['diagnostics'] ?? null) ? $child['diagnostics'] : []);
        $imageTagCount += max(0, (int) ($child['imageTagCount'] ?? 0));
        $imagesImported += max(0, (int) ($child['imagesImported'] ?? 0));
        $mediaDispositions[] = is_array($child['mediaDisposition'] ?? null) ? $child['mediaDisposition'] : [];
    }
    $diagnostics = array_values(array_unique(array_map('strval', $diagnostics)));
    $storedResult = [
        'kind' => 'pdf-page-tree',
        'format' => 'pdf',
        'path' => (string) ($document['path'] ?? 'document.pdf'),
        'imageTagCount' => $imageTagCount,
        'imagesImported' => $imagesImported,
        'mediaDisposition' => plpc_import_add_pdf_visual_dispositions(
            plpc_import_aggregate_media_dispositions($mediaDispositions),
            $job,
            (string) ($document['path'] ?? '')
        ),
        'diagnostics' => $diagnostics,
        'quality' => plpc_import_quality_report('pdf', $diagnostics, $imageTagCount, $imagesImported),
        'postCount' => count($children) + 1,
        'pageCount' => count($children),
    ];
    plpc_import_update_verified_page($rootPostId, [
        'post_type' => 'page',
        'post_title' => $title,
        'post_content' => plpc_pdf_page_tree_index_blocks($children),
        'meta_input' => [
            '_plpc_import_job_id' => (string) ($job['id'] ?? ''),
            '_plpc_import_document_index' => $documentIndex,
            '_plpc_import_pdf_role' => 'root',
            '_plpc_import_result' => $storedResult,
        ],
    ]);
    $result = $storedResult + [
        'postId' => $rootPostId,
        'pageUrl' => get_permalink($rootPostId),
        'editUrl' => get_edit_post_link($rootPostId, 'raw'),
        'title' => get_the_title($rootPostId),
        'children' => $children,
        'posts' => $children,
        'documentIndex' => $documentIndex,
    ];
    plpc_import_job_append_publication_result($job, $result);
    $document['pdfTreeResult'] = $result;
    plpc_import_job_clear_document_checkpoint($job, $documentIndex);
    plpc_import_job_complete_document($job, $documentIndex, $document, $result);
}

/** @param array<string, mixed> $job */
function plpc_import_job_reset_pdf_publication(array &$job): void
{
    $pdfIndexes = [];
    $postIds = [];
    foreach ($job['documents'] ?? [] as $index => $document) {
        if (!is_array($document) || (int) ($document['pdfPageCount'] ?? 0) < 1) {
            continue;
        }
        $pdfIndexes[(int) $index] = true;
        if ((int) ($document['pdfRootPostId'] ?? 0) > 0) {
            $postIds[] = (int) $document['pdfRootPostId'];
        }
        unset(
            $document['pdfSegments'],
            $document['pdfNextSegment'],
            $document['pdfDocumentFacts'],
            $document['pdfFinalBundle'],
            $document['pdfRootPostId'],
            $document['pdfSingleResult'],
            $document['pdfTreeResult'],
            $document['pdfAssembledBytes'],
            $document['result'],
            $document['completed']
        );
        $job['documents'][$index] = $document;
    }
    if ($pdfIndexes === []) {
        throw new RuntimeException('This import does not contain a reusable PDF.');
    }
    $keptResults = [];
    foreach (is_array($job['results'] ?? null) ? $job['results'] : [] as $result) {
        if (!is_array($result)) {
            continue;
        }
        if (isset($pdfIndexes[(int) ($result['documentIndex'] ?? -1)])) {
            if ((int) ($result['postId'] ?? 0) > 0) {
                $postIds[] = (int) $result['postId'];
            }
            continue;
        }
        $keptResults[] = $result;
    }
    foreach (array_unique($postIds) as $postId) {
        if ($postId > 0 && function_exists('wp_delete_post')) {
            wp_delete_post($postId, true);
        }
    }
    $job['results'] = $keptResults;
    $documentResults = is_array($job['documentResults'] ?? null) ? $job['documentResults'] : [];
    foreach (array_keys($pdfIndexes) as $index) {
        unset($documentResults[$index]);
    }
    $job['documentResults'] = $documentResults;
    $job['nextDocument'] = min(array_keys($pdfIndexes));
    $job['result'] = null;
    unset($job['publicationGroups'], $job['publicationRecovery'], $job['publishNextResult']);
}

/**
 * Locate each PDF page-tree publication group. Children are required to
 * precede their root so the root is the commit point that makes a complete
 * hierarchy discoverable.
 *
 * @param list<array<string, mixed>> $results
 * @return list<array{documentIndex:int,start:int,root:int,rootPostId:int,childCount:int}>
 */
function plpc_import_job_page_tree_publication_groups(array $results): array
{
    $byDocument = [];
    foreach ($results as $index => $result) {
        if (!is_array($result)) {
            continue;
        }
        $kind = (string) ($result['kind'] ?? '');
        if (!in_array($kind, ['pdf-page', 'pdf-page-tree'], true)) {
            continue;
        }
        $documentIndex = (int) ($result['documentIndex'] ?? -1);
        if ($documentIndex < 0) {
            throw new PlpcImportFailure(
                'publication_topology_invalid',
                'A PDF page-tree draft is missing its durable document identity.',
                true,
                'preparing_publication'
            );
        }
        $byDocument[$documentIndex] ??= ['children' => [], 'roots' => []];
        $byDocument[$documentIndex][$kind === 'pdf-page' ? 'children' : 'roots'][] = (int) $index;
    }

    $groups = [];
    foreach ($byDocument as $documentIndex => $positions) {
        $children = array_values(array_map('intval', $positions['children'] ?? []));
        $roots = array_values(array_map('intval', $positions['roots'] ?? []));
        if ($children === [] || count($roots) !== 1) {
            throw new PlpcImportFailure(
                'publication_topology_invalid',
                'A PDF page tree must have child drafts and exactly one root draft.',
                true,
                'preparing_publication'
            );
        }
        $root = $roots[0];
        $start = min($children);
        if (max($children) >= $root) {
            throw new PlpcImportFailure(
                'publication_topology_invalid',
                'The PDF root must be the final publication transition for its child pages.',
                true,
                'preparing_publication'
            );
        }
        $rootPostId = max(0, (int) ($results[$root]['postId'] ?? 0));
        if ($rootPostId < 1) {
            throw new PlpcImportFailure(
                'publication_result_missing',
                'The verified PDF root draft is no longer available.',
                true,
                'preparing_publication'
            );
        }
        foreach ($children as $childIndex) {
            $childPostId = max(0, (int) ($results[$childIndex]['postId'] ?? 0));
            if ($childPostId < 1) {
                throw new PlpcImportFailure(
                    'publication_result_missing',
                    'A verified PDF child draft is no longer available.',
                    true,
                    'preparing_publication'
                );
            }
            if (function_exists('get_post_field')) {
                $parent = (int) get_post_field('post_parent', $childPostId, 'raw');
                if ($parent !== $rootPostId) {
                    throw new PlpcImportFailure(
                        'publication_topology_invalid',
                        'A PDF child draft is no longer attached to its verified root draft.',
                        true,
                        'preparing_publication'
                    );
                }
            }
        }
        $groups[] = [
            'documentIndex' => (int) $documentIndex,
            'start' => $start,
            'root' => $root,
            'rootPostId' => $rootPostId,
            'childCount' => count($children),
        ];
    }
    usort($groups, static fn (array $left, array $right): int => $left['start'] <=> $right['start']);

    return $groups;
}

/**
 * @param list<array<string, mixed>> $results
 * @return array{documentIndex:int,start:int,root:int,rootPostId:int,childCount:int}|null
 */
function plpc_import_job_page_tree_group_for_result(array $results, int $index): ?array
{
    foreach (plpc_import_job_page_tree_publication_groups($results) as $group) {
        if ($index >= $group['start'] && $index <= $group['root']) {
            $candidate = $results[$index] ?? null;
            if (is_array($candidate)
                && (int) ($candidate['documentIndex'] ?? -1) === $group['documentIndex']
                && in_array((string) ($candidate['kind'] ?? ''), ['pdf-page', 'pdf-page-tree'], true)
            ) {
                return $group;
            }
        }
    }

    return null;
}

function plpc_import_job_post_status(int $postId): string
{
    if (function_exists('get_post_status')) {
        $status = get_post_status($postId);

        return is_string($status) ? $status : '';
    }
    if (function_exists('get_post_field')) {
        return (string) get_post_field('post_status', $postId, 'raw');
    }

    return '';
}

/**
 * Return every already-public member of a failed tree transition to draft.
 * The cursor moves back to the first child, so retrying is idempotent and
 * never inserts another post. A partial rollback stays explicit and is
 * completed before publication is allowed to resume.
 *
 * @param list<array<string, mixed>> $results
 * @param array{documentIndex:int,start:int,root:int,rootPostId:int,childCount:int} $group
 * @return array<string, mixed>
 */
function plpc_import_job_rollback_page_tree_publication(
    array &$job,
    array $results,
    array $group,
    string $trigger
): array {
    $failedPostIds = [];
    $drafted = 0;
    for ($index = $group['start']; $index <= $group['root']; $index++) {
        $result = $results[$index] ?? null;
        if (!is_array($result)
            || (int) ($result['documentIndex'] ?? -1) !== $group['documentIndex']
            || !in_array((string) ($result['kind'] ?? ''), ['pdf-page', 'pdf-page-tree'], true)
        ) {
            continue;
        }
        $postId = max(0, (int) ($result['postId'] ?? 0));
        if ($postId < 1 || plpc_import_job_post_status($postId) === 'draft') {
            continue;
        }
        $updated = wp_update_post(['ID' => $postId, 'post_status' => 'draft'], true);
        if (is_wp_error($updated) || (int) $updated < 1
            || (($status = plpc_import_job_post_status($postId)) !== '' && $status !== 'draft')
        ) {
            $failedPostIds[] = $postId;
            continue;
        }
        $drafted++;
    }
    $job['publishNextResult'] = $group['start'];
    $recovery = [
        'documentIndex' => $group['documentIndex'],
        'cursorResetTo' => $group['start'],
        'rootPostId' => $group['rootPostId'],
        'trigger' => $trigger,
        'status' => $failedPostIds === [] ? 'rolled_back' : 'rollback_incomplete',
        'draftedPosts' => $drafted,
        'failedPostIds' => $failedPostIds,
    ];
    $job['publicationRecovery'] = $recovery;
    plpc_import_job_add_event(
        $job,
        'publication_recovery',
        $failedPostIds === []
            ? 'A failed PDF hierarchy transition was rolled back to drafts; the same post IDs are ready to retry.'
            : 'A failed PDF hierarchy transition needs another rollback attempt before publication can resume.'
    );

    return $recovery;
}

/** @param array<string, mixed> $job */
function plpc_import_job_complete_publication_recovery(array &$job): void
{
    $recovery = is_array($job['publicationRecovery'] ?? null) ? $job['publicationRecovery'] : [];
    if (($recovery['status'] ?? '') !== 'rollback_incomplete') {
        return;
    }
    $results = is_array($job['results'] ?? null) ? array_values($job['results']) : [];
    $documentIndex = (int) ($recovery['documentIndex'] ?? -1);
    $group = null;
    foreach (plpc_import_job_page_tree_publication_groups($results) as $candidate) {
        if ($candidate['documentIndex'] === $documentIndex) {
            $group = $candidate;
            break;
        }
    }
    if ($group === null) {
        throw new PlpcImportFailure(
            'publication_recovery_missing',
            'The PDF hierarchy recovery cursor is no longer available.',
            true,
            'publishing_pdf_tree'
        );
    }
    $recovery = plpc_import_job_rollback_page_tree_publication($job, $results, $group, 'resume');
    if (($recovery['status'] ?? '') !== 'rolled_back') {
        throw new PlpcImportFailure(
            'publication_rollback_failed',
            'WordPress could not return every partially published PDF page to draft. No new publication was attempted.',
            true,
            'publishing_pdf_tree'
        );
    }
}

/**
 * Begin a separate, replay-safe publication phase. Conversion is already
 * complete here: every PDF result is a verified private draft backed by its
 * durable bundle, so an interrupted publish request cannot erase an hour of
 * parsing work.
 *
 * @param array<string, mixed> $job
 */
function plpc_import_job_begin_publication(array &$job): void
{
    $results = is_array($job['results'] ?? null) ? array_values($job['results']) : [];
    if ($results === []) {
        throw new PlpcImportFailure(
            'semantic_output_empty',
            'The import completed without creating a page.',
            false,
            'verifying_conversion'
        );
    }
    $job['results'] = $results;
    $job['publicationGroups'] = plpc_import_job_page_tree_publication_groups($results);
    $job['publishNextResult'] = max(0, min(count($results), (int) ($job['publishNextResult'] ?? 0)));
    $total = plpc_import_job_progress_total($job);
    plpc_import_job_set_progress(
        $job,
        'ready_to_publish',
        max(0, $total - 1),
        $total,
        'All converted pages are verified privately. Ready to publish page '
            . min(count($results), $job['publishNextResult'] + 1) . ' of ' . count($results) . '.'
    );
    plpc_import_job_add_event(
        $job,
        'ready_to_publish',
        'Conversion is safe. Publishing verified pages in resumable requests.'
    );
}

/** @param array<string, mixed> $job */
function plpc_import_job_publish_next_result(array &$job): void
{
    $results = is_array($job['results'] ?? null) ? array_values($job['results']) : [];
    if ($results === []) {
        throw new PlpcImportFailure(
            'publication_result_missing',
            'The verified publication result is no longer available.',
            true,
            'publishing'
        );
    }
    $index = max(0, (int) ($job['publishNextResult'] ?? 0));
    if ($index >= count($results)) {
        plpc_import_job_finish($job);

        return;
    }
    $result = is_array($results[$index] ?? null) ? $results[$index] : [];
    $postId = max(0, (int) ($result['postId'] ?? 0));
    if ($postId < 1) {
        throw new PlpcImportFailure(
            'publication_result_missing',
            'One verified page draft is no longer available.',
            true,
            'publishing'
        );
    }

    $treeGroup = plpc_import_job_page_tree_group_for_result($results, $index);
    $isTreeRoot = $treeGroup !== null && $index === $treeGroup['root'];
    if ($isTreeRoot) {
        for ($childIndex = $treeGroup['start']; $childIndex < $treeGroup['root']; $childIndex++) {
            $child = $results[$childIndex] ?? null;
            if (!is_array($child)
                || (int) ($child['documentIndex'] ?? -1) !== $treeGroup['documentIndex']
                || (string) ($child['kind'] ?? '') !== 'pdf-page'
            ) {
                continue;
            }
            $childPostId = max(0, (int) ($child['postId'] ?? 0));
            if ($childPostId < 1 || plpc_import_job_post_status($childPostId) !== 'publish') {
                plpc_import_job_rollback_page_tree_publication(
                    $job,
                    $results,
                    $treeGroup,
                    'root_before_children'
                );
                throw new PlpcImportFailure(
                    'publication_tree_incomplete',
                    'The PDF root stayed private because one or more child pages were not yet published. The hierarchy was reset to drafts for retry.',
                    true,
                    'publishing_pdf_root'
                );
            }
        }
    }

    // Verify both sides of the status transition. A retry may reach this
    // method after WordPress committed the update but before the job cursor
    // was saved; publishing the same ID again is intentionally idempotent.
    try {
        plpc_import_verify_stored_page($postId);
        $updated = wp_update_post(['ID' => $postId, 'post_status' => 'publish'], true);
        if (is_wp_error($updated) || (int) $updated < 1) {
            $message = is_wp_error($updated) && method_exists($updated, 'get_error_message')
                ? $updated->get_error_message()
                : 'WordPress could not publish the verified page draft.';
            throw new PlpcImportFailure(
                'publication_update_failed',
                $message,
                true,
                $isTreeRoot ? 'publishing_pdf_root' : ($treeGroup !== null ? 'publishing_pdf_child' : 'publishing')
            );
        }
        $publishedStatus = plpc_import_job_post_status($postId);
        if ($publishedStatus !== '' && $publishedStatus !== 'publish') {
            throw new PlpcImportFailure(
                'publication_status_mismatch',
                'WordPress acknowledged the page update but did not make the verified draft public.',
                true,
                $isTreeRoot ? 'publishing_pdf_root' : ($treeGroup !== null ? 'publishing_pdf_child' : 'publishing')
            );
        }
        plpc_import_verify_stored_page($postId);
    } catch (Throwable $error) {
        if ($treeGroup !== null) {
            plpc_import_job_rollback_page_tree_publication(
                $job,
                $results,
                $treeGroup,
                $isTreeRoot ? 'root_transition_failed' : 'child_transition_failed'
            );
        }
        if ($error instanceof PlpcImportFailure) {
            throw $error;
        }
        throw new PlpcImportFailure(
            'publication_update_failed',
            $error->getMessage(),
            true,
            $isTreeRoot ? 'publishing_pdf_root' : ($treeGroup !== null ? 'publishing_pdf_child' : 'publishing')
        );
    }
    $result['pageUrl'] = get_permalink($postId);
    $result['editUrl'] = get_edit_post_link($postId, 'raw');
    $result['title'] = get_the_title($postId);
    $results[$index] = $result;
    $job['results'] = $results;
    $job['publishNextResult'] = $index + 1;
    if ($isTreeRoot && is_array($job['publicationRecovery'] ?? null)) {
        $job['publicationRecovery']['status'] = 'tree_committed';
        $job['publicationRecovery']['rootPostId'] = $postId;
    }

    if ($job['publishNextResult'] < count($results)) {
        $total = plpc_import_job_progress_total($job);
        plpc_import_job_set_progress(
            $job,
            'ready_to_publish',
            max(0, $total - 1),
            $total,
            $treeGroup !== null && !$isTreeRoot
                ? 'Staged PDF child ' . ($index - $treeGroup['start'] + 1) . ' of ' . $treeGroup['childCount']
                    . '. The hierarchy remains hidden until its root is published.'
                : 'Published verified page ' . ($index + 1) . ' of ' . count($results)
                    . '. Ready to publish the next page.'
        );
        plpc_import_job_add_event(
            $job,
            'publishing',
            $treeGroup !== null && !$isTreeRoot
                ? 'Staged one verified PDF child; its root remains a draft until the complete hierarchy is ready.'
                : 'Published verified page ' . ($index + 1) . ' of ' . count($results) . '.'
        );

        return;
    }

    plpc_import_job_finish($job);
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_finish(array &$job): void
{
    $results = array_values(array_map(
        static fn (array $result): array => plpc_import_job_refresh_result($result),
        array_filter(is_array($job['results'] ?? null) ? $job['results'] : [], 'is_array')
    ));
    $job['results'] = $results;
    if ($results === []) {
        throw new RuntimeException('The import completed without creating a page.');
    }
    $documentResults = array_values(array_map(
        static fn (array $result): array => plpc_import_job_refresh_result($result),
        array_filter(is_array($job['documentResults'] ?? null) ? $job['documentResults'] : [], 'is_array')
    ));
    if ((int) ($job['version'] ?? 0) >= 3
        && ($job['sourceKind'] ?? 'single') === 'single'
        && count($documentResults) === 1
    ) {
        $job['result'] = $documentResults[0];
    } elseif (count($results) === 1 && ((int) ($job['version'] ?? 0) < 3 || $documentResults === [])) {
        // Compatibility for a version-2 job resumed after this upgrade.
        $job['result'] = $results[0];
    } else {
        $diagnostics = [];
        $imageTagCount = 0;
        $imagesImported = 0;
        $summaryResults = $documentResults !== [] ? $documentResults : $results;
        foreach ($summaryResults as $result) {
            if (!is_array($result)) {
                continue;
            }
            $imageTagCount += (int) ($result['imageTagCount'] ?? 0);
            $imagesImported += (int) ($result['imagesImported'] ?? 0);
            foreach ($result['diagnostics'] ?? [] as $diagnostic) {
                $diagnostics[] = (string) ($result['path'] ?? 'document') . ':' . (string) $diagnostic;
            }
        }
        $indexItems = $documentResults !== [] ? $documentResults : $results;
        $indexTitle = (string) ($job['title'] ?? $job['sourceLabel'] ?? 'Imported documents');
        $indexPostId = 0;
        $jobId = (string) ($job['id'] ?? '');
        if ($jobId !== '' && function_exists('get_posts')) {
            $existingIndexIds = get_posts([
                'post_type' => 'page',
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => [
                    ['key' => '_plpc_import_job_id', 'value' => $jobId],
                    ['key' => '_plpc_import_index', 'value' => '1'],
                ],
            ]);
            $indexPostId = max(0, (int) ($existingIndexIds[0] ?? 0));
        }
        if ($indexPostId < 1) {
            $indexPostId = plpc_insert_verified_page([
                'post_type' => 'page',
                'post_title' => $indexTitle,
                'post_content' => plpc_collection_index_blocks($indexTitle, $indexItems, $diagnostics),
                'meta_input' => [
                    '_plpc_import_job_id' => $jobId,
                    '_plpc_import_index' => 1,
                ],
            ]);
        }
        plpc_import_verify_stored_page((int) $indexPostId);
        $publishedIndex = wp_update_post(['ID' => (int) $indexPostId, 'post_status' => 'publish'], true);
        if (is_wp_error($publishedIndex) || (int) $publishedIndex < 1) {
            $message = is_wp_error($publishedIndex) && method_exists($publishedIndex, 'get_error_message')
                ? $publishedIndex->get_error_message()
                : 'WordPress could not publish the verified import index.';
            throw new PlpcImportFailure('publication_update_failed', $message, true, 'publishing_index');
        }
        plpc_import_verify_stored_page((int) $indexPostId);
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
            'documents' => $indexItems,
        ];
    }
    $total = plpc_import_job_progress_total($job);
    plpc_import_job_set_progress(
        $job,
        'complete',
        $total,
        $total,
        count($results) > 1
            ? 'Import complete. Your linked WordPress pages and index are ready.'
            : 'Import complete. Your WordPress page is ready.'
    );
    plpc_import_job_add_event($job, 'complete', 'Created the WordPress page' . (count($results) > 1 ? 's and collection index.' : '.'));
}

/** @param array<string, mixed> $result @return array<string, mixed> */
function plpc_import_job_refresh_result(array $result): array
{
    $postId = max(0, (int) ($result['postId'] ?? 0));
    if ($postId > 0) {
        $result['pageUrl'] = get_permalink($postId);
        $result['editUrl'] = get_edit_post_link($postId, 'raw');
        $result['title'] = get_the_title($postId);
    }
    foreach (['children', 'posts'] as $key) {
        if (!is_array($result[$key] ?? null)) {
            continue;
        }
        $result[$key] = array_values(array_map(
            static fn (array $child): array => plpc_import_job_refresh_result($child),
            array_filter($result[$key], 'is_array')
        ));
    }

    return $result;
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_fail(array &$job, string $message, ?array $failure = null): void
{
    $activeStage = (string) ($job['stage'] ?? 'converting');
    $activeStatus = (string) ($job['status'] ?? 'converting');
    $recoverable = is_array($failure) && (bool) ($failure['recoverable'] ?? false);
    $failureCode = is_array($failure) ? (string) ($failure['code'] ?? 'conversion_failed') : 'conversion_failed';
    $retryCounts = is_array($job['retryCounts'] ?? null) ? $job['retryCounts'] : [];
    $retryCount = $recoverable ? max(0, (int) ($retryCounts[$failureCode] ?? 0)) + 1 : 0;
    if ($recoverable) {
        $retryCounts[$failureCode] = $retryCount;
        $job['retryCounts'] = array_slice($retryCounts, -12, null, true);
    }
    if ($recoverable && $retryCount <= PLPC_IMPORT_JOB_MAX_RECOVERABLE_FAILURE_RETRIES) {
        $job['status'] = 'retryable_failure';
        $job['stage'] = 'retryable_failure';
    } else {
        $job['status'] = 'failed';
        $job['stage'] = 'failed';
        $recoverable = false;
    }
    $job['error'] = $message;
    if (is_array($failure)) {
        $job['failure'] = [
            'code' => $failureCode,
            'stage' => (string) ($failure['stage'] ?? 'converting'),
            'recoverable' => $recoverable,
            'retryCount' => $retryCount,
            'resumeStatus' => $activeStatus === 'ready_to_publish'
                || str_starts_with((string) ($failure['stage'] ?? ''), 'publishing')
                ? 'ready_to_publish'
                : 'ready_to_convert',
        ];
    } else {
        $job['failure'] = [
            'code' => 'conversion_failed',
            'stage' => $activeStage,
            'recoverable' => false,
        ];
    }
    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    $job['progress'] = [
        'completed' => max(0, (int) ($progress['completed'] ?? 0)),
        'total' => max(1, (int) ($progress['total'] ?? 1)),
        'label' => $recoverable
            ? 'A recoverable import step failed. Your saved progress is intact; resume to retry it.'
            : 'Import stopped: ' . $message,
    ];
    plpc_import_job_add_event(
        $job,
        $recoverable ? 'retryable_failure' : 'failed',
        $recoverable ? 'A recoverable step failed; the durable cursor and conversion bundles were preserved.' : 'Import stopped: ' . $message
    );
}

/** @param array<string, mixed> $job */
function plpc_import_job_resume_retryable_failure(array &$job): void
{
    $failure = is_array($job['failure'] ?? null) ? $job['failure'] : [];
    if (($job['status'] ?? '') !== 'retryable_failure' || !($failure['recoverable'] ?? false)) {
        throw new RuntimeException('This import does not have a recoverable failure to resume.');
    }
    $resumeStatus = ($failure['resumeStatus'] ?? '') === 'ready_to_publish'
        ? 'ready_to_publish'
        : 'ready_to_convert';
    if ($resumeStatus === 'ready_to_publish') {
        plpc_import_job_complete_publication_recovery($job);
    }
    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    plpc_import_job_set_progress(
        $job,
        $resumeStatus,
        max(0, (int) ($progress['completed'] ?? 0)),
        max(1, (int) ($progress['total'] ?? 1)),
        $resumeStatus === 'ready_to_publish'
            ? 'The verified publication cursor is ready to retry.'
            : 'The durable conversion cursor is ready to retry.'
    );
    unset($job['error'], $job['failure']);
    plpc_import_job_add_event(
        $job,
        'resuming',
        $resumeStatus === 'ready_to_publish' && is_array($job['publicationRecovery'] ?? null)
            ? 'The PDF hierarchy is back in drafts. Resuming root-last publication with the same post IDs.'
            : 'Resuming from the last durable cursor after a recoverable failure.'
    );
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
        'pdfBrowserFacts' => is_array($metadata['pdfBrowserFacts'] ?? null) ? $metadata['pdfBrowserFacts'] : [],
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
    $job['stateRevision'] = max(0, (int) ($job['stateRevision'] ?? 0)) + 1;
    $persisted = plpc_import_job_compact_persisted_state($job);
    unset($persisted['stateDigest']);
    $persisted['stateDigest'] = plpc_import_job_state_digest($persisted);
    $option = PLPC_IMPORT_JOB_OPTION_PREFIX . (string) $job['id'];
    update_option($option, $persisted, false);

    // update_option() returning false is ambiguous: WordPress uses false for
    // both an unchanged value and a failed write. A revision-bearing readback
    // is the only reliable checkpoint acknowledgement before the client is
    // told it can safely start the next request.
    $stored = get_option($option, null);
    if (!is_array($stored)
        || (string) ($stored['id'] ?? '') !== (string) ($job['id'] ?? '')
        || (int) ($stored['stateRevision'] ?? -1) !== (int) $job['stateRevision']
        || !plpc_import_job_state_digest_is_valid($stored)
    ) {
        throw new PlpcImportFailure(
            'job_state_commit_failed',
            'WordPress could not verify the saved import checkpoint. No additional conversion or publication work was started.',
            true,
            'saving_checkpoint'
        );
    }

    plpc_import_job_update_index($job);
    plpc_import_job_remove_stale_state_blobs($job, $stored);
}

/** @param array<string, mixed> $state */
function plpc_import_job_state_digest(array $state): string
{
    unset($state['stateDigest']);

    return hash('sha256', serialize($state));
}

/** @param array<string, mixed> $state */
function plpc_import_job_state_digest_is_valid(array $state): bool
{
    $expected = (string) ($state['stateDigest'] ?? '');

    return preg_match('/\A[a-f0-9]{64}\z/', $expected) === 1
        && hash_equals($expected, plpc_import_job_state_digest($state));
}

/**
 * Keep bulky, already-file-backed state out of the WordPress option. The
 * option remains the small transactional cursor; content-addressed JSON
 * blobs hold descriptor lists and result trees. Older/small jobs keep their
 * inline shape, and the loader transparently hydrates either representation.
 *
 * @param array<string, mixed> $job
 * @return array<string, mixed>
 */
function plpc_import_job_compact_persisted_state(array $job): array
{
    $jobId = (string) ($job['id'] ?? '');
    $persisted = $job;
    if (($persisted['status'] ?? '') === 'complete') {
        $persisted['publicationSummary'] = [
            'published' => count(is_array($persisted['results'] ?? null) ? $persisted['results'] : []),
            'documents' => count(is_array($persisted['documentResults'] ?? null) ? $persisted['documentResults'] : []),
        ];
        unset($persisted['results'], $persisted['documentResults'], $persisted['publishNextResult']);
    }
    $externalize = static function (mixed &$value, string $label, bool $force = false) use ($jobId): void {
        if (plpc_import_job_state_blob_descriptor($value) !== null) {
            return;
        }
        $json = plpc_json_encode_durable($value, JSON_UNESCAPED_SLASHES);
        if (!$force && strlen($json) < PLPC_IMPORT_JOB_STATE_BLOB_MIN_BYTES) {
            return;
        }
        $sha256 = hash('sha256', $json);
        $safeLabel = preg_replace('/[^a-z0-9-]+/i', '-', trim($label)) ?? 'state';
        $safeLabel = trim($safeLabel, '-');
        if ($safeLabel === '') {
            $safeLabel = 'state';
        }
        $storage = 'state/' . substr($safeLabel, 0, 48) . '-' . $sha256 . '.json';
        plpc_import_job_write_file(plpc_import_job_directory($jobId), $storage, $json);
        $value = [
            '__plpcStateBlob' => $storage,
            'sha256' => $sha256,
            'bytes' => strlen($json),
        ];
    };

    foreach ($persisted['documents'] ?? [] as $index => &$document) {
        if (!is_array($document)) {
            continue;
        }
        foreach (['pdfChunks', 'pdfChunkMetrics', 'pdfSegments', 'result'] as $field) {
            if (array_key_exists($field, $document)) {
                $externalize($document[$field], 'document-' . $index . '-' . $field);
            }
        }
    }
    unset($document);
    foreach (['results', 'documentResults', 'result', 'renderedForms'] as $field) {
        if (array_key_exists($field, $persisted)) {
            $externalize($persisted[$field], $field);
        }
    }

    // Many tiny page descriptors can together exceed the bound even though
    // none is individually large. Externalize the largest aggregate shapes
    // only when needed, preserving convenient inline state for small jobs.
    if (strlen(serialize($persisted)) > PLPC_IMPORT_JOB_MAX_OPTION_BYTES) {
        foreach (['documents', 'results', 'documentResults', 'result', 'renderedForms', 'browserFacts', 'pdfRasters'] as $field) {
            if (!array_key_exists($field, $persisted)) {
                continue;
            }
            $externalize($persisted[$field], $field, true);
            if (strlen(serialize($persisted)) <= PLPC_IMPORT_JOB_MAX_OPTION_BYTES) {
                break;
            }
        }
    }
    if (strlen(serialize($persisted)) > PLPC_IMPORT_JOB_MAX_OPTION_BYTES) {
        throw new PlpcImportFailure(
            'job_state_too_large',
            'The compact import cursor exceeded its safe storage limit. The source and completed bundles remain in private job storage.',
            true,
            'saving_checkpoint'
        );
    }

    return $persisted;
}

/** @return array{__plpcStateBlob:string,sha256:string,bytes:int}|null */
function plpc_import_job_state_blob_descriptor(mixed $value): ?array
{
    if (!is_array($value)
        || count($value) !== 3
        || !is_string($value['__plpcStateBlob'] ?? null)
        || preg_match('/\Astate\/[A-Za-z0-9._-]+\.json\z/', $value['__plpcStateBlob']) !== 1
        || preg_match('/\A[a-f0-9]{64}\z/', (string) ($value['sha256'] ?? '')) !== 1
        || (int) ($value['bytes'] ?? 0) < 1
    ) {
        return null;
    }

    return [
        '__plpcStateBlob' => $value['__plpcStateBlob'],
        'sha256' => (string) $value['sha256'],
        'bytes' => (int) $value['bytes'],
    ];
}

/** @param array<string, mixed> $job */
function plpc_import_job_hydrate_persisted_state(array $job): array
{
    $hydrate = static function (mixed $value) use (&$hydrate, $job): mixed {
        $descriptor = plpc_import_job_state_blob_descriptor($value);
        if ($descriptor !== null) {
            $json = plpc_import_job_read_file($job, $descriptor['__plpcStateBlob']);
            if (strlen($json) !== $descriptor['bytes']
                || !hash_equals($descriptor['sha256'], hash('sha256', $json))) {
                throw new PlpcImportFailure(
                    'job_state_blob_corrupt',
                    'A saved import-state record failed its integrity check.',
                    true,
                    'loading_checkpoint'
                );
            }
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $hydrate($decoded);
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $hydrate($item);
        }

        return $value;
    };

    $hydrated = $hydrate($job);

    return is_array($hydrated) ? $hydrated : $job;
}

/** @param array<string, mixed> $job @param array<string, mixed> $persisted */
function plpc_import_job_remove_stale_state_blobs(array $job, array $persisted): void
{
    $kept = [];
    $collect = static function (mixed $value) use (&$collect, &$kept, $job): void {
        $descriptor = plpc_import_job_state_blob_descriptor($value);
        if ($descriptor !== null) {
            $kept[$descriptor['__plpcStateBlob']] = true;

            // An aggregate blob can itself contain descriptors that were
            // externalized first. Keep that transitive closure so compacting
            // the whole documents array never deletes a child blob required
            // during recursive hydration.
            try {
                $json = plpc_import_job_read_file($job, $descriptor['__plpcStateBlob']);
                if (strlen($json) === $descriptor['bytes']
                    && hash_equals($descriptor['sha256'], hash('sha256', $json))) {
                    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    $collect($decoded);
                }
            } catch (Throwable) {
                // The just-verified option remains authoritative. A corrupt
                // state blob is reported when the job is loaded; cleanup must
                // not make that state harder to diagnose.
            }

            return;
        }
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $item) {
            $collect($item);
        }
    };
    $collect($persisted);
    $stateDirectory = plpc_import_job_directory($job) . DIRECTORY_SEPARATOR . 'state';
    if (!is_dir($stateDirectory)) {
        return;
    }
    foreach (glob($stateDirectory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
        $relative = 'state/' . basename($path);
        if (!isset($kept[$relative]) && is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }
}

/** @param array<string, mixed> $job */
function plpc_import_job_update_index(array $job): void
{
    $jobId = (string) ($job['id'] ?? '');
    if ($jobId === '') {
        return;
    }
    $lock = plpc_import_job_acquire_index_lock();
    try {
        $index = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);
        $index = is_array($index) ? $index : [];
        $storageSecurity = is_array($job['storageSecurity'] ?? null) ? $job['storageSecurity'] : [];
        $entry = [
            'updatedAt' => max(0, (int) ($job['updatedAt'] ?? time())),
            'status' => (string) ($job['status'] ?? 'queued'),
            'ownerId' => max(0, (int) ($job['ownerId'] ?? 0)),
            'storageMode' => (string) ($storageSecurity['mode'] ?? 'legacy-uploads'),
        ];
        $index[$jobId] = $entry;

        // This action is also a deterministic concurrency seam for hosts and
        // tests. A cooperating writer attempting a nested mutation cannot
        // pass the held file lock and must retry from a fresh index snapshot.
        if (function_exists('do_action')) {
            do_action('plpc_import_job_index_locked', $jobId, $entry);
        }
        update_option(PLPC_IMPORT_JOB_INDEX_OPTION, $index, false);
        $stored = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);
        if (!is_array($stored) || ($stored[$jobId] ?? null) !== $entry) {
            throw new PlpcImportFailure(
                'job_index_commit_failed',
                'WordPress could not verify the import-job index update. The job checkpoint remains saved and can be retried.',
                true,
                'saving_job_index'
            );
        }
    } finally {
        plpc_import_job_release_lock($lock);
    }
}

/**
 * Serialize the one global job-index read/modify/write cycle. Per-job locks
 * cannot protect two different imports from overwriting each other's index
 * entries, so the index has its own short, bounded advisory lock.
 *
 * @return resource
 */
function plpc_import_job_acquire_index_lock()
{
    $selection = plpc_import_job_select_storage_root();
    $root = $selection['path'];
    if (!is_dir($root) && !@wp_mkdir_p($root)) {
        throw new PlpcImportFailure(
            'job_index_lock_unavailable',
            'WordPress could not prepare the import-job index lock.',
            true,
            'saving_job_index'
        );
    }
    $path = $root . DIRECTORY_SEPARATOR . '.job-index.lock';
    $handle = @fopen($path, 'c');
    if (!is_resource($handle)) {
        throw new PlpcImportFailure(
            'job_index_lock_unavailable',
            'WordPress could not open the import-job index lock.',
            true,
            'saving_job_index'
        );
    }
    @chmod($path, 0600);
    $timeoutMs = PLPC_IMPORT_JOB_INDEX_LOCK_TIMEOUT_MS;
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_import_job_index_lock_timeout_ms', $timeoutMs);
        if (is_numeric($filtered)) {
            $timeoutMs = max(1, min(5000, (int) $filtered));
        }
    }
    $deadline = microtime(true) + ($timeoutMs / 1000);
    do {
        if (@flock($handle, LOCK_EX | LOCK_NB)) {
            return $handle;
        }
        usleep(5000);
    } while (microtime(true) < $deadline);
    @fclose($handle);

    throw new PlpcImportFailure(
        'job_index_lock_timeout',
        'Another import is updating the job index. This checkpoint can be retried without losing work.',
        true,
        'saving_job_index'
    );
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
    if (isset($job['stateDigest']) && !plpc_import_job_state_digest_is_valid($job)) {
        throw new PlpcImportFailure(
            'job_state_corrupt',
            'The saved import checkpoint failed its integrity check.',
            true,
            'loading_checkpoint'
        );
    }
    $job = plpc_import_job_hydrate_persisted_state($job);
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
    $storageSecurity = is_array($job) && is_array($job['storageSecurity'] ?? null)
        ? $job['storageSecurity']
        : [];
    $mode = (string) ($storageSecurity['mode'] ?? '');
    if ($mode !== '') {
        $selection = plpc_import_job_select_storage_root($mode);

        return $selection['path'] . DIRECTORY_SEPARATOR . $jobId;
    }

    // Compatibility for jobs created before storageSecurity was persisted.
    // Prefer whichever deterministic directory already contains that job so
    // an upgrade never strands a resumable import in its old uploads path.
    $private = plpc_import_job_private_storage_root_candidate();
    if (is_dir($private . DIRECTORY_SEPARATOR . $jobId)) {
        return $private . DIRECTORY_SEPARATOR . $jobId;
    }
    try {
        $legacy = plpc_import_job_upload_storage_root();
        if (is_dir($legacy . DIRECTORY_SEPARATOR . $jobId)) {
            return $legacy . DIRECTORY_SEPARATOR . $jobId;
        }
    } catch (Throwable) {
        // The selected private root below can still serve a new/source-only
        // import even while the site's public media directory is unavailable.
    }

    $selection = plpc_import_job_select_storage_root();

    return $selection['path'] . DIRECTORY_SEPARATOR . $jobId;
}

function plpc_import_job_private_storage_root_candidate(): string
{
    $temporary = function_exists('get_temp_dir') ? (string) get_temp_dir() : sys_get_temp_dir();
    if ($temporary === '') {
        $temporary = sys_get_temp_dir();
    }
    $siteIdentity = (defined('ABSPATH') ? (string) ABSPATH : __DIR__)
        . '|' . (function_exists('site_url') ? (string) site_url('/') : 'wordpress');
    $default = rtrim($temporary, "/\\") . DIRECTORY_SEPARATOR . PLPC_IMPORT_JOB_DIRECTORY
        . '-' . substr(hash('sha256', $siteIdentity), 0, 16);
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_import_job_private_storage_root', $default);
        if (is_string($filtered) && trim($filtered) !== '') {
            $default = trim($filtered);
        }
    }

    return rtrim($default, "/\\");
}

function plpc_import_job_upload_storage_root(): string
{
    $uploads = wp_upload_dir();
    if (!is_array($uploads) || !empty($uploads['error']) || !is_string($uploads['basedir'] ?? null) || $uploads['basedir'] === '') {
        throw new RuntimeException('WordPress could not prepare upload storage for this import.');
    }

    return rtrim($uploads['basedir'], "/\\") . DIRECTORY_SEPARATOR . PLPC_IMPORT_JOB_DIRECTORY;
}

function plpc_import_job_path_is_within(string $path, string $root): bool
{
    $normalize = static function (string $candidate): string {
        $candidate = str_replace('\\', '/', trim($candidate));
        $prefix = str_starts_with($candidate, '/') ? '/' : '';
        $parts = [];
        foreach (explode('/', $candidate) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return $prefix . implode('/', $parts);
    };
    $path = rtrim($normalize($path), '/');
    $root = rtrim($normalize($root), '/');
    if ($path === '' || $root === '') {
        return false;
    }

    return $path === $root || str_starts_with($path . '/', $root . '/');
}

/**
 * Return the web-server family whose per-directory access-control files can
 * be relied on by this request. Nginx deliberately remains distinct: it does
 * not consume either .htaccess or web.config. An absent or unfamiliar server
 * signature is never treated as Apache merely because PHP can write a file
 * named .htaccess.
 */
function plpc_import_job_web_server_family(): string
{
    $software = strtolower(trim((string) ($_SERVER['SERVER_SOFTWARE'] ?? '')));
    if ($software === '') {
        return 'unknown';
    }
    if (str_contains($software, 'nginx')) {
        return 'nginx';
    }
    if (str_contains($software, 'microsoft-iis') || preg_match('/(?:^|[\s\/])iis(?:[\s\/]|$)/', $software) === 1) {
        return 'iis';
    }
    if (str_contains($software, 'apache') || str_contains($software, 'litespeed')) {
        return 'apache';
    }

    return 'unknown';
}

/** @return array{path:string,mode:string,fallback:bool,outsidePublicUploads:bool,accessProtection:string,serverFamily:string} */
function plpc_import_job_select_storage_root(?string $requiredMode = null): array
{
    $privateRoot = plpc_import_job_private_storage_root_candidate();
    $uploadRoot = null;
    try {
        $uploadRoot = plpc_import_job_upload_storage_root();
    } catch (Throwable) {
        // A private source-only import can still start when public uploads are
        // temporarily unavailable. Media publication will report its own
        // upload failure later if the document actually needs attachments.
    }
    $webRoots = is_string($uploadRoot) ? [dirname($uploadRoot)] : [];
    if (defined('ABSPATH')) {
        $webRoots[] = (string) ABSPATH;
    }
    if (is_string($_SERVER['DOCUMENT_ROOT'] ?? null) && trim((string) $_SERVER['DOCUMENT_ROOT']) !== '') {
        $webRoots[] = (string) $_SERVER['DOCUMENT_ROOT'];
    }
    $privateAllowed = !is_link($privateRoot);
    foreach (array_unique($webRoots) as $webRoot) {
        $realPrivate = realpath($privateRoot);
        $realWebRoot = realpath($webRoot);
        if (plpc_import_job_path_is_within($privateRoot, $webRoot)
            || (is_string($realPrivate) && is_string($realWebRoot)
                && plpc_import_job_path_is_within($realPrivate, $realWebRoot))
        ) {
            $privateAllowed = false;
            break;
        }
    }
    if (($requiredMode === null || $requiredMode === 'private') && $privateAllowed) {
        $created = is_dir($privateRoot) || @wp_mkdir_p($privateRoot);
        if ($created && is_dir($privateRoot) && is_writable($privateRoot)) {
            @chmod($privateRoot, 0700);
            plpc_import_job_harden_storage_root($privateRoot);

            return [
                'path' => $privateRoot,
                'mode' => 'private',
                'fallback' => false,
                'outsidePublicUploads' => true,
                'accessProtection' => 'filesystem-private',
                'serverFamily' => plpc_import_job_web_server_family(),
            ];
        }
    }
    if ($requiredMode === 'private') {
        throw new RuntimeException('The private import storage used by this job is unavailable. Restore its server directory and resume the import.');
    }
    if ($requiredMode !== null && !in_array($requiredMode, ['uploads-fallback', 'legacy-uploads'], true)) {
        throw new RuntimeException('The import job storage mode is invalid.');
    }
    if (!is_string($uploadRoot) || $uploadRoot === '') {
        throw new RuntimeException('WordPress could not prepare upload storage for the protected import fallback.');
    }
    $serverFamily = plpc_import_job_web_server_family();
    $requiredRule = match ($serverFamily) {
        'apache' => '.htaccess',
        'iis' => 'web.config',
        default => null,
    };
    if ($requiredRule === null) {
        $serverLabel = $serverFamily === 'nginx' ? 'Nginx' : 'an unknown web server';
        throw new RuntimeException(
            'Private import storage is unavailable, and the uploads fallback was refused because '
                . $serverLabel . ' does not enforce the plugin\'s .htaccess or web.config deny files. '
                . 'Configure a writable private storage directory outside the web root. No source file was saved in public uploads.'
        );
    }
    if (!is_dir($uploadRoot) && !@wp_mkdir_p($uploadRoot)) {
        throw new RuntimeException('WordPress could not create fallback import storage.');
    }
    if (!plpc_import_job_harden_storage_root($uploadRoot, $requiredRule)) {
        throw new RuntimeException('WordPress could not install access controls for fallback import storage. No source file was saved there.');
    }

    return [
        'path' => $uploadRoot,
        'mode' => 'uploads-fallback',
        'fallback' => true,
        'outsidePublicUploads' => false,
        'accessProtection' => $serverFamily === 'apache' ? 'apache-htaccess-deny' : 'iis-web-config-deny',
        'serverFamily' => $serverFamily,
    ];
}

/** @param array<string, mixed>|null $storageSecurity */
function plpc_import_job_create_directory(string $jobId, ?array &$storageSecurity = null): string
{
    if (preg_match('/\A[A-Za-z0-9_-]{12,128}\z/', $jobId) !== 1) {
        throw new RuntimeException('The import job storage key is invalid.');
    }
    $selection = plpc_import_job_select_storage_root();
    $storageSecurity = [
        'mode' => $selection['mode'],
        'fallback' => $selection['fallback'],
        'outsidePublicUploads' => $selection['outsidePublicUploads'],
        'accessProtection' => $selection['accessProtection'],
        'serverFamily' => $selection['serverFamily'],
    ];
    $directory = $selection['path'] . DIRECTORY_SEPARATOR . $jobId;
    if (!wp_mkdir_p($directory)) {
        throw new RuntimeException('WordPress could not create temporary storage for this import.');
    }
    @chmod($directory, 0700);

    return $directory;
}

/** Schedule a low-cost daily sweep; creation/save also maintains the index. */
function plpc_import_job_schedule_cleanup(): void
{
    if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
        return;
    }
    if (wp_next_scheduled('plpc_cleanup_import_jobs') === false) {
        wp_schedule_event(time() + 300, 'daily', 'plpc_cleanup_import_jobs');
    }
}

/**
 * Remove expired job options and their private files without scanning every
 * WordPress option or uploads directory. Active and recoverable jobs receive
 * the longest retention window; completed jobs are short-lived because the
 * WordPress posts and attachments are already durable.
 *
 * @return array{removed:int,skippedLocked:int,kept:int,indexLocked?:bool}
 */
function plpc_cleanup_import_jobs(?int $now = null): array
{
    $now ??= time();
    $retention = [
        'complete' => PLPC_IMPORT_JOB_RETENTION_COMPLETE_SECONDS,
        'failed' => PLPC_IMPORT_JOB_RETENTION_FAILED_SECONDS,
        'active' => PLPC_IMPORT_JOB_RETENTION_ACTIVE_SECONDS,
    ];
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_import_job_retention_seconds', $retention);
        if (is_array($filtered)) {
            foreach ($retention as $key => $seconds) {
                if (isset($filtered[$key]) && is_numeric($filtered[$key])) {
                    $retention[$key] = max(3600, (int) $filtered[$key]);
                }
            }
        }
    }
    try {
        $indexLock = plpc_import_job_acquire_index_lock();
    } catch (PlpcImportFailure) {
        $current = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);

        return [
            'removed' => 0,
            'skippedLocked' => 0,
            'kept' => is_array($current) ? count($current) : 0,
            'indexLocked' => true,
        ];
    }
    $index = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);
    $index = is_array($index) ? $index : [];
    $removed = 0;
    $skippedLocked = 0;
    $kept = 0;
    try {
        foreach ($index as $jobId => $entry) {
            if (!is_string($jobId) || preg_match('/\A[A-Za-z0-9_-]{12,128}\z/', $jobId) !== 1 || !is_array($entry)) {
                unset($index[$jobId]);
                continue;
            }
            $status = (string) ($entry['status'] ?? 'queued');
            $class = $status === 'complete' ? 'complete' : ($status === 'failed' ? 'failed' : 'active');
            $updatedAt = max(0, (int) ($entry['updatedAt'] ?? 0));
            if ($updatedAt > 0 && $updatedAt + $retention[$class] > $now) {
                $kept++;
                continue;
            }

            $storageMode = (string) ($entry['storageMode'] ?? '');
            $locator = $storageMode === ''
                ? $jobId
                : ['id' => $jobId, 'storageSecurity' => ['mode' => $storageMode]];
            $directory = plpc_import_job_directory($locator);
            $lock = null;
            if (is_dir($directory)) {
                $lockPath = $directory . DIRECTORY_SEPARATOR . '.import.lock';
                $lock = @fopen($lockPath, 'c');
                if (!is_resource($lock) || !@flock($lock, LOCK_EX | LOCK_NB)) {
                    if (is_resource($lock)) {
                        @fclose($lock);
                    }
                    $skippedLocked++;
                    continue;
                }
            }

            try {
                if (is_dir($directory)) {
                    plpc_import_job_remove_directory_contents($directory, ['.import.lock']);
                }
            } finally {
                if (is_resource($lock)) {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
                }
                if (isset($directory) && is_file($directory . DIRECTORY_SEPARATOR . '.import.lock')) {
                    @unlink($directory . DIRECTORY_SEPARATOR . '.import.lock');
                }
                if (isset($directory) && is_dir($directory)) {
                    @rmdir($directory);
                }
            }
            if (is_dir($directory)) {
                // Keep the index and option so a later sweep can retry files
                // that were temporarily undeletable; otherwise private
                // storage would become an untracked leak.
                $kept++;
                continue;
            }
            delete_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
            unset($index[$jobId]);
            $removed++;
        }
        update_option(PLPC_IMPORT_JOB_INDEX_OPTION, $index, false);
    } finally {
        plpc_import_job_release_lock($indexLock);
    }

    return ['removed' => $removed, 'skippedLocked' => $skippedLocked, 'kept' => $kept];
}

/** @param list<string> $preserveNames */
function plpc_import_job_remove_directory_contents(string $directory, array $preserveNames = []): void
{
    if (!is_dir($directory) || is_link($directory)) {
        return;
    }
    $preserve = array_fill_keys($preserveNames, true);
    $entries = @scandir($directory);
    if (!is_array($entries)) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || isset($preserve[$entry])) {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            continue;
        }
        if (is_dir($path)) {
            plpc_import_job_remove_directory_contents($path);
            @rmdir($path);
        }
    }
}

/**
 * Add defense-in-depth server rules to both private storage and the uploads
 * fallback. The private default is already outside uploads. For an uploads
 * fallback, the caller names the one deny file that its detected server can
 * consume; unrelated defense-in-depth files do not make that check succeed.
 */
function plpc_import_job_harden_storage_root(string $storageRoot, ?string $requiredRule = null): bool
{
    $files = [
        'index.php' => "<?php\n// Silence is golden.\n",
        '.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
    ];
    $complete = true;
    foreach ($files as $filename => $contents) {
        $path = $storageRoot . DIRECTORY_SEPARATOR . $filename;
        $existing = is_file($path) ? @file_get_contents($path) : false;
        if (is_string($existing) && hash_equals($contents, $existing)) {
            continue;
        }
        $written = @file_put_contents($path, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            if ($requiredRule === null || $filename === $requiredRule) {
                $complete = false;
            }
            continue;
        }
        // These are access-control directives rather than source documents;
        // let the web server read them even when PHP-FPM uses another user.
        @chmod($path, 0644);
    }

    return $complete;
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
        'ready_to_publish' => 'ready_to_publish',
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

    $contracted = plpc_import_job_contract_pdf_range($job, $documentIndex, 'deadline');
    if ($contracted !== null) {
        plpc_import_job_save($job);

        throw new PlpcImportCheckpointYield('The import yielded after saving a smaller PDF extraction range.');
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
 * instead of an invisible endless replay of the same semantic work unit.
 *
 * @param array<string, mixed> $job
 */
function plpc_import_job_recover_interrupted_document(array &$job): bool
{
    $documentIndex = max(0, (int) ($job['nextDocument'] ?? 0));
    if (plpc_import_job_contract_pdf_range($job, $documentIndex, 'interruption') !== null) {
        return true;
    }
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

/**
 * Contract the exact unfinished physical-page range after a deadline or hard
 * worker interruption. The caller persists this decision before retrying.
 * Once one page is reached, the ordinary bounded retry cap takes over so a
 * pathological page cannot loop forever.
 *
 * @param array<string, mixed> $job
 * @return array{startPage:int,previousPages:int,nextPages:int}|null
 */
function plpc_import_job_contract_pdf_range(array &$job, int $documentIndex, string $reason): ?array
{
    $document = $job['documents'][$documentIndex] ?? null;
    if (!is_array($document)) {
        return null;
    }
    $pageCount = max(0, (int) ($document['pdfPageCount'] ?? 0));
    $startPage = max(1, (int) ($document['pdfNextPage'] ?? 1));
    $currentPages = max(1, (int) ($document['pdfPagesPerRequest'] ?? ($pageCount > 0 ? plpc_pdf_pages_per_request($pageCount) : 1)));
    if ($pageCount < 1 || $startPage > $pageCount || $currentPages <= 1) {
        return null;
    }
    $nextPages = max(1, (int) floor($currentPages / 2));
    $document['pdfPagesPerRequest'] = $nextPages;
    $job['documents'][$documentIndex] = $document;
    $job['checkpoint'] = [
        'documentIndex' => $documentIndex,
        'stage' => 'reading',
        'label' => 'Retrying PDF page ' . $startPage . ' with a smaller extraction range.',
        'rangeStartPage' => $startPage,
        'rangePreviousPages' => $currentPages,
        'rangePages' => $nextPages,
        'rangeContracted' => true,
        'deadlineYields' => 0,
        'interruptedRetries' => 0,
        'updatedAt' => time(),
    ];
    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    plpc_import_job_set_progress(
        $job,
        'ready_to_convert',
        max(0, (int) ($progress['completed'] ?? 0)),
        max(1, (int) ($progress['total'] ?? 1)),
        'The previous PDF range was too expensive. Retrying page ' . $startPage
            . ' with ' . $nextPages . ' page' . ($nextPages === 1 ? '' : 's') . ' per request.'
    );
    plpc_import_job_add_event(
        $job,
        'range_contracted',
        'Reduced the unfinished PDF extraction range from ' . $currentPages . ' to ' . $nextPages
            . ' page' . ($nextPages === 1 ? '' : 's') . ' after a server ' . $reason . '.'
    );

    return ['startPage' => $startPage, 'previousPages' => $currentPages, 'nextPages' => $nextPages];
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
    $units = 1;
    foreach ($job['documents'] ?? [] as $document) {
        if (!is_array($document)) {
            continue;
        }
        $pdfPageCount = max(0, (int) ($document['pdfPageCount'] ?? 0));
        $segmentCount = is_array($document['pdfSegments'] ?? null)
            ? max(1, count($document['pdfSegments']))
            : (plpc_import_job_pdf_output_mode($job) === 'pages' ? max(1, $pdfPageCount) : 1);
        $units += $pdfPageCount > 0 ? $pdfPageCount + (3 * $segmentCount) : 6;
    }

    return max(6, $units);
}

function plpc_pdf_pages_per_request(int $pageCount): int
{
    // The object graph and document diagnostics are global work. Eight pages
    // amortize that work without approaching the 128 MiB profile on the
    // dense visual corpus; durable storage still writes one record per page.
    $pages = 8;
    if (function_exists('apply_filters')) {
        $pages = (int) apply_filters('plpc_pdf_pages_per_request', $pages, $pageCount);
    }

    return max(1, min(25, $pages));
}

/**
 * Choose the next extraction range from measured work, not file names or
 * document-specific exceptions. Large/dense chunks contract; sparse chunks
 * grow up to sixteen pages, with no jump greater than 2x per request.
 *
 * @param array<string, mixed> $metric
 */
function plpc_pdf_adaptive_pages_per_request(int $currentPages, array $metric, int $remainingPages): int
{
    if ($remainingPages < 1) {
        return 1;
    }
    $currentPages = max(1, min(16, $currentPages));
    $measuredPages = max(1, (int) ($metric['pages'] ?? $currentPages));
    $factsBytes = max(1, (int) ($metric['factsBytes'] ?? 1));
    $durationMs = max(1, (int) ($metric['durationMs'] ?? 1));
    $bytesPerPage = $factsBytes / $measuredPages;

    // Six MiB of durable facts leaves ample headroom for the source, parser
    // graph, JSON serialization, and WordPress in a conventional 128 MiB
    // worker. Aim for a bounded request around twelve seconds as well.
    $byFacts = (int) floor(6_291_456 / max(1.0, $bytesPerPage));
    $byTime = (int) floor($measuredPages * (12_000 / $durationMs));
    $suggested = max(1, min(16, $byFacts, $byTime));

    $memoryLimit = plpc_php_ini_bytes(function_exists('ini_get') ? ini_get('memory_limit') : false);
    $peakBytes = max(0, (int) ($metric['peakBytes'] ?? 0));
    if ($memoryLimit > 0 && $peakBytes > (int) floor($memoryLimit * 0.72)) {
        $suggested = min($suggested, max(1, (int) floor($currentPages / 2)));
    }

    $suggested = max((int) ceil($currentPages / 2), min($currentPages * 2, $suggested));
    if (function_exists('apply_filters')) {
        $suggested = (int) apply_filters(
            'plpc_pdf_adaptive_pages_per_request',
            $suggested,
            $currentPages,
            $metric,
            $remainingPages
        );
    }

    return max(1, min(16, $remainingPages, $suggested));
}

function plpc_php_ini_bytes(mixed $value): int
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return 0;
    }
    $raw = trim((string) $value);
    if ($raw === '' || $raw === '-1') {
        return 0;
    }
    if (preg_match('/\A(\d+(?:\.\d+)?)\s*([KMG]?)\z/i', $raw, $match) !== 1) {
        return 0;
    }
    $multiplier = match (strtoupper($match[2])) {
        'G' => 1_073_741_824,
        'M' => 1_048_576,
        'K' => 1_024,
        default => 1,
    };

    return max(0, (int) floor((float) $match[1] * $multiplier));
}

/**
 * Bound one post_content value conservatively before WordPress or MySQL has
 * to copy it. longtext is much larger than this, but request memory and the
 * server packet ceiling are the practical limits on ordinary installations.
 */
function plpc_pdf_single_page_limit_bytes(): int
{
    $limits = [PLPC_PDF_SINGLE_PAGE_HARD_LIMIT_BYTES];
    $memoryBytes = plpc_php_ini_bytes(function_exists('ini_get') ? ini_get('memory_limit') : false);
    if ($memoryBytes > 0) {
        $limits[] = max(1, (int) floor($memoryBytes * 0.08));
    }

    global $wpdb;
    if (is_object($wpdb) && method_exists($wpdb, 'get_var')) {
        try {
            $packet = $wpdb->get_var('SELECT @@max_allowed_packet');
            if (is_numeric($packet) && (int) $packet > 0) {
                $limits[] = max(1, (int) floor((int) $packet * 0.5));
            }
        } catch (Throwable) {
            // An unavailable server variable does not make imports fail; the
            // hard and PHP-memory ceilings still provide a safe bound.
        }
    }

    $limit = min($limits);
    if (function_exists('apply_filters')) {
        // Sites may choose a smaller operational limit, never a larger one.
        $filtered = (int) apply_filters('plpc_pdf_single_page_limit_bytes', $limit);
        if ($filtered > 0) {
            $limit = min($limit, $filtered);
        }
    }

    return max(1, $limit);
}

function plpc_pdf_segment_max_fact_bytes(int $pageCount): int
{
    $bytes = PLPC_IMPORT_JOB_PDF_SEGMENT_MAX_FACT_BYTES;
    if (function_exists('apply_filters')) {
        $bytes = (int) apply_filters('plpc_pdf_segment_max_fact_bytes', $bytes, $pageCount);
    }

    return max(262144, min(33554432, $bytes));
}

function plpc_pdf_segment_max_pages(int $pageCount): int
{
    $pages = PLPC_IMPORT_JOB_PDF_SEGMENT_MAX_PAGES;
    if (function_exists('apply_filters')) {
        $pages = (int) apply_filters('plpc_pdf_segment_max_pages', $pages, $pageCount);
    }

    // A host may lower the bound for an unusually constrained installation,
    // but must not enlarge it and silently defeat the tested memory ceiling.
    return max(1, min(PLPC_IMPORT_JOB_PDF_SEGMENT_MAX_PAGES, $pages));
}

/** @param array<string, mixed> $job */
function plpc_import_job_progress_before_document(array $job, int $documentIndex): int
{
    $completed = 1;
    foreach ($job['documents'] ?? [] as $index => $document) {
        if ((int) $index >= $documentIndex) {
            break;
        }
        if (!is_array($document)) {
            continue;
        }
        $pdfPageCount = max(0, (int) ($document['pdfPageCount'] ?? 0));
        $segmentCount = is_array($document['pdfSegments'] ?? null)
            ? max(1, count($document['pdfSegments']))
            : (plpc_import_job_pdf_output_mode($job) === 'pages' ? max(1, $pdfPageCount) : 1);
        $completed += $pdfPageCount > 0 ? $pdfPageCount + (3 * $segmentCount) : 6;
    }

    return $completed;
}

/**
 * @param array<string, mixed> $job
 */
function plpc_import_job_response(array $job, int $status = 200): WP_REST_Response
{
    $requests = [];
    $allRenderRequests = is_array($job['renderRequests'] ?? null) ? array_values($job['renderRequests']) : [];
    foreach (array_slice($allRenderRequests, 0, PLPC_IMPORT_JOB_STATUS_MAX_RENDER_REQUESTS) as $request) {
        if (!is_array($request)) {
            continue;
        }
        $bbox = plpc_import_job_normalize_bbox($request['bbox'] ?? null);
        if ($bbox === null) {
            continue;
        }
        $requests[] = [
            'id' => (string) ($request['id'] ?? ''),
            // Clients group one PDF.js document at a time. The visible path
            // is length-bounded for status payloads, so use a digest of the
            // full normalized path to prevent two long common-prefix paths
            // from aliasing to the wrong source document.
            'sourceKey' => substr(hash('sha256', (string) ($request['path'] ?? '')), 0, 32),
            'visualId' => substr((string) ($request['visualId'] ?? $request['formId'] ?? ''), 0, 192),
            'visualKind' => substr((string) ($request['visualKind'] ?? 'form-xobject'), 0, 64),
            'path' => substr((string) ($request['path'] ?? ''), 0, 512),
            'page' => max(1, (int) ($request['page'] ?? 1)),
            'bbox' => $bbox,
            'label' => substr((string) ($request['label'] ?? 'PDF figure'), 0, 512),
        ];
    }
    $events = [];
    $allEvents = is_array($job['events'] ?? null) ? array_values($job['events']) : [];
    foreach (array_slice($allEvents, -PLPC_IMPORT_JOB_STATUS_MAX_EVENTS) as $event) {
        if (!is_array($event)) {
            continue;
        }
        $events[] = [
            'stage' => (string) ($event['stage'] ?? 'progress'),
            'message' => substr((string) ($event['message'] ?? 'Import is continuing.'), 0, 768),
            'time' => (int) ($event['time'] ?? 0),
        ];
    }
    $progress = is_array($job['progress'] ?? null) ? $job['progress'] : [];
    $snapshot = [
        'ok' => !in_array($job['status'] ?? '', ['failed', 'retryable_failure'], true),
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
        'output' => [
            'pdfOutputMode' => plpc_import_job_pdf_output_mode($job) === 'legacy_ranges'
                ? 'single'
                : plpc_import_job_pdf_output_mode($job),
            'singlePageLimitBytes' => max(1, (int) ($job['singlePageLimitBytes'] ?? plpc_pdf_single_page_limit_bytes())),
        ],
    ];
    $storageSecurity = is_array($job['storageSecurity'] ?? null) ? $job['storageSecurity'] : [];
    $snapshot['storage'] = [
        'mode' => (string) ($storageSecurity['mode'] ?? 'legacy-uploads'),
        'fallback' => (bool) ($storageSecurity['fallback'] ?? !isset($job['storageSecurity'])),
        'outsidePublicUploads' => (bool) ($storageSecurity['outsidePublicUploads'] ?? false),
        'accessProtection' => (string) ($storageSecurity['accessProtection'] ?? 'legacy-unverified'),
        'serverFamily' => (string) ($storageSecurity['serverFamily'] ?? 'unknown'),
    ];
    if (count($allEvents) > count($events) || count($allRenderRequests) > count($requests)) {
        $snapshot['truncated'] = [
            'eventsOmitted' => max(0, count($allEvents) - count($events)),
            'renderRequestsOmitted' => max(0, count($allRenderRequests) - count($requests)),
        ];
    }
    if (isset($job['assembledBytes'])) {
        $snapshot['output']['assembledBytes'] = max(0, (int) $job['assembledBytes']);
    }
    $pdfPagesTotal = 0;
    $pdfPagesExtracted = 0;
    $pdfChunkCount = 0;
    $pdfLastMetric = null;
    foreach ($job['documents'] ?? [] as $document) {
        if (!is_array($document) || (int) ($document['pdfPageCount'] ?? 0) < 1) {
            continue;
        }
        $pdfPagesTotal += (int) $document['pdfPageCount'];
        $pdfPagesExtracted += min(
            (int) $document['pdfPageCount'],
            max(0, (int) ($document['pdfNextPage'] ?? 1) - 1)
        );
        $documentMetrics = is_array($document['pdfChunkMetrics'] ?? null) ? $document['pdfChunkMetrics'] : [];
        $pdfChunkCount += count($documentMetrics);
        if ($documentMetrics !== []) {
            $candidate = $documentMetrics[count($documentMetrics) - 1];
            if (is_array($candidate)) {
                $pdfLastMetric = $candidate;
            }
        }
    }
    if ($pdfPagesTotal > 0) {
        $snapshot['metrics'] = [
            'pdfPagesExtracted' => $pdfPagesExtracted,
            'pdfPagesTotal' => $pdfPagesTotal,
            'pdfExtractionRequests' => $pdfChunkCount,
            'pdfPageSizedFormsSkipped' => max(0, (int) ($job['pdfPageSizedFormsSkipped'] ?? 0)),
            'pdfPageSizedVisualFormsRendered' => max(0, (int) ($job['pdfPageSizedVisualFormsRendered'] ?? 0)),
            'pdfFormRenderRequestsTruncated' => max(0, (int) ($job['pdfFormRenderRequestsTruncated'] ?? 0)),
        ];
        if (is_array($pdfLastMetric)) {
            $snapshot['metrics']['lastExtraction'] = [
                'pages' => max(0, (int) ($pdfLastMetric['pages'] ?? 0)),
                'durationMs' => max(0, (int) ($pdfLastMetric['durationMs'] ?? 0)),
                'factsBytes' => max(0, (int) ($pdfLastMetric['factsBytes'] ?? 0)),
                'peakBytes' => max(0, (int) ($pdfLastMetric['peakBytes'] ?? 0)),
            ];
        }
    }
    if (is_array($job['result'] ?? null)) {
        $resultTruncated = false;
        $snapshot['result'] = plpc_import_job_status_result($job['result'], $resultTruncated);
        if ($resultTruncated) {
            $snapshot['truncated']['result'] = true;
        }
    }
    if (array_key_exists('publishNextResult', $job)) {
        $snapshot['publication'] = [
            'completed' => max(0, (int) ($job['publishNextResult'] ?? 0)),
            'total' => count(is_array($job['results'] ?? null) ? $job['results'] : []),
            'pdfTreeCommit' => 'root-last',
        ];
        $recovery = is_array($job['publicationRecovery'] ?? null) ? $job['publicationRecovery'] : [];
        if ($recovery !== []) {
            $snapshot['publication']['recovery'] = [
                'status' => (string) ($recovery['status'] ?? 'unknown'),
                'documentIndex' => max(0, (int) ($recovery['documentIndex'] ?? 0)),
                'cursorResetTo' => max(0, (int) ($recovery['cursorResetTo'] ?? 0)),
                'draftedPosts' => max(0, (int) ($recovery['draftedPosts'] ?? 0)),
                'failedPosts' => count(is_array($recovery['failedPostIds'] ?? null) ? $recovery['failedPostIds'] : []),
            ];
        }
    }
    if (in_array($job['status'] ?? '', ['failed', 'retryable_failure', 'awaiting_output_mode'], true)) {
        $snapshot['message'] = substr((string) ($job['error'] ?? (($job['status'] ?? '') === 'awaiting_output_mode'
            ? 'The converted PDF is too large for one safe WordPress page.'
            : 'The import failed.')), 0, 2048);
        $failure = is_array($job['failure'] ?? null) ? $job['failure'] : [];
        $snapshot['failure'] = [
            'code' => (string) ($failure['code'] ?? 'conversion_failed'),
            'stage' => (string) ($failure['stage'] ?? 'converting'),
            'recoverable' => (bool) ($failure['recoverable'] ?? false),
        ];
        foreach (['actualBytes', 'allowedBytes'] as $field) {
            if (isset($failure[$field])) {
                $snapshot['failure'][$field] = max(0, (int) $failure[$field]);
            }
        }
    }

    $encodedSnapshot = plpc_json_encode_durable($snapshot, JSON_UNESCAPED_SLASHES);
    if (strlen($encodedSnapshot) > PLPC_IMPORT_JOB_MAX_STATUS_BYTES) {
        // Preserve the actionable cursor/result identity while dropping old
        // explanatory detail. The full audit state remains private on disk.
        $snapshot['events'] = array_slice($snapshot['events'], -6);
        if (is_array($snapshot['result'] ?? null)) {
            $ignored = false;
            $snapshot['result'] = plpc_import_job_status_result($snapshot['result'], $ignored, 0, 8);
        }
        $snapshot['truncated']['statusBytes'] = strlen($encodedSnapshot);
        if (strlen(plpc_json_encode_durable($snapshot, JSON_UNESCAPED_SLASHES)) > PLPC_IMPORT_JOB_MAX_STATUS_BYTES) {
            $result = is_array($snapshot['result'] ?? null) ? $snapshot['result'] : [];
            $snapshot['result'] = array_intersect_key($result, array_fill_keys([
                'postId', 'pageUrl', 'editUrl', 'title', 'kind', 'format', 'postCount', 'pageCount', 'batch',
            ], true));
            $snapshot['truncated']['result'] = true;
        }
    }

    return new WP_REST_Response($snapshot, $status);
}

/**
 * Return a bounded public result tree. Publication details stay useful while
 * large child/document lists, diagnostics, and occurrence ledgers cannot make
 * every polling response grow with the imported PDF.
 *
 * @param array<string, mixed> $result
 * @return array<string, mixed>
 */
function plpc_import_job_status_result(array $result, bool &$truncated, int $depth = 0, int $itemLimit = PLPC_IMPORT_JOB_STATUS_MAX_RESULT_ITEMS): array
{
    $safe = [];
    foreach ($result as $key => $value) {
        $key = (string) $key;
        if ($key === 'ledger' && is_array($value)) {
            $safe['ledgerCount'] = count($value);
            $truncated = $truncated || $value !== [];
            continue;
        }
        if (is_string($value)) {
            $safe[$key] = strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
            $truncated = $truncated || strlen($value) > 1024;
            continue;
        }
        if (!is_array($value)) {
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
            continue;
        }
        if ($key === 'diagnostics') {
            $safe[$key] = array_values(array_map('strval', array_slice($value, 0, 16)));
            $truncated = $truncated || count($value) > 16;
            continue;
        }
        $isList = array_is_list($value);
        if ($isList) {
            $items = array_slice($value, 0, max(1, $itemLimit));
            $safe[$key] = [];
            foreach ($items as $item) {
                if (is_array($item) && $depth < 4) {
                    $safe[$key][] = plpc_import_job_status_result($item, $truncated, $depth + 1, $itemLimit);
                } elseif (is_scalar($item) || $item === null) {
                    $safe[$key][] = is_string($item) && strlen($item) > 512 ? substr($item, 0, 512) : $item;
                }
            }
            if (count($value) > count($items)) {
                $safe[$key . 'Total'] = count($value);
                $truncated = true;
            }
            continue;
        }
        if ($depth < 4) {
            $safe[$key] = plpc_import_job_status_result($value, $truncated, $depth + 1, $itemLimit);
        } else {
            $safe[$key] = ['itemCount' => count($value)];
            $truncated = true;
        }
    }

    return $safe;
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
    $job['browserFacts'] = plpc_import_job_store_browser_facts(
        $directory,
        is_array($payload['pdfBrowserFacts'] ?? null) ? $payload['pdfBrowserFacts'] : [],
        $sourceFiles
    );
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
    if (($job['browserFacts'] ?? []) !== []) {
        plpc_import_job_add_event(
            $job,
            'browser_facts',
            'Saved optional PDF.js text and structure facts for ' . count($job['browserFacts']) . ' document' . (count($job['browserFacts']) === 1 ? '.' : 's.')
        );
    }
}

/**
 * Store bounded browser observations outside the WordPress options table.
 * Source hashes are verified again by BrowserPdfFactsProvider before use.
 *
 * @param array<string, mixed> $factsByPath
 * @param list<array{path:string,storage:string,size:int}> $sourceFiles
 * @return array<string, array{storage:string,bytes:int,provider:string,sourceSha256:string,pageCount:int,pages:int}>
 */
function plpc_import_job_store_browser_facts(string $directory, array $factsByPath, array $sourceFiles): array
{
    if ($factsByPath === []) {
        return [];
    }
    $sourcePaths = [];
    foreach ($sourceFiles as $source) {
        if (is_array($source)) {
            $sourcePaths[strtolower((string) ($source['path'] ?? ''))] = (string) ($source['path'] ?? '');
        }
    }
    $stored = [];
    $totalBytes = 0;
    foreach ($factsByPath as $path => $facts) {
        $normalizedPath = plpc_normalize_collection_path((string) $path);
        $sourcePath = $sourcePaths[strtolower($normalizedPath)] ?? '';
        if ($sourcePath === '' || !is_array($facts)) {
            continue;
        }
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
            : json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        if (!is_string($encoded) || strlen($encoded) > PLPC_MAX_PDF_BROWSER_FACTS_BYTES) {
            continue;
        }
        $totalBytes += strlen($encoded);
        if ($totalBytes > PLPC_MAX_PDF_BROWSER_FACTS_TOTAL_BYTES) {
            break;
        }
        if (($facts['schemaVersion'] ?? null) !== 1 || ($facts['provider'] ?? null) !== 'pdfjs-v1'
            || !is_string($facts['sourceSha256'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/', $facts['sourceSha256']) !== 1
            || !is_int($facts['pageCount'] ?? null) || $facts['pageCount'] < 1
            || !is_array($facts['pages'] ?? null)
        ) {
            continue;
        }
        $relative = 'facts/' . substr(sha1($sourcePath . "\0" . $facts['sourceSha256']), 0, 28) . '.json';
        plpc_import_job_write_file($directory, $relative, $encoded);
        $stored[$sourcePath] = [
            'storage' => $relative,
            'bytes' => strlen($encoded),
            'provider' => 'pdfjs-v1',
            'sourceSha256' => $facts['sourceSha256'],
            'pageCount' => $facts['pageCount'],
            'pages' => count($facts['pages']),
        ];
    }

    return $stored;
}

/** @param array<string, mixed> $job */
function plpc_import_job_load_browser_facts(array $job, string $path): ?array
{
    $record = $job['browserFacts'][$path] ?? null;
    if (!is_array($record)) {
        return null;
    }
    $storage = (string) ($record['storage'] ?? '');
    $expectedBytes = max(0, (int) ($record['bytes'] ?? 0));
    if ($storage === '' || $expectedBytes < 1 || $expectedBytes > PLPC_MAX_PDF_BROWSER_FACTS_BYTES) {
        return null;
    }
    $encoded = plpc_import_job_read_file($job, $storage);
    if (strlen($encoded) !== $expectedBytes) {
        return null;
    }
    $facts = json_decode($encoded, true);

    return is_array($facts) ? $facts : null;
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
    if (!preg_match('#\A(?:source|expanded|rasters|rendered|chunks|facts|state)/[A-Za-z0-9._-]+\z#', $relative)) {
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
    if (!preg_match('#\A(?:source|expanded|rasters|rendered|chunks|facts|state)/[A-Za-z0-9._-]+\z#', $relative)) {
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
    $totalBytes = 0;
    $totalImages = 0;
    foreach ($rastersByPath as $path => $rasters) {
        if ($totalBytes >= PLPC_MAX_PDF_RASTER_BYTES || $totalImages >= PLPC_MAX_PDF_RASTER_IMAGES) {
            break;
        }
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
            if ($totalImages >= PLPC_MAX_PDF_RASTER_IMAGES
                || $totalBytes + strlen($contents) > PLPC_MAX_PDF_RASTER_BYTES) {
                break;
            }
            $relative = 'rasters/' . substr(sha1((string) $path . '\0' . $object . '\0' . $index), 0, 28) . '.png';
            plpc_import_job_write_file($directory, $relative, $contents);
            $totalBytes += strlen($contents);
            $totalImages++;
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
function plpc_pdf_page_geometry_by_number(array $rows): array
{
    $byPage = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $page = max(0, (int) ($row['page_number'] ?? $row['page'] ?? 0));
        $bbox = $row['bbox'] ?? null;
        if ($page < 1 || !is_array($bbox) || count($bbox) < 4) {
            continue;
        }
        $normalized = plpc_import_job_normalize_bbox([
            'x1' => $bbox['x1'] ?? $bbox[0] ?? null,
            'y1' => $bbox['y1'] ?? $bbox[1] ?? null,
            'x2' => $bbox['x2'] ?? $bbox[2] ?? null,
            'y2' => $bbox['y2'] ?? $bbox[3] ?? null,
        ]);
        if ($normalized !== null) {
            $byPage[$page] = $normalized;
        }
    }

    return $byPage;
}

/**
 * Return true only when the visible intersection of a Form covers almost the
 * whole page. Raw Form boxes can extend beyond CropBox/MediaBox, so comparing
 * their un-clipped area produces ratios above 100% and misses the structural
 * fact that the browser would still rasterize a full page.
 *
 * @param array{x1:float,y1:float,x2:float,y2:float} $formBox
 * @param array{x1:float,y1:float,x2:float,y2:float} $pageBox
 */
function plpc_pdf_form_placement_covers_page(array $formBox, array $pageBox): bool
{
    $pageWidth = max(0.0, $pageBox['x2'] - $pageBox['x1']);
    $pageHeight = max(0.0, $pageBox['y2'] - $pageBox['y1']);
    if ($pageWidth <= 0.0 || $pageHeight <= 0.0) {
        return false;
    }
    $intersectionWidth = max(
        0.0,
        min($formBox['x2'], $pageBox['x2']) - max($formBox['x1'], $pageBox['x1'])
    );
    $intersectionHeight = max(
        0.0,
        min($formBox['y2'], $pageBox['y2']) - max($formBox['y1'], $pageBox['y1'])
    );
    $coverage = ($intersectionWidth * $intersectionHeight) / ($pageWidth * $pageHeight);
    $threshold = PLPC_IMPORT_JOB_PAGE_LIKE_FORM_COVERAGE;
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_pdf_page_like_form_coverage', $threshold, $formBox, $pageBox);
        if (is_numeric($filtered)) {
            $threshold = (float) $filtered;
        }
    }

    return $coverage >= max(0.5, min(0.98, $threshold));
}

/** @param array<string,mixed> $placement */
function plpc_pdf_page_like_form_is_content_rich(array $placement, int $sameObjectOccurrences): bool
{
    if ($sameObjectOccurrences > 1) {
        return false;
    }
    $summary = is_array($placement['visualSummary'] ?? null) ? $placement['visualSummary'] : [];
    if (($summary['complete'] ?? false) !== true) {
        return false;
    }

    return (int) ($summary['textShowOperatorCount'] ?? 0) > 0
        || (int) ($summary['vectorPaintOperatorCount'] ?? 0) >= 2
        || (int) ($summary['rasterXObjectCount'] ?? 0) > 0
        || (int) ($summary['nestedFormXObjectCount'] ?? 0) > 0;
}

function plpc_import_job_max_visual_occurrences(): int
{
    $limit = PLPC_IMPORT_JOB_MAX_VISUAL_OCCURRENCES;
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('plpc_pdf_visual_occurrence_limit', $limit);
        if (is_numeric($filtered)) {
            $limit = (int) $filtered;
        }
    }

    return max(2, min(PLPC_IMPORT_JOB_MAX_VISUAL_OCCURRENCES, $limit));
}

/**
 * Add one machine-readable visual-inspection resource issue without allowing
 * the durable occurrence ledger itself to grow past its abuse bound.
 *
 * @param array<string,mixed> $document
 */
function plpc_import_job_record_visual_resource_limit(
    array &$document,
    string $path,
    int $page,
    int $omittedOccurrences
): void {
    if ($omittedOccurrences < 1) {
        return;
    }
    $id = 'pdf-visual-inventory-limit-' . substr(hash('sha256', $path), 0, 20);
    $inventory = is_array($document['pdfVisualOccurrences'] ?? null)
        ? array_values(array_filter($document['pdfVisualOccurrences'], 'is_array'))
        : [];
    foreach ($inventory as $index => $occurrence) {
        if ((string) ($occurrence['id'] ?? '') !== $id) {
            continue;
        }
        $inventory[$index]['omittedOccurrences'] = max(0, (int) ($occurrence['omittedOccurrences'] ?? 0))
            + $omittedOccurrences;
        $inventory[$index]['page'] = max(1, $page);
        $document['pdfVisualOccurrences'] = $inventory;

        return;
    }
    if (count($inventory) >= plpc_import_job_max_visual_occurrences()) {
        array_pop($inventory);
    }
    $inventory[] = [
        'id' => $id,
        'kind' => 'inspection-issue',
        'page' => max(1, $page),
        'object' => 0,
        'paintOrder' => PHP_INT_MAX,
        'disposition' => 'unresolved',
        'reason' => 'visual-occurrence-limit',
        'issueType' => 'resource-limit',
        'recoverable' => true,
        'omittedOccurrences' => $omittedOccurrences,
    ];
    $document['pdfVisualOccurrences'] = $inventory;
}

/**
 * Checkpoint the visual evidence already present in one bounded page-facts
 * range. No source bytes are parsed here. Replaying an acknowledged range is
 * an idempotent no-op, while a later range appends only new occurrence ids.
 *
 * @param array<string,mixed> $job
 */
function plpc_import_job_checkpoint_pdf_chunk_visuals(
    array &$job,
    int $documentIndex,
    \PortLibs\MarkerPDF\PdfDocumentFacts $facts,
    int $startPage,
    int $endPage,
    int $pageCount
): int {
    $document = $job['documents'][$documentIndex] ?? null;
    if (!is_array($document)) {
        throw new RuntimeException('The PDF visual checkpoint did not match a prepared document.');
    }
    $startPage = max(1, $startPage);
    $endPage = max($startPage, min(max(1, $pageCount), $endPage));
    $completeThrough = max(0, (int) ($document['pdfVisualInspectionCompleteThroughPage'] ?? 0));
    if ($endPage <= $completeThrough) {
        return 0;
    }
    if ($startPage > $completeThrough + 1) {
        throw new PlpcImportFailure(
            'pdf_visual_checkpoint_gap',
            'The saved PDF visual checkpoint has a page gap. The import can retry from its last complete page range.',
            true,
            'inspecting_visuals'
        );
    }

    if (($job['imageMode'] ?? 'important') === 'none') {
        $document['pdfVisualInspectionCompleteThroughPage'] = $endPage;
        $document['pdfVisualInspectionNextPage'] = $endPage + 1;
        $document['pdfVisualInventoryComplete'] = $endPage >= $pageCount;
        $job['documents'][$documentIndex] = $document;

        return 0;
    }

    $inventory = is_array($document['pdfVisualOccurrences'] ?? null)
        ? array_values(array_filter($document['pdfVisualOccurrences'], 'is_array'))
        : [];
    $seenIds = [];
    foreach ($inventory as $occurrence) {
        $id = (string) ($occurrence['id'] ?? '');
        if ($id !== '') {
            $seenIds[$id] = true;
        }
    }
    $visualOccurrenceLimit = plpc_import_job_max_visual_occurrences();
    $normalCapacity = max(0, $visualOccurrenceLimit - 1 - count($inventory));
    $placements = [];
    $pageGeometry = [];
    $omittedOccurrences = 0;
    $formObjectCounts = is_array($document['pdfVisualFormObjectCounts'] ?? null)
        ? $document['pdfVisualFormObjectCounts']
        : [];
    foreach ($facts->pages() as $page) {
        $pageNumber = $page->pageNumber();
        if ($pageNumber <= $completeThrough || $pageNumber < $startPage || $pageNumber > $endPage) {
            continue;
        }
        $geometry = $page->geometry();
        $geometry['page_number'] = $pageNumber;
        $pageGeometry[] = $geometry;
        $graphics = $page->graphics();
        $pageOccurrences = is_array($graphics['visualOccurrences'] ?? null)
            ? $graphics['visualOccurrences']
            : [];
        foreach ($pageOccurrences as $placement) {
            if (!is_array($placement)) {
                continue;
            }
            if ((string) ($placement['kind'] ?? '') === 'inspection-issue'
                && (string) ($placement['dispositionReason'] ?? '') === 'visual-occurrence-limit'
            ) {
                // The native collector reserves its own terminal slot. Fold
                // that exact omitted count into this document-level terminal
                // issue instead of dropping it behind the plugin's cap.
                $omittedOccurrences += max(1, (int) ($placement['omittedOccurrences'] ?? 1));
                continue;
            }
            $nativeId = is_array($placement['provenance'] ?? null)
                ? (string) ($placement['provenance']['nativeId'] ?? '')
                : '';
            if ($nativeId !== '') {
                // Provider facts have their own record id, while renderer and
                // media reconciliation use the extractor's occurrence id.
                // Retain that native identity across independently loaded
                // page chunks so retries cannot create a second figure.
                $placement['id'] = $nativeId;
            }
            $id = (string) ($placement['id'] ?? '');
            if ($id !== '' && isset($seenIds[$id])) {
                continue;
            }
            if (count($placements) >= $normalCapacity) {
                $omittedOccurrences++;
                continue;
            }
            $placements[] = $placement;
            if ($id !== '') {
                $seenIds[$id] = true;
            }
            if ((string) ($placement['kind'] ?? '') === 'form-xobject') {
                $object = max(0, (int) ($placement['object'] ?? 0));
                if ($object > 0) {
                    $key = (string) $object;
                    $formObjectCounts[$key] = max(0, (int) ($formObjectCounts[$key] ?? 0)) + 1;
                }
            }
        }
    }

    $temporary = [
        'imageMode' => (string) ($job['imageMode'] ?? 'important'),
        'documents' => [[
            'format' => 'pdf',
            'path' => (string) ($document['path'] ?? 'document.pdf'),
            'pdfInspectionVisualOccurrences' => $placements,
            'pdfInspectionPageGeometry' => $pageGeometry,
        ]],
    ];
    $classifiedRequests = $placements === []
        ? []
        : plpc_import_job_collect_form_render_requests($temporary);
    $classifiedInventory = is_array($temporary['documents'][0]['pdfVisualOccurrences'] ?? null)
        ? $temporary['documents'][0]['pdfVisualOccurrences']
        : [];
    foreach ($classifiedInventory as $occurrence) {
        if (is_array($occurrence) && count($inventory) < $visualOccurrenceLimit - 1) {
            $inventory[] = $occurrence;
        } else {
            $omittedOccurrences++;
        }
    }
    $document['pdfVisualOccurrences'] = $inventory;
    $document['pdfVisualFormObjectCounts'] = $formObjectCounts;
    $document['pdfVisualInspectionCompleteThroughPage'] = $endPage;
    $document['pdfVisualInspectionNextPage'] = $endPage + 1;
    $document['pdfVisualInventoryComplete'] = $endPage >= $pageCount;

    $renderRequestCount = max(0, (int) ($document['pdfVisualRenderRequestCount'] ?? 0));
    $deferred = is_array($document['pdfDeferredPageLikeVisualRequests'] ?? null)
        ? $document['pdfDeferredPageLikeVisualRequests']
        : [];
    $outstandingIds = [];
    foreach ($job['renderRequests'] ?? [] as $request) {
        if (is_array($request) && is_string($request['id'] ?? null)) {
            $outstandingIds[$request['id']] = true;
        }
    }
    foreach ($job['renderedForms'] ?? [] as $requestId => $rendered) {
        if (is_string($requestId)) {
            $outstandingIds[$requestId] = true;
        }
    }
    foreach ($classifiedRequests as $request) {
        if (!is_array($request)) {
            continue;
        }
        $requestId = (string) ($request['id'] ?? '');
        $visualId = (string) ($request['visualId'] ?? '');
        if ($requestId === '' || isset($outstandingIds[$requestId]) || isset($deferred[$requestId])) {
            continue;
        }
        if ($renderRequestCount >= PLPC_IMPORT_JOB_MAX_FORM_RENDERS) {
            $job['pdfFormRenderRequestsTruncated'] = max(0, (int) ($job['pdfFormRenderRequestsTruncated'] ?? 0)) + 1;
            $job['documents'][$documentIndex] = $document;
            plpc_import_job_mark_pdf_visual_occurrence(
                $job,
                (string) ($document['path'] ?? ''),
                $visualId,
                'unresolved',
                'browser-render-occurrence-limit'
            );
            $document = $job['documents'][$documentIndex];
            continue;
        }
        $renderRequestCount++;
        if (($request['pageLikeVisual'] ?? false) === true) {
            // Repetition is a document-level fact. Delay only page-sized
            // Forms until the last page checkpoint; normal figures are sent
            // to the browser immediately after their bounded source range.
            $deferred[$requestId] = $request;
            continue;
        }
        $job['renderRequests'][] = $request;
        $outstandingIds[$requestId] = true;
    }
    $document['pdfVisualRenderRequestCount'] = $renderRequestCount;
    $document['pdfDeferredPageLikeVisualRequests'] = $deferred;
    $newPageSizedFormsSkipped = max(0, (int) ($temporary['pdfPageSizedFormsSkipped'] ?? 0));
    $job['pdfPageSizedFormsSkipped'] = max(0, (int) ($job['pdfPageSizedFormsSkipped'] ?? 0))
        + $newPageSizedFormsSkipped;
    if ($newPageSizedFormsSkipped > 0) {
        plpc_import_job_add_event(
            $job,
            'renderer',
            'Skipped ' . $newPageSizedFormsSkipped . ' page-sized PDF layout '
                . ($newPageSizedFormsSkipped === 1 ? 'wrapper' : 'wrappers')
                . '; text and ordinary extracted images will still be imported.'
        );
    }

    $visualInventoryLimited = $omittedOccurrences > 0;
    if (!$visualInventoryLimited) {
        foreach ($inventory as $occurrence) {
            if (is_array($occurrence)
                && (string) ($occurrence['kind'] ?? '') === 'inspection-issue'
                && (string) ($occurrence['reason'] ?? '') === 'visual-occurrence-limit'
            ) {
                $visualInventoryLimited = true;
                break;
            }
        }
    }
    if ($endPage >= $pageCount && $deferred !== []) {
        foreach ($deferred as $requestId => $request) {
            if (!is_array($request)) {
                unset($deferred[$requestId]);
                continue;
            }
            $object = max(0, (int) ($request['object'] ?? 0));
            $occurrences = $object > 0 ? max(1, (int) ($formObjectCounts[(string) $object] ?? 1)) : 1;
            if ($visualInventoryLimited) {
                $job['documents'][$documentIndex] = $document;
                plpc_import_job_mark_pdf_visual_occurrence(
                    $job,
                    (string) ($document['path'] ?? ''),
                    (string) ($request['visualId'] ?? ''),
                    'unresolved',
                    'visual-inventory-limit-prevents-page-wrapper-classification'
                );
                $document = $job['documents'][$documentIndex];
            } elseif ($occurrences > 1) {
                $job['documents'][$documentIndex] = $document;
                plpc_import_job_mark_pdf_visual_occurrence(
                    $job,
                    (string) ($document['path'] ?? ''),
                    (string) ($request['visualId'] ?? ''),
                    'intentional_omission',
                    'repeated-or-decorative-page-wrapper'
                );
                $document = $job['documents'][$documentIndex];
                $job['pdfPageSizedFormsSkipped'] = max(0, (int) ($job['pdfPageSizedFormsSkipped'] ?? 0)) + 1;
            } elseif (!isset($outstandingIds[$requestId])) {
                $job['renderRequests'][] = $request;
                $outstandingIds[$requestId] = true;
                $job['pdfPageSizedVisualFormsRendered'] = max(0, (int) ($job['pdfPageSizedVisualFormsRendered'] ?? 0)) + 1;
                plpc_import_job_add_event(
                    $job,
                    'renderer',
                    'Preserving one content-rich full-page PDF visual occurrence in source order.'
                );
            }
            unset($deferred[$requestId]);
        }
        $document['pdfDeferredPageLikeVisualRequests'] = $deferred;
    }
    if ($omittedOccurrences > 0) {
        plpc_import_job_record_visual_resource_limit(
            $document,
            (string) ($document['path'] ?? 'document.pdf'),
            $endPage,
            $omittedOccurrences
        );
        $job['pdfVisualResourceLimitIssues'] = max(0, (int) ($job['pdfVisualResourceLimitIssues'] ?? 0)) + 1;
    }
    $job['documents'][$documentIndex] = $document;

    return count($job['renderRequests'] ?? []);
}

/**
 * @param array<string, mixed> $job
 * @return list<array<string, mixed>>
 */
function plpc_import_job_collect_form_render_requests(array &$job): array
{
    if (($job['imageMode'] ?? 'important') === 'none'
        || !class_exists('PortLibs\\MarkerPDF\\PdfTextExtractor')) {
        return [];
    }
    $requests = [];
    $pageSizedFormsSkipped = 0;
    $pageSizedVisualFormsRendered = 0;
    $renderRequestsTruncated = 0;
    $visualDispositionsByPath = [];
    foreach ($job['documents'] ?? [] as $documentIndex => $document) {
        if (!is_array($document) || PandocConverter::canonicalInputFormat((string) ($document['format'] ?? '')) !== 'pdf') {
            continue;
        }
        $path = (string) ($document['path'] ?? '');
        if ($path === '') {
            continue;
        }
        $inventoryIsOccurrenceComplete = false;
        try {
            if (is_array($document['pdfInspectionVisualOccurrences'] ?? null)) {
                $placements = $document['pdfInspectionVisualOccurrences'];
                $inventoryIsOccurrenceComplete = true;
                $pageGeometry = plpc_pdf_page_geometry_by_number(
                    is_array($document['pdfInspectionPageGeometry'] ?? null)
                        ? $document['pdfInspectionPageGeometry']
                        : []
                );
            } elseif (is_array($document['pdfInspectionForms'] ?? null)) {
                // Backward-compatible shape for a job created immediately
                // before occurrence-complete visual inspection shipped.
                $placements = $document['pdfInspectionForms'];
                $pageGeometry = plpc_pdf_page_geometry_by_number(
                    is_array($document['pdfInspectionPageGeometry'] ?? null)
                        ? $document['pdfInspectionPageGeometry']
                        : []
                );
            } else {
                // Jobs made before page-range visual checkpoints may reach
                // this helper without a staged inventory. Never fall back to
                // a whole-PDF scan: the PDF chunk state machine migrates any
                // already-saved page facts in bounded requests. If no facts
                // are available, preserve an explicit recoverable issue.
                $placements = [[
                    'id' => 'pdf-visual-inventory-uncheckpointed-' . substr(hash('sha256', $path), 0, 20),
                    'kind' => 'inspection-issue',
                    'page' => max(1, (int) ($document['pdfVisualInspectionNextPage'] ?? 1)),
                    'object' => 0,
                    'paintOrder' => 0,
                    'bbox' => null,
                    'visible' => null,
                    'placementEligible' => false,
                    'disposition' => 'unresolved',
                    'dispositionReason' => 'visual-inventory-checkpoint-unavailable',
                    'issueType' => 'resource-limit',
                    'recoverable' => true,
                ]];
                $pageGeometry = [];
                $inventoryIsOccurrenceComplete = false;
            }
        } catch (Throwable $error) {
            // A failed inventory is itself a source occurrence. Silently
            // continuing here previously allowed an empty ledger to claim
            // complete media handling for a PDF whose visuals were unknown.
            $placements = [[
                'id' => 'pdf-visual-inspection-failed-' . substr(hash('sha256', $path), 0, 20),
                'kind' => 'inspection-issue',
                'page' => 1,
                'object' => 0,
                'paintOrder' => 0,
                'bbox' => null,
                'visible' => null,
                'placementEligible' => false,
                'disposition' => 'unresolved',
                'dispositionReason' => 'visual-inventory-extraction-failed',
                'errorType' => get_class($error),
            ]];
            $pageGeometry = [];
            $inventoryIsOccurrenceComplete = false;
        }
        unset(
            $job['documents'][$documentIndex]['pdfInspectionForms'],
            $job['documents'][$documentIndex]['pdfInspectionVisualOccurrences'],
            $job['documents'][$documentIndex]['pdfInspectionPageGeometry']
        );
        $objectOccurrences = [];
        foreach ($placements as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $kind = (string) ($candidate['kind'] ?? 'form-xobject');
            if ($kind !== 'form-xobject') {
                continue;
            }
            $object = (int) ($candidate['object'] ?? 0);
            if ($object > 0) {
                $objectOccurrences[$object] = ($objectOccurrences[$object] ?? 0) + 1;
            }
        }
        $documentInventory = [];
        foreach ($placements as $placementIndex => $placement) {
            if (!is_array($placement)) {
                continue;
            }
            $kind = (string) ($placement['kind'] ?? 'form-xobject');
            $placement['kind'] = $kind;
            $placement['id'] = is_string($placement['id'] ?? null) && $placement['id'] !== ''
                ? $placement['id']
                : 'pdf-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($kind))
                    . '-p' . max(1, (int) ($placement['page'] ?? 1))
                    . '-n' . max(1, (int) ($placement['paintOrder'] ?? ((int) $placementIndex + 1)))
                    . '-o' . max(0, (int) ($placement['object'] ?? 0));
            $disposition = (string) ($placement['disposition'] ?? 'pending');
            $reason = is_string($placement['dispositionReason'] ?? null)
                ? $placement['dispositionReason']
                : null;

            if ($kind === 'image-xobject') {
                // Direct raster occurrences use the lossless server media
                // path first. Their stable IDs are reconciled against the
                // emitted image blocks after upload/materialization.
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    in_array($disposition, ['intentional_omission', 'unresolved'], true) ? $disposition : 'pending',
                    $reason
                );
                continue;
            }
            if (!in_array($kind, ['form-xobject', 'inline-image', 'page-vector-region'], true)) {
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    $disposition === 'intentional_omission' ? 'intentional_omission' : 'unresolved',
                    $reason ?? 'visual-kind-not-renderable'
                );
                continue;
            }
            if ($disposition === 'intentional_omission' || $disposition === 'unresolved') {
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence($placement, $disposition, $reason);
                continue;
            }
            if (($placement['visible'] ?? false) !== true) {
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    'intentional_omission',
                    'visual-not-visible'
                );
                continue;
            }
            if (($placement['placementEligible'] ?? false) !== true) {
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    'unresolved',
                    'visual-placement-ineligible'
                );
                continue;
            }
            $bbox = plpc_import_job_normalize_bbox($placement['bbox'] ?? null);
            if ($bbox === null) {
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    'unresolved',
                    'visual-bbox-invalid'
                );
                continue;
            }
            $width = $bbox['x2'] - $bbox['x1'];
            $height = $bbox['y2'] - $bbox['y1'];
            if ($width < 12.0 || $height < 12.0) {
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    'intentional_omission',
                    'small-decorative-visual'
                );
                continue;
            }
            if ($width > 10000.0 || $height > 10000.0) {
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    'unresolved',
                    'visual-bbox-exceeds-render-limit'
                );
                continue;
            }
            $page = max(1, (int) ($placement['page'] ?? 1));
            $pageLike = $kind === 'form-xobject' && isset($pageGeometry[$page])
                && plpc_pdf_form_placement_covers_page($bbox, $pageGeometry[$page]);
            if ($pageLike) {
                $object = (int) ($placement['object'] ?? 0);
                if (!plpc_pdf_page_like_form_is_content_rich(
                    $placement,
                    max(1, (int) ($objectOccurrences[$object] ?? 1))
                )) {
                    $summary = is_array($placement['visualSummary'] ?? null)
                        ? $placement['visualSummary']
                        : [];
                    if (($summary['complete'] ?? false) === true) {
                        $pageSizedFormsSkipped++;
                        $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                            $placement,
                            'intentional_omission',
                            'repeated-or-decorative-page-wrapper'
                        );
                    } else {
                        $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                            $placement,
                            'unresolved',
                            'form-content-inspection-incomplete'
                        );
                    }
                    continue;
                }
                $pageSizedVisualFormsRendered++;
            }
            $visualId = (string) $placement['id'];
            $requestId = 'form-' . substr(hash('sha256', $path . "\0" . $visualId), 0, 28);
            $labelPrefix = match ($kind) {
                'inline-image' => 'Inline PDF image',
                'page-vector-region' => 'PDF vector figure',
                default => $pageLike ? 'Full-page PDF visual' : 'PDF figure',
            };
            $request = [
                'id' => $requestId,
                'path' => $path,
                'page' => $page,
                'bbox' => $bbox,
                'formId' => $visualId,
                'visualId' => $visualId,
                'visualKind' => $kind,
                'object' => (int) ($placement['object'] ?? 0),
                'paintOrder' => (int) ($placement['paintOrder'] ?? 0),
                'precedingText' => is_string($placement['precedingText'] ?? null) ? $placement['precedingText'] : null,
                'followingText' => is_string($placement['followingText'] ?? null) ? $placement['followingText'] : null,
                'label' => $labelPrefix . ' on page ' . $page,
                'pageLikeVisual' => $pageLike,
            ];
            if (count($requests) < PLPC_IMPORT_JOB_MAX_FORM_RENDERS) {
                $requests[] = $request;
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    'pending',
                    'browser-render-requested'
                );
            } else {
                $renderRequestsTruncated++;
                $documentInventory[] = plpc_import_compact_pdf_visual_occurrence(
                    $placement,
                    'unresolved',
                    'browser-render-occurrence-limit'
                );
            }
        }
        if ($inventoryIsOccurrenceComplete || $documentInventory !== []) {
            $job['documents'][$documentIndex]['pdfVisualOccurrences'] = $documentInventory;
            $job['documents'][$documentIndex]['pdfVisualInventoryComplete'] = $inventoryIsOccurrenceComplete;
        }
    }

    $job['pdfPageSizedFormsSkipped'] = $pageSizedFormsSkipped;
    $job['pdfPageSizedVisualFormsRendered'] = $pageSizedVisualFormsRendered;
    $job['pdfFormRenderRequestsTruncated'] = $renderRequestsTruncated;
    $job['pdfVisualDispositionsByPath'] = $visualDispositionsByPath;

    return $requests;
}

/**
 * Keep the durable source occurrence compact. Bboxes remain in render
 * requests and provider facts; final reconciliation needs only stable source
 * identity, order, and the pre-materialization disposition.
 *
 * @param array<string,mixed> $occurrence
 * @return array{id:string,kind:string,page:int,object:int,paintOrder:int,disposition:string,reason:?string}
 */
function plpc_import_compact_pdf_visual_occurrence(
    array $occurrence,
    string $disposition,
    ?string $reason
): array {
    $compact = [
        'id' => (string) ($occurrence['id'] ?? ''),
        'kind' => (string) ($occurrence['kind'] ?? 'unknown'),
        'page' => max(1, (int) ($occurrence['page'] ?? 1)),
        'object' => max(0, (int) ($occurrence['object'] ?? 0)),
        'paintOrder' => max(0, (int) ($occurrence['paintOrder'] ?? 0)),
        'disposition' => in_array($disposition, ['pending', 'intentional_omission', 'unresolved'], true)
            ? $disposition
            : 'unresolved',
        'reason' => $reason === null ? null : substr($reason, 0, 120),
    ];
    if ((string) ($occurrence['kind'] ?? '') === 'inspection-issue') {
        $resourceLimit = is_array($occurrence['resourceLimit'] ?? null)
            ? $occurrence['resourceLimit']
            : [];
        $issueType = (string) ($occurrence['issueType'] ?? ($resourceLimit !== [] ? 'resource-limit' : 'inspection'));
        $compact['issueType'] = substr($issueType === '' ? 'inspection' : $issueType, 0, 64);
        $compact['recoverable'] = (bool) ($occurrence['recoverable'] ?? true);
        if (isset($occurrence['omittedOccurrences'])) {
            $compact['omittedOccurrences'] = max(0, (int) $occurrence['omittedOccurrences']);
        }
    }

    return $compact;
}

/**
 * Record a renderer outcome against the durable source occurrence itself.
 * The rendered-media record is only an implementation artifact; the source
 * occurrence remains the audit identity even when no image block is emitted.
 *
 * @param array<string,mixed> $job
 */
function plpc_import_job_mark_pdf_visual_occurrence(
    array &$job,
    string $path,
    string $visualId,
    string $disposition,
    string $reason
): bool {
    if ($visualId === '') {
        return false;
    }
    foreach ($job['documents'] ?? [] as $documentIndex => $document) {
        if (!is_array($document) || (string) ($document['path'] ?? '') !== $path) {
            continue;
        }
        foreach ($document['pdfVisualOccurrences'] ?? [] as $occurrenceIndex => $occurrence) {
            if (!is_array($occurrence) || !hash_equals((string) ($occurrence['id'] ?? ''), $visualId)) {
                continue;
            }
            $job['documents'][$documentIndex]['pdfVisualOccurrences'][$occurrenceIndex]['disposition'] =
                in_array($disposition, ['pending', 'intentional_omission', 'unresolved'], true)
                    ? $disposition
                    : 'unresolved';
            $job['documents'][$documentIndex]['pdfVisualOccurrences'][$occurrenceIndex]['reason'] = substr($reason, 0, 120);

            return true;
        }
    }

    return false;
}

/**
 * Keep a bounded, order-sensitive ledger for PDF visual occurrences which do
 * not become ordinary rendered image blocks. The complete source identifiers
 * are folded into a digest so thousands of occurrences cannot inflate a
 * durable import-job option.
 *
 * @param array<string, array<string, mixed>> $byPath
 * @param array<string, mixed> $placement
 */
function plpc_import_job_record_pdf_visual_disposition(
    array &$byPath,
    string $path,
    array $placement,
    string $disposition
): void {
    if (!isset($byPath[$path]) || !is_array($byPath[$path])) {
        $byPath[$path] = [
            'totalOccurrences' => 0,
            'intentionalOmissionOccurrences' => 0,
            'unresolvedOccurrences' => 0,
            'ledgerSha256' => hash('sha256', ''),
        ];
    }
    $entry = [
        'id' => (string) ($placement['id'] ?? ''),
        'page' => max(1, (int) ($placement['page'] ?? 1)),
        'object' => max(0, (int) ($placement['object'] ?? 0)),
        'paintOrder' => max(0, (int) ($placement['paintOrder'] ?? 0)),
        'disposition' => $disposition,
    ];
    $byPath[$path]['totalOccurrences'] = max(0, (int) $byPath[$path]['totalOccurrences']) + 1;
    if ($disposition === 'intentional_omission') {
        $byPath[$path]['intentionalOmissionOccurrences'] = max(
            0,
            (int) $byPath[$path]['intentionalOmissionOccurrences']
        ) + 1;
    } else {
        $byPath[$path]['unresolvedOccurrences'] = max(0, (int) $byPath[$path]['unresolvedOccurrences']) + 1;
    }
    $byPath[$path]['ledgerSha256'] = hash(
        'sha256',
        (string) $byPath[$path]['ledgerSha256'] . "\0" . plpc_json_encode_durable($entry, JSON_UNESCAPED_SLASHES)
    );
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
 * Return the durable cumulative render size. Current jobs are O(1). A legacy
 * job without the counter performs one metadata/file-size scan, stores the
 * migrated value in the in-memory job, and persists it with the same request.
 * Missing legacy files are ignored conservatively for compatibility; current
 * records retain their byte length even if a later cleanup removes the file.
 *
 * @param array<string, mixed> $job
 */
function plpc_import_job_rendered_form_total_bytes(array &$job): int
{
    if (array_key_exists('renderedFormBytes', $job)) {
        $job['renderedFormBytes'] = max(
            0,
            min(PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES, (int) $job['renderedFormBytes'])
        );

        return $job['renderedFormBytes'];
    }
    $total = 0;
    foreach ($job['renderedForms'] ?? [] as $rendered) {
        if (!is_array($rendered) || !isset($rendered['storage'])) {
            continue;
        }
        $recordedBytes = max(0, (int) ($rendered['bytes'] ?? 0));
        if ($recordedBytes > 0) {
            $total = min(PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES, $total + $recordedBytes);
            if ($total >= PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES) {
                break;
            }
            continue;
        }
        try {
            $path = plpc_import_job_storage_path($job, (string) $rendered['storage']);
            $bytes = filesize($path);
            if (is_int($bytes) && $bytes > 0) {
                $total = min(PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES, $total + $bytes);
            }
        } catch (Throwable) {
            continue;
        }
        if ($total >= PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES) {
            $total = PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES;
            break;
        }
    }
    $job['renderedFormBytes'] = max(0, min(PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES, $total));

    return $job['renderedFormBytes'];
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
        'visualId' => (string) ($renderRequest['visualId'] ?? $renderRequest['formId'] ?? ''),
        'visualKind' => (string) ($renderRequest['visualKind'] ?? 'form-xobject'),
        'path' => (string) ($renderRequest['path'] ?? ''),
        'storage' => $relative,
        'bytes' => strlen($rendered['contents']),
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
            'visualId' => (string) ($rendered['visualId'] ?? $rendered['formId'] ?? ''),
            'visualKind' => (string) ($rendered['visualKind'] ?? 'form-xobject'),
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

/**
 * Browser rendering is performed once for the complete PDF, but bounded
 * semantic passes must only receive figures painted on their own pages.
 * Otherwise every segment repeats every image, its provenance, media upload,
 * and WordPress block parsing work.
 *
 * @param list<array<string, mixed>> $renders
 * @return list<array<string, mixed>>
 */
function plpc_pdf_form_renders_for_page_range(array $renders, int $startPage, int $endPage): array
{
    $startPage = max(1, $startPage);
    $endPage = max($startPage, $endPage);

    return array_values(array_filter(
        $renders,
        static function (mixed $render) use ($startPage, $endPage): bool {
            if (!is_array($render)) {
                return false;
            }
            $page = (int) ($render['page'] ?? 0);

            return $page >= $startPage && $page <= $endPage;
        }
    ));
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
            'mediaDisposition' => is_array($result['mediaDisposition'] ?? null) ? $result['mediaDisposition'] : [],
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
    $mediaDispositions = [];
    foreach ($documents as $file) {
        try {
            $result = plpc_convert_collection_file_to_page($file, $collection, null, $imageMode, $pdfMode);
            $posts[] = $result;
            $imageTagCount += $result['imageTagCount'];
            $imagesImported += $result['imagesImported'];
            $mediaDispositions[] = is_array($result['mediaDisposition'] ?? null) ? $result['mediaDisposition'] : [];
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
            'mediaDisposition' => plpc_import_aggregate_media_dispositions($mediaDispositions),
            'diagnostics' => $diagnostics,
            'quality' => plpc_import_quality_report($post['format'], $diagnostics, $imageTagCount, $imagesImported),
        ]);
    }

    $indexTitle = $title !== '' ? $title : (string) $collection['label'];
    $indexBlocks = plpc_collection_index_blocks($indexTitle, $posts, $diagnostics);
    $indexPostId = plpc_insert_verified_page([
        'post_type' => 'page',
        'post_title' => $indexTitle,
        'post_content' => $indexBlocks,
    ]);
    $published = wp_update_post(['ID' => $indexPostId, 'post_status' => 'publish'], true);
    if (is_wp_error($published) || (int) $published < 1) {
        $message = is_wp_error($published) && method_exists($published, 'get_error_message')
            ? $published->get_error_message()
            : 'WordPress could not publish the verified collection index.';
        throw new PlpcImportFailure('publication_update_failed', $message, true, 'publishing_index');
    }
    plpc_import_verify_stored_page($indexPostId);

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
        'mediaDisposition' => plpc_import_aggregate_media_dispositions($mediaDispositions),
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
 * Extract one original PDF page range without deciding reading order or
 * creating output blocks. Semantic work is deliberately deferred until every
 * page fact is durable, then resolved in one memory-bounded contiguous range.
 *
 * @param array{path:string,bytes:string,format:string,pdfBrowserFacts?:array<string,mixed>|null} $file
 * @return array{facts:\PortLibs\MarkerPDF\PdfDocumentFacts,startPage:int,endPage:int,pageNumbers:list<int>}
 */
function plpc_convert_pdf_page_chunk(
    array $file,
    int $startPage,
    int $maxPages,
    string $imageMode = 'important',
    string $pdfMode = 'layout',
    ?callable $reportProgress = null
): array {
    $path = (string) ($file['path'] ?? 'document.pdf');
    $bytes = (string) ($file['bytes'] ?? '');
    $format = (string) ($file['format'] ?? 'pdf');
    if (PandocConverter::canonicalInputFormat($format) !== 'pdf') {
        throw new RuntimeException('A non-PDF document cannot be converted as a PDF page chunk.');
    }

    $options = plpc_converter_options($format, $pdfMode)['readerOptions'];
    $options['pdfStartPage'] = max(1, $startPage);
    $options['pdfMaxPages'] = max(1, $maxPages);
    $options['pdfMaxPositionedTextRuns'] = max(20_000, min(250_000, max(1, $maxPages) * 1_000));
    if ($imageMode === 'none') {
        $options['pdfCollectImagePlacements'] = false;
        $options['pdfCollectFormXObjectPlacements'] = false;
    }
    if (is_array($file['pdfBrowserFacts'] ?? null)) {
        $options['browserFacts'] = $file['pdfBrowserFacts'];
    }
    plpc_conversion_progress($reportProgress, 'reading', 'Extracting lossless facts from the selected PDF page range.');
    $facts = (new \PortLibs\MarkerPDF\BrowserPdfFactsProvider())->extract($bytes, $options);
    $pageNumbers = array_values(array_map('intval', $facts->inventory()['pageNumbers'] ?? []));
    $expectedEndPage = max(1, $startPage) + max(1, $maxPages) - 1;
    $expectedPageNumbers = range(max(1, $startPage), $expectedEndPage);
    $issues = [];
    $positionedRunsLimited = false;
    foreach ($facts->pages() as $page) {
        array_push($issues, ...$page->issues());
        $positionedRunsLimited = $positionedRunsLimited
            || (($page->text()['positionedRunsLimited'] ?? false) === true);
    }
    if ($pageNumbers !== $expectedPageNumbers || $issues !== []
        || ($pdfMode === 'layout' && $positionedRunsLimited)
    ) {
        $reasons = [];
        if ($issues !== []) {
            $reasons[] = 'page extraction';
        }
        if ($positionedRunsLimited) {
            $reasons[] = 'positioned text limit';
        }
        throw new RuntimeException(
            'PDF pages ' . max(1, $startPage) . '–' . $expectedEndPage . ' could not be extracted completely'
            . ($reasons === [] ? '.' : ' (' . implode(', ', $reasons) . ').')
        );
    }

    return [
        'facts' => $facts,
        'startPage' => max(1, $startPage),
        'endPage' => $expectedEndPage,
        'pageNumbers' => $pageNumbers,
    ];
}

/**
 * @param array<string, mixed> $job
 * @param array{facts:\PortLibs\MarkerPDF\PdfDocumentFacts,startPage:int,endPage:int,pageNumbers:list<int>} $chunk
 * @return array{startPage:int,endPage:int,facts:string,sha256:string,bytes:int}
 */
function plpc_import_job_store_pdf_chunk(array $job, int $documentIndex, array $chunk): array
{
    $startPage = max(1, (int) ($chunk['startPage'] ?? 1));
    $endPage = max($startPage, (int) ($chunk['endPage'] ?? $startPage));
    $facts = $chunk['facts'] ?? null;
    if (!$facts instanceof \PortLibs\MarkerPDF\PdfDocumentFacts) {
        throw new RuntimeException('A PDF page chunk did not contain serializable facts.');
    }
    $base = sprintf('pdf-%03d-pages-%06d-%06d', $documentIndex, $startPage, $endPage);
    $factsStorage = 'facts/' . $base . '.json';
    $directory = plpc_import_job_directory($job);
    $json = $facts->toJson();
    plpc_import_job_write_file($directory, $factsStorage, $json);

    return compact('startPage', 'endPage') + [
        'facts' => $factsStorage,
        'sha256' => hash('sha256', $json),
        'bytes' => strlen($json),
    ];
}

/**
 * @param array<string, mixed> $job
 * @param array{startPage:int,endPage:int,facts:string,sha256:string,bytes:int} $record
 * @return array{facts:\PortLibs\MarkerPDF\PdfDocumentFacts,startPage:int,endPage:int,pageNumbers:list<int>}
 */
function plpc_import_job_load_pdf_chunk(array $job, array $record): array
{
    $json = plpc_import_job_read_file($job, (string) ($record['facts'] ?? ''));
    if ((int) ($record['bytes'] ?? -1) !== strlen($json)
        || !hash_equals((string) ($record['sha256'] ?? ''), hash('sha256', $json))) {
        throw new RuntimeException('A saved PDF page facts chunk failed its integrity check.');
    }
    $facts = \PortLibs\MarkerPDF\PdfDocumentFacts::fromJson($json);
    $pageNumbers = array_values(array_map('intval', $facts->inventory()['pageNumbers'] ?? []));

    return [
        'facts' => $facts,
        'startPage' => max(1, (int) ($record['startPage'] ?? 1)),
        'endPage' => max(1, (int) ($record['endPage'] ?? 1)),
        'pageNumbers' => $pageNumbers,
    ];
}

/**
 * @param array<string, mixed> $document
 * @return list<array{startPage:int,endPage:int,factsBytes:int,facts?:array<string,mixed>,finalBundle?:array<string,mixed>}>
 */
function plpc_import_job_plan_pdf_segments(array $document): array
{
    $pageCount = max(1, (int) ($document['pdfPageCount'] ?? 0));
    if (is_array($document['pdfFinalBundle'] ?? null)) {
        $segment = [
            'startPage' => 1,
            'endPage' => $pageCount,
            'factsBytes' => max(0, (int) ($document['pdfDocumentFacts']['bytes'] ?? 0)),
            'finalBundle' => $document['pdfFinalBundle'],
        ];
        if (is_array($document['pdfDocumentFacts'] ?? null)) {
            $segment['facts'] = $document['pdfDocumentFacts'];
        }

        return [$segment];
    }

    $records = is_array($document['pdfChunks'] ?? null) ? array_values($document['pdfChunks']) : [];
    usort($records, static fn (array $left, array $right): int => ((int) ($left['startPage'] ?? 0)) <=> ((int) ($right['startPage'] ?? 0)));
    if ($records === [] && is_array($document['pdfDocumentFacts'] ?? null)) {
        return [[
            'startPage' => 1,
            'endPage' => $pageCount,
            'factsBytes' => max(0, (int) ($document['pdfDocumentFacts']['bytes'] ?? 0)),
            'facts' => $document['pdfDocumentFacts'],
        ]];
    }

    $maxBytes = plpc_pdf_segment_max_fact_bytes($pageCount);
    $maxPages = plpc_pdf_segment_max_pages($pageCount);
    $segments = [];
    $current = null;
    $expectedPage = 1;
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $startPage = max(1, (int) ($record['startPage'] ?? 1));
        $endPage = max($startPage, (int) ($record['endPage'] ?? $startPage));
        if ($startPage !== $expectedPage) {
            throw new RuntimeException('The PDF cannot be segmented until every durable page range is contiguous.');
        }
        $recordBytes = max(0, (int) ($record['bytes'] ?? 0));
        $wouldExceedBytes = is_array($current) && $current['factsBytes'] + $recordBytes > $maxBytes;
        $wouldExceedPages = is_array($current) && $endPage - $current['startPage'] + 1 > $maxPages;
        if (($wouldExceedBytes || $wouldExceedPages) && is_array($current)) {
            $segments[] = $current;
            $current = null;
        }
        if (!is_array($current)) {
            $current = ['startPage' => $startPage, 'endPage' => $endPage, 'factsBytes' => 0];
        }
        $current['endPage'] = $endPage;
        $current['factsBytes'] += $recordBytes;
        $expectedPage = $endPage + 1;
    }
    if (is_array($current)) {
        $segments[] = $current;
    }
    if ($expectedPage !== $pageCount + 1 || $segments === []) {
        throw new RuntimeException('The PDF cannot be segmented until every page fact is present.');
    }

    return $segments;
}

/**
 * The page-tree mode deliberately resolves semantics one physical source
 * page at a time. Durable extraction still batches source parsing, but each
 * child page has an exact page provenance and can be resumed independently.
 *
 * @param array<string, mixed> $document
 * @return list<array{startPage:int,endPage:int,factsBytes:int}>
 */
function plpc_import_job_plan_pdf_page_segments(array $document): array
{
    $pageCount = max(1, (int) ($document['pdfPageCount'] ?? 0));
    $records = is_array($document['pdfChunks'] ?? null) ? array_values($document['pdfChunks']) : [];
    usort($records, static fn (array $left, array $right): int => ((int) ($left['startPage'] ?? 0)) <=> ((int) ($right['startPage'] ?? 0)));
    $segments = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $page = max(1, (int) ($record['startPage'] ?? 1));
        if ($page !== (int) ($record['endPage'] ?? $page) || $page !== count($segments) + 1) {
            throw new RuntimeException('The PDF page tree requires one contiguous durable facts record per page.');
        }
        $segments[] = [
            'startPage' => $page,
            'endPage' => $page,
            'factsBytes' => max(0, (int) ($record['bytes'] ?? 0)),
        ];
    }
    if (count($segments) !== $pageCount) {
        throw new RuntimeException('The PDF page tree cannot be created until every physical page fact is present.');
    }

    return $segments;
}

/** @param array<string, mixed> $job @param array<string, mixed> $factsRecord */
function plpc_import_job_pdf_page_is_certified_blank(array $job, array $factsRecord): bool
{
    $pageNumber = max(1, (int) ($factsRecord['startPage'] ?? 1));
    $facts = plpc_import_job_load_pdf_facts_record($job, $factsRecord, $pageNumber, $pageNumber);
    $page = $facts->page($pageNumber);
    if (!$page instanceof \PortLibs\MarkerPDF\PdfPageFacts) {
        return false;
    }
    // Empty output is not evidence that the physical page was blank. Any
    // page-scoped extraction issue means the parser may simply have failed to
    // observe nonblank source content, so the normal empty-output guard must
    // remain in force.
    if ($page->issues() !== []) {
        return false;
    }
    $text = $page->text();
    if (($text['positionedRunsLimited'] ?? false) === true) {
        return false;
    }
    foreach (['lines', 'runs', 'spans'] as $key) {
        if (is_array($text[$key] ?? null) && $text[$key] !== []) {
            return false;
        }
    }
    if (is_array($text['browser']['spans'] ?? null) && $text['browser']['spans'] !== []) {
        return false;
    }
    foreach ($page->graphics() as $records) {
        if (is_array($records) && $records !== []) {
            return false;
        }
    }
    foreach ($page->annotations() as $records) {
        if (is_array($records) && $records !== []) {
            return false;
        }
    }

    // Facts created by an older plugin version may not have copied every
    // document diagnostic into PdfPageFacts::issues(). Fail closed on the
    // extraction/resource signals that can turn a nonblank page into empty
    // output, while leaving optional browser-facts availability unrelated to
    // native blank-page certification.
    $diagnostics = $facts->diagnostics();
    $incompleteCounters = [
        'failedStreams',
        'malformedXrefStreams',
        'malformedObjectStreams',
        'pagesWithExtractionIssues',
    ];
    foreach ($incompleteCounters as $key) {
        if ((int) ($diagnostics[$key] ?? 0) > 0) {
            return false;
        }
    }
    foreach (['unsupportedFilters', 'malformedXrefOffsets', 'resourceLimitIssues'] as $key) {
        if (is_array($diagnostics[$key] ?? null) && $diagnostics[$key] !== []) {
            return false;
        }
    }
    if (($diagnostics['encrypted'] ?? false) === true
        && ($diagnostics['encryptionDecrypted'] ?? false) !== true
    ) {
        return false;
    }
    if (($diagnostics['encryptionAllowsContentExtraction'] ?? null) === false) {
        return false;
    }

    return true;
}

/**
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @param array<string, mixed> $segment
 * @return array{storage:string,sha256:string,bytes:int,provider:string,pages:int,startPage:int,endPage:int}
 */
function plpc_import_job_merge_pdf_segment_facts(
    array $job,
    int $documentIndex,
    array $document,
    int $segmentIndex,
    array $segment
): array {
    $startPage = max(1, (int) ($segment['startPage'] ?? 1));
    $endPage = max($startPage, (int) ($segment['endPage'] ?? $startPage));
    $records = is_array($document['pdfChunks'] ?? null) ? array_values($document['pdfChunks']) : [];
    usort($records, static fn (array $left, array $right): int => ((int) ($left['startPage'] ?? 0)) <=> ((int) ($right['startPage'] ?? 0)));
    $expectedPage = $startPage;
    $ranges = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $recordStart = max(1, (int) ($record['startPage'] ?? 1));
        $recordEnd = max($recordStart, (int) ($record['endPage'] ?? $recordStart));
        if ($recordEnd < $startPage || $recordStart > $endPage) {
            continue;
        }
        if ($recordStart !== $expectedPage || $recordEnd > $endPage) {
            throw new RuntimeException('The saved PDF segment facts were not contiguous.');
        }
        $chunk = plpc_import_job_load_pdf_chunk($job, $record);
        if ($chunk['pageNumbers'] !== range($recordStart, $recordEnd)) {
            throw new RuntimeException('A saved PDF segment chunk did not contain its declared pages.');
        }
        $ranges[] = $chunk['facts'];
        $expectedPage = $recordEnd + 1;
    }
    if ($expectedPage !== $endPage + 1 || $ranges === []) {
        throw new RuntimeException('The PDF segment cannot be finalized until every page fact is present.');
    }
    $facts = (new \PortLibs\MarkerPDF\PdfDocumentFactsMerger())->mergeRange($ranges, $startPage, $endPage);
    if (is_array($document['pdfDocumentProfile'] ?? null)) {
        $profile = plpc_import_job_load_pdf_document_profile($job, $document['pdfDocumentProfile']);
        $factsData = $facts->toArray();
        $factsData['structure'] = is_array($factsData['structure'] ?? null) ? $factsData['structure'] : [];
        $factsData['structure']['documentProfile'] = $profile;
        $facts = \PortLibs\MarkerPDF\PdfDocumentFacts::fromArray($factsData);
    }
    $json = $facts->toJson();
    $storage = 'facts/' . sprintf(
        'pdf-%03d-segment-%03d-pages-%06d-%06d.json',
        max(0, $documentIndex),
        max(0, $segmentIndex),
        $startPage,
        $endPage
    );
    plpc_import_job_write_file(plpc_import_job_directory($job), $storage, $json);

    return [
        'storage' => $storage,
        'sha256' => hash('sha256', $json),
        'bytes' => strlen($json),
        'provider' => $facts->provider(),
        'pages' => count($facts->pages()),
        'startPage' => $startPage,
        'endPage' => $endPage,
    ];
}

/**
 * Merge only compact page-range profiles after extraction, then persist one
 * immutable document-wide profile. Every bounded semantic segment receives
 * this same profile, so furniture, column, direction, and cue evidence does
 * not change at an arbitrary chunk boundary.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @return array{storage:string,sha256:string,bytes:int,profileDigest:string}
 */
function plpc_import_job_merge_pdf_document_profile(array $job, int $documentIndex, array $document): array
{
    $records = is_array($document['pdfChunks'] ?? null) ? array_values($document['pdfChunks']) : [];
    usort($records, static fn (array $left, array $right): int => ((int) ($left['startPage'] ?? 0)) <=> ((int) ($right['startPage'] ?? 0)));
    $pageCount = max(1, (int) ($document['pdfPageCount'] ?? 0));
    $expectedPage = 1;
    $profiles = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $chunk = plpc_import_job_load_pdf_chunk($job, $record);
        if ($chunk['startPage'] !== $expectedPage
            || $chunk['pageNumbers'] !== range($chunk['startPage'], $chunk['endPage'])) {
            throw new RuntimeException('The PDF document profile requires contiguous saved page facts.');
        }
        $profile = $chunk['facts']->structure()['documentProfile'] ?? null;
        if (is_array($profile)) {
            $profiles[] = $profile;
        }
        $expectedPage = $chunk['endPage'] + 1;
    }
    if ($expectedPage !== $pageCount + 1 || $profiles === []) {
        throw new RuntimeException('The PDF document profile cannot be finalized until every page profile is present.');
    }
    $profile = \PortLibs\MarkerPDF\PdfDocumentLayoutProfile::merge($profiles, $pageCount);
    $json = plpc_json_encode_durable(
        $profile,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );
    $storage = 'facts/' . sprintf('pdf-%03d-document-profile.json', max(0, $documentIndex));
    plpc_import_job_write_file(plpc_import_job_directory($job), $storage, $json);

    return [
        'storage' => $storage,
        'sha256' => hash('sha256', $json),
        'bytes' => strlen($json),
        'profileDigest' => (string) ($profile['profileDigest'] ?? hash('sha256', $json)),
    ];
}

/** @param array<string, mixed> $job @param array<string, mixed> $record @return array<string, mixed> */
function plpc_import_job_load_pdf_document_profile(array $job, array $record): array
{
    $json = plpc_import_job_read_file($job, (string) ($record['storage'] ?? ''));
    if ((int) ($record['bytes'] ?? -1) !== strlen($json)
        || !hash_equals((string) ($record['sha256'] ?? ''), hash('sha256', $json))) {
        throw new RuntimeException('The saved PDF document profile failed its integrity check.');
    }
    $profile = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($profile)
        || ((string) ($record['profileDigest'] ?? '')) !== (string) ($profile['profileDigest'] ?? '')) {
        throw new RuntimeException('The saved PDF document profile descriptor was invalid.');
    }

    return $profile;
}

function plpc_import_job_merge_pdf_document_facts(array $job, int $documentIndex, array $document): array
{
    $records = is_array($document['pdfChunks'] ?? null) ? array_values($document['pdfChunks']) : [];
    usort($records, static fn (array $left, array $right): int => ((int) ($left['startPage'] ?? 0)) <=> ((int) ($right['startPage'] ?? 0)));
    $pageCount = max(1, (int) ($document['pdfPageCount'] ?? 0));
    $expectedPage = 1;
    $ranges = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $chunk = plpc_import_job_load_pdf_chunk($job, $record);
        if ($chunk['startPage'] !== $expectedPage
            || $chunk['pageNumbers'] !== range($chunk['startPage'], $chunk['endPage'])) {
            throw new RuntimeException('The saved PDF page facts were not contiguous.');
        }
        $expectedPage = $chunk['endPage'] + 1;
        $ranges[] = $chunk['facts'];
    }
    if ($expectedPage !== $pageCount + 1) {
        throw new RuntimeException('The PDF cannot be finalized until every page fact is present.');
    }
    $facts = (new \PortLibs\MarkerPDF\PdfDocumentFactsMerger())->mergeComplete($ranges);
    $json = $facts->toJson();
    $storage = 'facts/' . sprintf('pdf-%03d-document.json', max(0, $documentIndex));
    plpc_import_job_write_file(plpc_import_job_directory($job), $storage, $json);

    return [
        'storage' => $storage,
        'sha256' => hash('sha256', $json),
        'bytes' => strlen($json),
        'provider' => $facts->provider(),
        'pages' => count($facts->pages()),
    ];
}

/** @param array<string, mixed> $job @param array<string, mixed> $document */
function plpc_import_job_load_pdf_document_facts(array $job, array $document): \PortLibs\MarkerPDF\PdfDocumentFacts
{
    $record = $document['pdfDocumentFacts'] ?? null;
    if (!is_array($record)) {
        throw new RuntimeException('The complete PDF facts snapshot is not available.');
    }
    return plpc_import_job_load_pdf_facts_record(
        $job,
        $record,
        1,
        max(1, (int) ($document['pdfPageCount'] ?? 0))
    );
}

/** @param array<string, mixed> $job @param array<string, mixed> $record */
function plpc_import_job_load_pdf_facts_record(
    array $job,
    array $record,
    int $expectedStartPage,
    int $expectedEndPage
): \PortLibs\MarkerPDF\PdfDocumentFacts {
    $json = plpc_import_job_read_file($job, (string) ($record['storage'] ?? ''));
    if ((int) ($record['bytes'] ?? -1) !== strlen($json)
        || !hash_equals((string) ($record['sha256'] ?? ''), hash('sha256', $json))) {
        throw new RuntimeException('The saved PDF facts snapshot failed its integrity check.');
    }
    $facts = \PortLibs\MarkerPDF\PdfDocumentFacts::fromJson($json);
    $pageNumbers = array_values(array_map('intval', $facts->inventory()['pageNumbers'] ?? []));
    if ($pageNumbers !== range($expectedStartPage, $expectedEndPage)) {
        throw new RuntimeException('The saved PDF facts snapshot did not cover its declared page range.');
    }

    return $facts;
}

/**
 * Run the existing reader semantics from a verified facts snapshot, then
 * persist the private block/media bundle before any public side effect.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @return array{manifest:string,sha256:string,bytes:int}
 */
function plpc_import_job_prepare_pdf_final_bundle(
    array $job,
    int $documentIndex,
    array $document,
    string $pdfBytes,
    string $imageMode,
    string $pdfMode,
    ?callable $reportProgress = null,
    ?array $factsRecord = null,
    ?int $segmentIndex = null
): array {
    $format = (string) ($document['format'] ?? 'pdf');
    $path = (string) ($document['path'] ?? 'document.pdf');
    $facts = $factsRecord === null
        ? plpc_import_job_load_pdf_document_facts($job, $document)
        : plpc_import_job_load_pdf_facts_record(
            $job,
            $factsRecord,
            max(1, (int) ($factsRecord['startPage'] ?? 1)),
            max(1, (int) ($factsRecord['endPage'] ?? $factsRecord['pages'] ?? 1))
        );
    $options = plpc_converter_options($format, $pdfMode);
    $options['readerOptions']['pdfDocumentFacts'] = $facts;
    if (is_array($document['pdfReaderStructuralMetadata'] ?? null)) {
        $options['readerOptions']['pdfReaderStructuralMetadata'] = $document['pdfReaderStructuralMetadata'];
    }
    if (is_array($document['pdfReaderMetadata'] ?? null)) {
        $options['readerOptions']['pdfReaderMetadata'] = $document['pdfReaderMetadata'];
    }
    if ($imageMode === 'none') {
        $options['readerOptions']['pdfCollectImagePlacements'] = false;
        $options['readerOptions']['pdfCollectFormXObjectPlacements'] = false;
    }
    plpc_conversion_progress($reportProgress, 'reading', 'Resolving PDF reading order from durable page facts.');
    $ast = PandocConverter::read($pdfBytes, $format, $options['readerOptions']);
    $pageNumbers = array_values(array_map('intval', $facts->inventory()['pageNumbers'] ?? []));
    $rangeStartPage = $pageNumbers === [] ? 1 : min($pageNumbers);
    $rangeEndPage = $pageNumbers === [] ? max(1, (int) ($document['pdfPageCount'] ?? 1)) : max($pageNumbers);
    $formRenders = plpc_pdf_form_renders_for_page_range(
        plpc_import_job_load_rendered_forms($job, $path),
        $rangeStartPage,
        $rangeEndPage
    );
    if ($formRenders !== []) {
        plpc_conversion_progress($reportProgress, 'extracting_media', 'Placing browser-rendered PDF figures in the resolved page range.');
        $ast = plpc_document_with_browser_pdf_form_renders($ast, $formRenders);
    }
    plpc_conversion_progress($reportProgress, 'extracting_media', 'Extracting media after PDF semantics are resolved.');
    $media = (new PandocMediaExtractor())->extract($ast, $pdfBytes, $format, [
        'destination' => 'media',
        'imageMode' => $imageMode,
        'pdfRasterImages' => plpc_import_job_load_pdf_rasters($job, $path),
    ]);
    $ast = $media['document'];
    plpc_conversion_progress($reportProgress, 'writing_blocks', 'Writing the WordPress block document.');
    $blocks = plpc_import_sanitize_post_content(
        PandocConverter::write($ast, 'wordpress', $options['writerOptions'])
    );
    $diagnostics = array_values(array_unique(array_merge(
        plpc_document_diagnostics($ast, $format),
        is_array($media['diagnostics'] ?? null) ? $media['diagnostics'] : []
    )));

    return plpc_import_job_store_pdf_final_bundle($job, $documentIndex, [
        'blocks' => $blocks,
        'entries' => is_array($media['entries'] ?? null) ? array_values($media['entries']) : [],
        'diagnostics' => $diagnostics,
        'imageTagCount' => count(plpc_rendered_media_occurrences($blocks)),
    ], $segmentIndex);
}

/**
 * @param array<string, mixed> $job
 * @param array{blocks:string,entries:list<array<string,mixed>>,diagnostics:list<string>,imageTagCount:int} $bundle
 * @return array{manifest:string,sha256:string,bytes:int}
 */
function plpc_import_job_store_pdf_final_bundle(array $job, int $documentIndex, array $bundle, ?int $segmentIndex = null): array
{
    $base = $segmentIndex === null
        ? sprintf('pdf-%03d-final', max(0, $documentIndex))
        : sprintf('pdf-%03d-segment-%03d-final', max(0, $documentIndex), max(0, $segmentIndex));
    $blocksStorage = 'chunks/' . $base . '.blocks';
    $manifestStorage = 'chunks/' . $base . '.json';
    $directory = plpc_import_job_directory($job);
    plpc_import_job_write_file($directory, $blocksStorage, (string) ($bundle['blocks'] ?? ''));
    $entries = [];
    foreach ($bundle['entries'] ?? [] as $entry) {
        if (!is_array($entry) || !is_string($entry['contents'] ?? null) || $entry['contents'] === '') {
            continue;
        }
        $contents = $entry['contents'];
        $sha1 = sha1($contents);
        foreach (['source', 'canonicalSource'] as $provenanceKey) {
            if (is_string($entry[$provenanceKey] ?? null)
                && str_starts_with($entry[$provenanceKey], 'data:')
            ) {
                $entry[$provenanceKey] = 'data-uri:sha1:' . $sha1;
            }
        }
        $storage = 'chunks/pdf-final-media-' . $sha1 . '.bin';
        plpc_import_job_write_file($directory, $storage, $contents);
        unset($entry['contents']);
        $entries[] = $entry + ['sha1' => $sha1, 'storage' => $storage];
    }
    $manifest = [
        'version' => 1,
        'blocksStorage' => $blocksStorage,
        'blocksSha256' => hash('sha256', (string) ($bundle['blocks'] ?? '')),
        'entries' => $entries,
        'diagnostics' => array_values(array_map('strval', $bundle['diagnostics'] ?? [])),
        'imageTagCount' => max(0, (int) ($bundle['imageTagCount'] ?? 0)),
    ];
    $json = plpc_json_encode_durable($manifest, JSON_UNESCAPED_SLASHES);
    plpc_import_job_write_file($directory, $manifestStorage, $json);

    return ['manifest' => $manifestStorage, 'sha256' => hash('sha256', $json), 'bytes' => strlen($json)];
}

/**
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @return array{blocks:string,entries:list<array<string,mixed>>,diagnostics:list<string>,imageTagCount:int}
 */
function plpc_import_job_load_pdf_final_bundle(array $job, array $document, ?array $bundleRecord = null): array
{
    $record = $bundleRecord ?? ($document['pdfFinalBundle'] ?? null);
    if (!is_array($record)) {
        throw new RuntimeException('The finalized PDF bundle is not available.');
    }
    $json = plpc_import_job_read_file($job, (string) ($record['manifest'] ?? ''));
    if ((int) ($record['bytes'] ?? -1) !== strlen($json)
        || !hash_equals((string) ($record['sha256'] ?? ''), hash('sha256', $json))) {
        throw new RuntimeException('The finalized PDF bundle failed its integrity check.');
    }
    $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || (int) ($manifest['version'] ?? 0) !== 1) {
        throw new RuntimeException('The finalized PDF bundle manifest was invalid.');
    }
    $blocks = plpc_import_job_read_file($job, (string) ($manifest['blocksStorage'] ?? ''));
    if (!hash_equals((string) ($manifest['blocksSha256'] ?? ''), hash('sha256', $blocks))) {
        throw new RuntimeException('The finalized PDF blocks failed their integrity check.');
    }
    $entries = [];
    foreach ($manifest['entries'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $contents = plpc_import_job_read_file($job, (string) ($entry['storage'] ?? ''));
        if (!hash_equals((string) ($entry['sha1'] ?? ''), sha1($contents))) {
            throw new RuntimeException('The finalized PDF media failed its integrity check.');
        }
        unset($entry['storage']);
        $entry['contents'] = $contents;
        $entries[] = $entry;
    }

    return [
        'blocks' => $blocks,
        'entries' => $entries,
        'diagnostics' => array_values(array_map('strval', is_array($manifest['diagnostics'] ?? null) ? $manifest['diagnostics'] : [])),
        'imageTagCount' => max(0, (int) ($manifest['imageTagCount'] ?? 0)),
    ];
}

/**
 * Resolve a durable PDF bundle's media references without creating a post.
 * The result can be assembled with adjacent bounded segments later, so the
 * server never needs the full PDF AST and the full WordPress page in memory
 * at the same time.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @return array{blocks:string,diagnostics:list<string>,quality:array<string,mixed>,imageTagCount:int,imagesImported:int,format:string}
 */
function plpc_import_job_materialize_pdf_bundle(
    array $job,
    array $document,
    string $pdfBytes,
    ?array $collection,
    array $bundleRecord,
    ?callable $reportProgress = null
): array {
    $bundle = plpc_import_job_load_pdf_final_bundle($job, $document, $bundleRecord);
    $blockMarkup = plpc_import_sanitize_post_content($bundle['blocks']);
    $originalBlockMarkup = $blockMarkup;
    $diagnostics = $bundle['diagnostics'];
    $imageSources = plpc_rendered_image_sources($blockMarkup);
    plpc_conversion_progress($reportProgress, 'uploading_media', 'Uploading media from the durable PDF bundle.');
    $mediaResult = plpc_import_extracted_media_entries($blockMarkup, $imageSources, $bundle['entries']);
    $remainingSources = array_values(array_filter(
        $imageSources,
        static fn (string $source): bool => !in_array($source, $mediaResult['sources'], true)
    ));
    $fallback = $remainingSources === []
        ? ['blocks' => $mediaResult['blocks'], 'imported' => 0, 'diagnostics' => []]
        : plpc_import_rendered_images(
            $mediaResult['blocks'],
            $remainingSources,
            $pdfBytes,
            basename((string) ($document['path'] ?? 'document.pdf')),
            $collection,
            (string) ($document['path'] ?? 'document.pdf')
        );
    $diagnostics = array_values(array_unique(array_merge(
        $diagnostics,
        $mediaResult['diagnostics'],
        $fallback['diagnostics']
    )));
    $imageTagCount = count(plpc_rendered_media_occurrences($originalBlockMarkup));
    $imagesImported = (int) $mediaResult['imported'] + (int) $fallback['imported'];
    $format = (string) ($document['format'] ?? 'pdf');
    $mediaDisposition = plpc_import_media_disposition_summary(
        $originalBlockMarkup,
        (string) $fallback['blocks'],
        $diagnostics
    );
    plpc_import_assert_media_disposition((string) $fallback['blocks'], $mediaDisposition);

    return [
        'blocks' => (string) $fallback['blocks'],
        'diagnostics' => $diagnostics,
        'quality' => plpc_import_quality_report($format, $diagnostics, $imageTagCount, $imagesImported),
        'imageTagCount' => $imageTagCount,
        'imagesImported' => $imagesImported,
        'mediaDisposition' => $mediaDisposition,
        'format' => $format,
    ];
}

/** @param array<string, mixed> $bundle @return array{manifest:string,sha256:string,bytes:int,blockBytes:int} */
function plpc_import_job_store_pdf_publication_bundle(
    array $job,
    int $documentIndex,
    int $segmentIndex,
    array $bundle
): array {
    $base = sprintf('pdf-%03d-segment-%03d-publication', max(0, $documentIndex), max(0, $segmentIndex));
    $blocksStorage = 'chunks/' . $base . '.blocks';
    $manifestStorage = 'chunks/' . $base . '.json';
    $blocks = (string) ($bundle['blocks'] ?? '');
    $directory = plpc_import_job_directory($job);
    plpc_import_job_write_file($directory, $blocksStorage, $blocks);
    $manifest = [
        'version' => 1,
        'blocksStorage' => $blocksStorage,
        'blocksSha256' => hash('sha256', $blocks),
        'blockBytes' => strlen($blocks),
        'diagnostics' => array_values(array_map('strval', is_array($bundle['diagnostics'] ?? null) ? $bundle['diagnostics'] : [])),
        'quality' => is_array($bundle['quality'] ?? null) ? $bundle['quality'] : [],
        'imageTagCount' => max(0, (int) ($bundle['imageTagCount'] ?? 0)),
        'imagesImported' => max(0, (int) ($bundle['imagesImported'] ?? 0)),
        'mediaDisposition' => is_array($bundle['mediaDisposition'] ?? null) ? $bundle['mediaDisposition'] : [],
        'format' => (string) ($bundle['format'] ?? 'pdf'),
    ];
    $json = plpc_json_encode_durable($manifest, JSON_UNESCAPED_SLASHES);
    plpc_import_job_write_file($directory, $manifestStorage, $json);

    return [
        'manifest' => $manifestStorage,
        'sha256' => hash('sha256', $json),
        'bytes' => strlen($json),
        'blockBytes' => strlen($blocks),
    ];
}

/** @return array{blocks:string,diagnostics:list<string>,quality:array<string,mixed>,imageTagCount:int,imagesImported:int,format:string} */
function plpc_import_job_load_pdf_publication_bundle(array $job, array $record): array
{
    $json = plpc_import_job_read_file($job, (string) ($record['manifest'] ?? ''));
    if ((int) ($record['bytes'] ?? -1) !== strlen($json)
        || !hash_equals((string) ($record['sha256'] ?? ''), hash('sha256', $json))) {
        throw new RuntimeException('The PDF publication bundle failed its integrity check.');
    }
    $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || (int) ($manifest['version'] ?? 0) !== 1) {
        throw new RuntimeException('The PDF publication bundle manifest was invalid.');
    }
    $blocks = plpc_import_job_read_file($job, (string) ($manifest['blocksStorage'] ?? ''));
    if ((int) ($manifest['blockBytes'] ?? -1) !== strlen($blocks)
        || !hash_equals((string) ($manifest['blocksSha256'] ?? ''), hash('sha256', $blocks))) {
        throw new RuntimeException('The PDF publication blocks failed their integrity check.');
    }

    return [
        'blocks' => $blocks,
        'diagnostics' => array_values(array_map('strval', is_array($manifest['diagnostics'] ?? null) ? $manifest['diagnostics'] : [])),
        'quality' => is_array($manifest['quality'] ?? null) ? $manifest['quality'] : [],
        'imageTagCount' => max(0, (int) ($manifest['imageTagCount'] ?? 0)),
        'imagesImported' => max(0, (int) ($manifest['imagesImported'] ?? 0)),
        'mediaDisposition' => is_array($manifest['mediaDisposition'] ?? null) ? $manifest['mediaDisposition'] : [],
        'format' => (string) ($manifest['format'] ?? 'pdf'),
    ];
}

/**
 * Encode durable metadata without allowing one malformed source byte to
 * abort a completed conversion. Imported text is normalized separately;
 * this boundary protects diagnostics, media provenance, and hashes.
 */
function plpc_json_encode_durable(mixed $value, int $flags = 0): string
{
    return json_encode(
        $value,
        $flags | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    );
}

/** Normalize invalid UTF-8 and controls that WordPress cannot store. */
function plpc_import_sanitize_post_content(string $blocks): string
{
    if ($blocks !== '' && preg_match('//u', $blocks) !== 1) {
        // JSON's well-tested UTF-8 decoder replaces only malformed byte
        // sequences with U+FFFD. Valid neighboring letters remain intact,
        // unlike dropping an entire line or guessing document-specific text.
        $encoded = plpc_json_encode_durable($blocks);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        $blocks = is_string($decoded) ? $decoded : '';
    }
    // WordPress/database layers remove non-whitespace C0 controls. Normalize
    // them before fingerprinting and insertion so legitimate source damage
    // cannot produce a false round-trip mismatch. Tabs and line endings stay
    // intact; unsupported controls become spaces to avoid joining words.
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $blocks) ?? $blocks;
}

/**
 * Describe the user-visible content that WordPress must preserve when it
 * accepts an imported page. This intentionally ignores byte-for-byte block
 * serialization differences while making silent text or media loss fatal.
 *
 * @return array{rawBytes:int,visibleText:string,visibleTextBytes:int,visibleTextSha256:string,imageCount:int,imageSourcesSha256:string,meaningfulBlockCount:int,orderedStructureCount:int,orderedStructureSha256:string}
 */
function plpc_import_content_fingerprint(string $blocks): array
{
    $withoutComments = preg_replace('/<!--.*?-->/s', ' ', $blocks) ?? $blocks;
    $visibleText = html_entity_decode(strip_tags($withoutComments), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    $visibleText = preg_replace('/\s+/u', ' ', trim($visibleText)) ?? trim($visibleText);
    $imageOccurrences = plpc_rendered_media_occurrences($blocks);
    $imageSources = array_values(array_map(
        static fn (array $occurrence): string => (string) ($occurrence['source'] ?? ''),
        $imageOccurrences
    ));
    $structure = plpc_import_ordered_structure_tokens($blocks);
    $meaningfulBlockCount = trim($blocks) === '' ? 0 : 1;
    if (function_exists('parse_blocks')) {
        $parsed = parse_blocks($blocks);
        if (is_array($parsed)) {
            $meaningfulBlockCount = count(plpc_filter_meaningful_parsed_blocks($parsed));
        }
    }

    return [
        'rawBytes' => strlen($blocks),
        'visibleText' => $visibleText,
        'visibleTextBytes' => strlen($visibleText),
        'visibleTextSha256' => hash('sha256', $visibleText),
        'imageCount' => count($imageSources),
        'imageSourcesSha256' => hash('sha256', plpc_json_encode_durable($imageSources, JSON_UNESCAPED_SLASHES)),
        'meaningfulBlockCount' => $meaningfulBlockCount,
        'orderedStructureCount' => count($structure),
        'orderedStructureSha256' => hash('sha256', plpc_json_encode_durable($structure, JSON_UNESCAPED_SLASHES)),
    ];
}

/**
 * Fingerprint ordered Gutenberg boundaries and the semantic HTML skeleton.
 * Text and media have separate conservation hashes; this sequence catches a
 * paragraph being merged, table cells becoming prose, link targets changing,
 * or otherwise identical blocks moving around.
 *
 * @return list<string>
 */
function plpc_import_ordered_structure_tokens(string $blocks): array
{
    $tokens = [];
    if (preg_match_all('/<!--\s*(\/?)wp:([a-z0-9_\/-]+)(?:\s+(.*?))?\s*-->/is', $blocks, $comments, PREG_SET_ORDER) !== false) {
        foreach ($comments as $comment) {
            $closing = (string) ($comment[1] ?? '') === '/';
            $token = 'block:' . ($closing ? 'close:' : 'open:')
                . strtolower((string) ($comment[2] ?? ''));
            if (!$closing) {
                $rawAttributes = trim((string) ($comment[3] ?? ''));
                $attributes = $rawAttributes === '' ? [] : json_decode($rawAttributes, true);
                $canonical = is_array($attributes)
                    ? plpc_import_canonical_fingerprint_value($attributes)
                    : ['unparsed' => $rawAttributes];
                $token .= ':' . hash('sha256', plpc_json_encode_durable($canonical, JSON_UNESCAPED_SLASHES));
            }
            $tokens[] = $token;
        }
    }

    $semanticTags = array_fill_keys([
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'figure', 'figcaption', 'a', 'img', 'hr', 'br', 'dl', 'dt', 'dd',
    ], true);
    $attributeNames = array_fill_keys([
        'href', 'src', 'rowspan', 'colspan', 'start', 'type', 'dir', 'lang',
        'data-pandoc-pdf-image-original', 'data-plpc-imported-media',
    ], true);
    if (class_exists('DOMDocument')) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML(
                '<!doctype html><html><body>' . $blocks . '</body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($loaded) {
            $body = $dom->getElementsByTagName('body')->item(0);
            $walk = static function (mixed $node) use (&$walk, &$tokens, $semanticTags, $attributeNames): void {
                if ($node instanceof DOMElement) {
                    $tag = strtolower($node->tagName);
                    $included = isset($semanticTags[$tag]);
                    if ($included) {
                        $attributes = [];
                        foreach ($attributeNames as $name => $_) {
                            if ($node->hasAttribute($name)) {
                                $attributes[$name] = html_entity_decode(
                                    trim($node->getAttribute($name)),
                                    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                                    'UTF-8'
                                );
                            }
                        }
                        ksort($attributes);
                        $tokens[] = 'html:open:' . $tag . ':' . hash('sha256', plpc_json_encode_durable($attributes, JSON_UNESCAPED_SLASHES));
                    }
                    foreach ($node->childNodes as $child) {
                        $walk($child);
                    }
                    if ($included && !in_array($tag, ['img', 'hr', 'br'], true)) {
                        $tokens[] = 'html:close:' . $tag;
                    }
                }
            };
            if ($body instanceof DOMElement) {
                foreach ($body->childNodes as $child) {
                    $walk($child);
                }
            }
        }
    } elseif (preg_match_all('/<\/?(p|h[1-6]|blockquote|pre|code|ul|ol|li|table|thead|tbody|tfoot|tr|th|td|figure|figcaption|a|img|hr|br|dl|dt|dd)\b[^>]*>/i', $blocks, $tags) !== false) {
        foreach ($tags[0] ?? [] as $tag) {
            $tokens[] = 'html-fallback:' . strtolower(preg_replace('/\s+/', ' ', trim((string) $tag)) ?? (string) $tag);
        }
    }

    return $tokens;
}

/**
 * Canonicalize Gutenberg attributes before hashing them. WordPress may
 * reserialize object keys without changing their meaning; values such as an
 * image attachment `id`, list `start`, or table dimensions must nevertheless
 * survive the storage round trip exactly.
 */
function plpc_import_canonical_fingerprint_value(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('plpc_import_canonical_fingerprint_value', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = plpc_import_canonical_fingerprint_value($item);
    }

    return $value;
}

/** @param array<string, mixed> $expected */
function plpc_import_assert_content_fingerprint(array $expected, string $storedBlocks, bool $allowEmpty = false): void
{
    $actual = plpc_import_content_fingerprint($storedBlocks);
    $expectedHasContent = (int) ($expected['visibleTextBytes'] ?? 0) > 0
        || (int) ($expected['imageCount'] ?? 0) > 0;
    if ((!$expectedHasContent || (int) ($expected['meaningfulBlockCount'] ?? 0) < 1) && !$allowEmpty) {
        throw new PlpcImportFailure(
            'semantic_output_empty',
            'The converted document did not contain publishable text or media.',
            false,
            'verifying_conversion'
        );
    }
    if (((int) ($actual['rawBytes'] ?? 0) < 1 || (int) ($actual['meaningfulBlockCount'] ?? 0) < 1) && !$allowEmpty) {
        throw new PlpcImportFailure(
            'publication_roundtrip_mismatch',
            'WordPress did not preserve the converted page content. The private conversion bundle is still available for retry.',
            true,
            'verifying_publication'
        );
    }
    if ((int) ($expected['visibleTextBytes'] ?? 0) > 0
        && !hash_equals((string) ($expected['visibleTextSha256'] ?? ''), (string) ($actual['visibleTextSha256'] ?? ''))
    ) {
        throw new PlpcImportFailure(
            'publication_roundtrip_mismatch',
            'WordPress did not preserve the converted page text. The private conversion bundle is still available for retry.',
            true,
            'verifying_publication'
        );
    }
    if ((int) ($expected['imageCount'] ?? 0) !== (int) ($actual['imageCount'] ?? 0)
        || !hash_equals((string) ($expected['imageSourcesSha256'] ?? ''), (string) ($actual['imageSourcesSha256'] ?? ''))
    ) {
        throw new PlpcImportFailure(
            'publication_roundtrip_mismatch',
            'WordPress did not preserve the converted page media. The private conversion bundle is still available for retry.',
            true,
            'verifying_publication'
        );
    }
    if (isset($expected['orderedStructureSha256'])
        && ((int) ($expected['orderedStructureCount'] ?? -1) !== (int) ($actual['orderedStructureCount'] ?? -2)
            || !hash_equals((string) $expected['orderedStructureSha256'], (string) ($actual['orderedStructureSha256'] ?? '')))
    ) {
        throw new PlpcImportFailure(
            'publication_structure_mismatch',
            'WordPress did not preserve the converted block order or structure. The private conversion bundle is still available for retry.',
            true,
            'verifying_publication'
        );
    }
}

/**
 * Insert a private candidate and immediately round-trip it through the real
 * WordPress storage path. A successful post id therefore means its content,
 * not merely its database row, is durable.
 *
 * @param array<string, mixed> $post
 */
function plpc_insert_verified_page(array $post, bool $allowEmpty = false): int
{
    $blocks = plpc_import_sanitize_post_content((string) ($post['post_content'] ?? ''));
    $post['post_content'] = $blocks;
    $expected = plpc_import_content_fingerprint($blocks);
    // Validate before any public side effect, including an empty draft row.
    plpc_import_assert_content_fingerprint($expected, $blocks, $allowEmpty);
    $storedFingerprint = $expected;
    unset($storedFingerprint['visibleText']);
    $post['meta_input'] = is_array($post['meta_input'] ?? null) ? $post['meta_input'] : [];
    $post['meta_input']['_plpc_import_content_fingerprint'] = $storedFingerprint;
    if ($allowEmpty) {
        $post['meta_input']['_plpc_import_allow_empty'] = 1;
    }
    $post['post_status'] = 'draft';
    $postId = wp_insert_post($post, true);
    if (is_wp_error($postId) || (int) $postId < 1) {
        $message = is_wp_error($postId) && method_exists($postId, 'get_error_message')
            ? $postId->get_error_message()
            : 'WordPress could not create the imported page draft.';
        throw new RuntimeException($message);
    }
    $postId = (int) $postId;
    $storedBlocks = function_exists('get_post_field')
        ? (string) get_post_field('post_content', $postId, 'raw')
        : $blocks;
    try {
        plpc_import_assert_content_fingerprint($expected, $storedBlocks, $allowEmpty);
        $status = plpc_import_job_post_status($postId);
        if ($status !== '' && $status !== 'draft') {
            throw new PlpcImportFailure(
                'publication_draft_status_mismatch',
                'WordPress did not preserve the imported page as a private draft.',
                true,
                'preparing_publication'
            );
        }
    } catch (Throwable $error) {
        if (function_exists('wp_delete_post')) {
            wp_delete_post($postId, true);
        }
        throw $error;
    }

    return $postId;
}

/** @return array<string, mixed> */
function plpc_import_page_expected_fingerprint(int $postId, string $storedBlocks): array
{
    $expected = function_exists('get_post_meta')
        ? get_post_meta($postId, '_plpc_import_content_fingerprint', true)
        : [];
    if (is_string($expected) && $expected !== '') {
        $decoded = json_decode($expected, true);
        $expected = is_array($decoded) ? $decoded : [];
    }

    return is_array($expected) && $expected !== []
        ? $expected
        : plpc_import_content_fingerprint($storedBlocks);
}

function plpc_import_verify_stored_page(int $postId): void
{
    $storedBlocks = function_exists('get_post_field')
        ? (string) get_post_field('post_content', $postId, 'raw')
        : '';
    plpc_import_assert_content_fingerprint(
        plpc_import_page_expected_fingerprint($postId, $storedBlocks),
        $storedBlocks,
        function_exists('get_post_meta') && (int) get_post_meta($postId, '_plpc_import_allow_empty', true) === 1
    );
}

/**
 * Replay the already durable global block/media bundle, perform public media
 * side effects, and publish exactly one page.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @param array{label:string,files:list<array{path:string,bytes:string}>}|null $collection
 * @return array<string, mixed>
 */
function plpc_import_job_finalize_pdf_document(
    array $job,
    int $documentIndex,
    array $document,
    string $bytes,
    ?array $collection,
    ?string $title,
    ?callable $reportProgress = null,
    ?array $bundleRecord = null,
    ?int $segmentIndex = null,
    ?array $pageRange = null,
    array $postOptions = [],
    ?string $role = null,
    ?array $materializedBundle = null,
    bool $allowEmpty = false
): array {
    $existingResult = plpc_import_job_existing_pdf_result($job, $documentIndex, $document, $segmentIndex, $role);
    if ($existingResult !== null) {
        plpc_conversion_progress(
            $reportProgress,
            'creating_page',
            'Recovered the complete WordPress page created by the interrupted request.'
        );

        return $existingResult;
    }

    if ($materializedBundle === null) {
        if (!is_array($bundleRecord)) {
            $bundleRecord = is_array($document['pdfFinalBundle'] ?? null) ? $document['pdfFinalBundle'] : null;
        }
        if (!is_array($bundleRecord)) {
            throw new RuntimeException('The finalized PDF bundle is not available.');
        }
        $materializedBundle = plpc_import_job_materialize_pdf_bundle(
            $job,
            $document,
            $bytes,
            $collection,
            $bundleRecord,
            $reportProgress
        );
    }
    $blockMarkup = (string) ($materializedBundle['blocks'] ?? '');
    $diagnostics = array_values(array_map('strval', is_array($materializedBundle['diagnostics'] ?? null) ? $materializedBundle['diagnostics'] : []));
    $imageTagCount = max(0, (int) ($materializedBundle['imageTagCount'] ?? 0));
    $imagesImported = max(0, (int) ($materializedBundle['imagesImported'] ?? 0));
    $mediaDisposition = is_array($materializedBundle['mediaDisposition'] ?? null)
        ? $materializedBundle['mediaDisposition']
        : [];
    $format = (string) ($materializedBundle['format'] ?? $document['format'] ?? 'pdf');
    $quality = is_array($materializedBundle['quality'] ?? null)
        ? $materializedBundle['quality']
        : plpc_import_quality_report($format, $diagnostics, $imageTagCount, $imagesImported);
    plpc_conversion_progress($reportProgress, 'creating_page', 'Creating the WordPress page from the saved PDF conversion.');
    $postTitle = $title !== null && $title !== ''
        ? $title
        : plpc_title_from_filename((string) ($document['path'] ?? 'document.pdf'));
    $sourcePath = (string) ($document['path'] ?? 'document.pdf');
    $resultPath = $sourcePath;
    if (is_array($pageRange) && count($document['pdfSegments'] ?? []) > 1) {
        $resultPath .= '#pages=' . max(1, (int) ($pageRange['startPage'] ?? 1))
            . '-' . max(1, (int) ($pageRange['endPage'] ?? 1));
    }
    $storedResult = [
        'format' => $format,
        'path' => $resultPath,
        'imageTagCount' => $imageTagCount,
        'imagesImported' => $imagesImported,
        'mediaDisposition' => $mediaDisposition,
        'diagnostics' => $diagnostics,
        'quality' => $quality,
    ];
    if ($role !== null) {
        $storedResult['kind'] = $role === 'page' ? 'pdf-page' : $role;
    }
    if (is_array($pageRange)) {
        $storedResult['pageRange'] = [
            'startPage' => max(1, (int) ($pageRange['startPage'] ?? 1)),
            'endPage' => max(1, (int) ($pageRange['endPage'] ?? 1)),
        ];
    }
    if ($allowEmpty) {
        $storedResult['intentionalBlank'] = true;
    }
    $metaInput = [
        '_plpc_import_job_id' => (string) ($job['id'] ?? ''),
        '_plpc_import_document_index' => $documentIndex,
        '_plpc_import_result' => $storedResult,
    ];
    if ($segmentIndex !== null) {
        $metaInput['_plpc_import_segment_index'] = $segmentIndex;
    }
    if ($role !== null) {
        $metaInput['_plpc_import_pdf_role'] = $role;
    }
    $post = array_replace([
        'post_type' => 'page',
        'post_title' => $postTitle,
        'post_content' => $blockMarkup,
        'meta_input' => $metaInput,
    ], $postOptions);
    $post['meta_input'] = array_replace($metaInput, is_array($postOptions['meta_input'] ?? null) ? $postOptions['meta_input'] : []);
    $postId = plpc_insert_verified_page($post, $allowEmpty);

    return [
        'postId' => (int) $postId,
        'pageUrl' => get_permalink((int) $postId),
        'editUrl' => get_edit_post_link((int) $postId, 'raw'),
        'format' => $storedResult['format'],
        'title' => get_the_title((int) $postId),
        'path' => $storedResult['path'],
        'imageTagCount' => $storedResult['imageTagCount'],
        'imagesImported' => $storedResult['imagesImported'],
        'mediaDisposition' => $storedResult['mediaDisposition'],
        'diagnostics' => $storedResult['diagnostics'],
        'quality' => $storedResult['quality'],
    ] + (isset($storedResult['pageRange']) ? ['pageRange' => $storedResult['pageRange']] : [])
        + (isset($storedResult['kind']) ? ['kind' => $storedResult['kind']] : [])
        + (isset($storedResult['intentionalBlank']) ? ['intentionalBlank' => true] : []);
}

/**
 * A worker can terminate after wp_insert_post() commits and before the job
 * option records completion. Find the already-published page by a stable
 * job/document identity so the next request never creates a duplicate.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $document
 * @return array<string, mixed>|null
 */
function plpc_import_job_existing_pdf_result(
    array $job,
    int $documentIndex,
    array $document,
    ?int $segmentIndex = null,
    ?string $role = null
): ?array
{
    $jobId = (string) ($job['id'] ?? '');
    if ($jobId === '' || !function_exists('get_posts')) {
        return null;
    }
    $metaQuery = [
        ['key' => '_plpc_import_job_id', 'value' => $jobId],
        ['key' => '_plpc_import_document_index', 'value' => (string) $documentIndex],
    ];
    if ($segmentIndex !== null) {
        $metaQuery[] = ['key' => '_plpc_import_segment_index', 'value' => (string) $segmentIndex];
    }
    if ($role !== null) {
        $metaQuery[] = ['key' => '_plpc_import_pdf_role', 'value' => $role];
    }
    $postIds = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => $metaQuery,
    ]);
    $postId = max(0, (int) ($postIds[0] ?? 0));
    if ($postId < 1) {
        return null;
    }

    try {
        plpc_import_verify_stored_page($postId);
    } catch (PlpcImportFailure) {
        // The authoritative block/media bundle is still private and durable.
        // Remove a torn draft so this retry can replay it without creating a
        // duplicate or mistaking an empty database row for completed work.
        if (function_exists('wp_delete_post')) {
            wp_delete_post($postId, true);
        }

        return null;
    }

    $stored = function_exists('get_post_meta')
        ? get_post_meta($postId, '_plpc_import_result', true)
        : [];
    if (is_string($stored) && $stored !== '') {
        $decoded = json_decode($stored, true);
        $stored = is_array($decoded) ? $decoded : [];
    }
    $stored = is_array($stored) ? $stored : [];
    $format = (string) ($stored['format'] ?? $document['format'] ?? 'pdf');
    $diagnostics = array_values(array_map('strval', is_array($stored['diagnostics'] ?? null) ? $stored['diagnostics'] : []));
    $quality = is_array($stored['quality'] ?? null)
        ? $stored['quality']
        : plpc_import_quality_report($format, $diagnostics, 0, 0);

    return [
        'postId' => $postId,
        'pageUrl' => get_permalink($postId),
        'editUrl' => get_edit_post_link($postId, 'raw'),
        'format' => $format,
        'title' => get_the_title($postId),
        'path' => (string) ($stored['path'] ?? $document['path'] ?? 'document.pdf'),
        'imageTagCount' => max(0, (int) ($stored['imageTagCount'] ?? 0)),
        'imagesImported' => max(0, (int) ($stored['imagesImported'] ?? 0)),
        'mediaDisposition' => is_array($stored['mediaDisposition'] ?? null) ? $stored['mediaDisposition'] : [],
        'diagnostics' => $diagnostics,
        'quality' => $quality,
    ] + (is_array($stored['pageRange'] ?? null) ? ['pageRange' => $stored['pageRange']] : [])
        + (isset($stored['kind']) ? ['kind' => (string) $stored['kind']] : []);
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
    $originalBlocks = $blocks;

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
        // unresolved source would defeat that memory boundary. Replace the
        // unusable image with a visible placeholder instead of publishing a
        // broken relative URL.
        $placeholderBlocks = $mediaResult['blocks'];
        $placeholderDiagnostics = [];
        foreach ($remainingSources as $source) {
            $placeholderBlocks = plpc_replace_unresolved_image_source_with_placeholder($placeholderBlocks, $source);
            $placeholderDiagnostics[] = 'image-not-resolved:' . $source;
            $placeholderDiagnostics[] = 'image-placeholder:' . $source;
        }
        $fallbackMediaResult = [
            'blocks' => $placeholderBlocks,
            'imported' => 0,
            'diagnostics' => $placeholderDiagnostics,
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
    $imageTagCount = count(plpc_rendered_media_occurrences($originalBlocks));
    $imagesImported = $mediaResult['imported'] + $fallbackMediaResult['imported'];
    $quality = plpc_import_quality_report($format, $diagnostics, $imageTagCount, $imagesImported);
    $mediaDisposition = plpc_import_media_disposition_summary($originalBlocks, $blocks, $diagnostics);
    plpc_import_assert_media_disposition($blocks, $mediaDisposition);

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
        $originalBlocks,
        $file,
        $bytes,
        $collection,
        $options
    );
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    plpc_conversion_progress($reportProgress, 'creating_page', 'Creating the WordPress page.');
    $postId = plpc_insert_verified_page([
        'post_type' => 'page',
        'post_title' => $postTitle,
        'post_content' => $blocks,
        'meta_input' => [
            '_plpc_import_result' => [
                'format' => $format,
                'path' => $path,
                'imageTagCount' => $imageTagCount,
                'imagesImported' => $imagesImported,
                'mediaDisposition' => $mediaDisposition,
                'diagnostics' => $diagnostics,
                'quality' => $quality,
            ],
        ],
    ]);
    // Legacy/direct conversions are synchronous and retain their published
    // API. Persisted import jobs pass a progress callback and publish their
    // verified draft later through the resumable publication cursor.
    if ($reportProgress === null) {
        $published = wp_update_post(['ID' => $postId, 'post_status' => 'publish'], true);
        if (is_wp_error($published) || (int) $published < 1) {
            $message = is_wp_error($published) && method_exists($published, 'get_error_message')
                ? $published->get_error_message()
                : 'WordPress could not publish the verified imported page.';
            throw new PlpcImportFailure('publication_update_failed', $message, true, 'publishing');
        }
        plpc_import_verify_stored_page($postId);
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
        'mediaDisposition' => $mediaDisposition,
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
    $anchor = plpc_browser_pdf_form_normalize_anchor_text($anchor);
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
 * Positioned PDF text commonly ends an extracted visual line with a hyphen
 * before continuing the word on the next line.  A placement anchor is a
 * prefix used to find the final, reflowed WordPress paragraph, so discard
 * only a terminal discretionary hyphen.  This is deliberately content
 * agnostic: it applies equally to captions, prose, and technical labels.
 */
function plpc_browser_pdf_form_normalize_anchor_text(string $text): string
{
    $normalized = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

    return preg_replace('/(?:-|\x{00AD})$/u', '', $normalized) ?? $normalized;
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
    $visualId = (string) ($render['visualId'] ?? $render['formId'] ?? '');
    if ($visualId !== '') {
        $attributes['data-pandoc-pdf-visual-id'] = $visualId;
        $attributes['data-pandoc-pdf-visual-kind'] = (string) ($render['visualKind'] ?? 'form-xobject');
    }
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

    return $blocks;
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
 * @return array{readerOptions: array<string, mixed>, writerOptions: array<string, mixed>}
 */
function plpc_converter_options(string $format, string $pdfMode = 'layout'): array
{
    $readerOptions = [];
    $canonicalFormat = PandocConverter::canonicalInputFormat($format);
    if ($canonicalFormat === 'pdf') {
        $pdfMode = plpc_normalize_pdf_mode($pdfMode);
        // Page-range jobs bound work by original PDF pages. A second global
        // text ceiling would silently clip a long page or a later page in a
        // direct conversion, so text stays complete within each range.
        $readerOptions['maxTextBytes'] = PHP_INT_MAX;
        $readerOptions['pdfFastTextOnly'] = false;
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
function plpc_pdf_raster_images_from_payload(mixed $images, int $maxTotalBytes = PLPC_MAX_PDF_RASTER_BYTES): array
{
    if (!is_array($images)) {
        return [];
    }

    $maxTotalBytes = max(0, min(PLPC_MAX_PDF_RASTER_BYTES, $maxTotalBytes));
    if ($maxTotalBytes === 0) {
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
        if ($totalBytes > $maxTotalBytes) {
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
function plpc_pdf_raster_images_by_path(mixed $images, int $maxTotalBytes = PLPC_MAX_PDF_RASTER_BYTES): array
{
    if (!is_array($images)) {
        return [];
    }

    $remainingBytes = max(0, min(PLPC_MAX_PDF_RASTER_BYTES, $maxTotalBytes));
    $byPath = [];
    foreach ($images as $path => $records) {
        if ($remainingBytes <= 0) {
            break;
        }
        if (!is_string($path)) {
            continue;
        }
        $path = plpc_normalize_collection_path($path);
        if ($path === '') {
            continue;
        }
        $decoded = plpc_pdf_raster_images_from_payload($records, $remainingBytes);
        if ($decoded !== []) {
            $byPath[$path] = $decoded;
            foreach ($decoded as $raster) {
                $remainingBytes -= strlen((string) ($raster['contents'] ?? ''));
            }
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

function plpc_normalize_pdf_output_mode(mixed $mode): string
{
    $mode = strtolower(str_replace(['_', ' '], '-', trim((string) $mode)));

    return match ($mode) {
        'pages', 'page', 'per-page', 'one-per-page', 'children', 'page-tree' => 'pages',
        default => 'single',
    };
}

/** @param array<string, mixed> $job */
function plpc_import_job_pdf_output_mode(array $job): string
{
    if (!array_key_exists('pdfOutputMode', $job) && (int) ($job['version'] ?? 0) < 3) {
        return 'legacy_ranges';
    }

    return plpc_normalize_pdf_output_mode($job['pdfOutputMode'] ?? 'single');
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
    foreach (plpc_rendered_media_occurrences($blocks) as $occurrence) {
        $source = (string) ($occurrence['source'] ?? '');
        if ($source !== '') {
            $sources[$source] = true;
        }
    }

    return array_keys($sources);
}

/**
 * Preserve occurrence order and duplicates. A source list is convenient for
 * deduplicated uploads, but publication integrity must account for two uses
 * of the same image as two independently placed visual occurrences.
 *
 * @return list<array{index:int,kind:string,source:string,sourceId:string,sourceKind:string}>
 */
function plpc_rendered_media_occurrences(string $blocks): array
{
    $occurrences = [];
    $collectHtml = static function (string $html) use (&$occurrences): void {
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
        $body = $dom->getElementsByTagName('body')->item(0);
        $walk = static function (
            mixed $node,
            string $inheritedSourceId = '',
            string $inheritedSourceKind = ''
        ) use (&$walk, &$occurrences): void {
            if (!$node instanceof DOMElement) {
                return;
            }
            $tag = strtolower($node->tagName);
            $sourceId = trim($node->getAttribute('data-pandoc-pdf-visual-id'));
            if ($sourceId === '') {
                $sourceId = $inheritedSourceId;
            }
            $sourceKind = trim($node->getAttribute('data-pandoc-pdf-visual-kind'));
            if ($sourceKind === '') {
                $sourceKind = $inheritedSourceKind;
            }
            $kind = null;
            $source = '';
            if ($tag === 'img') {
                $kind = 'image';
                $source = $node->getAttribute('src');
            } elseif ($tag === 'a' && $node->getAttribute('data-pandoc-pdf-image-original') === 'true') {
                $kind = 'original-download';
                $source = $node->getAttribute('href');
            }
            $source = html_entity_decode(trim($source), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            if ($kind !== null && $source !== '') {
                $occurrences[] = [
                    'index' => count($occurrences),
                    'kind' => $kind,
                    'source' => $source,
                    'sourceId' => $sourceId,
                    'sourceKind' => $sourceKind,
                ];
            }
            foreach ($node->childNodes as $child) {
                $walk($child, $sourceId, $sourceKind);
            }
        };
        if ($body instanceof DOMElement) {
            foreach ($body->childNodes as $child) {
                $walk($child);
            }
        }
    };

    if (function_exists('parse_blocks')) {
        $parsed = parse_blocks($blocks);
        if (is_array($parsed)) {
            $walkBlocks = static function (array $parsedBlocks) use (&$walkBlocks, $collectHtml): void {
                foreach ($parsedBlocks as $block) {
                    if (!is_array($block)) {
                        continue;
                    }
                    if (is_string($block['innerHTML'] ?? null) && $block['innerHTML'] !== '') {
                        $collectHtml($block['innerHTML']);
                    }
                    if (is_array($block['innerBlocks'] ?? null) && $block['innerBlocks'] !== []) {
                        $walkBlocks($block['innerBlocks']);
                    }
                }
            };
            $walkBlocks($parsed);

            return $occurrences;
        }
    }
    $collectHtml($blocks);

    return $occurrences;
}

/** @return array<string, int> */
function plpc_rendered_media_occurrence_counts(string $blocks): array
{
    $counts = [];
    foreach (plpc_rendered_media_occurrences($blocks) as $occurrence) {
        $source = (string) ($occurrence['source'] ?? '');
        if ($source !== '') {
            $counts[$source] = max(0, (int) ($counts[$source] ?? 0)) + 1;
        }
    }

    return $counts;
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
    $occurrenceCounts = plpc_rendered_media_occurrence_counts($blocks);
    $package = plpc_zip_package($uploadedBytes);
    foreach ($imageSources as $source) {
        $resolved = plpc_resolve_image_source($source, $uploadedBytes, $package, $collection, $documentPath);
        if ($resolved === null) {
            $diagnostics[] = 'image-not-resolved:' . $source;
            $blocks = plpc_replace_unresolved_image_source_with_placeholder($blocks, $source);
            $diagnostics[] = 'image-placeholder:' . $source;
            continue;
        }

        $attachment = plpc_insert_media_attachment($resolved['bytes'], $resolved['filename'], $resolved['mimeType']);
        if ($attachment === null) {
            $diagnostics[] = 'image-upload-failed:' . $source;
            $blocks = plpc_replace_unresolved_image_source_with_placeholder($blocks, $source);
            $diagnostics[] = 'image-placeholder:' . $source;
            continue;
        }

        $blocks = plpc_replace_image_source($blocks, $source, $attachment['url'], $attachment['id']);
        $imported += max(1, (int) ($occurrenceCounts[$source] ?? 1));
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
    $occurrenceCounts = plpc_rendered_media_occurrence_counts($blocks);
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
            $blocks = plpc_replace_unresolved_image_source_with_placeholder($blocks, $source);
            $diagnostics[] = 'image-placeholder:' . $source;
            $importedSources[] = $source;
            continue;
        }

        $blocks = plpc_replace_image_source($blocks, $source, $attachment['url'], $attachment['id']);
        $imported += max(1, (int) ($occurrenceCounts[$source] ?? 1));
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

/**
 * Account for every original visual occurrence after upload/materialization.
 * Raw source strings are hashed in the durable ledger so data URIs and local
 * package paths do not bloat options or REST responses.
 *
 * @param list<string> $diagnostics
 * @return array{totalOccurrences:int,attachmentOccurrences:int,placeholderOccurrences:int,intentionalOmissionOccurrences:int,unresolvedOccurrences:int,ledger:list<array<string,mixed>>,sourceDispositions:array<string,array<string,mixed>>,ledgerSha256:string}
 */
function plpc_import_media_disposition_summary(string $originalBlocks, string $finalBlocks, array $diagnostics): array
{
    $dispositions = [];
    foreach ($diagnostics as $diagnostic) {
        $diagnostic = (string) $diagnostic;
        if (preg_match('/\Aimage-imported:(.*)=>\d+\z/s', $diagnostic, $match) === 1) {
            $dispositions[$match[1]] = 'attachment';
        } elseif (str_starts_with($diagnostic, 'image-placeholder:')) {
            $dispositions[substr($diagnostic, strlen('image-placeholder:'))] = 'placeholder';
        }
    }
    $finalSources = [];
    foreach (plpc_rendered_media_occurrences($finalBlocks) as $occurrence) {
        $finalSources[(string) ($occurrence['source'] ?? '')] = true;
    }
    $anonymousLedger = [];
    $sourceDispositions = [];
    foreach (plpc_rendered_media_occurrences($originalBlocks) as $occurrence) {
        $source = (string) ($occurrence['source'] ?? '');
        $sourceId = trim((string) ($occurrence['sourceId'] ?? ''));
        $disposition = (string) ($dispositions[$source] ?? '');
        if ($disposition === '') {
            // If a source survived unchanged, it was not made durable and is
            // unresolved. If it disappeared without an explicit diagnostic,
            // treat that as unresolved as well rather than silently passing.
            $disposition = 'unresolved';
        }
        $entry = [
            'index' => max(0, (int) ($occurrence['index'] ?? count($anonymousLedger))),
            'kind' => (string) ($occurrence['kind'] ?? 'image'),
            'sourceSha256' => hash('sha256', $source),
            'disposition' => $disposition,
            'survivedUnchanged' => isset($finalSources[$source]),
        ];
        if ($sourceId !== '') {
            $entry['sourceId'] = $sourceId;
            $entry['sourceKind'] = (string) ($occurrence['sourceKind'] ?? '');
            if (isset($sourceDispositions[$sourceId])) {
                $existing = $sourceDispositions[$sourceId];
                if (($existing['sourceSha256'] ?? '') !== $entry['sourceSha256']) {
                    $existing['disposition'] = 'unresolved';
                    $existing['sourceConflict'] = true;
                } else {
                    $existing['disposition'] = plpc_import_preferred_media_disposition(
                        (string) ($existing['disposition'] ?? 'unresolved'),
                        $disposition
                    );
                }
                $existing['survivedUnchanged'] = (bool) ($existing['survivedUnchanged'] ?? false)
                    || $entry['survivedUnchanged'];
                $sourceDispositions[$sourceId] = $existing;
            } else {
                $sourceDispositions[$sourceId] = $entry;
            }
            continue;
        }
        $anonymousLedger[] = $entry;
    }
    $ledger = array_merge($anonymousLedger, array_values($sourceDispositions));
    foreach ($ledger as $index => &$entry) {
        $entry['index'] = $index;
    }
    unset($entry);
    $encodedLedger = plpc_json_encode_durable($ledger, JSON_UNESCAPED_SLASHES);
    $counts = plpc_import_media_disposition_counts($ledger);

    return $counts + [
        'ledger' => $ledger,
        'sourceDispositions' => $sourceDispositions,
        'ledgerSha256' => hash('sha256', $encodedLedger),
    ];
}

function plpc_import_preferred_media_disposition(string $left, string $right): string
{
    $rank = [
        'unresolved' => 0,
        'intentional_omission' => 1,
        'placeholder' => 2,
        'attachment' => 3,
    ];
    $left = isset($rank[$left]) ? $left : 'unresolved';
    $right = isset($rank[$right]) ? $right : 'unresolved';

    return $rank[$right] > $rank[$left] ? $right : $left;
}

/**
 * @param list<array<string,mixed>> $ledger
 * @return array{totalOccurrences:int,attachmentOccurrences:int,placeholderOccurrences:int,intentionalOmissionOccurrences:int,unresolvedOccurrences:int}
 */
function plpc_import_media_disposition_counts(array $ledger): array
{
    $counts = [
        'totalOccurrences' => 0,
        'attachmentOccurrences' => 0,
        'placeholderOccurrences' => 0,
        'intentionalOmissionOccurrences' => 0,
        'unresolvedOccurrences' => 0,
    ];
    foreach ($ledger as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $counts['totalOccurrences']++;
        $field = match ((string) ($entry['disposition'] ?? 'unresolved')) {
            'attachment' => 'attachmentOccurrences',
            'placeholder' => 'placeholderOccurrences',
            'intentional_omission' => 'intentionalOmissionOccurrences',
            default => 'unresolvedOccurrences',
        };
        $counts[$field]++;
    }

    return $counts;
}

/** @param list<array<string, mixed>> $summaries @return array<string, mixed> */
function plpc_import_aggregate_media_dispositions(array $summaries): array
{
    $legacyCounts = [
        'totalOccurrences' => 0,
        'attachmentOccurrences' => 0,
        'placeholderOccurrences' => 0,
        'intentionalOmissionOccurrences' => 0,
        'unresolvedOccurrences' => 0,
    ];
    $legacyHashes = [];
    $anonymousLedger = [];
    $sourceDispositions = [];
    foreach ($summaries as $summary) {
        if (!is_array($summary)) {
            continue;
        }
        if (!is_array($summary['ledger'] ?? null)) {
            foreach (array_keys($legacyCounts) as $field) {
                $legacyCounts[$field] += max(0, (int) ($summary[$field] ?? 0));
            }
            $hash = (string) ($summary['ledgerSha256'] ?? '');
            if ($hash !== '') {
                $legacyHashes[] = $hash;
            }
            continue;
        }
        foreach ($summary['ledger'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sourceId = trim((string) ($entry['sourceId'] ?? ''));
            if ($sourceId === '') {
                $anonymousLedger[] = $entry;
                continue;
            }
            if (!isset($sourceDispositions[$sourceId])) {
                $sourceDispositions[$sourceId] = $entry;
                continue;
            }
            $existing = $sourceDispositions[$sourceId];
            $existingHash = (string) ($existing['sourceSha256'] ?? '');
            $incomingHash = (string) ($entry['sourceSha256'] ?? '');
            if ($existingHash !== '' && $incomingHash !== '' && !hash_equals($existingHash, $incomingHash)) {
                $existing['disposition'] = 'unresolved';
                $existing['sourceConflict'] = true;
            } else {
                $existing['disposition'] = plpc_import_preferred_media_disposition(
                    (string) ($existing['disposition'] ?? 'unresolved'),
                    (string) ($entry['disposition'] ?? 'unresolved')
                );
            }
            $existing['survivedUnchanged'] = (bool) ($existing['survivedUnchanged'] ?? false)
                || (bool) ($entry['survivedUnchanged'] ?? false);
            $sourceDispositions[$sourceId] = $existing;
        }
    }
    ksort($sourceDispositions, SORT_STRING);
    $ledger = array_merge($anonymousLedger, array_values($sourceDispositions));
    foreach ($ledger as $index => &$entry) {
        $entry['index'] = $index;
    }
    unset($entry);
    $aggregate = plpc_import_media_disposition_counts($ledger);
    foreach (array_keys($legacyCounts) as $field) {
        $aggregate[$field] += $legacyCounts[$field];
    }
    $aggregate['ledger'] = $ledger;
    $aggregate['sourceDispositions'] = $sourceDispositions;
    $aggregate['ledgerSha256'] = hash('sha256', plpc_json_encode_durable([
        'ledger' => $ledger,
        'legacy' => $legacyHashes,
    ], JSON_UNESCAPED_SLASHES));

    return $aggregate;
}

/**
 * Add compact dispositions for page-layout Forms which deliberately did not
 * become ordinary image blocks. This is applied once per source PDF after its
 * segment-level media ledgers are aggregated, so neither chunking nor output
 * mode can double count an occurrence.
 *
 * @param array<string, mixed> $summary
 * @param array<string, mixed> $job
 * @return array<string, mixed>
 */
function plpc_import_add_pdf_visual_dispositions(array $summary, array $job, string $path): array
{
    $document = null;
    foreach ($job['documents'] ?? [] as $candidate) {
        if (is_array($candidate) && (string) ($candidate['path'] ?? '') === $path) {
            $document = $candidate;
            break;
        }
    }
    if (is_array($document) && array_key_exists('pdfVisualOccurrences', $document)) {
        $inventory = is_array($document['pdfVisualOccurrences'])
            ? array_values(array_filter($document['pdfVisualOccurrences'], 'is_array'))
            : [];
        $inventoryComplete = ($document['pdfVisualInventoryComplete'] ?? false) === true;
        $hasInspectionIssue = false;
        foreach ($inventory as $occurrence) {
            if ((string) ($occurrence['kind'] ?? '') === 'inspection-issue') {
                $hasInspectionIssue = true;
                break;
            }
        }
        if (!$inventoryComplete && !$hasInspectionIssue) {
            $inventory[] = [
                'id' => 'pdf-visual-inventory-incomplete-' . substr(hash('sha256', $path), 0, 20),
                'kind' => 'inspection-issue',
                'page' => 1,
                'object' => 0,
                'paintOrder' => 0,
                'disposition' => 'unresolved',
                'reason' => 'visual-inventory-incomplete',
            ];
        }

        $existingLedger = is_array($summary['ledger'] ?? null) ? array_values($summary['ledger']) : [];
        $representedCounts = plpc_import_media_disposition_counts($existingLedger);
        $legacyCounts = [];
        foreach (array_keys($representedCounts) as $field) {
            $legacyCounts[$field] = max(
                0,
                (int) ($summary[$field] ?? 0) - (int) ($representedCounts[$field] ?? 0)
            );
        }
        $emittedById = is_array($summary['sourceDispositions'] ?? null)
            ? $summary['sourceDispositions']
            : [];
        foreach ($existingLedger as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sourceId = trim((string) ($entry['sourceId'] ?? ''));
            if ($sourceId !== '' && !isset($emittedById[$sourceId])) {
                $emittedById[$sourceId] = $entry;
            }
        }

        $inventoryIds = [];
        foreach ($inventory as $index => &$occurrence) {
            $sourceId = trim((string) ($occurrence['id'] ?? ''));
            if ($sourceId === '') {
                $sourceId = 'pdf-visual-missing-id-' . substr(hash(
                    'sha256',
                    $path . "\0" . (string) $index . "\0" . plpc_json_encode_durable($occurrence, JSON_UNESCAPED_SLASHES)
                ), 0, 24);
                $occurrence['id'] = $sourceId;
                $occurrence['disposition'] = 'unresolved';
                $occurrence['reason'] = 'visual-source-id-missing';
            }
            if (isset($inventoryIds[$sourceId])) {
                $sourceId .= '-duplicate-' . ($index + 1);
                $occurrence['id'] = $sourceId;
                $occurrence['disposition'] = 'unresolved';
                $occurrence['reason'] = 'visual-source-id-duplicated';
            }
            $inventoryIds[$sourceId] = true;
        }
        unset($occurrence);

        $anonymousLedger = [];
        $sourceDispositions = [];
        foreach ($existingLedger as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sourceId = trim((string) ($entry['sourceId'] ?? ''));
            if ($sourceId === '') {
                $anonymousLedger[] = $entry;
            } elseif (!isset($inventoryIds[$sourceId])) {
                $sourceDispositions[$sourceId] = $entry;
            }
        }

        $sourceOccurrences = [];
        foreach ($inventory as $occurrence) {
            $sourceId = (string) ($occurrence['id'] ?? '');
            $kind = (string) ($occurrence['kind'] ?? 'unknown');
            $emitted = is_array($emittedById[$sourceId] ?? null) ? $emittedById[$sourceId] : null;
            $emittedDisposition = (string) ($emitted['disposition'] ?? '');
            $storedDisposition = (string) ($occurrence['disposition'] ?? 'pending');
            if (in_array($emittedDisposition, ['attachment', 'placeholder'], true)) {
                $disposition = $emittedDisposition;
                $reason = $emittedDisposition === 'attachment'
                    ? 'materialized-as-wordpress-attachment'
                    : 'materialized-as-visible-placeholder';
            } elseif ($storedDisposition === 'intentional_omission') {
                $disposition = 'intentional_omission';
                $reason = (string) ($occurrence['reason'] ?? 'visual-intentionally-omitted');
            } else {
                $disposition = 'unresolved';
                $reason = (string) ($occurrence['reason'] ?? '');
                if ($reason === '' || $storedDisposition === 'pending') {
                    $reason = $kind === 'image-xobject'
                        ? 'server-raster-occurrence-not-materialized'
                        : 'browser-render-occurrence-not-materialized';
                }
            }
            $sourceEntry = [
                'id' => $sourceId,
                'kind' => $kind,
                'page' => max(1, (int) ($occurrence['page'] ?? 1)),
                'object' => max(0, (int) ($occurrence['object'] ?? 0)),
                'paintOrder' => max(0, (int) ($occurrence['paintOrder'] ?? 0)),
                'disposition' => $disposition,
                'reason' => substr($reason, 0, 120),
            ];
            $sourceOccurrences[] = $sourceEntry;
            $ledgerEntry = [
                'sourceId' => $sourceId,
                'sourceKind' => $kind,
                'kind' => (string) ($emitted['kind'] ?? 'pdf-visual'),
                'sourceSha256' => (string) ($emitted['sourceSha256'] ?? hash('sha256', $path . "\0" . $sourceId)),
                'disposition' => $disposition,
                'survivedUnchanged' => (bool) ($emitted['survivedUnchanged'] ?? false),
                'page' => $sourceEntry['page'],
                'paintOrder' => $sourceEntry['paintOrder'],
                'reason' => $sourceEntry['reason'],
            ];
            $sourceDispositions[$sourceId] = $ledgerEntry;
        }
        ksort($sourceDispositions, SORT_STRING);
        $ledger = array_merge($anonymousLedger, array_values($sourceDispositions));
        foreach ($ledger as $index => &$entry) {
            $entry['index'] = $index;
        }
        unset($entry);
        $counts = plpc_import_media_disposition_counts($ledger);
        foreach (array_keys($legacyCounts) as $field) {
            $counts[$field] += $legacyCounts[$field];
        }

        return $counts + [
            'ledger' => $ledger,
            'sourceDispositions' => $sourceDispositions,
            'sourceOccurrences' => $sourceOccurrences,
            'sourceOccurrenceCount' => count($sourceOccurrences),
            'inventoryComplete' => $inventoryComplete,
            'accountedOccurrences' => $counts['totalOccurrences'],
            'resolvedOccurrences' => max(0, $counts['totalOccurrences'] - $counts['unresolvedOccurrences']),
            'complete' => $inventoryComplete && $counts['unresolvedOccurrences'] === 0,
            'ledgerSha256' => hash('sha256', plpc_json_encode_durable([
                'ledger' => $ledger,
                'legacyCounts' => $legacyCounts,
                'inventoryComplete' => $inventoryComplete,
            ], JSON_UNESCAPED_SLASHES)),
        ];
    }

    // Backward-compatible reconciliation for jobs created before the source
    // occurrence inventory was persisted on each document.
    $byPath = is_array($job['pdfVisualDispositionsByPath'] ?? null)
        ? $job['pdfVisualDispositionsByPath']
        : [];
    $supplement = is_array($byPath[$path] ?? null) ? $byPath[$path] : [];
    $total = max(0, (int) ($supplement['totalOccurrences'] ?? 0));
    $intentional = max(0, (int) ($supplement['intentionalOmissionOccurrences'] ?? 0));
    $unresolved = max(0, (int) ($supplement['unresolvedOccurrences'] ?? 0));
    if ($total === 0) {
        $summary['intentionalOmissionOccurrences'] = max(
            0,
            (int) ($summary['intentionalOmissionOccurrences'] ?? 0)
        );

        return $summary;
    }
    $summary['totalOccurrences'] = max(0, (int) ($summary['totalOccurrences'] ?? 0)) + $total;
    $summary['intentionalOmissionOccurrences'] = max(
        0,
        (int) ($summary['intentionalOmissionOccurrences'] ?? 0)
    ) + $intentional;
    $summary['unresolvedOccurrences'] = max(0, (int) ($summary['unresolvedOccurrences'] ?? 0)) + $unresolved;
    $summary['ledgerSha256'] = hash(
        'sha256',
        (string) ($summary['ledgerSha256'] ?? hash('sha256', ''))
            . "\0"
            . (string) ($supplement['ledgerSha256'] ?? hash('sha256', ''))
    );

    return $summary;
}

/** @param array<string, mixed> $summary */
function plpc_import_assert_media_disposition(string $blocks, array $summary): void
{
    if ((int) ($summary['unresolvedOccurrences'] ?? 0) > 0) {
        throw new PlpcImportFailure(
            'media_disposition_incomplete',
            'One or more document images were neither imported nor replaced by a visible placeholder.',
            true,
            'uploading_media'
        );
    }
    if (!class_exists('DOMDocument')) {
        if (preg_match('/<img\b[^>]*\bsrc\s*=\s*["\'](?!https?:\/\/)[^"\']*["\']/i', $blocks) === 1) {
            throw new PlpcImportFailure('media_reference_not_durable', 'A non-durable image reference remained after media materialization.', true, 'uploading_media');
        }

        return;
    }
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML(
            '<!doctype html><html><body>' . $blocks . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        throw new PlpcImportFailure('media_reference_not_durable', 'The final image markup could not be verified.', true, 'uploading_media');
    }
    foreach ($dom->getElementsByTagName('img') as $image) {
        if (!$image instanceof DOMElement) {
            continue;
        }
        $source = html_entity_decode(trim($image->getAttribute('src')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $hasAttachmentMarker = preg_match('/(?:^|\s)wp-image-\d+(?:\s|$)/', $image->getAttribute('class')) === 1
            || preg_match('/\A\d+\z/', $image->getAttribute('data-plpc-imported-media')) === 1;
        if ($source === '' || preg_match('/\Ahttps?:\/\//i', $source) !== 1 || !$hasAttachmentMarker) {
            throw new PlpcImportFailure(
                'media_reference_not_durable',
                'An unverified image reference remained after media materialization.',
                true,
                'uploading_media'
            );
        }
    }
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

    return plpc_replace_image_source_in_html($blocks, $source, $url, $attachmentId);
}

/**
 * Replace an unresolved image element with visible, inert content. Leaving a
 * relative media path in a published post creates a broken image while still
 * satisfying a simple tag-count check. The source is retained as metadata,
 * and PDF original-download links remain links rather than broken images.
 */
function plpc_replace_unresolved_image_source_with_placeholder(string $blocks, string $source): string
{
    if (function_exists('parse_blocks') && function_exists('serialize_blocks')) {
        $parsed = parse_blocks($blocks);
        if (is_array($parsed)) {
            $changed = false;
            plpc_replace_unresolved_image_source_in_blocks($parsed, $source, $changed);
            if ($changed) {
                return serialize_blocks($parsed);
            }
        }
    }

    $matched = false;

    return plpc_replace_unresolved_image_source_in_html($blocks, $source, $matched);
}

/** @param list<array<string, mixed>> $blocks */
function plpc_replace_unresolved_image_source_in_blocks(array &$blocks, string $source, bool &$changed): void
{
    foreach ($blocks as &$block) {
        if (!is_array($block)) {
            continue;
        }
        $blockMatched = false;
        if (is_string($block['innerHTML'] ?? null)) {
            $matched = false;
            $block['innerHTML'] = plpc_replace_unresolved_image_source_in_html($block['innerHTML'], $source, $matched);
            $changed = $changed || $matched;
            $blockMatched = $blockMatched || $matched;
        }
        if (is_array($block['innerContent'] ?? null)) {
            foreach ($block['innerContent'] as &$content) {
                if (!is_string($content) || $content === '') {
                    continue;
                }
                $matched = false;
                $content = plpc_replace_unresolved_image_source_in_html($content, $source, $matched);
                $changed = $changed || $matched;
                $blockMatched = $blockMatched || $matched;
            }
            unset($content);
        }
        if ($blockMatched && ($block['blockName'] ?? '') === 'core/image') {
            // A core/image block without an <img> is invalid in Gutenberg.
            // The inert placeholder is intentionally a valid Custom HTML
            // block so opening the imported page does not trigger recovery.
            $block['blockName'] = 'core/html';
            $block['attrs'] = [];
        }
        if (is_array($block['innerBlocks'] ?? null) && $block['innerBlocks'] !== []) {
            plpc_replace_unresolved_image_source_in_blocks($block['innerBlocks'], $source, $changed);
        }
    }
    unset($block);
}

function plpc_replace_unresolved_image_source_in_html(string $html, string $source, bool &$matched): string
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
    $images = [];
    foreach ($dom->getElementsByTagName('img') as $image) {
        if ($image instanceof DOMElement) {
            $images[] = $image;
        }
    }
    foreach ($images as $image) {
        $currentSource = html_entity_decode(trim($image->getAttribute('src')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($currentSource !== $source || !($image->parentNode instanceof DOMNode)) {
            continue;
        }
        $label = trim($image->getAttribute('alt'));
        $placeholder = $dom->createElement('span');
        $placeholder->setAttribute('class', 'pandoc-import-image-placeholder');
        $placeholder->setAttribute('data-plpc-original-source', $source);
        $placeholder->appendChild($dom->createTextNode($label !== '' ? $label . ' — image could not be imported.' : 'Image could not be imported.'));
        $image->parentNode->replaceChild($placeholder, $image);
        $matched = true;
    }
    $links = [];
    foreach ($dom->getElementsByTagName('a') as $link) {
        if ($link instanceof DOMElement) {
            $links[] = $link;
        }
    }
    foreach ($links as $link) {
        if ($link->getAttribute('data-pandoc-pdf-image-original') !== 'true') {
            continue;
        }
        $currentSource = html_entity_decode(trim($link->getAttribute('href')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($currentSource !== $source || !($link->parentNode instanceof DOMNode)) {
            continue;
        }
        $placeholder = $dom->createElement('span');
        $placeholder->setAttribute('class', 'pandoc-import-image-placeholder');
        $placeholder->setAttribute('data-plpc-original-source', $source);
        $placeholder->appendChild($dom->createTextNode('Original image could not be stored.'));
        $link->parentNode->replaceChild($placeholder, $link);
        $matched = true;
    }

    return $matched ? plpc_dom_body_inner_html($dom) : $html;
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
            $image->setAttribute('data-plpc-imported-media', (string) $attachmentId);
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
        if ($attachmentId !== null && $attachmentId > 0) {
            $link->setAttribute('data-plpc-imported-media', (string) $attachmentId);
        }
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
    if (function_exists('get_posts')) {
        $attachmentIds = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_key' => '_plpc_content_key',
            'meta_value' => $cacheKey,
        ]);
        $attachmentId = max(0, (int) ($attachmentIds[0] ?? 0));
        $url = $attachmentId > 0 ? wp_get_attachment_url($attachmentId) : false;
        if ($attachmentId > 0 && is_string($url) && $url !== '') {
            $attachment = ['id' => $attachmentId, 'url' => $url];
            $GLOBALS['plpc_imported_media_by_hash'][$cacheKey] = $attachment;

            return $attachment;
        }
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
        'meta_input' => [
            '_plpc_content_key' => $cacheKey,
            '_plpc_content_sha1' => $hash,
        ],
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
