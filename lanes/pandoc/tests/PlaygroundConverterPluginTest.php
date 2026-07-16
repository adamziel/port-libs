<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(private array $data, private int $status = 200)
        {
        }

        public function get_data(): array
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status(int $status): void
        {
            $this->status = $status;
        }

        public function set_data(array $data): void
        {
            $this->data = $data;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /**
         * @param array<string, mixed> $params
         * @param array<string, mixed> $urlParams
         * @param array<string, string> $headers
         */
        public function __construct(
            private string $body = '',
            private array $params = [],
            private array $urlParams = [],
            private array $headers = [],
        )
        {
        }

        public function get_body(): string
        {
            return $this->body;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_json_params(): array
        {
            $decoded = json_decode($this->body, true);

            return is_array($decoded) ? $decoded : [];
        }

        public function get_param(string $key): mixed
        {
            if (array_key_exists($key, $this->params)) {
                return $this->params[$key];
            }
            if (array_key_exists($key, $this->urlParams)) {
                return $this->urlParams[$key];
            }

            $json = $this->get_json_params();

            return $json[$key] ?? null;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_params(): array
        {
            return $this->params + $this->urlParams + $this->get_json_params();
        }

        /**
         * @return array<string, mixed>
         */
        public function get_url_params(): array
        {
            return $this->urlParams;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        /**
         * @param array<string, mixed> $params
         */
        public function set_url_params(array $params): void
        {
            $this->urlParams = $params;
        }

        public function get_header(string $key): string
        {
            foreach ($this->headers as $header => $value) {
                if (strtolower($header) === strtolower($key)) {
                    return $value;
                }
            }

            return '';
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            private string $code,
            private string $message,
            private array $data = []
        ) {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): array
        {
            return $this->data;
        }
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hookName, mixed $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['plpc_test_filters'][$hookName][$priority][] = [
            'callback' => $callback,
            'acceptedArgs' => max(1, $acceptedArgs),
        ];

        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
    {
        $filters = $GLOBALS['plpc_test_filters'][$hookName] ?? [];
        if (!is_array($filters)) {
            return $value;
        }
        ksort($filters, SORT_NUMERIC);
        foreach ($filters as $entries) {
            foreach (is_array($entries) ? $entries : [] as $entry) {
                if (!is_array($entry) || !is_callable($entry['callback'] ?? null)) {
                    continue;
                }
                $acceptedArgs = max(1, (int) ($entry['acceptedArgs'] ?? 1));
                $value = ($entry['callback'])(...array_slice([$value, ...$args], 0, $acceptedArgs));
            }
        }

        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hookName, mixed $callback): void
    {
        $GLOBALS['plpc_test_actions'][$hookName] ??= [];
        $GLOBALS['plpc_test_actions'][$hookName][] = $callback;
    }
}

if (!function_exists('register_rest_route')) {
    /**
     * @param array<string, mixed>|list<array<string, mixed>> $args
     */
    function register_rest_route(string $namespace, string $route, array $args): bool
    {
        $key = $namespace . $route;
        $GLOBALS['plpc_test_rest_routes'][$key] ??= [];
        $GLOBALS['plpc_test_rest_routes'][$key][] = $args;

        return true;
    }
}

/**
 * @param mixed ...$args
 */
function plpc_test_do_action(string $hookName, mixed ...$args): void
{
    foreach ($GLOBALS['plpc_test_actions'][$hookName] ?? [] as $callback) {
        if (!is_callable($callback)) {
            throw new RuntimeException("Action '{$hookName}' has no callable handler.");
        }
        $callback(...$args);
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $defaultValue = false): mixed
    {
        return $GLOBALS['plpc_test_options'][$option] ?? $defaultValue;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $option, mixed $value = '', mixed $deprecated = '', mixed $autoload = 'yes'): bool
    {
        if (array_key_exists($option, $GLOBALS['plpc_test_options'] ?? [])) {
            return false;
        }
        $GLOBALS['plpc_test_options'][$option] = $value;

        return true;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value, mixed $autoload = null): bool
    {
        $changed = !array_key_exists($option, $GLOBALS['plpc_test_options'] ?? [])
            || $GLOBALS['plpc_test_options'][$option] !== $value;
        $GLOBALS['plpc_test_options'][$option] = $value;

        return $changed;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        if (!array_key_exists($option, $GLOBALS['plpc_test_options'] ?? [])) {
            return false;
        }
        unset($GLOBALS['plpc_test_options'][$option]);

        return true;
    }
}

function plpc_test_import_job_upload_dir(): string
{
    return sys_get_temp_dir() . '/plpc-import-jobs-test';
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool
    {
        return is_dir($target) || mkdir($target, 0777, true);
    }
}

if (!function_exists('wp_upload_dir')) {
    /**
     * @return array{path: string, url: string, subdir: string, basedir: string, baseurl: string, error: false}
     */
    function wp_upload_dir(?string $time = null, bool $createDir = true, bool $refreshCache = false): array
    {
        $directory = plpc_test_import_job_upload_dir();
        if ($createDir && !is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        return [
            'path' => $directory,
            'url' => 'https://playground.test/uploads',
            'subdir' => '',
            'basedir' => $directory,
            'baseurl' => 'https://playground.test/uploads',
            'error' => false,
        ];
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        $sequence = ((int) ($GLOBALS['plpc_test_uuid_sequence'] ?? 0)) + 1;
        $GLOBALS['plpc_test_uuid_sequence'] = $sequence;

        return sprintf('00000000-0000-4000-8000-%012d', $sequence);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['plpc_test_current_user_id'] ?? 1);
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename)) ?? basename($filename);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url): array|false|int|string|null
    {
        return parse_url($url);
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype(string $filename): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $type = match ($extension) {
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => '',
        };

        return ['ext' => $extension, 'type' => $type];
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return in_array($capability, $GLOBALS['plpc_test_current_user_caps'] ?? [], true);
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post(array $post, bool $wpError = false): int
    {
        $GLOBALS['plpc_test_posts'] ??= [];
        if (isset($GLOBALS['plpc_test_wp_insert_content_filter'])
            && is_callable($GLOBALS['plpc_test_wp_insert_content_filter'])
        ) {
            $post['post_content'] = (string) ($GLOBALS['plpc_test_wp_insert_content_filter'])(
                (string) ($post['post_content'] ?? ''),
                $post
            );
        }
        $ids = array_map('intval', array_keys($GLOBALS['plpc_test_posts']));
        $id = ($ids === [] ? 0 : max($ids)) + 1;
        $GLOBALS['plpc_test_posts'][$id] = $post;

        return $id;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post(array $post, bool $wpError = false): int
    {
        $id = max(0, (int) ($post['ID'] ?? 0));
        if ($id < 1 || !isset($GLOBALS['plpc_test_posts'][$id])) {
            return 0;
        }
        unset($post['ID']);
        $GLOBALS['plpc_test_posts'][$id] = array_replace($GLOBALS['plpc_test_posts'][$id], $post);

        return $id;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post(int $postId, bool $forceDelete = false): object|false
    {
        if (!isset($GLOBALS['plpc_test_posts'][$postId])) {
            return false;
        }
        $post = (object) (['ID' => $postId] + $GLOBALS['plpc_test_posts'][$postId]);
        unset($GLOBALS['plpc_test_posts'][$postId]);

        return $post;
    }
}

if (!function_exists('get_post_field')) {
    function get_post_field(string $field, int $postId, string $context = 'display'): mixed
    {
        return $GLOBALS['plpc_test_posts'][$postId][$field] ?? '';
    }
}

if (!function_exists('get_posts')) {
    function get_posts(array $args = []): array
    {
        $postType = (string) ($args['post_type'] ?? 'post');
        $fields = (string) ($args['fields'] ?? 'all');
        $matches = [];
        $sources = $postType === 'attachment'
            ? ($GLOBALS['plpc_test_attachments'] ?? [])
            : ($GLOBALS['plpc_test_posts'] ?? []);
        foreach ($sources as $id => $post) {
            if (!is_array($post)) {
                continue;
            }
            $actualType = $postType === 'attachment' ? 'attachment' : (string) ($post['post_type'] ?? 'post');
            if ($actualType !== $postType) {
                continue;
            }
            $meta = is_array($post['meta_input'] ?? null) ? $post['meta_input'] : [];
            if (isset($args['meta_key']) && ($meta[(string) $args['meta_key']] ?? null) !== ($args['meta_value'] ?? null)) {
                continue;
            }
            foreach (is_array($args['meta_query'] ?? null) ? $args['meta_query'] : [] as $query) {
                if (!is_array($query) || !isset($query['key'])) {
                    continue;
                }
                if ((string) ($meta[(string) $query['key']] ?? '') !== (string) ($query['value'] ?? '')) {
                    continue 2;
                }
            }
            $matches[] = $fields === 'ids' ? (int) $id : (object) (['ID' => (int) $id] + $post);
        }

        return array_slice($matches, 0, max(1, (int) ($args['posts_per_page'] ?? $args['numberposts'] ?? 5)));
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
    {
        $post = $GLOBALS['plpc_test_posts'][$postId] ?? $GLOBALS['plpc_test_attachments'][$postId] ?? [];
        $meta = is_array($post['meta_input'] ?? null) ? $post['meta_input'] : [];
        if ($key === '') {
            return $meta;
        }
        $value = $meta[$key] ?? '';

        return $single ? $value : [$value];
    }
}

if (!function_exists('wp_upload_bits')) {
    function wp_upload_bits(string $filename, mixed $deprecated, string $bits): array
    {
        $GLOBALS['plpc_test_uploads'] ??= [];
        $index = count($GLOBALS['plpc_test_uploads']) + 1;
        $file = '/tmp/plpc-upload-' . $index . '-' . $filename;
        $GLOBALS['plpc_test_uploads'][] = [
            'filename' => $filename,
            'bits' => $bits,
            'file' => $file,
        ];

        return [
            'file' => $file,
            'url' => 'https://playground.test/uploads/' . rawurlencode($filename),
            'error' => '',
        ];
    }
}

if (!function_exists('wp_insert_attachment')) {
    function wp_insert_attachment(array $attachment, string $file): int
    {
        $GLOBALS['plpc_test_attachments'] ??= [];
        $id = count($GLOBALS['plpc_test_attachments']) + 1;
        $GLOBALS['plpc_test_attachments'][$id] = $attachment + ['file' => $file];

        return $id;
    }
}

if (!function_exists('wp_generate_attachment_metadata')) {
    function wp_generate_attachment_metadata(int $attachmentId, string $file): array
    {
        return ['file' => basename($file), 'id' => $attachmentId];
    }
}

if (!function_exists('wp_update_attachment_metadata')) {
    function wp_update_attachment_metadata(int $attachmentId, array $metadata): void
    {
        $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] = $metadata;
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url(int $attachmentId): string
    {
        return 'https://playground.test/uploads/attachment-' . $attachmentId . '.png';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return false;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink(int $postId): string
    {
        return 'https://playground.test/?page_id=' . $postId;
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link(int $postId, string $context = 'display'): string
    {
        return 'https://playground.test/wp-admin/post.php?post=' . $postId . '&action=edit';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(int $postId): string
    {
        return (string) ($GLOBALS['plpc_test_posts'][$postId]['post_title'] ?? '');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('parse_blocks')) {
    function parse_blocks(string $content): array
    {
        $blocks = [];
        $matchCount = preg_match_all('/<!--\s+wp:([a-z0-9-\/]+)(?:\s+(\{.*?\}))?\s+-->(.*?)<!--\s+\/wp:\1\s+-->/is', $content, $matches, PREG_SET_ORDER);
        if ($matchCount === false || $matchCount === 0) {
            $trimmed = trim($content);

            return $trimmed === '' ? [] : [[
                'blockName' => null,
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => $content,
                'innerContent' => [$content],
            ]];
        }
        foreach ($matches as $match) {
            $name = strtolower((string) $match[1]);
            $attrs = isset($match[2]) && trim((string) $match[2]) !== '' ? json_decode((string) $match[2], true) : [];
            $innerHTML = (string) $match[3];
            $blocks[] = [
                'blockName' => str_contains($name, '/') ? $name : 'core/' . $name,
                'attrs' => is_array($attrs) ? $attrs : [],
                'innerBlocks' => [],
                'innerHTML' => $innerHTML,
                'innerContent' => [$innerHTML],
            ];
        }

        return $blocks;
    }
}

if (!function_exists('serialize_blocks')) {
    function serialize_blocks(array $blocks): string
    {
        return implode("\n\n", array_map('serialize_block', $blocks));
    }
}

if (!function_exists('serialize_block')) {
    function serialize_block(array $block): string
    {
        $blockName = $block['blockName'] ?? null;
        $innerContent = $block['innerContent'] ?? [];
        $innerHTML = is_array($innerContent) ? implode('', array_map('strval', $innerContent)) : (string) ($block['innerHTML'] ?? '');
        if (!is_string($blockName) || $blockName === '') {
            return $innerHTML;
        }
        $commentName = str_starts_with($blockName, 'core/') ? substr($blockName, 5) : $blockName;
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $attrsJson = $attrs === [] ? '' : ' ' . json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return '<!-- wp:' . $commentName . $attrsJson . ' -->' . $innerHTML . '<!-- /wp:' . $commentName . ' -->';
    }
}

/**
 * @param array<string, mixed> $payload
 */
function plpc_test_import_job_request(array $payload = [], ?string $jobId = null): WP_REST_Request
{
    return new WP_REST_Request(
        $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
        [],
        $jobId === null ? [] : ['jobId' => $jobId]
    );
}

function plpc_test_reset_import_job_state(): void
{
    $GLOBALS['plpc_test_filters'] = [];
    $GLOBALS['plpc_test_options'] = [];
    $GLOBALS['plpc_test_posts'] = [];
    $GLOBALS['plpc_test_uploads'] = [];
    $GLOBALS['plpc_test_attachments'] = [];
    $GLOBALS['plpc_test_attachment_metadata'] = [];
    $GLOBALS['plpc_imported_media_by_hash'] = [];
    $GLOBALS['plpc_test_uuid_sequence'] = 0;
    unset($GLOBALS['plpc_test_wp_insert_content_filter']);

    $directory = plpc_test_import_job_upload_dir();
    if (!is_dir($directory)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($directory);
}

function plpc_test_renderable_form_xobject_pdf(): string
{
    $pageContent = "q\n1 0 0 1 72 600 cm\n/Chart Do\nQ\n";
    $formContent = "0.15 0.35 0.8 rg\n0 0 100 50 re\nf\n";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /XObject << /Chart 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 50] /Resources << >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}endstream\nendobj\n"
        . "%%EOF\n";
}

function plpc_test_page_wrapper_form_xobject_pdf(): string
{
    $pageContent = "q\n/Backdrop Do\nQ\nBT /F1 14 Tf 72 700 Td (Readable page text) Tj ET\n";
    $formContent = "0.95 0.95 0.95 rg\n0 0 600 800 re\nf\n";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 800] /Resources << /Font << /F1 6 0 R >> /XObject << /Backdrop 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 600 800] /Resources << >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}endstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF\n";
}

/** @param list<string> $pageTexts */
function plpc_test_multipage_pdf(array $pageTexts): string
{
    $catalogObject = 1;
    $pagesObject = 2;
    $fontObject = 3;
    $pageObjects = [];
    $contentObjects = [];
    $nextObject = 4;
    foreach ($pageTexts as $_pageText) {
        $pageObjects[] = $nextObject++;
        $contentObjects[] = $nextObject++;
    }

    $objects = [
        $catalogObject => "<< /Type /Catalog /Pages {$pagesObject} 0 R >>",
        $pagesObject => '<< /Type /Pages /Kids ['
            . implode(' ', array_map(static fn (int $object): string => "{$object} 0 R", $pageObjects))
            . '] /Count ' . count($pageObjects) . ' >>',
        $fontObject => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    ];
    foreach ($pageTexts as $index => $pageText) {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $pageText);
        $content = "BT /F1 12 Tf 72 720 Td ({$escaped}) Tj ET";
        $pageObject = $pageObjects[$index];
        $contentObject = $contentObjects[$index];
        $objects[$pageObject] = "<< /Type /Page /Parent {$pagesObject} 0 R /MediaBox [0 0 612 792]"
            . " /Resources << /Font << /F1 {$fontObject} 0 R >> >> /Contents {$contentObject} 0 R >>";
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
    }

    ksort($objects, SORT_NUMERIC);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $body) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $size = max(array_keys($objects)) + 1;
    $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber < $size; $objectNumber++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer\n<< /Size {$size} /Root {$catalogObject} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
}

/**
 * @return list<array<string, mixed>>
 */
function plpc_test_rest_route_handlers(string $route): array
{
    $handlers = [];
    foreach ($GLOBALS['plpc_test_rest_routes'][$route] ?? [] as $registration) {
        $entries = array_is_list($registration) ? $registration : [$registration];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $handlers[] = $entry;
            }
        }
    }

    return $handlers;
}

/**
 * @param array<string, mixed> $snapshot
 */
function plpc_test_assert_import_job_snapshot(TestRunner $t, array $snapshot, string $jobId): void
{
    $t->same(true, $snapshot['ok'] ?? null);
    $t->same($jobId, $snapshot['jobId'] ?? null);
    $t->true(is_string($snapshot['status'] ?? null) && $snapshot['status'] !== '', 'A job snapshot needs a status.');
    $t->true(is_string($snapshot['stage'] ?? null) && $snapshot['stage'] !== '', 'A job snapshot needs a current stage.');
    $t->true(is_array($snapshot['progress'] ?? null), 'A job snapshot needs progress data.');
    $t->true(is_int($snapshot['progress']['completed'] ?? null), 'Progress needs an integer completed count.');
    $t->true(is_int($snapshot['progress']['total'] ?? null), 'Progress needs an integer total count.');
    $t->true(($snapshot['progress']['completed'] ?? -1) >= 0, 'Progress cannot be negative.');
    $t->true(($snapshot['progress']['total'] ?? -1) >= ($snapshot['progress']['completed'] ?? PHP_INT_MAX), 'Progress total must cover completed work.');
    $t->true(is_string($snapshot['progress']['label'] ?? null) && $snapshot['progress']['label'] !== '', 'Progress needs a human-readable active label.');
    $t->true(is_array($snapshot['events'] ?? null), 'A job snapshot needs its activity log.');
    $t->true(is_array($snapshot['renderRequests'] ?? null), 'A job snapshot needs outstanding renderer requests.');

    foreach ($snapshot['events'] as $event) {
        $t->true(is_array($event), 'Each activity entry must be structured data.');
        $t->true(is_string($event['stage'] ?? null) && $event['stage'] !== '', 'Each activity entry needs a stage.');
        $t->true(is_string($event['message'] ?? null) && $event['message'] !== '', 'Each activity entry needs a message for the person waiting for the import.');
    }
}

require_once dirname(__DIR__, 3) . '/tools/playground-converter-plugin/port-libs-playground-converter.php';

return [
    'playground converter skips page-sized Form wrappers but keeps ordinary figures' => static function (TestRunner $t): void {
        $page = ['x1' => 0.0, 'y1' => 0.0, 'x2' => 600.0, 'y2' => 800.0];

        $t->same(true, plpc_pdf_form_placement_covers_page(
            ['x1' => 0.0, 'y1' => 0.0, 'x2' => 600.0, 'y2' => 800.0],
            $page
        ));
        $t->same(true, plpc_pdf_form_placement_covers_page(
            ['x1' => -200.0, 'y1' => -100.0, 'x2' => 900.0, 'y2' => 900.0],
            $page
        ), 'A clipped oversized layout wrapper still covers the visible page.');
        $t->same(false, plpc_pdf_form_placement_covers_page(
            ['x1' => 50.0, 'y1' => 400.0, 'x2' => 550.0, 'y2' => 700.0],
            $page
        ), 'A large chart occupying part of a page remains renderable.');
        $t->same(false, plpc_pdf_form_placement_covers_page(
            ['x1' => 0.0, 'y1' => 720.0, 'x2' => 600.0, 'y2' => 800.0],
            $page
        ), 'A full-width header or banner is not mistaken for a page wrapper.');
    },
    'playground converter scopes browser-rendered PDF figures to the active page range' => static function (TestRunner $t): void {
        $renders = [
            ['id' => 'first', 'page' => 1, 'contents' => 'one'],
            ['id' => 'middle', 'page' => 4, 'contents' => 'four'],
            ['id' => 'last', 'page' => 9, 'contents' => 'nine'],
            ['id' => 'invalid', 'page' => 0, 'contents' => 'zero'],
        ];

        $selected = plpc_pdf_form_renders_for_page_range($renders, 3, 5);

        $t->same(['middle'], array_column($selected, 'id'));
    },
    'playground converter fingerprints text and image content before publication' => static function (TestRunner $t): void {
        $blocks = '<!-- wp:paragraph --><p>Hello <strong>stored world</strong>.</p><!-- /wp:paragraph -->'
            . '<!-- wp:image --><figure><img src="https://playground.test/media/chart.png"/></figure><!-- /wp:image -->';
        $fingerprint = plpc_import_content_fingerprint($blocks);

        $t->true(($fingerprint['rawBytes'] ?? 0) > 0);
        $t->same('Hello stored world.', $fingerprint['visibleText'] ?? null);
        $t->same(1, $fingerprint['imageCount'] ?? null);
        $t->true(($fingerprint['meaningfulBlockCount'] ?? 0) >= 2);
    },
    'playground converter normalizes unsupported controls before WordPress round trip verification' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $postId = plpc_insert_verified_page([
            'post_type' => 'page',
            'post_title' => 'Control-safe content',
            'post_content' => "<!-- wp:paragraph --><p>Alpha\x1EBeta\x1FGamma</p><!-- /wp:paragraph -->",
        ]);
        $stored = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');

        $t->same(0, preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $stored));
        $t->contains('Alpha Beta Gamma', $stored);
        plpc_import_verify_stored_page($postId);
    },
    'playground converter refuses a WordPress insert that silently empties converted blocks' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $GLOBALS['plpc_test_wp_insert_content_filter'] = static fn (string $content): string => '';

        $error = null;
        try {
            plpc_insert_verified_page([
                'post_type' => 'page',
                'post_title' => 'Must survive',
                'post_status' => 'draft',
                'post_content' => '<!-- wp:paragraph --><p>Publication sentinel</p><!-- /wp:paragraph -->',
            ]);
        } catch (Throwable $throwable) {
            $error = $throwable;
        }

        $t->true($error instanceof PlpcImportFailure, 'Silent WordPress content loss must become a classified import failure.');
        $t->same('publication_roundtrip_mismatch', $error instanceof PlpcImportFailure ? $error->failureCode : null);
        $t->same(true, $error instanceof PlpcImportFailure ? $error->recoverable : null);
        $t->contains('did not preserve', strtolower((string) $error?->getMessage()));
        $t->same([], $GLOBALS['plpc_test_posts'], 'The invalid draft must be removed while the durable bundle remains retryable.');
    },
    'playground converter verifies image-only pages without requiring fabricated text' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $postId = plpc_insert_verified_page([
            'post_type' => 'page',
            'post_title' => 'Image only',
            'post_status' => 'draft',
            'post_content' => '<!-- wp:image --><figure><img src="https://playground.test/media/scan.png"/></figure><!-- /wp:image -->',
        ]);

        $t->true($postId > 0);
        $t->same('draft', $GLOBALS['plpc_test_posts'][$postId]['post_status'] ?? null);
        $storedFingerprint = $GLOBALS['plpc_test_posts'][$postId]['meta_input']['_plpc_import_content_fingerprint'] ?? [];
        $t->same(1, $storedFingerprint['imageCount'] ?? null);
        $t->true(!array_key_exists('visibleText', $storedFingerprint), 'Publication metadata must not duplicate the converted document text.');
    },
    'playground pdf extraction scheduler adapts to measured fact time density and memory' => static function (TestRunner $t): void {
        $t->same(16, plpc_pdf_adaptive_pages_per_request(8, [
            'pages' => 8,
            'factsBytes' => 800_000,
            'durationMs' => 2_000,
            'peakBytes' => 32_000_000,
        ], 50));
        $t->same(4, plpc_pdf_adaptive_pages_per_request(8, [
            'pages' => 8,
            'factsBytes' => 12_000_000,
            'durationMs' => 24_000,
            'peakBytes' => 32_000_000,
        ], 50));
        $t->same(3, plpc_pdf_adaptive_pages_per_request(8, [
            'pages' => 8,
            'factsBytes' => 800_000,
            'durationMs' => 2_000,
            'peakBytes' => 32_000_000,
        ], 3));
        $t->same(134_217_728, plpc_php_ini_bytes('128M'));
        $t->same(0, plpc_php_ini_bytes('-1'));
    },
    'playground single-page PDF limit can only tighten the hard memory and database ceilings' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new class {
            public function get_var(string $query): int
            {
                return 1_000_000;
            }
        };
        try {
            $t->same(500_000, plpc_pdf_single_page_limit_bytes());
            add_filter('plpc_pdf_single_page_limit_bytes', static fn (mixed $bytes): int => 123_456);
            $t->same(123_456, plpc_pdf_single_page_limit_bytes());
            add_filter('plpc_pdf_single_page_limit_bytes', static fn (mixed $bytes): int => 99_000_000, 20);
            $t->same(500_000, plpc_pdf_single_page_limit_bytes(), 'A site filter cannot raise the derived server ceiling.');
        } finally {
            if ($previousWpdb === null) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    },
    'playground converter matches a visual PDF anchor across a terminal line-break hyphen' => static function (TestRunner $t): void {
        $blocks = [
            new \PortLibs\Pandoc\AstNode('paragraph', [
                'text' => 'Figure 2. State machine describing the major activities of TraceMonkey.',
            ]),
        ];

        $t->same(
            0,
            plpc_browser_pdf_form_anchor_index(
                $blocks,
                'Figure 2. State machine describing the major activities of Trace-'
            )
        );
    },
    'playground converter inserts a browser-rendered Form directly before its reflowed caption' => static function (TestRunner $t): void {
        $document = new \PortLibs\Pandoc\AstNode('document', [
            'meta' => [
                'pdfFormXObjectPlacements' => [[
                    'id' => 'pdf-form-p2-n1-o41',
                    'page' => 2,
                    'bbox' => ['x1' => 317.0, 'y1' => 414.0, 'x2' => 556.0, 'y2' => 601.0],
                    'paintOrder' => 1,
                    'followingText' => 'Figure 2. State machine describing the major activities of Trace-',
                ]],
            ],
        ], [
            new \PortLibs\Pandoc\AstNode('paragraph', ['text' => 'The overview introduces the state machine.']),
            new \PortLibs\Pandoc\AstNode('paragraph', ['text' => 'Figure 2. State machine describing the major activities of TraceMonkey.']),
        ]);

        $placed = plpc_document_with_browser_pdf_form_renders($document, [[
            'formId' => 'pdf-form-p2-n1-o41',
            'contents' => "\x89PNG\r\n",
            'mimeType' => 'image/png',
        ]]);
        $children = $placed->children;

        $t->same(3, count($children));
        $t->same('paragraph', $children[1]->type);
        $t->true(in_array('pandoc-pdf-form-figure', $children[1]->attr('classes', []), true));
        $t->same('Figure 2. State machine describing the major activities of TraceMonkey.', $children[2]->attr('text'));
    },
    'playground converter route permits WordPress Playground hosts' => static function (TestRunner $t): void {
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $previousReferer = $_SERVER['HTTP_REFERER'] ?? null;
        $GLOBALS['plpc_test_current_user_caps'] = [];
        $_SERVER['HTTP_HOST'] = 'preview.playground.wordpress.net';
        unset($_SERVER['HTTP_REFERER']);

        try {
            $t->same(true, plpc_convert_permission());
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
            if ($previousReferer === null) {
                unset($_SERVER['HTTP_REFERER']);
            } else {
                $_SERVER['HTTP_REFERER'] = $previousReferer;
            }
            $GLOBALS['plpc_test_current_user_caps'] = [];
        }
    },
    'playground converter route permits authenticated editors outside Playground' => static function (TestRunner $t): void {
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'example.test';
        $GLOBALS['plpc_test_current_user_caps'] = ['upload_files', 'edit_pages'];

        try {
            $t->same(true, plpc_convert_permission());
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
            $GLOBALS['plpc_test_current_user_caps'] = [];
        }
    },
    'playground converter route rejects anonymous non Playground installs' => static function (TestRunner $t): void {
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $previousReferer = $_SERVER['HTTP_REFERER'] ?? null;
        $_SERVER['HTTP_HOST'] = 'example.test';
        unset($_SERVER['HTTP_REFERER']);
        $GLOBALS['plpc_test_current_user_caps'] = [];

        try {
            $permission = plpc_convert_permission();
            $t->true($permission instanceof WP_Error, 'Anonymous non-Playground requests should be rejected with WP_Error.');
            $t->same('rest_forbidden', $permission->get_error_code());
            $t->same(['status' => 403], $permission->get_error_data());
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
            if ($previousReferer === null) {
                unset($_SERVER['HTTP_REFERER']);
            } else {
                $_SERVER['HTTP_REFERER'] = $previousReferer;
            }
            $GLOBALS['plpc_test_current_user_caps'] = [];
        }
    },
    'playground converter registers persisted import job endpoints' => static function (TestRunner $t): void {
        $GLOBALS['plpc_test_rest_routes'] = [];
        plpc_test_do_action('rest_api_init');

        $createHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports');
        $jobHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)');
        $advanceHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/advance');
        $outputHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/output-mode');
        $renderHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/rendered-media');
        $sourceHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/render-source/(?P<requestId>form-[a-f0-9]+)');

        $t->same(1, count(array_filter($createHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'POST' && ($handler['callback'] ?? null) === 'plpc_create_import_job')));
        $t->same(1, count(array_filter($jobHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'GET' && ($handler['callback'] ?? null) === 'plpc_import_job_status')));
        $t->same(1, count(array_filter($advanceHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'POST' && ($handler['callback'] ?? null) === 'plpc_advance_import_job')));
        $t->same(1, count(array_filter($outputHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'POST' && ($handler['callback'] ?? null) === 'plpc_switch_import_output_mode')));
        $t->same(1, count(array_filter($renderHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'POST' && ($handler['callback'] ?? null) === 'plpc_submit_import_rendered_media')));
        $t->same(1, count(array_filter($sourceHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'GET' && ($handler['callback'] ?? null) === 'plpc_import_job_render_source')));
    },
    'playground converter persists a created import job and exposes progress before any work begins' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();

        $response = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'notes.gfm',
            'title' => 'Persisted job',
            'imageMode' => 'important',
            'pdfMode' => 'layout',
            'bytes' => base64_encode("# Persisted job\n\nThe browser can poll this import.\n"),
        ]));
        $created = $response->get_data();
        $jobId = $created['jobId'] ?? '';

        $t->same(201, $response->get_status());
        $t->true(is_string($jobId) && preg_match('/\A[A-Za-z0-9_-]+\z/', $jobId) === 1, 'A created import must receive a URL-safe job id.');
        $t->same('queued', $created['status'] ?? null);
        $t->same('single', $created['output']['pdfOutputMode'] ?? null);
        $t->true((int) ($created['output']['singlePageLimitBytes'] ?? 0) > 0, 'The browser should know the effective single-page limit before conversion.');
        plpc_test_assert_import_job_snapshot($t, $created, $jobId);
        $t->same(0, $created['progress']['completed'] ?? null);
        $t->true(($created['events'] ?? []) !== [], 'Creating a job should immediately tell the waiting person what happened.');

        $status = plpc_import_job_status(plpc_test_import_job_request([], $jobId));
        $snapshot = $status->get_data();

        $t->same(200, $status->get_status());
        plpc_test_assert_import_job_snapshot($t, $snapshot, $jobId);
        $t->same('queued', $snapshot['status'] ?? null);
        $t->same($created['events'], $snapshot['events']);
        $t->true(!array_key_exists('bytes', $snapshot), 'Status polling must never echo the uploaded source bytes back to the browser.');
    },
    'persisted import jobs store bounded PDF js facts privately with native fallback evidence' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $pdf = plpc_test_multipage_pdf(['Native browser handoff']);
        $browserFacts = [
            'schemaVersion' => 1,
            'provider' => 'pdfjs-v1',
            'sourceSha256' => hash('sha256', $pdf),
            'pageCount' => 1,
            'pages' => [[
                'pageNumber' => 1,
                'viewport' => ['width' => 612.0, 'height' => 792.0, 'rotation' => 0, 'viewBox' => [0.0, 0.0, 612.0, 792.0]],
                'spans' => [[
                    'text' => 'Browser handoff',
                    'direction' => 'ltr',
                    'transform' => [12.0, 0.0, 0.0, 12.0, 72.0, 720.0],
                    'width' => 95.0,
                    'height' => 12.0,
                    'fontName' => 'f1',
                    'hasEol' => true,
                ]],
                'markedContent' => [],
                'styles' => ['f1' => ['fontFamily' => 'Helvetica', 'vertical' => false]],
                'structure' => ['role' => 'Document', 'children' => [['role' => 'P']]],
            ]],
            'failures' => [],
        ];
        $response = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'browser.pdf',
            'bytes' => base64_encode($pdf),
            'pdfBrowserFacts' => ['browser.pdf' => $browserFacts],
        ]));
        $snapshot = $response->get_data();
        $jobId = (string) ($snapshot['jobId'] ?? '');
        $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $record = is_array($job) ? ($job['browserFacts']['browser.pdf'] ?? null) : null;

        $t->same(201, $response->get_status());
        $t->true(is_array($record), 'The job should retain only a private browser-facts descriptor.');
        $t->true(str_starts_with((string) ($record['storage'] ?? ''), 'facts/'));
        $t->same(1, $record['pages'] ?? null);
        $t->true(!array_key_exists('pages', $record) || is_int($record['pages']), 'Browser page payloads must not be stored in the WordPress option.');
        $loaded = plpc_import_job_load_browser_facts($job, 'browser.pdf');
        $t->same(json_decode(json_encode($browserFacts, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), $loaded);

        $merged = (new \PortLibs\MarkerPDF\BrowserPdfFactsProvider())->extract($pdf, ['browserFacts' => $loaded]);
        $t->same('applied', $merged->diagnostics()['browserFacts']['status'] ?? null);
        $t->same('Browser handoff', $merged->page(1)?->text()['browser']['spans'][0]['text'] ?? null);
        $t->same(['Native browser handoff'], array_column($merged->page(1)?->text()['lines'] ?? [], 'text'));

        $prepared = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $t->same(200, $prepared->get_status());
        $preparedJob = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $t->true(is_array($preparedJob['documents'][0]['pdfBrowserFacts'] ?? null), 'Prepared PDF documents should retain their browser-facts descriptor for resumed chunks.');
        $t->true(!array_key_exists('pdfBrowserFacts', $prepared->get_data()), 'Job snapshots must not expose browser text or structure facts.');

        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $mergedJob = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $mergedFacts = plpc_import_job_load_pdf_document_facts($mergedJob, $mergedJob['documents'][0]);
        $t->same('native-php-v1+pdfjs-v1', $mergedFacts->provider());
        $t->same('Browser handoff', $mergedFacts->page(1)?->text()['browser']['spans'][0]['text'] ?? null);
    },
    'playground converter scopes persisted import jobs to their WordPress owner' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $previousReferer = $_SERVER['HTTP_REFERER'] ?? null;
        $_SERVER['HTTP_HOST'] = 'example.test';
        unset($_SERVER['HTTP_REFERER']);
        $GLOBALS['plpc_test_current_user_id'] = 41;

        try {
            $created = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'private.md',
                'bytes' => base64_encode("# Private job\n"),
            ]))->get_data();
            $jobId = (string) ($created['jobId'] ?? '');
            $GLOBALS['plpc_test_current_user_id'] = 42;

            $response = plpc_import_job_status(plpc_test_import_job_request([], $jobId));

            $t->same(404, $response->get_status());
            $t->same(false, $response->get_data()['ok'] ?? null);
            $t->contains('another wordpress user', strtolower((string) ($response->get_data()['message'] ?? '')));
        } finally {
            unset($GLOBALS['plpc_test_current_user_id']);
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
            if ($previousReferer === null) {
                unset($_SERVER['HTTP_REFERER']);
            } else {
                $_SERVER['HTTP_REFERER'] = $previousReferer;
            }
        }
    },
    'playground converter advances an import job with visible stages through page creation' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'stages.md',
            'title' => 'Visible stages',
            'bytes' => base64_encode("# Visible stages\n\nA small document for the resumable job protocol.\n"),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        $stages = [];

        for ($attempt = 0; $attempt < 8 && !in_array($snapshot['status'] ?? '', ['complete', 'completed'], true); $attempt++) {
            $response = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
            $t->same(200, $response->get_status());
            $snapshot = $response->get_data();
            plpc_test_assert_import_job_snapshot($t, $snapshot, $jobId);
            $stages[] = $snapshot['stage'] ?? '';
        }

        $t->true(in_array($snapshot['status'] ?? '', ['complete', 'completed'], true), 'An ordinary document should complete after a bounded number of job advances.');
        $t->true(count(array_unique($stages)) >= 1, 'Advancing a job should expose its current work stage.');
        $t->true(is_array($snapshot['result'] ?? null), 'A completed job should expose its imported page result.');
        $postId = $snapshot['result']['postId'] ?? null;
        $t->true(is_int($postId) && $postId > 0, 'A completed job should report its created WordPress page.');
        $t->contains('Visible stages', $GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');
    },
    'playground pdf jobs amortize extraction while persisting independently segmentable pages' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'wide-checkpoint.pdf',
            'title' => 'Wide checkpoint',
            'imageMode' => 'none',
            'pdfMode' => 'layout',
            'bytes' => base64_encode(plpc_test_multipage_pdf([
                'WIDE PAGE ONE',
                'WIDE PAGE TWO',
                'WIDE PAGE THREE',
            ])),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $chunks = $job['documents'][0]['pdfChunks'] ?? [];

        $t->same('ready_to_convert', $snapshot['status'] ?? null);
        $t->same(3, count($chunks));
        $t->same([[1, 1], [2, 2], [3, 3]], array_map(
            static fn (array $chunk): array => [$chunk['startPage'], $chunk['endPage']],
            $chunks
        ));
        $t->same(1, count($job['documents'][0]['pdfChunkMetrics'] ?? []), 'Three pages should share one measured parse request.');
        $t->same(3, $snapshot['metrics']['pdfPagesExtracted'] ?? null);
        $t->same(1, $snapshot['metrics']['pdfExtractionRequests'] ?? null);
    },
    'playground pdf jobs checkpoint page chunks without publishing partial documents and resume idempotently' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 1);
        $pdf = plpc_test_multipage_pdf([
            'FIRST PAGE CHECKPOINT SENTINEL',
            'SECOND PAGE CHECKPOINT SENTINEL',
            'THIRD PAGE CHECKPOINT SENTINEL',
        ]);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'checkpointed.pdf',
            'title' => 'Checkpointed PDF',
            'imageMode' => 'none',
            'pdfMode' => 'layout',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;

        $prepared = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_convert', $prepared['status'] ?? null);
        $job = get_option($option);
        $t->same(3, $job['documents'][0]['pdfPageCount'] ?? null);
        $t->same(1, $job['documents'][0]['pdfNextPage'] ?? null);

        $first = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $jobAfterFirst = get_option($option);
        $t->same('ready_to_convert', $first['status'] ?? null);
        $t->contains('page 1 of 3', strtolower((string) ($first['progress']['label'] ?? '')));
        $t->same(2, $jobAfterFirst['documents'][0]['pdfNextPage'] ?? null);
        $t->same(1, count($jobAfterFirst['documents'][0]['pdfChunks'] ?? []));
        $t->true(isset($jobAfterFirst['documents'][0]['pdfChunks'][0]['facts']), 'A PDF page checkpoint must persist facts, not already-decided blocks.');
        $t->true(!isset($jobAfterFirst['documents'][0]['pdfChunks'][0]['manifest']), 'Page checkpoints must not contain a block/media manifest.');
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'A persisted first-page chunk must not create a partial WordPress page.');

        // Model a worker dying after the deterministic chunk files reached
        // disk but before the cursor-bearing option was saved. Replaying the
        // same page must overwrite that chunk, not append duplicate content.
        $replay = $jobAfterFirst;
        $replay['documents'][0]['pdfNextPage'] = 1;
        $replay['documents'][0]['pdfChunks'] = [];
        update_option($option, $replay, false);
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $jobAfterReplay = get_option($option);
        $t->same(2, $jobAfterReplay['documents'][0]['pdfNextPage'] ?? null);
        $t->same(1, count($jobAfterReplay['documents'][0]['pdfChunks'] ?? []));
        $t->same(0, count($GLOBALS['plpc_test_posts']));

        $second = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $jobAfterSecond = get_option($option);
        $t->contains('page 2 of 3', strtolower((string) ($second['progress']['label'] ?? '')));
        $t->same(3, $jobAfterSecond['documents'][0]['pdfNextPage'] ?? null);
        $t->same(2, count($jobAfterSecond['documents'][0]['pdfChunks'] ?? []));
        $t->same(0, count($GLOBALS['plpc_test_posts']));

        $third = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $jobAfterThird = get_option($option);
        $t->contains('page 3 of 3', strtolower((string) ($third['progress']['label'] ?? '')));
        $t->same(4, $jobAfterThird['documents'][0]['pdfNextPage'] ?? null);
        $t->same(3, count($jobAfterThird['documents'][0]['pdfChunks'] ?? []));
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'All chunks must be durable before the final page is published.');

        $merged = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $jobAfterMerge = get_option($option);
        $t->same('ready_to_convert', $merged['status'] ?? null);
        $t->true(is_array($jobAfterMerge['documents'][0]['pdfDocumentFacts'] ?? null), 'All page facts should merge into one durable document snapshot before semantics run.');
        $t->same(0, count($GLOBALS['plpc_test_posts']));

        $semantics = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $jobAfterSemantics = get_option($option);
        $t->same('ready_to_convert', $semantics['status'] ?? null);
        $t->true(is_array($jobAfterSemantics['documents'][0]['pdfFinalBundle'] ?? null), 'The one global semantic pass should become a durable private bundle before publication.');
        $t->same(0, count($GLOBALS['plpc_test_posts']));
        $durableBundle = plpc_import_job_load_pdf_final_bundle($jobAfterSemantics, $jobAfterSemantics['documents'][0]);
        foreach (['FIRST', 'SECOND', 'THIRD'] as $ordinal) {
            $sentinel = $ordinal . ' PAGE CHECKPOINT SENTINEL';
            $t->same(1, substr_count($durableBundle['blocks'], $sentinel), $sentinel . ' should occur exactly once after global semantics.');
        }

        $materialized = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_convert', $materialized['status'] ?? null);
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'Post-ready blocks must remain private files until the complete PDF is assembled.');
        $readyToPublish = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_publish', $readyToPublish['status'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']), 'Finalization must create exactly one verified private draft.');
        $draftId = (int) ($readyToPublish['publication']['completed'] ?? -1) === 0
            ? (int) array_key_first($GLOBALS['plpc_test_posts'])
            : 0;
        $t->same('draft', $GLOBALS['plpc_test_posts'][$draftId]['post_status'] ?? null);

        $completed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('complete', $completed['status'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']), 'Publication must retain exactly one page.');
        $postId = (int) ($completed['result']['postId'] ?? 0);
        $t->same('publish', $GLOBALS['plpc_test_posts'][$postId]['post_status'] ?? null);
        $blocks = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');
        foreach (['FIRST', 'SECOND', 'THIRD'] as $ordinal) {
            $sentinel = $ordinal . ' PAGE CHECKPOINT SENTINEL';
            $t->same(1, substr_count($blocks, $sentinel), $sentinel . ' should occur exactly once after a replay.');
        }
        $t->true(!str_contains($blocks, 'Conversion notes'));
        $t->true(!str_contains($blocks, 'Import quality:'));

        // Model a worker dying after wp_insert_post() committed but before
        // the completed job option was saved. A fresh request must discover
        // and reuse that page by its durable job/document identity.
        update_option($option, $jobAfterSemantics, false);
        $GLOBALS['plpc_imported_media_by_hash'] = [];
        $recovered = [];
        for ($attempt = 0; $attempt < 5 && ($recovered['status'] ?? '') !== 'complete'; $attempt++) {
            $recovered = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $t->same('complete', $recovered['status'] ?? null);
        $t->same($postId, $recovered['result']['postId'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']), 'Final-page retry must not publish a duplicate page.');
    },

    'playground pdf segment planner uses durable fact bytes instead of upload size or document special cases' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_segment_max_fact_bytes', static fn (mixed $bytes): int => 300000);
        $segments = plpc_import_job_plan_pdf_segments([
            'pdfPageCount' => 4,
            'pdfChunks' => [
                ['startPage' => 1, 'endPage' => 1, 'bytes' => 100000],
                ['startPage' => 2, 'endPage' => 2, 'bytes' => 100000],
                ['startPage' => 3, 'endPage' => 3, 'bytes' => 100000],
                ['startPage' => 4, 'endPage' => 4, 'bytes' => 100000],
            ],
        ]);

        $t->same(2, count($segments));
        $t->same([1, 3, 300000], [
            $segments[0]['startPage'],
            $segments[0]['endPage'],
            $segments[0]['factsBytes'],
        ]);
        $t->same([4, 4, 100000], [
            $segments[1]['startPage'],
            $segments[1]['endPage'],
            $segments[1]['factsBytes'],
        ]);
    },

    'playground pdf jobs keep bounded semantic ranges internal and publish one continuous page by default' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_segment_max_pages', static fn (mixed $pages): int => 2);
        $sentinels = [
            'SEGMENTED PAGE ONE SENTINEL',
            'SEGMENTED PAGE TWO SENTINEL',
            'SEGMENTED PAGE THREE SENTINEL',
            'SEGMENTED PAGE FOUR SENTINEL',
            'SEGMENTED PAGE FIVE SENTINEL',
            'SEGMENTED PAGE SIX SENTINEL',
        ];
        $pdf = plpc_test_multipage_pdf($sentinels);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'dense-checkpointed.pdf',
            'title' => 'Dense checkpointed PDF',
            'imageMode' => 'none',
            'pdfMode' => 'layout',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        $jobBeforeFinalPublication = null;

        for ($attempt = 0; $attempt < 32 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $response = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
            $t->same(200, $response->get_status());
            $snapshot = $response->get_data();
            $currentJob = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
            $currentSegments = is_array($currentJob['documents'][0]['pdfSegments'] ?? null)
                ? $currentJob['documents'][0]['pdfSegments']
                : [];
            $nextSegment = max(0, (int) ($currentJob['documents'][0]['pdfNextSegment'] ?? 0));
            if (($snapshot['status'] ?? '') !== 'complete'
                && $currentSegments !== []
                && $nextSegment === count($currentSegments)
                && count(array_filter($currentSegments, static fn (array $segment): bool => is_array($segment['publicationBundle'] ?? null))) === count($currentSegments)
            ) {
                $jobBeforeFinalPublication = $currentJob;
            }
        }

        $t->same('complete', $snapshot['status'] ?? null);
        $t->same('single', $snapshot['result']['kind'] ?? null);
        $t->same(1, $snapshot['result']['postCount'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']), 'Internal semantic ranges must become one WordPress page, not range pages plus an index.');
        $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $segments = $job['documents'][0]['pdfSegments'] ?? [];
        $t->same(3, count($segments));
        $t->same([[1, 2], [3, 4], [5, 6]], array_map(
            static fn (array $segment): array => [$segment['startPage'], $segment['endPage']],
            $segments
        ));

        $postId = (int) ($snapshot['result']['postId'] ?? 0);
        $rangeContent = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');
        foreach ($sentinels as $sentinel) {
            $t->same(1, substr_count($rangeContent, $sentinel), $sentinel . ' must survive exactly once in the continuous result.');
        }
        $t->true(!str_contains($rangeContent, 'pages 1–2'), 'Internal segment boundaries must not leak into the page body.');

        // Model the assembled page committing immediately before the job
        // cursor is saved. The stable single-output identity must recover it.
        $t->true(is_array($jobBeforeFinalPublication), 'Every bounded range should have a post-ready bundle before assembly.');
        update_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, $jobBeforeFinalPublication, false);
        $recovered = [];
        for ($attempt = 0; $attempt < 4 && ($recovered['status'] ?? '') !== 'complete'; $attempt++) {
            $recovered = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $t->same('complete', $recovered['status'] ?? null);
        $t->same($postId, $recovered['result']['postId'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']), 'A final retry must not duplicate the assembled page.');
    },

    'playground pdf page-tree mode creates one ordered child per physical page below a root index' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 2);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 2);
        $pdf = plpc_test_multipage_pdf([
            'PHYSICAL PAGE ONE',
            '',
            'PHYSICAL PAGE THREE',
        ]);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'physical-pages.pdf',
            'title' => 'Physical pages',
            'imageMode' => 'none',
            'pdfMode' => 'layout',
            'pdfOutputMode' => 'pages',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        for ($attempt = 0; $attempt < 48 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }

        $t->same('complete', $snapshot['status'] ?? null);
        $t->same('pdf-page-tree', $snapshot['result']['kind'] ?? null);
        $t->same(4, $snapshot['result']['postCount'] ?? null);
        $t->same(3, $snapshot['result']['pageCount'] ?? null);
        $t->same(4, count($GLOBALS['plpc_test_posts']));
        $rootId = (int) ($snapshot['result']['postId'] ?? 0);
        $children = $snapshot['result']['children'] ?? [];
        $t->same([1, 2, 3], array_map(static fn (array $child): int => (int) ($child['pageNumber'] ?? 0), $children));
        foreach ($children as $index => $child) {
            $childId = (int) ($child['postId'] ?? 0);
            $t->same($rootId, $GLOBALS['plpc_test_posts'][$childId]['post_parent'] ?? null);
            $t->same($index + 1, $GLOBALS['plpc_test_posts'][$childId]['menu_order'] ?? null);
            $t->same('publish', $GLOBALS['plpc_test_posts'][$childId]['post_status'] ?? null);
        }
        $blankChildId = (int) ($children[1]['postId'] ?? 0);
        $t->true((bool) ($children[1]['intentionalBlank'] ?? false), 'A facts-certified blank physical page should remain explicitly identifiable.');
        $blankFingerprint = plpc_import_content_fingerprint((string) ($GLOBALS['plpc_test_posts'][$blankChildId]['post_content'] ?? ''));
        $t->same(0, $blankFingerprint['visibleTextBytes'] ?? null, 'A certified blank physical page must not gain fabricated text.');
        $t->same(0, $blankFingerprint['imageCount'] ?? null);
        $rootBlocks = (string) ($GLOBALS['plpc_test_posts'][$rootId]['post_content'] ?? '');
        $t->contains('<ol class="wp-block-list">', $rootBlocks);
        foreach ($children as $child) {
            $t->contains((string) ($child['pageUrl'] ?? ''), $rootBlocks);
        }
        $t->true(!str_contains($rootBlocks, 'Conversion notes'));
        $t->true(!str_contains($rootBlocks, 'Import quality:'));
    },

    'playground oversized single-page PDF stops before post creation and resumes the same job as a page tree' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_single_page_limit_bytes', static fn (mixed $bytes): int => 256);
        $pdf = plpc_test_multipage_pdf([
            str_repeat('OVERSIZED PAGE ONE ', 20),
            str_repeat('OVERSIZED PAGE TWO ', 20),
        ]);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'oversized.pdf',
            'title' => 'Oversized PDF',
            'imageMode' => 'none',
            'pdfMode' => 'layout',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $snapshot = $created;
        for ($attempt = 0; $attempt < 32 && ($snapshot['status'] ?? '') !== 'awaiting_output_mode'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }

        $t->same('awaiting_output_mode', $snapshot['status'] ?? null);
        $t->same('pdf_single_page_too_large', $snapshot['failure']['code'] ?? null);
        $t->same(true, $snapshot['failure']['recoverable'] ?? null);
        $t->true((int) ($snapshot['failure']['actualBytes'] ?? 0) > (int) ($snapshot['failure']['allowedBytes'] ?? PHP_INT_MAX));
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'The size guard must run before any partial single page is inserted.');
        $pausedJob = get_option($option);
        $chunkDigests = array_column($pausedJob['documents'][0]['pdfChunks'] ?? [], 'sha256');
        $t->same(2, count($chunkDigests));

        $resumed = plpc_switch_import_output_mode(plpc_test_import_job_request([
            'pdfOutputMode' => 'pages',
        ], $jobId))->get_data();
        $t->same('ready_to_convert', $resumed['status'] ?? null);
        $t->same('pages', $resumed['output']['pdfOutputMode'] ?? null);
        $t->same($chunkDigests, array_column(get_option($option)['documents'][0]['pdfChunks'] ?? [], 'sha256'), 'Mode switching must reuse the exact saved physical-page facts.');

        for ($attempt = 0; $attempt < 40 && ($resumed['status'] ?? '') !== 'complete'; $attempt++) {
            $resumed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $t->same('complete', $resumed['status'] ?? null);
        $t->same('pdf-page-tree', $resumed['result']['kind'] ?? null);
        $t->same(3, $resumed['result']['postCount'] ?? null);
        $t->same(3, count($GLOBALS['plpc_test_posts']));
    },

    'playground collection index links a PDF root and ordinary documents without flattening PDF children' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'Mixed import',
            'title' => 'Mixed import',
            'imageMode' => 'none',
            'pdfOutputMode' => 'pages',
            'files' => [
                ['path' => 'book.pdf', 'bytes' => base64_encode(plpc_test_multipage_pdf(['BOOK PAGE ONE', 'BOOK PAGE TWO']))],
                ['path' => 'notes.md', 'bytes' => base64_encode("# Notes\n\nOrdinary document.\n")],
            ],
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        for ($attempt = 0; $attempt < 48 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }

        $t->same('complete', $snapshot['status'] ?? null);
        $t->same(true, $snapshot['result']['batch'] ?? null);
        $documents = $snapshot['result']['documents'] ?? [];
        $t->same(2, count($documents));
        $pdfResult = current(array_filter($documents, static fn (array $result): bool => ($result['kind'] ?? '') === 'pdf-page-tree'));
        $ordinary = current(array_filter($documents, static fn (array $result): bool => ($result['kind'] ?? '') === 'document'));
        $t->true(is_array($pdfResult));
        $t->true(is_array($ordinary));
        $indexBlocks = (string) ($GLOBALS['plpc_test_posts'][(int) ($snapshot['result']['postId'] ?? 0)]['post_content'] ?? '');
        $t->contains((string) ($pdfResult['pageUrl'] ?? ''), $indexBlocks);
        $t->contains((string) ($ordinary['pageUrl'] ?? ''), $indexBlocks);
        foreach ($pdfResult['children'] ?? [] as $child) {
            $t->true(!str_contains($indexBlocks, '<code>' . (string) ($child['path'] ?? '') . '</code>'), 'The collection index should link the PDF root, not list each physical child as a peer document.');
        }
    },

    'playground media attachment retries deduplicate across PHP requests' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $first = plpc_insert_media_attachment('same durable image bytes', 'first.png', 'image/png');
        $GLOBALS['plpc_imported_media_by_hash'] = [];
        $second = plpc_insert_media_attachment('same durable image bytes', 'second.png', 'image/png');

        $t->same($first, $second);
        $t->same(1, count($GLOBALS['plpc_test_uploads']), 'A retry in a new PHP request must reuse the existing upload.');
        $t->same(1, count($GLOBALS['plpc_test_attachments']), 'A retry in a new PHP request must reuse the existing attachment.');
    },
    'playground converter resumes a checkpoint left converting by an interrupted PHP request' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'resume.md',
            'bytes' => base64_encode("# Resume me\n"),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = get_option($option);
        $job['status'] = 'converting';
        $job['stage'] = 'inspecting';
        $job['progress'] = ['completed' => 0, 'total' => 1, 'label' => 'Inspecting before an interruption.'];
        update_option($option, $job, false);

        $afterInspection = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_convert', $afterInspection['status'] ?? null);
        $t->true(in_array('resuming', array_column($afterInspection['events'] ?? [], 'stage'), true), 'An interrupted inspection should be visible in the activity log.');

        $job = get_option($option);
        $job['status'] = 'converting';
        $job['stage'] = 'writing_blocks';
        $job['progress'] = ['completed' => 4, 'total' => 6, 'label' => 'Writing blocks before an interruption.'];
        update_option($option, $job, false);

        $resumed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $t->same(200, $resumed->get_status());
        $snapshot = $resumed->get_data();
        $t->same('ready_to_publish', $snapshot['status'] ?? null);
        $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('complete', $snapshot['status'] ?? null);
        $t->true(is_array($snapshot['result'] ?? null), 'A retried durable document unit should still produce an import result.');
    },
    'playground converter yields a durable conversion checkpoint before the PHP deadline and finishes on the next request' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'deadline.md',
            'bytes' => base64_encode("# Deadline checkpoint\n\nThis document must not spin after a server-time handoff.\n"),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');

        $prepared = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_convert', $prepared['status'] ?? null);

        add_filter('plpc_import_request_deadline', static fn (mixed $deadline): float => microtime(true) - 1.0);
        $yielded = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = $yielded->get_data();
        $t->same(200, $yielded->get_status());
        $t->same('ready_to_convert', $snapshot['status'] ?? null);
        $t->contains('Pausing before this server reaches its execution limit', $snapshot['progress']['label'] ?? '');
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'An intentional deadline handoff must happen before a page is created.');
        $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $t->same(1, $job['checkpoint']['deadlineYields'] ?? null);
        $t->true(in_array('checkpoint', array_column($snapshot['events'] ?? [], 'stage'), true), 'The saved handoff should be visible in the activity log.');

        $GLOBALS['plpc_test_filters'] = [];
        $readyToPublish = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_publish', $readyToPublish['status'] ?? null);
        $completed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('complete', $completed['status'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']), 'A fresh request should finish the same durable document unit exactly once.');
    },
    'playground converter stops a repeatedly interrupted durable document instead of retrying forever' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'interrupted.md',
            'bytes' => base64_encode("# Interrupted\n"),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));

        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = get_option($option);
        $job['status'] = 'converting';
        $job['stage'] = 'reading';
        $job['checkpoint'] = [
            'documentIndex' => 0,
            'deadlineYields' => 0,
            'interruptedRetries' => PLPC_IMPORT_JOB_MAX_INTERRUPTED_RETRIES_PER_DOCUMENT - 1,
        ];
        update_option($option, $job, false);

        $response = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = $response->get_data();
        $t->same(200, $response->get_status());
        $t->same('failed', $snapshot['status'] ?? null);
        $t->contains('stopped to avoid a retry loop', strtolower((string) ($snapshot['message'] ?? '')));
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'The retry cap must fail before duplicate page work begins.');
    },
    'playground converter skips a full-page Form wrapper without blocking its text import' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'page-wrapper.pdf',
            'title' => 'Page wrapper',
            'imageMode' => 'all',
            'pdfMode' => 'layout',
            'bytes' => base64_encode(plpc_test_page_wrapper_form_xobject_pdf()),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();

        $t->same([], $snapshot['renderRequests'] ?? null, 'A page-sized layout wrapper must not become a blocking browser figure request.');
        $t->same(1, $snapshot['metrics']['pdfPageSizedFormsSkipped'] ?? null);
        $t->true(
            count(array_filter(
                $snapshot['events'] ?? [],
                static fn (array $event): bool => str_contains((string) ($event['message'] ?? ''), 'page-sized PDF layout wrapper')
            )) >= 1,
            'The skipped enhancement should remain visible in progress diagnostics.'
        );

        for ($attempt = 0; $attempt < 12 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $t->same('complete', $snapshot['status'] ?? null);
        $postId = max(0, (int) ($snapshot['result']['postId'] ?? 0));
        $t->contains('Readable page text', (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? ''));
    },
    'playground converter round trips a PDF Form XObject through the browser renderer job protocol' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0oAAAAABJRU5ErkJggg==', true);
        $t->true(is_string($png) && $png !== '', 'Expected a valid PNG payload for the browser renderer response.');

        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'chart.pdf',
            'title' => 'Browser-rendered chart',
            'imageMode' => 'all',
            'pdfMode' => 'layout',
            'bytes' => base64_encode(plpc_test_renderable_form_xobject_pdf()),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;

        for ($attempt = 0; $attempt < 3 && ($snapshot['renderRequests'] ?? []) === []; $attempt++) {
            $response = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
            $t->same(200, $response->get_status());
            $snapshot = $response->get_data();
            plpc_test_assert_import_job_snapshot($t, $snapshot, $jobId);
        }

        $t->true(($snapshot['renderRequests'] ?? []) !== [], 'A placed Form XObject should request a browser PDF.js render rather than silently disappearing.');
        $renderRequest = $snapshot['renderRequests'][0];
        $t->true(is_string($renderRequest['id'] ?? null) && $renderRequest['id'] !== '', 'A renderer request needs an opaque id.');
        $t->same(1, $renderRequest['page'] ?? null);
        $t->true(is_array($renderRequest['bbox'] ?? null) && count($renderRequest['bbox']) === 4, 'A renderer request needs the page-space Form bounding box.');

        $source = plpc_import_job_render_source(new WP_REST_Request('', [], [
            'jobId' => $jobId,
            'requestId' => (string) $renderRequest['id'],
        ]));
        $t->same(200, $source->get_status());
        $sourceData = $source->get_data();
        $t->same(true, $sourceData['ok'] ?? null);
        $t->same('chart.pdf', $sourceData['path'] ?? null);
        $t->same(plpc_test_renderable_form_xobject_pdf(), base64_decode((string) ($sourceData['bytes'] ?? ''), true));
        $t->true(!array_key_exists('storage', $sourceData), 'The browser source handoff must not disclose a server storage path.');

        $unknown = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => 'not-a-request-for-this-job',
            'bytes' => base64_encode($png),
            'mimeType' => 'image/png',
            'width' => 1,
            'height' => 1,
        ], $jobId));
        $t->true($unknown->get_status() >= 400, 'A renderer result must be tied to an outstanding request id.');
        $t->same(false, $unknown->get_data()['ok'] ?? null);

        $submitted = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $renderRequest['id'],
            'bytes' => base64_encode($png),
            'mimeType' => 'image/png',
            'width' => 1,
            'height' => 1,
        ], $jobId));
        $t->same(200, $submitted->get_status());
        $snapshot = $submitted->get_data();
        plpc_test_assert_import_job_snapshot($t, $snapshot, $jobId);

        $consumedSource = plpc_import_job_render_source(new WP_REST_Request('', [], [
            'jobId' => $jobId,
            'requestId' => (string) $renderRequest['id'],
        ]));
        $t->same(404, $consumedSource->get_status(), 'A PDF source handoff is available only while its render request is outstanding.');

        for ($attempt = 0; $attempt < 8 && !in_array($snapshot['status'] ?? '', ['complete', 'completed'], true); $attempt++) {
            $response = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
            $t->same(200, $response->get_status());
            $snapshot = $response->get_data();
            plpc_test_assert_import_job_snapshot($t, $snapshot, $jobId);
        }

        $t->true(in_array($snapshot['status'] ?? '', ['complete', 'completed'], true), 'The job should continue after the browser returns its PDF.js render.');
        $t->true(is_array($snapshot['result'] ?? null), 'The completed browser-rendered import needs a page result.');
        $t->true(($snapshot['result']['imagesImported'] ?? 0) >= 1, 'The returned browser render should become imported WordPress media.');
        $t->true(count($GLOBALS['plpc_test_uploads']) >= 1, 'The returned browser render should be written through the normal media importer.');
        $t->same($png, $GLOBALS['plpc_test_uploads'][0]['bits'] ?? null);
    },
    'playground renderer rejects image dimensions that do not match the returned PNG' => static function (TestRunner $t): void {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0oAAAAABJRU5ErkJggg==', true);
        $t->true(is_string($png) && $png !== '', 'Expected a valid one-pixel PNG fixture.');

        $t->throws(RuntimeException::class, static fn (): array => plpc_import_job_rendered_image_from_payload([
            'bytes' => base64_encode($png),
            'mimeType' => 'image/png',
            'width' => 2,
            'height' => 1,
        ]));
    },
    'playground import storage adds server-side web access protections' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'private.md',
            'bytes' => base64_encode('# Private source'),
        ]));

        $root = plpc_test_import_job_upload_dir() . '/' . PLPC_IMPORT_JOB_DIRECTORY;
        $rules = file_get_contents($root . '/.htaccess');
        $t->true(is_string($rules), 'Import storage should create an Apache deny rule when permitted.');
        $t->contains('Require all denied', (string) $rules);
        $t->true(is_file($root . '/web.config'), 'Import storage should include the IIS deny rule when permitted.');
    },
    'playground converter only enables svg uploads in trusted contexts' => static function (TestRunner $t): void {
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'example.test';
        $GLOBALS['plpc_test_current_user_caps'] = [];

        try {
            $untrusted = plpc_upload_mimes([]);
            $t->same('image/webp', $untrusted['webp'] ?? null);
            $t->true(!isset($untrusted['svg']), 'Anonymous non-Playground installs should not enable SVG uploads.');

            $GLOBALS['plpc_test_current_user_caps'] = ['unfiltered_html'];
            $trustedUser = plpc_upload_mimes([]);
            $t->same('image/svg+xml', $trustedUser['svg'] ?? null);

            $_SERVER['HTTP_HOST'] = 'playground.wordpress.net';
            $GLOBALS['plpc_test_current_user_caps'] = [];
            $playground = plpc_upload_mimes([]);
            $t->same('image/svg+xml', $playground['svg'] ?? null);
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
            $GLOBALS['plpc_test_current_user_caps'] = [];
        }
    },
    'playground converter does not bypass SVG trust checks while importing extracted media' => static function (TestRunner $t): void {
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'example.test';
        $GLOBALS['plpc_test_current_user_caps'] = [];
        $GLOBALS['plpc_test_uploads'] = [];
        $GLOBALS['plpc_test_attachments'] = [];
        $GLOBALS['plpc_imported_media_by_hash'] = [];

        try {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
            $t->same(null, plpc_insert_media_attachment($svg, 'untrusted.svg', 'image/svg+xml'), 'Extracted SVG media must use the same trust gate as direct SVG uploads.');
            $t->same([], $GLOBALS['plpc_test_uploads'], 'An untrusted SVG must not be written below public uploads.');

            $GLOBALS['plpc_test_current_user_caps'] = ['unfiltered_html'];
            $attachment = plpc_insert_media_attachment($svg, 'trusted.svg', 'image/svg+xml');
            $t->true(is_array($attachment) && (int) ($attachment['id'] ?? 0) > 0, 'A trusted administrator may import SVG media.');
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
            $GLOBALS['plpc_test_current_user_caps'] = [];
        }
    },
    'playground pdf importer keeps geometry table reconstruction enabled with prose repair by default' => static function (TestRunner $t): void {
        $options = plpc_converter_options('pdf');

        $t->same(PHP_INT_MAX, $options['readerOptions']['maxTextBytes'] ?? null);
        $t->same(false, $options['readerOptions']['pdfFastTextOnly'] ?? null);
        $t->same(true, $options['readerOptions']['pdfGeometryTables'] ?? null);
        $t->same(true, $options['readerOptions']['pdfRepairProseText'] ?? null);
        $t->same(true, $options['readerOptions']['pdfCollectImagePlacements'] ?? null);
    },
    'playground pdf importer can retry in text only mode without geometry tables' => static function (TestRunner $t): void {
        $options = plpc_converter_options('pdf', 'text-only');

        $t->same(PHP_INT_MAX, $options['readerOptions']['maxTextBytes'] ?? null);
        $t->same(false, $options['readerOptions']['pdfFastTextOnly'] ?? null);
        $t->same(false, $options['readerOptions']['pdfGeometryTables'] ?? null);
        $t->same(true, $options['readerOptions']['pdfRepairProseText'] ?? null);
    },
    'playground csv importer still permits blank records' => static function (TestRunner $t): void {
        $options = plpc_converter_options('csv');

        $t->same(true, $options['readerOptions']['allowBlankRecords'] ?? null);
    },
    'playground importer normalizes image import modes' => static function (TestRunner $t): void {
        $t->same('important', plpc_normalize_image_mode(''));
        $t->same('important', plpc_normalize_image_mode('auto'));
        $t->same('none', plpc_normalize_image_mode('no_images'));
        $t->same('none', plpc_normalize_image_mode(false));
        $t->same('all', plpc_normalize_image_mode('all-images'));
        $t->same('all', plpc_normalize_image_mode(true));
    },
    'playground importer infers document types consistently for single files and collections' => static function (TestRunner $t): void {
        $expectedByPath = [
            'notes/guide.gfm' => 'gfm',
            'notes/guide.dokuwiki' => 'dokuwiki',
            'references/records.csl.json' => 'csljson',
            'references/records.biblatex' => 'biblatex',
            'papers/article.jats.xml' => 'jats',
            'manuals/tool.mdoc' => 'mdoc',
            'exports/library.enl' => 'endnotexml',
            'notes/guide.rst' => 'rst',
        ];

        foreach ($expectedByPath as $path => $format) {
            $t->same($format, plpc_infer_document_format($path, 'fixture bytes'), $path);
        }

        $documents = plpc_convertible_collection_files([
            'label' => 'Inference fixtures',
            'files' => array_map(
                static fn (string $path): array => ['path' => $path, 'bytes' => 'fixture bytes'],
                array_keys($expectedByPath)
            ),
        ]);
        $inferredByPath = [];
        foreach ($documents as $document) {
            $inferredByPath[$document['path']] = $document['format'];
        }
        $t->same($expectedByPath, $inferredByPath);

        $docx = PortLibs\Pandoc\ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="urn:example"/>'],
        ]);
        $t->same('pdf', plpc_infer_document_format('upload', "%PDF-1.7\n"));
        $t->same('rtf', plpc_infer_document_format('upload', '{\\rtf1\\ansi Inferred}'));
        $t->same('docx', plpc_infer_document_format('upload', $docx));
        $t->same('markdown', plpc_infer_document_format('upload', '# Untitled document'));

        $t->same('pdf', plpc_infer_document_format('renamed.txt', "%PDF-1.7\n"), 'A verified signature must override a misleading filename.');
        $t->same('opml', plpc_infer_document_format('upload', '<?xml version="1.0"?><opml version="2.0"><body/></opml>'));
        $t->same('json', plpc_infer_document_format('upload', '{"title":"nbformat"}'), 'A string value must not turn arbitrary JSON into a notebook.');
        $t->same('ipynb', plpc_infer_document_format('upload', '{"nbformat":4,"cells":[]}'));
        $t->same('markdown', plpc_infer_document_format('upload', "😀 !!!"), 'Valid text need not contain letters or numbers.');

        $collectionWithWordEntry = PortLibs\Pandoc\ZipPackage::build([
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="urn:example"/>'],
        ]);
        $t->same('zip', plpc_infer_document_format('upload', $collectionWithWordEntry), 'A collection is not an Office document without OPC metadata.');
    },
    'playground endpoint ignores a client document-type hint and uses inference' => static function (TestRunner $t): void {
        $GLOBALS['plpc_test_posts'] = [];
        $request = new WP_REST_Request(json_encode([
            'filename' => 'guide.gfm',
            'format' => 'pdf',
            'title' => 'Inferred guide',
            'bytes' => base64_encode('# Inferred heading'),
        ], JSON_THROW_ON_ERROR));

        $response = plpc_convert_uploaded_document($request);
        $data = $response->get_data();

        $t->same(true, $data['ok'] ?? null);
        $t->same('gfm', $data['format'] ?? null);
        $t->contains('Inferred heading', $GLOBALS['plpc_test_posts'][$data['postId']]['post_content'] ?? '');
    },
    'playground endpoint converts a staged epub upload and removes its temporary file' => static function (TestRunner $t): void {
        $GLOBALS['plpc_test_posts'] = [];
        $fixture = dirname(__DIR__, 3) . '/pandoc-showcase/samples/epub-features-features.epub';
        $bytes = file_get_contents($fixture);
        $t->true(is_string($bytes) && $bytes !== '', 'Expected a readable EPUB fixture.');

        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'preview.playground.wordpress.net';
        if (!is_dir(PLPC_STAGED_UPLOAD_DIRECTORY)) {
            mkdir(PLPC_STAGED_UPLOAD_DIRECTORY, 0777, true);
        }
        $stagedPath = PLPC_STAGED_UPLOAD_DIRECTORY . '/' . bin2hex(random_bytes(12)) . '.upload';
        file_put_contents($stagedPath, $bytes);

        try {
            $request = new WP_REST_Request(json_encode([
                'filename' => 'features.epub',
                'title' => 'Staged EPUB',
                'stagedPath' => $stagedPath,
            ], JSON_THROW_ON_ERROR));
            $response = plpc_convert_uploaded_document($request);
            $data = $response->get_data();

            $t->same(200, $response->get_status());
            $t->same(true, $data['ok'] ?? null);
            $t->same('epub', $data['format'] ?? null);
            $t->contains('Staged EPUB', $GLOBALS['plpc_test_posts'][$data['postId']]['post_title'] ?? '');
            $t->same(false, is_file($stagedPath), 'The temporary staged source must be removed after it is read.');
        } finally {
            @unlink($stagedPath);
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
        }
    },
    'persisted import jobs convert an EPUB through the same advance protocol as PDFs' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $fixture = dirname(__DIR__, 3) . '/pandoc-showcase/samples/epub-features-features.epub';
        $bytes = file_get_contents($fixture);
        $t->true(is_string($bytes) && $bytes !== '', 'Expected a readable EPUB fixture.');

        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'features.epub',
            'title' => 'Persisted EPUB',
            'bytes' => base64_encode($bytes),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $storedJob = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $preparedSources = is_array($storedJob) ? plpc_import_job_prepare_source_files($storedJob) : [];
        $t->same('epub', $preparedSources[0]['format'] ?? null, 'A persisted EPUB should be recognized from its bounded package metadata.');
        $t->same('', $preparedSources[0]['bytes'] ?? null, 'Preparing a persisted EPUB must not load its ZIP source into PHP memory.');
        $snapshot = $created;
        for ($attempt = 0; $attempt < 6 && !in_array($snapshot['status'] ?? '', ['complete', 'failed'], true); $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }

        $t->same('complete', $snapshot['status'] ?? null, 'The persisted-job protocol must complete an ordinary EPUB without a browser renderer round trip.');
        $t->same('epub', $snapshot['result']['format'] ?? null);
        $postId = (int) ($snapshot['result']['postId'] ?? 0);
        $t->true($postId > 0, 'The persisted EPUB import should create a WordPress page.');
        $t->contains('Persisted EPUB', $GLOBALS['plpc_test_posts'][$postId]['post_title'] ?? '');
    },
    'playground converter uses file backed EPUB reader and media extractor without source bytes' => static function (TestRunner $t): void {
        $GLOBALS['plpc_test_posts'] = [];
        $fixture = dirname(__DIR__, 3) . '/pandoc-showcase/samples/epub-features-features.epub';
        $t->true(is_file($fixture), 'Expected a file-backed EPUB fixture.');

        $result = plpc_convert_collection_file_to_page([
            'path' => 'features.epub',
            'format' => 'epub',
            'sourcePath' => $fixture,
        ], null, 'File-backed EPUB');

        $t->same('epub', $result['format'] ?? null);
        $postId = (int) ($result['postId'] ?? 0);
        $t->true($postId > 0, 'The file-backed EPUB should create a WordPress page without a bytes payload.');
        $t->contains('File-backed EPUB', $GLOBALS['plpc_test_posts'][$postId]['post_title'] ?? '');
    },
    'persisted import jobs move browser-staged sources without base64 source bodies' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'preview.playground.wordpress.net';
        if (!is_dir(PLPC_STAGED_UPLOAD_DIRECTORY)) {
            mkdir(PLPC_STAGED_UPLOAD_DIRECTORY, 0777, true);
        }
        $stagedPath = PLPC_STAGED_UPLOAD_DIRECTORY . '/' . bin2hex(random_bytes(12)) . '.upload';
        file_put_contents($stagedPath, "# Staged import\n\nThe source never enters the JSON request body.\n");

        try {
            $created = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'staged-import.md',
                'title' => 'Staged import',
                'stagedFiles' => [[
                    'path' => 'staged-import.md',
                    'stagedPath' => $stagedPath,
                ]],
            ]));
            $t->same(201, $created->get_status());
            $snapshot = $created->get_data();
            $jobId = (string) ($snapshot['jobId'] ?? '');
            $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
            $source = is_array($job) ? ($job['sourceFiles'][0] ?? null) : null;

            $t->same(false, is_file($stagedPath), 'Creating the job should move the staged source out of temporary browser storage.');
            $t->true(is_array($source), 'The staged source must be recorded in durable job storage.');
            $t->same("# Staged import\n\nThe source never enters the JSON request body.\n", plpc_import_job_read_file($job, (string) ($source['storage'] ?? '')));

            for ($attempt = 0; $attempt < 6 && !in_array($snapshot['status'] ?? '', ['complete', 'failed'], true); $attempt++) {
                $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
            }
            $t->same('complete', $snapshot['status'] ?? null);
            $t->contains('Staged import', $GLOBALS['plpc_test_posts'][(int) ($snapshot['result']['postId'] ?? 0)]['post_title'] ?? '');
        } finally {
            @unlink($stagedPath);
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
        }
    },
    'persisted import jobs reject ambiguous staged source manifests' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'preview.playground.wordpress.net';

        try {
            $response = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'ambiguous.md',
                'stagedFiles' => [[
                    'path' => 'ambiguous.md',
                    'stagedPath' => PLPC_STAGED_UPLOAD_DIRECTORY . '/not-read.upload',
                ]],
                'bytes' => base64_encode('# ignored'),
            ]));
            $t->same(400, $response->get_status());
            $t->same(false, $response->get_data()['ok'] ?? null);
            $t->contains('either a staged-file manifest', strtolower((string) ($response->get_data()['message'] ?? '')));
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
        }
    },
    'playground endpoint rejects staged paths outside its temporary upload namespace' => static function (TestRunner $t): void {
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'preview.playground.wordpress.net';

        try {
            $request = new WP_REST_Request(json_encode([
                'filename' => 'outside.epub',
                'title' => 'Outside path',
                'stagedPath' => '/tmp/not-a-port-libs-upload.epub',
            ], JSON_THROW_ON_ERROR));
            $response = plpc_convert_uploaded_document($request);
            $data = $response->get_data();

            $t->same(500, $response->get_status());
            $t->same(false, $data['ok'] ?? null);
            $t->contains('staged upload', strtolower((string) ($data['message'] ?? '')));
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
        }
    },
    'playground endpoint rejects ambiguous staged and encoded document sources' => static function (TestRunner $t): void {
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'preview.playground.wordpress.net';

        try {
            $request = new WP_REST_Request(json_encode([
                'filename' => 'ambiguous.epub',
                'stagedPath' => PLPC_STAGED_UPLOAD_DIRECTORY . '/unread.upload',
                'bytes' => base64_encode('not used'),
            ], JSON_THROW_ON_ERROR));
            $response = plpc_convert_uploaded_document($request);
            $data = $response->get_data();

            $t->same(500, $response->get_status());
            $t->same(false, $data['ok'] ?? null);
            $t->contains('either staged bytes or encoded bytes', strtolower((string) ($data['message'] ?? '')));
        } finally {
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
        }
    },
    'playground importer accepts bounded browser decoded pdf rasters' => static function (TestRunner $t): void {
        $png = "\x89PNG\r\n\x1a\n"
            . pack('N', 13) . 'IHDR' . pack('NNCCCCC', 100, 100, 1, 0, 0, 0, 0)
            . pack('N', 0);
        $rasters = plpc_pdf_raster_images_from_payload([
            [
                'object' => '00017',
                'bytes' => base64_encode($png),
                'mimeType' => 'image/png',
                'width' => 100,
                'height' => 100,
            ],
            [
                'object' => '../bad',
                'bytes' => base64_encode($png),
                'mimeType' => 'image/png',
                'width' => 100,
                'height' => 100,
            ],
            [
                'object' => '18',
                'bytes' => 'not base64',
                'mimeType' => 'image/png',
                'width' => 100,
                'height' => 100,
            ],
        ]);

        $t->same(1, count($rasters));
        $t->same('00017', $rasters[0]['object'] ?? null);
        $t->same($png, $rasters[0]['contents'] ?? null);

        $byPath = plpc_pdf_raster_images_by_path([
            'docs/../docs/book.pdf' => [[
                'object' => '00017',
                'bytes' => base64_encode($png),
                'mimeType' => 'image/png',
                'width' => 100,
                'height' => 100,
            ]],
        ]);
        $t->same(['docs/book.pdf'], array_keys($byPath));
        $t->same('00017', $byPath['docs/book.pdf'][0]['object'] ?? null);
    },
    'playground importer turns browser PDF rasters into WordPress media' => static function (TestRunner $t): void {
        $GLOBALS['plpc_imported_media_by_hash'] = [];
        $GLOBALS['plpc_test_uploads'] = [];
        $GLOBALS['plpc_test_attachments'] = [];
        $GLOBALS['plpc_test_posts'] = [];
        $png = "\x89PNG\r\n\x1a\n"
            . pack('N', 13) . 'IHDR' . pack('NNCCCCC', 100, 100, 1, 0, 0, 0, 0)
            . pack('N', 0);
        $content = "BT /F1 12 Tf 72 720 Td (Before image) Tj ET\n"
            . "q 100 0 0 100 72 580 cm /Image17 Do Q\n"
            . "BT /F1 12 Tf 72 540 Td (After image) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> /XObject << /Image17 17 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "00017 0 obj\n"
            . "<< /Type /XObject /Subtype /Image /Width 100 /Height 100 /BitsPerComponent 1 /Filter /JBIG2Decode /Length 3 >>\n"
            . "stream\nabc\nendstream\nendobj\n%%EOF\n";

        $result = plpc_convert_collection_file_to_page([
            'path' => 'scan.pdf',
            'bytes' => $pdf,
            'pdfRasterImages' => [[
                'object' => '17',
                'contents' => $png,
                'mimeType' => 'image/png',
                'width' => 100,
                'height' => 100,
            ]],
        ], null, 'Scanned import', 'important');

        $t->same(1, $result['imageTagCount'] ?? null);
        $t->same(1, $result['imagesImported'] ?? null);
        $t->true(in_array('extract-media-pdf-image-raster-loaded:00017:important', $result['diagnostics'] ?? [], true));
        $t->same(1, count($GLOBALS['plpc_test_attachments']));
        $t->contains('https://playground.test/uploads/attachment-1.png', $GLOBALS['plpc_test_posts'][1]['post_content'] ?? '');
    },
    'playground importer preserves an unrasterized JPEG 2000 PDF image as a downloadable attachment' => static function (TestRunner $t): void {
        $GLOBALS['plpc_imported_media_by_hash'] = [];
        $GLOBALS['plpc_test_uploads'] = [];
        $GLOBALS['plpc_test_attachments'] = [];
        $GLOBALS['plpc_test_posts'] = [];
        $jpx = str_repeat('J', 8500);
        $content = "BT /F1 12 Tf 72 720 Td (Before image) Tj ET\n"
            . "q 100 0 0 100 72 580 cm /Image17 Do Q\n"
            . "BT /F1 12 Tf 72 540 Td (After image) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> /XObject << /Image17 17 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "00017 0 obj\n"
            . "<< /Type /XObject /Subtype /Image /Width 100 /Height 100 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /Length " . strlen($jpx) . " >>\n"
            . "stream\n{$jpx}\nendstream\nendobj\n%%EOF\n";

        $result = plpc_convert_collection_file_to_page([
            'path' => 'scan.pdf',
            'bytes' => $pdf,
        ], null, 'JPEG 2000 import', 'important');
        $blocks = (string) ($GLOBALS['plpc_test_posts'][$result['postId']]['post_content'] ?? '');

        $t->same(1, $result['imageTagCount'] ?? null);
        $t->same(1, $result['imagesImported'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_uploads']));
        $t->same($jpx, $GLOBALS['plpc_test_uploads'][0]['bits'] ?? null);
        $t->same('image/jp2', $GLOBALS['plpc_test_attachments'][1]['post_mime_type'] ?? null);
        $t->contains('pandoc-pdf-image-placeholder', $blocks);
        $t->contains('href="https://playground.test/uploads/attachment-1.png"', $blocks);
        $t->true(!str_contains($blocks, '<img'));
        $t->true(in_array('extract-media-pdf-image-placeholder:00017:jpeg2000-raster-unavailable', $result['diagnostics'] ?? [], true));
        $t->true(in_array('image-imported:media/pdf/image-00017.jp2=>1', $result['diagnostics'] ?? [], true));
    },
    'playground importer normalizes pdf retry modes' => static function (TestRunner $t): void {
        $t->same('layout', plpc_normalize_pdf_mode(''));
        $t->same('layout', plpc_normalize_pdf_mode('layout-aware'));
        $t->same('layout', plpc_normalize_pdf_mode('geometry'));
        $t->same('text', plpc_normalize_pdf_mode('text'));
        $t->same('text', plpc_normalize_pdf_mode('text_only'));
        $t->same('text', plpc_normalize_pdf_mode('without layout'));
        $t->same('single', plpc_normalize_pdf_output_mode(''));
        $t->same('single', plpc_normalize_pdf_output_mode('single'));
        $t->same('pages', plpc_normalize_pdf_output_mode('one per page'));
        $t->same('pages', plpc_normalize_pdf_output_mode('page_tree'));
    },
    'playground importer reports conversion quality from diagnostics' => static function (TestRunner $t): void {
        $quality = plpc_import_quality_report('pdf', [
            'document-truncated:pdf-text-limit',
            'image-not-resolved:media/missing.png',
            'pdf-layout-uncertain:geometry-tables',
        ], 2, 1);

        $t->same('truncated', $quality['status']);
        $t->same(['best_effort', 'layout_uncertain', 'media_missing', 'truncated', 'partial'], $quality['flags']);
        $t->contains('Only part of the document text was imported because the browser importer reached its safety limit.', implode("\n", $quality['warnings']));
    },
    'playground importer keeps diagnostics in result metadata instead of prepending body notes' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $result = plpc_convert_collection_file_to_page([
            'path' => 'notes.md',
            'bytes' => "# Imported body\n\n![Missing](missing.png)\n",
            'format' => 'markdown',
        ], null, 'Imported body');
        $blocks = (string) ($GLOBALS['plpc_test_posts'][$result['postId']]['post_content'] ?? '');
        $stored = $GLOBALS['plpc_test_posts'][$result['postId']]['meta_input']['_plpc_import_result'] ?? [];

        $t->contains('Imported body', $blocks);
        $t->true(!str_contains($blocks, 'Conversion notes'), 'Conversion diagnostics must not alter imported body content.');
        $t->true(!str_contains($blocks, 'Import quality:'), 'Quality summaries belong in UI and metadata, not the page body.');
        $t->same($result['diagnostics'], $stored['diagnostics'] ?? null);
        $t->same($result['quality'], $stored['quality'] ?? null);
    },
    'playground importer replaces malformed utf8 without discarding neighboring document text' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $damaged = '<!-- wp:paragraph --><p>Before ' . "\xC3\x28" . ' after</p><!-- /wp:paragraph -->';
        $clean = plpc_import_sanitize_post_content($damaged);
        $encodedMetadata = plpc_json_encode_durable([
            'diagnostic' => 'Source label ' . "\xFF" . ' retained',
        ], JSON_UNESCAPED_SLASHES);
        $postId = plpc_insert_verified_page([
            'post_type' => 'page',
            'post_title' => 'Malformed UTF-8 boundary',
            'post_content' => $damaged,
        ]);
        $stored = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');

        $t->same(1, preg_match('//u', $clean));
        $t->contains('Before ', $clean);
        $t->contains(' after', $clean);
        $t->contains("\u{FFFD}(", $clean);
        $t->same(1, preg_match('//u', $encodedMetadata));
        $t->same($clean, $stored);
        $t->same('draft', $GLOBALS['plpc_test_posts'][$postId]['post_status'] ?? null);
    },
    'playground importer maps pdf metadata to quality diagnostics' => static function (TestRunner $t): void {
        $document = new PortLibs\Pandoc\AstNode('document', [
            'meta' => [
                'pdfTextLimited' => true,
                'pdfFastTextOnly' => true,
                'pdfTextLines' => 0,
                'pdfEstimatedPages' => 1,
                'pdfTableReconstruction' => 'geometry',
            ],
        ]);

        $diagnostics = plpc_document_diagnostics($document, 'pdf');
        $quality = plpc_import_quality_report('pdf', $diagnostics);

        $t->same([
            'document-truncated:pdf-text-limit',
            'pdf-fast-text-only',
            'pdf-scanned-or-image-only',
            'pdf-layout-uncertain:geometry-tables',
        ], $diagnostics);
        $t->same('truncated', $quality['status']);
        $t->same(['best_effort', 'layout_uncertain', 'truncated', 'partial', 'ocr_needed'], $quality['flags']);
        $t->contains('Scanned pages may need OCR before import.', implode("\n", $quality['warnings']));
    },
    'playground importer reports scanned pdfs as needing ocr' => static function (TestRunner $t): void {
        $quality = plpc_import_quality_report('pdf', ['pdf-scanned-or-image-only']);

        $t->same('ocr_needed', $quality['status']);
        $t->same(['best_effort', 'layout_uncertain', 'ocr_needed', 'partial'], $quality['flags']);
    },
    'playground importer accepts and converts legacy doc files' => static function (TestRunner $t): void {
        $fixture = dirname(__DIR__, 3) . '/pandoc-showcase/samples/doc-poi-47304-47304.doc';
        $bytes = (string) file_get_contents($fixture);
        $collection = [
            'label' => 'Legacy bundle',
            'files' => [
                ['path' => 'old.doc', 'bytes' => $bytes],
                ['path' => 'modern.docx', 'bytes' => "PK\x03\x04"],
            ],
        ];

        $documents = plpc_convertible_collection_files($collection);

        $t->same(['old.doc', 'modern.docx'], array_map(static fn (array $file): string => $file['path'], $documents));

        $GLOBALS['plpc_test_posts'] = [];
        $result = plpc_convert_collection_file_to_page([
            'path' => 'old.doc',
            'bytes' => $bytes,
        ], null, 'Legacy DOC import');
        $blocks = (string) ($GLOBALS['plpc_test_posts'][$result['postId']]['post_content'] ?? '');

        $t->same('doc', $result['format']);
        $t->same('complete', $result['quality']['status']);
        $t->contains('Just  a “test” ', $blocks);
        $t->true(!str_contains($blocks, '&#xd;'));
    },
    'playground importer discovers rendered image sources via dom fragments' => static function (TestRunner $t): void {
        $blocks = <<<'HTML'
<!-- wp:image -->
<figure class="wp-block-image"><img alt="One" src="media/photo &amp; one.png"></figure>
<!-- /wp:image -->
<!-- wp:html -->
<picture><source srcset="ignored.webp"><img src='media/second.png' alt='Two'></picture>
<!-- /wp:html -->
HTML;

        $t->same(['media/photo & one.png', 'media/second.png'], plpc_rendered_image_sources($blocks));
    },
    'playground importer marks imported wp image blocks with attachment metadata' => static function (TestRunner $t): void {
        $blocks = [
            [
                'blockName' => 'core/image',
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => '<figure class="wp-block-image"><img src="media/photo &amp; one.png" alt="First"/></figure>',
                'innerContent' => ['<figure class="wp-block-image"><img src="media/photo &amp; one.png" alt="First"/></figure>'],
            ],
            [
                'blockName' => 'core/image',
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => '<figure class="wp-block-image"><img src="media/other.png" alt="Other"/></figure>',
                'innerContent' => ['<figure class="wp-block-image"><img src="media/other.png" alt="Other"/></figure>'],
            ],
            [
                'blockName' => 'core/html',
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => '<div><img src="media/photo &amp; one.png" alt="Raw"></div>',
                'innerContent' => ['<div><img src="media/photo &amp; one.png" alt="Raw"></div>'],
            ],
        ];
        $changed = false;

        plpc_replace_image_source_in_blocks(
            $blocks,
            'media/photo & one.png',
            'https://playground.test/uploads/photo.png?x=1&y=2',
            42,
            $changed
        );

        $t->true($changed, 'Matching image sources should be rewritten through parsed block data.');
        $t->same(['id' => 42], $blocks[0]['attrs']);
        $t->contains('<img src="https://playground.test/uploads/photo.png?x=1&amp;y=2" alt="First" class="wp-image-42">', $blocks[0]['innerContent'][0]);
        $t->same([], $blocks[1]['attrs']);
        $t->contains('<img src="media/other.png" alt="Other"/>', $blocks[1]['innerContent'][0]);
        $t->contains('<div><img src="https://playground.test/uploads/photo.png?x=1&amp;y=2" alt="Raw"></div>', $blocks[2]['innerContent'][0]);
        $t->true(!str_contains($blocks[2]['innerContent'][0], 'wp-image-42'), 'Raw HTML images should not be marked as native wp:image attachments.');
    },
    'playground importer preserves existing image block attrs and classes when marking attachments' => static function (TestRunner $t): void {
        $blocks = [[
            'blockName' => 'core/image',
            'attrs' => ['sizeSlug' => 'large', 'id' => 9],
            'innerBlocks' => [],
            'innerHTML' => '<figure class="wp-block-image"><img src="media/logo.png" alt="Logo" class="alignnone"/></figure>',
            'innerContent' => ['<figure class="wp-block-image"><img src="media/logo.png" alt="Logo" class="alignnone"/></figure>'],
        ]];
        $changed = false;

        plpc_replace_image_source_in_blocks($blocks, 'media/logo.png', 'https://playground.test/uploads/logo.png', 77, $changed);

        $t->true($changed, 'Matching image block should be marked as changed.');
        $t->same(['id' => 77, 'sizeSlug' => 'large'], $blocks[0]['attrs']);
        $t->contains('class="alignnone wp-image-77"', $blocks[0]['innerContent'][0]);
        $t->true(!str_contains($blocks[0]['innerContent'][0], 'wp-image-77 wp-image-77'), 'Attachment class should not be duplicated.');
    },
    'playground importer dedupes media uploads by content hash' => static function (TestRunner $t): void {
        $GLOBALS['plpc_imported_media_by_hash'] = [];
        $GLOBALS['plpc_test_uploads'] = [];
        $GLOBALS['plpc_test_attachments'] = [];

        $first = plpc_insert_media_attachment('PNGDATA', 'first.png', 'image/png');
        $second = plpc_insert_media_attachment('PNGDATA', 'second.png', 'image/png');

        $t->same($first, $second);
        $t->same(1, count($GLOBALS['plpc_test_uploads']));
        $t->same(1, count($GLOBALS['plpc_test_attachments']));
    },
    'playground importer keeps actionable diagnostic messages available to the UI' => static function (TestRunner $t): void {
        $messages = plpc_conversion_warning_messages('markdown', [
            'extract-media-image-mode:important',
            'image-imported:media/photo.png=>42',
            'extract-media-package-loaded:media-photo.png',
            'image-not-resolved:media/missing.png',
            'extract-media-data-uri-invalid',
        ]);

        $t->contains('An image reference could not be found in the uploaded file or folder: media/missing.png', implode("\n", $messages));
        $t->contains('One embedded data URI image was invalid and was not imported.', implode("\n", $messages));
        $t->true(!str_contains(implode("\n", $messages), 'image-imported'), 'Routine media events should stay out of user-facing diagnostics.');
    },
    'playground importer warns that pdf imports are best effort' => static function (TestRunner $t): void {
        $messages = plpc_conversion_warning_messages('pdf', ['extract-media-image-mode:important']);

        $t->same([
            'PDF layout was reconstructed from page geometry. Reading order, columns, tables, and image placement may need review.',
        ], $messages);
    },
    'playground collection index does not prepend conversion or quality notes' => static function (TestRunner $t): void {
        $blocks = plpc_collection_index_blocks('Batch', [
            [
                'postId' => 1,
                'pageUrl' => 'https://playground.test/page',
                'editUrl' => '',
                'format' => 'markdown',
                'title' => 'Converted',
                'path' => 'docs/page.md',
                'imageTagCount' => 0,
                'imagesImported' => 0,
                'diagnostics' => [],
            ],
        ], ['broken.docx:document-failed:Corrupt package']);

        $t->true(!str_contains($blocks, 'Corrupt package'));
        $t->true(!str_contains($blocks, 'Conversion notes'));
        $t->true(!str_contains($blocks, 'Import quality:'));
        $t->contains('<a href="https://playground.test/page">Converted</a>', $blocks);
    },
    'playground importer expands zip files into safe collection entries' => static function (TestRunner $t): void {
        $zip = PortLibs\Pandoc\ZipPackage::build([
            ['name' => 'bundle/intro.md', 'data' => "# Intro\n\n![Logo](images/logo.png)\n"],
            ['name' => 'bundle/images/logo.png', 'data' => 'PNGDATA'],
            ['name' => '__MACOSX/._intro.md', 'data' => 'ignored'],
            ['name' => 'bundle/.DS_Store', 'data' => 'ignored'],
        ]);

        $collection = plpc_collection_from_zip($zip, 'bundle.zip', 'Bundle Import');
        $documents = plpc_convertible_collection_files($collection);

        $t->same('Bundle Import', $collection['label']);
        $t->same(['bundle/images/logo.png', 'bundle/intro.md'], array_map(static fn (array $file): string => $file['path'], $collection['files']));
        $t->same(['bundle/intro.md'], array_map(static fn (array $file): string => $file['path'], $documents));
        $t->same('markdown', $documents[0]['format'] ?? null);
    },
    'playground importer resolves sibling images from folder collections' => static function (TestRunner $t): void {
        $collection = [
            'label' => 'Folder Import',
            'files' => [
                ['path' => 'docs/intro.md', 'bytes' => "![Logo](images/logo.png)\n"],
                ['path' => 'docs/images/logo.png', 'bytes' => 'PNGDATA'],
            ],
        ];

        $resolved = plpc_resolve_image_source('images/logo.png', '', null, $collection, 'docs/intro.md');
        $remote = plpc_resolve_image_source('https://example.com/images/logo.png', '', null, $collection, 'docs/intro.md');

        $t->same('PNGDATA', $resolved['bytes'] ?? null);
        $t->same('logo.png', $resolved['filename'] ?? null);
        $t->same('image/png', $resolved['mimeType'] ?? null);
        $t->same(null, $remote);
    },
    'playground importer accepts browser directory payloads as collections' => static function (TestRunner $t): void {
        $payload = [
            'filename' => 'Local Folder',
            'files' => [
                ['path' => 'docs/page.md', 'bytes' => base64_encode("# Page\n")],
                ['path' => 'docs/images/photo.webp', 'bytes' => base64_encode('WEBP')],
                ['path' => '../ignored.md', 'bytes' => base64_encode('ignored')],
                ['path' => 'docs/.DS_Store', 'bytes' => base64_encode('ignored')],
            ],
        ];

        $collection = plpc_collection_from_payload($payload, 'Fallback');
        $documents = plpc_convertible_collection_files($collection);

        $t->same('Local Folder', $collection['label']);
        $t->same(['docs/images/photo.webp', 'docs/page.md', 'ignored.md'], array_map(static fn (array $file): string => $file['path'], $collection['files']));
        $t->same(['docs/page.md', 'ignored.md'], array_map(static fn (array $file): string => $file['path'], $documents));
    },
    'playground importer creates multiple pages and an index for collections' => static function (TestRunner $t): void {
        $GLOBALS['plpc_test_posts'] = [];
        $collection = [
            'label' => 'Import Batch',
            'files' => [
                ['path' => 'docs/alpha.md', 'bytes' => "# Alpha\n\nFirst page."],
                ['path' => 'docs/beta.md', 'bytes' => "# Beta\n\nSecond page."],
            ],
        ];

        $response = plpc_collection_response($collection, 'Import Batch');
        $data = $response->get_data();

        $t->same(true, $data['ok'] ?? null);
        $t->same(true, $data['batch'] ?? null);
        $t->same(2, $data['postCount'] ?? null);
        $t->same(3, $data['postId'] ?? null, 'index page is opened after the two imported pages are created');
        $t->same(['Alpha', 'Beta'], array_map(static fn (array $post): string => $post['title'], $data['posts']));
        $t->contains('docs/alpha.md', $GLOBALS['plpc_test_posts'][3]['post_content'] ?? '');
        $t->contains('docs/beta.md', $GLOBALS['plpc_test_posts'][3]['post_content'] ?? '');
    },
];
