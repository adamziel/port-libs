<?php
/**
 * Plugin Name: Port Libs Playground Converter
 * Description: Converts uploaded documents to WordPress block markup inside WordPress Playground.
 * Version: 0.1.0
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
const PLPC_STAGED_UPLOAD_DIRECTORY = '/tmp/port-libs-converter';
const PLPC_MAX_PDF_RASTER_IMAGES = 96;
const PLPC_MAX_PDF_RASTER_BYTES = 24000000;
const PLPC_MAX_PDF_RASTER_IMAGE_BYTES = 16777216;

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
    if (
        plpc_is_playground_environment()
        || (function_exists('current_user_can') && current_user_can('unfiltered_html'))
    ) {
        $mimes['svg'] = 'image/svg+xml';
    }

    return $mimes;
}

add_action('rest_api_init', static function (): void {
    register_rest_route('port-libs/v1', '/convert', [
        'methods' => 'POST',
        'permission_callback' => 'plpc_convert_permission',
        'callback' => 'plpc_convert_uploaded_document',
    ]);
});

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

function plpc_is_playground_environment(): bool
{
    foreach (['WP_PLAYGROUND', 'WORDPRESS_PLAYGROUND', 'IS_WORDPRESS_PLAYGROUND'] as $constant) {
        if (defined($constant) && constant($constant)) {
            return true;
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = preg_replace('/:\d+\z/', '', $host) ?? $host;
    if ($host === 'playground.wordpress.net' || str_ends_with($host, '.playground.wordpress.net')) {
        return true;
    }

    $referer = strtolower((string) ($_SERVER['HTTP_REFERER'] ?? ''));

    return str_contains($referer, 'playground.wordpress.net');
}

function plpc_convert_uploaded_document(WP_REST_Request $request): WP_REST_Response
{
    @ini_set('memory_limit', '512M');
    @set_time_limit(120);

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
    $bytes = base64_decode($base64, true);
    if (!is_string($bytes) || $bytes === '') {
        throw new RuntimeException('The uploaded file was empty or could not be decoded.');
    }

    return $bytes;
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
    if (!plpc_is_playground_environment()) {
        throw new RuntimeException('Staged uploads are only available inside WordPress Playground.');
    }
    if (!is_string($payload['stagedPath'])) {
        throw new RuntimeException('The staged upload path was invalid. Please choose the file again.');
    }

    $path = $payload['stagedPath'];
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
        $bytes = base64_decode((string) ($file['bytes'] ?? ''), true);
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
        $format = plpc_infer_document_format($path, $file['bytes']);
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
 * @param array{path: string, bytes: string, pdfRasterImages?: list<array{object:string,contents:string,mimeType:string,width:int,height:int}>} $file
 * @param array{label: string, files: list<array{path: string, bytes: string}>}|null $collection
 * @return array{postId: int, pageUrl: string, editUrl: string, format: string, title: string, path: string, imageTagCount: int, imagesImported: int, diagnostics: list<string>, quality: array{status:string, flags:list<string>, warnings:list<string>}}
 */
function plpc_convert_collection_file_to_page(array $file, ?array $collection = null, ?string $title = null, string $imageMode = 'important', string $pdfMode = 'layout'): array
{
    $path = $file['path'];
    $format = plpc_infer_document_format($path, $file['bytes']);
    if ($format === '' || $format === 'zip' || !PandocConverter::canRead($format)) {
        throw new RuntimeException('Could not infer a supported document type for ' . basename($path) . '.');
    }
    $postTitle = $title !== null && $title !== '' ? $title : plpc_title_from_filename($path);
    $options = plpc_converter_options($format, $pdfMode);
    $document = PandocConverter::read($file['bytes'], $format, $options['readerOptions']);
    $media = (new PandocMediaExtractor())->extract($document, $file['bytes'], $format, [
        'destination' => 'media',
        'imageMode' => $imageMode,
        'pdfRasterImages' => is_array($file['pdfRasterImages'] ?? null) ? $file['pdfRasterImages'] : [],
    ]);
    $document = $media['document'];
    $blocks = PandocConverter::write($document, 'wordpress', $options['writerOptions']);

    $imageSources = plpc_rendered_image_sources($blocks);
    $mediaResult = plpc_import_extracted_media_entries($blocks, $imageSources, $media['entries']);
    $remainingSources = array_values(array_filter($imageSources, static fn (string $source): bool => !in_array($source, $mediaResult['sources'], true)));
    $fallbackMediaResult = plpc_import_rendered_images($mediaResult['blocks'], $remainingSources, $file['bytes'], basename($path), $collection, $path);
    $blocks = $mediaResult['blocks'];
    $blocks = $fallbackMediaResult['blocks'];

    $diagnostics = array_values(array_merge(
        plpc_document_diagnostics($document, $format),
        $media['diagnostics'],
        $mediaResult['diagnostics'],
        $fallbackMediaResult['diagnostics']
    ));
    $quality = plpc_import_quality_report($format, $diagnostics, count($imageSources), $mediaResult['imported'] + $fallbackMediaResult['imported']);
    $blocks = plpc_prepend_conversion_warning_blocks($blocks, $format, $diagnostics);
    $blocks = plpc_prepend_import_quality_blocks($blocks, $quality);

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
        'imageTagCount' => count($imageSources),
        'imagesImported' => $mediaResult['imported'] + $fallbackMediaResult['imported'],
        'diagnostics' => $diagnostics,
        'quality' => $quality,
    ];
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
