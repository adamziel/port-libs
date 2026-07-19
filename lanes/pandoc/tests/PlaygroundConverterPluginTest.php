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

if (!function_exists('do_action')) {
    function do_action(string $hookName, mixed ...$args): void
    {
        plpc_test_do_action($hookName, ...$args);
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
        if (($GLOBALS['plpc_test_update_option_failure'] ?? null) === $option) {
            return false;
        }
        if (str_starts_with($option, 'plpc_import_job_')
            && $option !== 'plpc_import_job_index'
            && is_array($value)
            && function_exists('plpc_import_job_state_digest')
        ) {
            unset($value['stateDigest']);
            $value['stateDigest'] = plpc_import_job_state_digest($value);
        }
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

function plpc_test_private_import_job_dir(): string
{
    return sys_get_temp_dir() . '/plpc-import-jobs-private-test';
}

if (!function_exists('get_temp_dir')) {
    function get_temp_dir(): string
    {
        return plpc_test_private_import_job_dir() . '/';
    }
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
        if (isset($GLOBALS['plpc_test_wp_update_failure_injector'])
            && is_callable($GLOBALS['plpc_test_wp_update_failure_injector'])
            && (bool) ($GLOBALS['plpc_test_wp_update_failure_injector'])(
                $id,
                $post,
                $GLOBALS['plpc_test_posts'][$id]
            )
        ) {
            return 0;
        }
        $failures = max(0, (int) ($GLOBALS['plpc_test_wp_update_failures'] ?? 0));
        if ($failures > 0) {
            $GLOBALS['plpc_test_wp_update_failures'] = $failures - 1;

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

if (!function_exists('get_post_status')) {
    function get_post_status(int $postId): string|false
    {
        return isset($GLOBALS['plpc_test_posts'][$postId])
            ? (string) ($GLOBALS['plpc_test_posts'][$postId]['post_status'] ?? 'draft')
            : false;
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
        // The production posts table has one global ID namespace. These
        // lightweight fixtures keep pages and attachments separately, so an
        // attachment-only key must disambiguate an artificial numeric clash.
        $attachmentKey = in_array($key, [
            '_plpc_import_metadata_complete',
            '_plpc_import_source_file',
            '_plpc_content_key',
            '_plpc_content_sha1',
        ], true);
        $post = $attachmentKey
            ? ($GLOBALS['plpc_test_attachments'][$postId] ?? $GLOBALS['plpc_test_posts'][$postId] ?? [])
            : ($GLOBALS['plpc_test_posts'][$postId] ?? $GLOBALS['plpc_test_attachments'][$postId] ?? []);
        $meta = is_array($post['meta_input'] ?? null) ? $post['meta_input'] : [];
        if ($key === '') {
            return $meta;
        }
        $value = $meta[$key] ?? '';

        return $single ? $value : [$value];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, mixed $value): bool
    {
        $collection = isset($GLOBALS['plpc_test_attachments'][$postId])
            ? 'plpc_test_attachments'
            : 'plpc_test_posts';
        if (!isset($GLOBALS[$collection][$postId])) {
            return false;
        }
        $GLOBALS[$collection][$postId]['meta_input'] ??= [];
        if (($GLOBALS[$collection][$postId]['meta_input'][$key] ?? null) === $value) {
            return false;
        }
        $GLOBALS[$collection][$postId]['meta_input'][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta(int $postId, string $key): bool
    {
        $collection = isset($GLOBALS['plpc_test_attachments'][$postId])
            ? 'plpc_test_attachments'
            : 'plpc_test_posts';
        if (!isset($GLOBALS[$collection][$postId]['meta_input'][$key])) {
            return false;
        }
        unset($GLOBALS[$collection][$postId]['meta_input'][$key]);

        return true;
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
        $GLOBALS['plpc_test_attachment_metadata_generate_calls'][] = $attachmentId;
        if (($GLOBALS['plpc_test_attachment_metadata_fail_id'] ?? null) === $attachmentId) {
            return [];
        }
        if (!file_is_displayable_image($file)) {
            return [];
        }
        $metadata = [
            'file' => basename($file),
            'id' => $attachmentId,
            'sizes' => [],
        ];
        // Match WordPress core: base metadata and every generated subsize are
        // persisted before the function returns.
        $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] = $metadata;
        foreach ($GLOBALS['plpc_test_required_image_subsizes'] ?? ['thumbnail', 'medium'] as $size) {
            $metadata['sizes'][(string) $size] = ['file' => (string) $size . '-' . basename($file)];
            $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] = $metadata;
        }

        return $metadata;
    }
}

if (!function_exists('wp_update_attachment_metadata')) {
    function wp_update_attachment_metadata(int $attachmentId, array $metadata): bool
    {
        $GLOBALS['plpc_test_attachment_metadata_update_calls'][] = $attachmentId;
        if (($GLOBALS['plpc_test_attachment_metadata'][$attachmentId] ?? null) === $metadata) {
            $GLOBALS['plpc_test_attachment_metadata_unchanged_calls'][] = $attachmentId;
            return false;
        }
        $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] = $metadata;

        return true;
    }
}

if (!function_exists('file_is_displayable_image')) {
    function file_is_displayable_image(string $file): bool
    {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'], true);
    }
}

if (!function_exists('wp_get_missing_image_subsizes')) {
    function wp_get_missing_image_subsizes(int $attachmentId): array
    {
        $file = (string) ($GLOBALS['plpc_test_attachments'][$attachmentId]['file'] ?? '');
        if (!file_is_displayable_image($file)) {
            return [];
        }
        $metadata = $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] ?? [];
        $sizes = is_array($metadata['sizes'] ?? null) ? $metadata['sizes'] : [];
        $missing = [];
        foreach ($GLOBALS['plpc_test_required_image_subsizes'] ?? ['thumbnail', 'medium'] as $size) {
            if (!isset($sizes[(string) $size])) {
                $missing[(string) $size] = ['width' => 100, 'height' => 100, 'crop' => false];
            }
        }

        return $missing;
    }
}

if (!function_exists('wp_update_image_subsizes')) {
    function wp_update_image_subsizes(int $attachmentId): array
    {
        $GLOBALS['plpc_test_attachment_metadata_resume_calls'][] = $attachmentId;
        $file = (string) ($GLOBALS['plpc_test_attachments'][$attachmentId]['file'] ?? '');
        $metadata = $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] ?? [];
        if (!is_array($metadata) || $metadata === [] || !file_is_displayable_image($file)) {
            return [];
        }
        $metadata['sizes'] = is_array($metadata['sizes'] ?? null) ? $metadata['sizes'] : [];
        foreach (array_keys(wp_get_missing_image_subsizes($attachmentId)) as $size) {
            $metadata['sizes'][$size] = ['file' => $size . '-' . basename($file)];
            $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] = $metadata;
        }

        return $metadata;
    }
}

if (!function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata(int $attachmentId): array|false
    {
        return $GLOBALS['plpc_test_attachment_metadata'][$attachmentId] ?? false;
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file(int $attachmentId, bool $unfiltered = false): string|false
    {
        $file = $GLOBALS['plpc_test_attachments'][$attachmentId]['file'] ?? '';

        return is_string($file) && $file !== '' ? $file : false;
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
    $GLOBALS['plpc_test_attachment_metadata_generate_calls'] = [];
    $GLOBALS['plpc_test_attachment_metadata_update_calls'] = [];
    $GLOBALS['plpc_test_attachment_metadata_unchanged_calls'] = [];
    $GLOBALS['plpc_test_attachment_metadata_resume_calls'] = [];
    $GLOBALS['plpc_imported_media_by_hash'] = [];
    $GLOBALS['plpc_test_uuid_sequence'] = 0;
    unset(
        $GLOBALS['plpc_test_wp_insert_content_filter'],
        $GLOBALS['plpc_test_update_option_failure'],
        $GLOBALS['plpc_test_wp_update_failures'],
        $GLOBALS['plpc_test_wp_update_failure_injector'],
        $GLOBALS['plpc_import_request_time_limit_fallback'],
        $GLOBALS['plpc_test_attachment_metadata_fail_id'],
        $GLOBALS['plpc_test_required_image_subsizes'],
        $GLOBALS['plpc_import_media_metadata_capture']
    );

    foreach ([plpc_test_import_job_upload_dir(), plpc_test_private_import_job_dir()] as $directory) {
        if (!is_dir($directory)) {
            continue;
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

function plpc_test_whole_page_raster_jpeg(): string
{
    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////'
        . '2wBDAf//////////////////////////////////////////////////////////////////////////////////////'
        . 'wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBAB'
        . 'AAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/a'
        . 'AAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIA'
        . 'AwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAA'
        . 'AAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
        true
    );
    if (!is_string($jpeg)) {
        throw new RuntimeException('Unable to build the page-raster PDF fixture.');
    }

    return $jpeg;
}

function plpc_test_whole_page_raster_pdf(): string
{
    $jpeg = plpc_test_whole_page_raster_jpeg();
    $content = "q 1 0 0 1 0 0 cm /Scan Do Q\n";
    $image = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1'
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode'
        . ' /Length ' . strlen($jpeg) . ">>\nstream\n" . $jpeg . "\nendstream";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 1 1]"
        . " /Resources << /XObject << /Scan 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . ">>\nstream\n{$content}endstream\nendobj\n"
        . "5 0 obj\n{$image}\nendobj\n%%EOF\n";
}

function plpc_test_whole_page_raster_boundary_pdf(): string
{
    $jpeg = plpc_test_whole_page_raster_jpeg();
    $pageOne = 'BT /F1 12 Tf 72 720 Td (First physical boundary page) Tj ET';
    $pageTwo = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
        . "0 0 612 792 re f\n"
        . 'BT /F1 12 Tf 3 Tr 72 720 Td (HIDDEN-BOUNDARY-OCR) Tj ET';
    $pageThree = 'BT /F1 12 Tf 72 720 Td (Third physical boundary page) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 5 0 R 7 0 R] /Count 3 /MediaBox [0 0 612 792] '
            . '/Resources << /Font << /F1 9 0 R >> /XObject << /Scan 10 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>',
        4 => '<< /Length ' . strlen($pageOne) . ">>\nstream\n{$pageOne}\nendstream",
        5 => '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>',
        6 => '<< /Length ' . strlen($pageTwo) . ">>\nstream\n{$pageTwo}\nendstream",
        7 => '<< /Type /Page /Parent 2 0 R /Contents 8 0 R >>',
        8 => '<< /Length ' . strlen($pageThree) . ">>\nstream\n{$pageThree}\nendstream",
        9 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        10 => '<< /Type /XObject /Subtype /Image /Width 100 /Height 100'
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode'
            . ' /Length ' . strlen($jpeg) . ">>\nstream\n{$jpeg}\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n{$body}\nendobj\n";
    }

    return $pdf . "%%EOF\n";
}

function plpc_test_rgb_png(int $width, int $height): string
{
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    $scanline = "\x00" . str_repeat("\x00", $width * 3);

    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        . $chunk('IDAT', gzcompress(str_repeat($scanline, $height)))
        . $chunk('IEND', '');
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

function plpc_test_full_page_infographic_form_xobject_pdf(): string
{
    $pageContent = "q\n/Infographic Do\nQ\nBT /F1 14 Tf 72 700 Td (Infographic caption) Tj ET\n";
    $formContent = "0.08 0.18 0.38 rg\n0 0 600 800 re\nf\n"
        . "1 0.7 0.1 RG\n8 w\n60 120 m\n540 680 l\nS\n";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 800] /Resources << /Font << /F1 6 0 R >> /XObject << /Infographic 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 600 800] /Resources << >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}endstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF\n";
}

function plpc_test_two_page_form_xobject_pdf(): string
{
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [4 0 R 6 0 R] /Count 2 >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    foreach ([1, 2] as $page) {
        $pageObject = $page === 1 ? 4 : 6;
        $contentObject = $pageObject + 1;
        $formObject = $page === 1 ? 8 : 9;
        $resource = 'Chart' . $page;
        $content = "BT /F1 12 Tf 20 180 Td (VISUAL PAGE {$page}) Tj ET\n"
            . "q 1 0 0 1 20 40 cm /{$resource} Do Q\n";
        $form = "0.1 0.4 0.8 rg 0 0 80 40 re f\n";
        $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200]"
            . " /Resources << /Font << /F1 3 0 R >> /XObject << /{$resource} {$formObject} 0 R >> >>"
            . " /Contents {$contentObject} 0 R >>";
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}endstream";
        $objects[$formObject] = '<< /Type /XObject /Subtype /Form /BBox [0 0 80 40] /Length '
            . strlen($form) . ">>\nstream\n{$form}endstream";
    }
    ksort($objects, SORT_NUMERIC);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $object => $body) {
        $offsets[$object] = strlen($pdf);
        $pdf .= "{$object} 0 obj\n{$body}\nendobj\n";
    }
    $xref = strlen($pdf);
    $size = max(array_keys($objects)) + 1;
    $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
    for ($object = 1; $object < $size; $object++) {
        $pdf .= isset($offsets[$object])
            ? sprintf("%010d 00000 n \n", $offsets[$object])
            : "0000000000 00000 f \n";
    }

    return $pdf . "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
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

/** Two physical pages with independently page-scoped tagged semantics. */
function plpc_test_tagged_page_tree_pdf(): string
{
    $pageObjects = [];
    $rootKids = [];
    $objects = [
        1 => '',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        4 => '',
    ];
    for ($page = 1; $page <= 2; $page++) {
        $base = 100 + (($page - 1) * 20);
        $pageObject = $base;
        $contentObject = $base + 1;
        $headingObject = $base + 2;
        $listObject = $base + 3;
        $tableObject = $base + 4;
        $rowOneObject = $base + 5;
        $rowTwoObject = $base + 6;
        $cells = [$base + 7, $base + 8, $base + 9, $base + 10];
        $heading = 'Tagged page ' . $page . ' heading';
        $item = 'Tagged page ' . $page . ' item';
        $entry = 'Tagged page ' . $page . ' entry';
        $content = "BT /F1 12 Tf 14 TL 72 720 Td "
            . "/Span << /MCID 0 >> BDC ({$heading}) Tj EMC T* "
            . "/Span << /MCID 1 >> BDC ({$item}) Tj EMC T* "
            . "/Span << /MCID 2 >> BDC (Name) Tj EMC T* "
            . "/Span << /MCID 3 >> BDC (Count) Tj EMC T* "
            . "/Span << /MCID 4 >> BDC ({$entry}) Tj EMC T* "
            . "/Span << /MCID 5 >> BDC ({$page}) Tj EMC ET";

        $pageObjects[] = $pageObject . ' 0 R';
        array_push($rootKids, $headingObject . ' 0 R', $listObject . ' 0 R', $tableObject . ' 0 R');
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /StructParents ' . ($page - 1)
            . ' /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '
            . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
        $objects[$headingObject] = "<< /Type /StructElem /S /H1 /ActualText ({$heading}) /K << /Type /MCR /Pg {$pageObject} 0 R /MCID 0 >> >>";
        $objects[$listObject] = "<< /Type /StructElem /S /LI /A << /O /List /ListNumbering /Disc >> /ActualText ({$item}) /K << /Type /MCR /Pg {$pageObject} 0 R /MCID 1 >> >>";
        $objects[$tableObject] = "<< /Type /StructElem /S /Table /K [{$rowOneObject} 0 R {$rowTwoObject} 0 R] >>";
        $objects[$rowOneObject] = "<< /Type /StructElem /S /TR /K [{$cells[0]} 0 R {$cells[1]} 0 R] >>";
        $objects[$rowTwoObject] = "<< /Type /StructElem /S /TR /K [{$cells[2]} 0 R {$cells[3]} 0 R] >>";
        foreach (['Name', 'Count', $entry, (string) $page] as $index => $text) {
            $role = $index < 2 ? 'TH' : 'TD';
            $mcid = $index + 2;
            $objects[$cells[$index]] = "<< /Type /StructElem /S /{$role} /ActualText ({$text}) /K << /Type /MCR /Pg {$pageObject} 0 R /MCID {$mcid} >> >>";
        }
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 4 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageObjects) . '] /Count 2 >>';
    $objects[4] = '<< /Type /StructTreeRoot /K [' . implode(' ', $rootKids) . '] >>';
    ksort($objects, SORT_NUMERIC);
    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "%%EOF\n";
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

/** @return array{job:array<string,mixed>,rootId:int,childIds:list<int>} */
function plpc_test_pdf_page_tree_publication_job(): array
{
    $jobId = 'publicationtree0000000000000001';
    $rootId = plpc_insert_verified_page([
        'post_type' => 'page',
        'post_title' => 'PDF root',
        'post_content' => '<!-- wp:paragraph --><p>PDF page index</p><!-- /wp:paragraph -->',
        'meta_input' => [
            '_plpc_import_job_id' => $jobId,
            '_plpc_import_document_index' => 0,
            '_plpc_import_pdf_role' => 'root',
        ],
    ]);
    $children = [];
    $results = [];
    for ($page = 1; $page <= 2; $page++) {
        $postId = plpc_insert_verified_page([
            'post_type' => 'page',
            'post_title' => 'PDF page ' . $page,
            'post_content' => '<!-- wp:paragraph --><p>Physical page ' . $page . '</p><!-- /wp:paragraph -->',
            'post_parent' => $rootId,
            'menu_order' => $page,
            'meta_input' => [
                '_plpc_import_job_id' => $jobId,
                '_plpc_import_document_index' => 0,
                '_plpc_import_segment_index' => $page - 1,
                '_plpc_import_pdf_role' => 'page',
            ],
        ]);
        $children[] = $postId;
        $results[] = [
            'postId' => $postId,
            'kind' => 'pdf-page',
            'documentIndex' => 0,
            'pageNumber' => $page,
            'title' => 'PDF page ' . $page,
        ];
    }
    $rootResult = [
        'postId' => $rootId,
        'kind' => 'pdf-page-tree',
        'documentIndex' => 0,
        'postCount' => 3,
        'pageCount' => 2,
        'title' => 'PDF root',
        'children' => $results,
        'posts' => $results,
    ];
    $results[] = $rootResult;
    $job = [
        'version' => PLPC_IMPORT_JOB_VERSION,
        'id' => $jobId,
        'ownerId' => 1,
        'sourceKind' => 'single',
        'title' => 'PDF root',
        'status' => 'ready_to_convert',
        'stage' => 'ready_to_convert',
        'progress' => ['completed' => 1, 'total' => 2, 'label' => 'Ready'],
        'events' => [],
        'results' => $results,
        'documentResults' => [$rootResult],
        'documents' => [['path' => 'tree.pdf', 'completed' => true]],
        'nextDocument' => 1,
    ];
    plpc_import_job_begin_publication($job);

    return ['job' => $job, 'rootId' => $rootId, 'childIds' => $children];
}

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
        $t->same(true, plpc_pdf_page_like_form_is_content_rich([
            'visualSummary' => [
                'complete' => true,
                'textShowOperatorCount' => 1,
                'vectorPaintOperatorCount' => 3,
                'rasterXObjectCount' => 0,
                'nestedFormXObjectCount' => 0,
            ],
        ], 1), 'A unique content-rich full-page infographic must remain renderable.');
        $t->same(false, plpc_pdf_page_like_form_is_content_rich([
            'visualSummary' => [
                'complete' => true,
                'textShowOperatorCount' => 0,
                'vectorPaintOperatorCount' => 1,
            ],
        ], 1), 'A simple page-sized background fill remains a wrapper.');
        $t->same(false, plpc_pdf_page_like_form_is_content_rich([
            'visualSummary' => [
                'complete' => true,
                'textShowOperatorCount' => 2,
                'vectorPaintOperatorCount' => 4,
            ],
        ], 2), 'A repeated page-sized Form is furniture rather than one visual per page.');
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
    'playground converter pages more than forty eight legitimate PDF figure requests without dropping them' => static function (TestRunner $t): void {
        $placements = [];
        for ($index = 1; $index <= 49; $index++) {
            $placements[] = [
                'id' => 'pdf-form-p1-n' . $index . '-o' . (100 + $index),
                'page' => 1,
                'object' => 100 + $index,
                'paintOrder' => $index,
                'visible' => true,
                'placementEligible' => true,
                'bbox' => ['x1' => 20.0, 'y1' => 20.0 + $index, 'x2' => 120.0, 'y2' => 60.0 + $index],
            ];
        }
        $job = [
            'id' => 'job_figure_paging_fixture',
            'status' => 'awaiting_renderer',
            'stage' => 'awaiting_renderer',
            'imageMode' => 'all',
            'progress' => ['completed' => 1, 'total' => 50, 'label' => 'Rendering figures.'],
            'documents' => [[
                'format' => 'pdf',
                'path' => 'many-figures.pdf',
                'pdfInspectionForms' => $placements,
                'pdfInspectionPageGeometry' => [[
                    'page_number' => 1,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                ]],
            ]],
        ];

        $job['renderRequests'] = plpc_import_job_collect_form_render_requests($job);
        $t->same(49, count($job['renderRequests']));
        $t->same(0, $job['pdfFormRenderRequestsTruncated'] ?? null);
        $snapshot = plpc_import_job_response($job)->get_data();
        $t->same(PLPC_IMPORT_JOB_STATUS_MAX_RENDER_REQUESTS, count($snapshot['renderRequests'] ?? []));
        $t->same(25, $snapshot['truncated']['renderRequestsOmitted'] ?? null);
        $t->same(substr(hash('sha256', 'many-figures.pdf'), 0, 32), $snapshot['renderRequests'][0]['sourceKey'] ?? null);

        $commonPrefix = str_repeat('nested/', 74);
        $collisionSnapshot = plpc_import_job_response([
            'id' => 'job_long_path_source_keys',
            'status' => 'awaiting_renderer',
            'renderRequests' => [
                ['id' => 'form-' . str_repeat('a', 16), 'path' => $commonPrefix . 'one.pdf', 'page' => 1, 'bbox' => ['x1' => 1, 'y1' => 1, 'x2' => 2, 'y2' => 2]],
                ['id' => 'form-' . str_repeat('b', 16), 'path' => $commonPrefix . 'two.pdf', 'page' => 1, 'bbox' => ['x1' => 1, 'y1' => 1, 'x2' => 2, 'y2' => 2]],
            ],
        ])->get_data();
        $t->same(
            $collisionSnapshot['renderRequests'][0]['path'] ?? null,
            $collisionSnapshot['renderRequests'][1]['path'] ?? null,
            'This fixture must collide after the bounded display-path prefix.'
        );
        $t->true(
            ($collisionSnapshot['renderRequests'][0]['sourceKey'] ?? '') !== ($collisionSnapshot['renderRequests'][1]['sourceKey'] ?? ''),
            'Full-path source identities must remain distinct when display paths collide.'
        );
    },
    'playground converter accounts for omitted and unresolved PDF visual occurrences once per document' => static function (TestRunner $t): void {
        $byPath = [];
        plpc_import_job_record_pdf_visual_disposition($byPath, 'play.pdf', [
            'id' => 'page-wrapper-1',
            'page' => 1,
            'object' => 11,
            'paintOrder' => 1,
        ], 'intentional_omission');
        plpc_import_job_record_pdf_visual_disposition($byPath, 'play.pdf', [
            'id' => 'figure-after-bound-1',
            'page' => 200,
            'object' => 220,
            'paintOrder' => 9,
        ], 'unresolved');
        $base = plpc_import_aggregate_media_dispositions([[
            'totalOccurrences' => 1,
            'attachmentOccurrences' => 1,
            'placeholderOccurrences' => 0,
            'intentionalOmissionOccurrences' => 0,
            'unresolvedOccurrences' => 0,
            'ledgerSha256' => hash('sha256', 'ordinary-image'),
        ]]);
        $summary = plpc_import_add_pdf_visual_dispositions($base, [
            'pdfVisualDispositionsByPath' => $byPath,
        ], 'play.pdf');

        $t->same(3, $summary['totalOccurrences'] ?? null);
        $t->same(1, $summary['attachmentOccurrences'] ?? null);
        $t->same(1, $summary['intentionalOmissionOccurrences'] ?? null);
        $t->same(1, $summary['unresolvedOccurrences'] ?? null);
        $t->true(
            is_string($summary['ledgerSha256'] ?? null) && strlen($summary['ledgerSha256']) === 64,
            'A bounded digest must preserve the ordered source-occurrence ledger without bloating job state.'
        );
        $t->same(
            1,
            plpc_import_add_pdf_visual_dispositions($base, ['pdfVisualDispositionsByPath' => $byPath], 'other.pdf')['totalOccurrences'] ?? null,
            'A document may not inherit another PDF\'s visual dispositions.'
        );
    },
    'playground converter retains every inspected PDF visual with an explicit pre-materialization disposition' => static function (TestRunner $t): void {
        $base = [
            'page' => 1,
            'object' => 20,
            'visible' => true,
            'placementEligible' => true,
            'bbox' => ['x1' => 20.0, 'y1' => 20.0, 'x2' => 120.0, 'y2' => 80.0],
            'disposition' => 'pending',
        ];
        $placements = [
            array_replace($base, ['id' => 'pdf-form-p1-n1-o20', 'kind' => 'form-xobject', 'paintOrder' => 1]),
            array_replace($base, ['id' => 'pdf-inline-image-p1-n2', 'kind' => 'inline-image', 'object' => 0, 'paintOrder' => 2]),
            array_replace($base, ['id' => 'pdf-vector-p1-n3-6', 'kind' => 'page-vector-region', 'object' => 0, 'paintOrder' => 3]),
            array_replace($base, [
                'id' => 'pdf-image-p1-n7-o27',
                'kind' => 'image-xobject',
                'object' => 27,
                'paintOrder' => 7,
            ]),
            array_replace($base, [
                'id' => 'pdf-form-p1-n8-o28',
                'kind' => 'form-xobject',
                'object' => 28,
                'paintOrder' => 8,
                'visible' => false,
            ]),
            array_replace($base, [
                'id' => 'pdf-form-p1-n9-o29',
                'kind' => 'form-xobject',
                'object' => 29,
                'paintOrder' => 9,
                'placementEligible' => false,
            ]),
            array_replace($base, [
                'id' => 'pdf-form-p1-n10-o30',
                'kind' => 'form-xobject',
                'object' => 30,
                'paintOrder' => 10,
                'bbox' => ['x1' => 20.0, 'y1' => 20.0, 'x2' => 25.0, 'y2' => 25.0],
            ]),
            array_replace($base, [
                'id' => 'pdf-form-p1-n11-o31',
                'kind' => 'form-xobject',
                'object' => 31,
                'paintOrder' => 11,
                'bbox' => ['x1' => 0.0, 'y1' => 0.0, 'x2' => 10001.0, 'y2' => 10001.0],
            ]),
            [
                'id' => 'pdf-visual-inspection-p1-s1-o4',
                'kind' => 'inspection-issue',
                'page' => 1,
                'object' => 4,
                'paintOrder' => 0,
                'bbox' => null,
                'visible' => null,
                'placementEligible' => false,
                'disposition' => 'unresolved',
                'dispositionReason' => 'visual-content-stream-decode-failed',
            ],
        ];
        $job = [
            'imageMode' => 'all',
            'documents' => [[
                'format' => 'pdf',
                'path' => 'inventory.pdf',
                'pdfInspectionVisualOccurrences' => $placements,
                'pdfInspectionPageGeometry' => [[
                    'page_number' => 1,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                ]],
            ]],
        ];

        $requests = plpc_import_job_collect_form_render_requests($job);
        $inventory = $job['documents'][0]['pdfVisualOccurrences'] ?? [];
        $byId = array_column($inventory, null, 'id');

        $t->same(3, count($requests), 'Only the eligible Form, inline image, and vector region need browser crops.');
        $t->same(['form-xobject', 'inline-image', 'page-vector-region'], array_column($requests, 'visualKind'));
        $t->same(count($placements), count($inventory), 'No inspected occurrence may disappear during request classification.');
        $t->same(count($inventory), count(array_unique(array_column($inventory, 'id'))), 'Each occurrence keeps a stable unique source id.');
        $t->same(true, $job['documents'][0]['pdfVisualInventoryComplete'] ?? null);
        $t->same('pending', $byId['pdf-image-p1-n7-o27']['disposition'] ?? null);
        $t->same('intentional_omission', $byId['pdf-form-p1-n8-o28']['disposition'] ?? null);
        $t->same('visual-not-visible', $byId['pdf-form-p1-n8-o28']['reason'] ?? null);
        $t->same('unresolved', $byId['pdf-form-p1-n9-o29']['disposition'] ?? null);
        $t->same('small-decorative-visual', $byId['pdf-form-p1-n10-o30']['reason'] ?? null);
        $t->same('visual-bbox-exceeds-render-limit', $byId['pdf-form-p1-n11-o31']['reason'] ?? null);
        $t->same('unresolved', $byId['pdf-visual-inspection-p1-s1-o4']['disposition'] ?? null);
    },
    'playground media ledgers deduplicate stable PDF occurrence ids across arbitrary chunks' => static function (TestRunner $t): void {
        $block = static fn (string $id, string $source): string => '<!-- wp:image --><figure>'
            . '<img data-pandoc-pdf-visual-id="' . $id . '" data-pandoc-pdf-visual-kind="image-xobject" src="' . $source . '">'
            . '</figure><!-- /wp:image -->';
        $firstBlocks = $block('pdf-image-p1-n1-o20', 'media/first.png');
        $secondBlocks = $block('pdf-image-p2-n1-o21', 'media/second.png');
        $first = plpc_import_media_disposition_summary(
            $firstBlocks,
            $firstBlocks,
            ['image-imported:media/first.png=>1']
        );
        $second = plpc_import_media_disposition_summary(
            $secondBlocks,
            $secondBlocks,
            ['image-imported:media/second.png=>2']
        );
        $all = plpc_import_media_disposition_summary(
            $firstBlocks . $secondBlocks,
            $firstBlocks . $secondBlocks,
            ['image-imported:media/first.png=>1', 'image-imported:media/second.png=>2']
        );
        $whole = plpc_import_aggregate_media_dispositions([$all]);
        $chunked = plpc_import_aggregate_media_dispositions([$first, $second, $first]);

        $t->same(2, $chunked['totalOccurrences'] ?? null, 'An overlapped chunk must not count the same stable occurrence twice.');
        $t->same(2, $chunked['attachmentOccurrences'] ?? null);
        $t->same(array_keys($whole['sourceDispositions'] ?? []), array_keys($chunked['sourceDispositions'] ?? []));
        $t->same($whole['ledgerSha256'] ?? null, $chunked['ledgerSha256'] ?? null, 'The canonical occurrence ledger must not depend on chunk boundaries.');
    },
    'playground final PDF media report reconciles every source occurrence exactly once' => static function (TestRunner $t): void {
        $blocks = '<!-- wp:image --><figure><img data-pandoc-pdf-visual-id="pdf-image-p1-n1-o20" src="media/chart.png"></figure><!-- /wp:image -->';
        $base = plpc_import_aggregate_media_dispositions([
            plpc_import_media_disposition_summary($blocks, $blocks, ['image-imported:media/chart.png=>1']),
            plpc_import_media_disposition_summary($blocks, $blocks, ['image-imported:media/chart.png=>1']),
        ]);
        $summary = plpc_import_add_pdf_visual_dispositions($base, [
            'documents' => [[
                'path' => 'audit.pdf',
                'pdfVisualInventoryComplete' => true,
                'pdfVisualOccurrences' => [
                    ['id' => 'pdf-image-p1-n1-o20', 'kind' => 'image-xobject', 'page' => 1, 'object' => 20, 'paintOrder' => 1, 'disposition' => 'pending', 'reason' => null],
                    ['id' => 'pdf-vector-p1-n2-2', 'kind' => 'page-vector-region', 'page' => 1, 'object' => 0, 'paintOrder' => 2, 'disposition' => 'intentional_omission', 'reason' => 'isolated-or-decorative-vector-paint'],
                    ['id' => 'pdf-form-p1-n3-o30', 'kind' => 'form-xobject', 'page' => 1, 'object' => 30, 'paintOrder' => 3, 'disposition' => 'unresolved', 'reason' => 'form-transform-invalid'],
                    ['id' => 'pdf-inline-image-p1-n4', 'kind' => 'inline-image', 'page' => 1, 'object' => 0, 'paintOrder' => 4, 'disposition' => 'pending', 'reason' => 'browser-render-requested'],
                ],
            ]],
        ], 'audit.pdf');
        $byId = array_column($summary['sourceOccurrences'] ?? [], null, 'id');

        $t->same(4, $summary['totalOccurrences'] ?? null);
        $t->same(1, $summary['attachmentOccurrences'] ?? null);
        $t->same(1, $summary['intentionalOmissionOccurrences'] ?? null);
        $t->same(2, $summary['unresolvedOccurrences'] ?? null);
        $t->same(4, $summary['sourceOccurrenceCount'] ?? null);
        $t->same(true, $summary['inventoryComplete'] ?? null);
        $t->same(false, $summary['complete'] ?? null);
        $t->same('attachment', $byId['pdf-image-p1-n1-o20']['disposition'] ?? null);
        $t->same('intentional_omission', $byId['pdf-vector-p1-n2-2']['disposition'] ?? null);
        $t->same('unresolved', $byId['pdf-form-p1-n3-o30']['disposition'] ?? null);
        $t->same('browser-render-occurrence-not-materialized', $byId['pdf-inline-image-p1-n4']['reason'] ?? null);
    },
    'playground converter fingerprints text and image content before publication' => static function (TestRunner $t): void {
        $blocks = '<!-- wp:paragraph --><p>Hello <strong>stored world</strong>.</p><!-- /wp:paragraph -->'
            . '<!-- wp:image --><figure><img src="https://playground.test/media/chart.png"/></figure><!-- /wp:image -->';
        $fingerprint = plpc_import_content_fingerprint($blocks);

        $t->true(($fingerprint['rawBytes'] ?? 0) > 0);
        $t->same('Hello stored world.', $fingerprint['visibleText'] ?? null);
        $t->same(1, $fingerprint['imageCount'] ?? null);
        $t->true(($fingerprint['meaningfulBlockCount'] ?? 0) >= 2);
        $t->true(($fingerprint['orderedStructureCount'] ?? 0) >= 4, 'The fingerprint must retain ordered block and HTML boundaries.');
    },
    'playground converter rejects structural publication loss even when visible text is identical' => static function (TestRunner $t): void {
        $expectedBlocks = '<!-- wp:paragraph --><p>Alpha</p><!-- /wp:paragraph -->'
            . '<!-- wp:paragraph --><p>Beta</p><!-- /wp:paragraph -->';
        $collapsedBlocks = '<!-- wp:paragraph --><p>Alpha Beta</p><!-- /wp:paragraph -->';
        $expected = plpc_import_content_fingerprint($expectedBlocks);
        $collapsed = plpc_import_content_fingerprint($collapsedBlocks);

        $t->same($expected['visibleTextSha256'], $collapsed['visibleTextSha256'], 'The fixture isolates structure rather than text loss.');
        $error = null;
        try {
            plpc_import_assert_content_fingerprint($expected, $collapsedBlocks);
        } catch (Throwable $throwable) {
            $error = $throwable;
        }
        $t->true($error instanceof PlpcImportFailure);
        $t->same('publication_structure_mismatch', $error instanceof PlpcImportFailure ? $error->failureCode : null);
        $t->same(true, $error instanceof PlpcImportFailure ? $error->recoverable : null);
    },
    'playground converter fingerprints exact cells links captions and attachment ids' => static function (TestRunner $t): void {
        $expectedBlocks = '<!-- wp:table --><figure><table><tbody><tr><td>A</td><td>B</td></tr></tbody></table></figure><!-- /wp:table -->'
            . '<!-- wp:paragraph --><p><a href="https://example.test/first">Linked text</a></p><!-- /wp:paragraph -->'
            . '<!-- wp:image {"sizeSlug":"large","id":41} --><figure><img src="https://example.test/chart.png" data-plpc-imported-media="41"/><figcaption>Chart caption</figcaption></figure><!-- /wp:image -->';
        $mutations = [
            'cell boundary' => str_replace('<td>A</td><td>B</td>', '<td>AB</td>', $expectedBlocks),
            'link target' => str_replace('/first', '/second', $expectedBlocks),
            'caption boundary' => str_replace('<figcaption>Chart caption</figcaption>', '<p>Chart caption</p>', $expectedBlocks),
            'attachment id' => str_replace(['"id":41', 'media="41"'], ['"id":42', 'media="42"'], $expectedBlocks),
        ];
        $expected = plpc_import_content_fingerprint($expectedBlocks);

        foreach ($mutations as $label => $storedBlocks) {
            $t->same(
                $expected['visibleTextSha256'],
                plpc_import_content_fingerprint($storedBlocks)['visibleTextSha256'] ?? null,
                $label . ' fixture must isolate structural identity from visible text.'
            );
            $error = null;
            try {
                plpc_import_assert_content_fingerprint($expected, $storedBlocks);
            } catch (Throwable $throwable) {
                $error = $throwable;
            }
            $t->true($error instanceof PlpcImportFailure, $label);
            $t->same(
                'publication_structure_mismatch',
                $error instanceof PlpcImportFailure ? $error->failureCode : null,
                $label
            );
        }
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
    'playground importer applies finite defaults only to unlimited or more permissive runtimes' => static function (TestRunner $t): void {
        $t->same('512M', plpc_import_memory_limit_policy('-1'));
        $t->same('512M', plpc_import_memory_limit_policy('1G'));
        $t->same(null, plpc_import_memory_limit_policy('512M'));
        $t->same(null, plpc_import_memory_limit_policy('128M'));
        $t->same(45, plpc_import_time_limit_policy('0'));
        $t->same(45, plpc_import_time_limit_policy('120'));
        $t->same(null, plpc_import_time_limit_policy('45'));
        $t->same(null, plpc_import_time_limit_policy('30'));

        $t->same(45.0, plpc_import_request_time_limit_from_observed(0, 45));
        $t->same(45.0, plpc_import_request_time_limit_from_observed(120, 45));
        $t->same(30.0, plpc_import_request_time_limit_from_observed(30, 45));
        $t->same(null, plpc_import_request_time_limit_from_observed(0, null));
    },
    'playground importer runtime constants and filters can explicitly override or opt out' => static function (TestRunner $t): void {
        $t->same('768M', plpc_import_memory_limit_policy('128M', '768M', true));
        $t->same('-1', plpc_import_memory_limit_policy('128M', '-1', true));
        $t->same(null, plpc_import_memory_limit_policy('-1', false, true));
        $t->same(null, plpc_import_memory_limit_policy('-1', null, true));
        $t->same('512M', plpc_import_memory_limit_policy('1G', 'invalid', true), 'An invalid override must not silently disable the safe default.');
        $t->same(90, plpc_import_time_limit_policy('30', 90, true));
        $t->same(0, plpc_import_time_limit_policy('30', 0, true));
        $t->same(null, plpc_import_time_limit_policy('120', false, true));
        $t->same(null, plpc_import_time_limit_policy('120', '', true));
        $t->same(45, plpc_import_time_limit_policy('120', 'invalid', true), 'An invalid override must not silently disable the safe default.');

        plpc_test_reset_import_job_state();
        $memoryFilterArgs = [];
        $timeFilterArgs = [];
        add_filter('plpc_import_memory_limit', static function (
            mixed $limit,
            mixed $hostLimit,
            mixed $hasConstant
        ) use (&$memoryFilterArgs): mixed {
            $memoryFilterArgs = [$limit, $hostLimit, $hasConstant];

            return false;
        }, 10, 3);
        add_filter('plpc_import_time_limit', static function (
            mixed $limit,
            mixed $hostLimit,
            mixed $hasConstant
        ) use (&$timeFilterArgs): mixed {
            $timeFilterArgs = [$limit, $hostLimit, $hasConstant];

            return false;
        }, 10, 3);
        $optedOut = plpc_import_runtime_limit_policy('-1', '0');
        $t->same(['512M', '-1', false], $memoryFilterArgs);
        $t->same([45, '0', false], $timeFilterArgs);
        $t->same(null, $optedOut['memoryLimit']);
        $t->same(null, $optedOut['timeLimit']);
        $t->same(null, $optedOut['effectiveTimeLimit']);

        plpc_test_reset_import_job_state();
        add_filter('plpc_import_memory_limit', static fn (mixed $limit): string => '768M');
        add_filter('plpc_import_time_limit', static fn (mixed $limit): int => 90);
        $overridden = plpc_import_runtime_limit_policy('128M', '30');
        $t->same('768M', $overridden['memoryLimit']);
        $t->same(90, $overridden['timeLimit']);
        $t->same(90.0, $overridden['effectiveTimeLimit']);
        plpc_test_reset_import_job_state();
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
        $t->true(plpc_import_request_reserve_seconds(30.0) >= 6.0, 'A request must yield before consuming 80% of its PHP execution window.');
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
        $cancelHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/cancel');
        $outputHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/output-mode');
        $renderHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/rendered-media');
        $sourceHandlers = plpc_test_rest_route_handlers('port-libs/v1/imports/(?P<jobId>[A-Za-z0-9_-]+)/render-source/(?P<requestId>form-[a-f0-9]+)');

        $t->same(1, count(array_filter($createHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'POST' && ($handler['callback'] ?? null) === 'plpc_create_import_job')));
        $t->same(1, count(array_filter($jobHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'GET' && ($handler['callback'] ?? null) === 'plpc_import_job_status')));
        $t->same(1, count(array_filter($advanceHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'POST' && ($handler['callback'] ?? null) === 'plpc_advance_import_job')));
        $t->same(1, count(array_filter($cancelHandlers, static fn (array $handler): bool => ($handler['methods'] ?? null) === 'POST' && ($handler['callback'] ?? null) === 'plpc_cancel_import_job')));
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
    'persisted import cancellation is durable idempotent and prevents future work' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'cancel-me.md',
            'bytes' => base64_encode("# Cancel me\n\nThis source must remain private."),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = get_option($option);
        $job['renderRequests'] = [[
            'id' => 'form-' . str_repeat('a', 24),
            'path' => 'cancel-me.pdf',
            'page' => 1,
            'bbox' => ['x1' => 0, 'y1' => 0, 'x2' => 10, 'y2' => 10],
        ]];
        plpc_import_job_save($job);

        $first = plpc_cancel_import_job(plpc_test_import_job_request([], $jobId));
        $firstSnapshot = $first->get_data();
        $stored = get_option($option);
        $revision = (int) ($stored['stateRevision'] ?? 0);

        $t->same(200, $first->get_status());
        $t->same('cancelled', $firstSnapshot['status'] ?? null);
        $t->same('cancelled', $firstSnapshot['stage'] ?? null);
        $t->same([], $firstSnapshot['renderRequests'] ?? null);
        $t->same([], $stored['renderRequests'] ?? null);
        $t->true((int) ($stored['cancelledAt'] ?? 0) > 0);
        $t->same([
            'postsRetainedAsDraft' => 0,
            'mediaAttachmentsRetained' => 0,
            'policy' => 'wordpress_artifacts_retained_for_review_or_reuse',
        ], $firstSnapshot['cancellation'] ?? null);
        $t->same(true, plpc_import_job_state_digest_is_valid($stored));

        $second = plpc_cancel_import_job(plpc_test_import_job_request([], $jobId));
        $advanced = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $t->same('cancelled', $second->get_data()['status'] ?? null);
        $t->same('cancelled', $advanced->get_data()['status'] ?? null);
        $t->same($revision, (int) (get_option($option)['stateRevision'] ?? 0), 'Idempotent cancel/advance reads must not rewrite a terminal job.');
        $t->same([], $GLOBALS['plpc_test_posts'], 'A cancelled queued import must never create a post.');
    },
    'cancellation discovers a publication committed after the last saved cursor and returns it to draft' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'cancel-crash.md',
            'bytes' => base64_encode('# Cancel after a committed transition'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $postId = plpc_insert_verified_page([
            'post_type' => 'page',
            'post_title' => 'Crash-window page',
            'post_content' => '<!-- wp:paragraph --><p>Verified private content.</p><!-- /wp:paragraph -->',
            'meta_input' => ['_plpc_import_job_id' => $jobId],
        ]);
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $job['documents'] = [['path' => 'cancel-crash.md', 'completed' => true]];
        $job['nextDocument'] = 1;
        $job['results'] = [[
            'postId' => $postId,
            'documentIndex' => 0,
            'kind' => 'document',
            'title' => 'Crash-window page',
        ]];
        plpc_import_job_begin_publication($job);
        plpc_import_job_save($job);

        $workerCopy = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        plpc_import_job_publish_next_result($workerCopy);
        $t->same('publish', get_post_status($postId));
        $t->same(0, get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId)['publishNextResult'] ?? null, 'The simulated worker terminates before saving its publication cursor.');

        $cancelled = plpc_cancel_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = $cancelled->get_data();
        $t->same(200, $cancelled->get_status());
        $t->same('cancelled', $snapshot['status'] ?? null);
        $t->same('draft', get_post_status($postId), 'Cancellation must reconcile WordPress state rather than trust the stale cursor.');
        $t->same(1, $snapshot['cancellation']['postsRetainedAsDraft'] ?? null);
        $advanced = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $t->same('cancelled', $advanced->get_data()['status'] ?? null);
        $t->same('draft', get_post_status($postId));
    },
    'cancellation retries an incomplete rollback and reports retained drafts and media explicitly' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'cancel-partial.md',
            'bytes' => base64_encode('# Cancel a partially published batch'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $results = [];
        for ($index = 1; $index <= 2; $index++) {
            $postId = plpc_insert_verified_page([
                'post_type' => 'page',
                'post_title' => 'Partial page ' . $index,
                'post_content' => '<!-- wp:paragraph --><p>Verified page ' . $index . '.</p><!-- /wp:paragraph -->',
                'meta_input' => ['_plpc_import_job_id' => $jobId],
            ]);
            $results[] = [
                'postId' => $postId,
                'documentIndex' => $index - 1,
                'kind' => 'document',
                'title' => 'Partial page ' . $index,
            ];
        }
        $attachmentId = wp_insert_attachment([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_input' => ['_plpc_import_job_id' => $jobId],
        ], '/tmp/cancelled-retained.png');
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $job['documents'] = [
            ['path' => 'one.md', 'completed' => true],
            ['path' => 'two.md', 'completed' => true],
        ];
        $job['nextDocument'] = 2;
        $job['results'] = $results;
        plpc_import_job_begin_publication($job);
        plpc_import_job_publish_next_result($job);
        plpc_import_job_save($job);
        $publishedId = (int) $results[0]['postId'];
        $t->same('publish', get_post_status($publishedId));

        $GLOBALS['plpc_test_wp_update_failure_injector'] = static fn (int $postId, array $update): bool =>
            $postId === $publishedId && ($update['post_status'] ?? '') === 'draft';
        $contended = plpc_cancel_import_job(plpc_test_import_job_request([], $jobId));
        $t->same(409, $contended->get_status(), 'Cancellation must remain retryable until every public artifact is private.');
        $t->same('ready_to_publish', get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId)['status'] ?? null);
        $t->same('publish', get_post_status($publishedId));
        unset($GLOBALS['plpc_test_wp_update_failure_injector']);

        $cancelled = plpc_cancel_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = $cancelled->get_data();
        $t->same(200, $cancelled->get_status());
        $t->same('cancelled', $snapshot['status'] ?? null);
        $t->same('draft', get_post_status($publishedId));
        $t->same('draft', get_post_status((int) $results[1]['postId']));
        $t->same(2, $snapshot['cancellation']['postsRetainedAsDraft'] ?? null);
        $t->same(1, $snapshot['cancellation']['mediaAttachmentsRetained'] ?? null);
        $t->same('wordpress_artifacts_retained_for_review_or_reuse', $snapshot['cancellation']['policy'] ?? null);
        $t->true(isset($GLOBALS['plpc_test_attachments'][$attachmentId]), 'Retained media remains explicit instead of becoming an unreported orphan.');
    },
    'persisted imports generate media metadata one attachment per resumable request' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'metadata.md',
            'bytes' => base64_encode('# Metadata queue'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $firstAttachment = wp_insert_attachment([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_input' => [],
        ], '/tmp/metadata-first.png');
        $secondAttachment = wp_insert_attachment([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_input' => [],
        ], '/tmp/metadata-second.png');
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $job['status'] = 'ready_for_media_metadata';
        $job['stage'] = 'ready_for_media_metadata';
        $job['documents'] = [[
            'path' => 'metadata.md',
            'format' => 'markdown',
            'completed' => true,
        ]];
        $job['results'] = [[
            'postId' => 99,
            'documentIndex' => 0,
            'kind' => 'document',
        ]];
        $job['mediaMetadataQueue'] = [
            ['attachmentId' => $firstAttachment, 'file' => '/tmp/metadata-first.png'],
            ['attachmentId' => $secondAttachment, 'file' => '/tmp/metadata-second.png'],
        ];
        $job['mediaMetadataTotal'] = 2;
        $job['mediaMetadataCompleted'] = 0;
        $job['progress'] = ['completed' => 1, 'total' => 4, 'label' => 'Preparing media metadata.'];
        plpc_import_job_save($job);

        $first = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_for_media_metadata', $first['status'] ?? null);
        $t->same(['completed' => 1, 'pending' => 1, 'total' => 2, 'perRequest' => 1], $first['mediaMetadata'] ?? null);
        $t->same([$firstAttachment], $GLOBALS['plpc_test_attachment_metadata_generate_calls']);
        $t->same($firstAttachment, $GLOBALS['plpc_test_attachment_metadata'][$firstAttachment]['id'] ?? null);

        $GLOBALS['plpc_test_attachment_metadata_fail_id'] = $secondAttachment;
        $failed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('retryable_failure', $failed['status'] ?? null);
        $t->same('media_metadata_generation_failed', $failed['failure']['code'] ?? null);
        $t->same(1, $failed['mediaMetadata']['pending'] ?? null);
        unset($GLOBALS['plpc_test_attachment_metadata_fail_id']);

        $resumed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_for_media_metadata', $resumed['status'] ?? null, 'The first retry only acknowledges the durable resume cursor.');
        $finished = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_publish', $finished['status'] ?? null);
        $t->same(['completed' => 2, 'pending' => 0, 'total' => 2, 'perRequest' => 1], $finished['mediaMetadata'] ?? null);
        $t->same(
            [$firstAttachment, $secondAttachment, $secondAttachment],
            $GLOBALS['plpc_test_attachment_metadata_generate_calls'],
            'A failed attachment may retry, but no request may advance more than one queue item.'
        );
        $t->same(true, plpc_import_job_state_digest_is_valid(get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId)));
    },
    'persisted media metadata accepts core unchanged writes without exhausting the global retry budget' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'metadata-core.md',
            'bytes' => base64_encode('# Core metadata behavior'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $queue = [];
        $attachmentIds = [];
        for ($index = 1; $index <= 4; $index++) {
            $file = '/tmp/metadata-core-' . $index . '.png';
            $attachmentId = wp_insert_attachment([
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'meta_input' => ['_plpc_import_source_file' => $file],
            ], $file);
            $attachmentIds[] = $attachmentId;
            $queue[] = ['attachmentId' => $attachmentId, 'file' => $file];
        }
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $job['status'] = 'ready_for_media_metadata';
        $job['stage'] = 'ready_for_media_metadata';
        $job['documents'] = [['path' => 'metadata-core.md', 'format' => 'markdown', 'completed' => true]];
        $job['results'] = [['postId' => 99, 'documentIndex' => 0, 'kind' => 'document']];
        $job['mediaMetadataQueue'] = $queue;
        $job['mediaMetadataTotal'] = 4;
        $job['mediaMetadataCompleted'] = 0;
        $job['progress'] = ['completed' => 1, 'total' => 6, 'label' => 'Preparing media metadata.'];
        plpc_import_job_save($job);

        $snapshot = [];
        for ($request = 1; $request <= 4; $request++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
            $t->true(($snapshot['status'] ?? '') !== 'retryable_failure', 'An unchanged core metadata write must not consume a retry.');
            $t->same(4 - $request, $snapshot['mediaMetadata']['pending'] ?? null);
        }

        $t->same('ready_to_publish', $snapshot['status'] ?? null);
        $t->same($attachmentIds, $GLOBALS['plpc_test_attachment_metadata_generate_calls']);
        $t->same($attachmentIds, $GLOBALS['plpc_test_attachment_metadata_unchanged_calls']);
        foreach ($attachmentIds as $attachmentId) {
            $t->same('1', get_post_meta($attachmentId, '_plpc_import_metadata_complete', true));
            $t->same('', get_post_meta($attachmentId, '_plpc_import_source_file', true), 'The absolute source path must be removed after readback completes.');
            $t->same([], wp_get_missing_image_subsizes($attachmentId));
        }
        $stored = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $t->same([], $stored['retryCounts'] ?? []);
        $t->same(true, plpc_import_job_state_digest_is_valid($stored));
    },
    'persisted media metadata repairs partial subsizes and explicitly completes unsupported images' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'metadata-resume.md',
            'bytes' => base64_encode('# Resume metadata'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $partialFile = '/tmp/metadata-partial.png';
        $partialId = wp_insert_attachment([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_input' => [],
        ], $partialFile);
        $GLOBALS['plpc_test_attachment_metadata'][$partialId] = [
            'file' => basename($partialFile),
            'id' => $partialId,
            'sizes' => ['thumbnail' => ['file' => 'thumbnail-' . basename($partialFile)]],
        ];
        $svgFile = '/tmp/metadata-vector.svg';
        $svgId = wp_insert_attachment([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_input' => [],
        ], $svgFile);
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $job['status'] = 'ready_for_media_metadata';
        $job['stage'] = 'ready_for_media_metadata';
        $job['documents'] = [['path' => 'metadata-resume.md', 'format' => 'markdown', 'completed' => true]];
        $job['results'] = [['postId' => 99, 'documentIndex' => 0, 'kind' => 'document']];
        $job['mediaMetadataQueue'] = [
            ['attachmentId' => $partialId, 'file' => $partialFile],
            ['attachmentId' => $svgId, 'file' => $svgFile],
        ];
        $job['mediaMetadataTotal'] = 2;
        $job['mediaMetadataCompleted'] = 0;
        $job['progress'] = ['completed' => 1, 'total' => 4, 'label' => 'Preparing media metadata.'];
        plpc_import_job_save($job);

        $partial = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_for_media_metadata', $partial['status'] ?? null);
        $t->same([$partialId], $GLOBALS['plpc_test_attachment_metadata_resume_calls']);
        $t->same([], wp_get_missing_image_subsizes($partialId), 'The cursor may shift only after the missing medium subsize is persisted.');
        $t->same('1', get_post_meta($partialId, '_plpc_import_metadata_complete', true));
        $t->same([], $GLOBALS['plpc_test_attachment_metadata_generate_calls'], 'Partial core metadata must resume rather than start generation over.');

        $svg = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_publish', $svg['status'] ?? null);
        $t->same([$svgId], $GLOBALS['plpc_test_attachment_metadata_generate_calls']);
        $t->same('1', get_post_meta($svgId, '_plpc_import_metadata_complete', true), 'An unsupported empty-metadata format needs a durable completion marker.');
        $t->same(true, plpc_import_attachment_metadata_complete($svgId));
    },
    'persisted import jobs verify each option checkpoint by revision and digest readback' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'commit.md',
            'bytes' => base64_encode('# Commit verification'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $storedRevision = (int) ($job['stateRevision'] ?? 0);
        $GLOBALS['plpc_test_update_option_failure'] = $option;

        $error = null;
        try {
            plpc_import_job_save($job);
        } catch (Throwable $throwable) {
            $error = $throwable;
        } finally {
            unset($GLOBALS['plpc_test_update_option_failure']);
        }

        $t->true($error instanceof PlpcImportFailure);
        $t->same('job_state_commit_failed', $error instanceof PlpcImportFailure ? $error->failureCode : null);
        $t->same($storedRevision, get_option($option)['stateRevision'] ?? null, 'An unverified write must not advance the durable cursor.');
        $t->same(true, plpc_import_job_state_digest_is_valid(get_option($option)));
    },
    'durable import files commit atomically and retain the previous checkpoint after a staged-write fault' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $directory = plpc_test_private_import_job_dir() . '/atomic-write-fixture';
        $relative = 'state/checkpoint.json';
        plpc_import_job_write_file($directory, $relative, 'previous checkpoint');
        $target = plpc_import_job_storage_target($directory, $relative);
        $faultInjected = false;
        add_action('plpc_import_job_file_staged', static function (string $temporary, string $committed) use ($target, &$faultInjected): void {
            if ($committed !== $target || $faultInjected) {
                return;
            }
            $faultInjected = true;
            file_put_contents($temporary, 'short');
        });

        $error = null;
        try {
            plpc_import_job_write_file($directory, $relative, 'replacement checkpoint that must not be partially committed');
        } catch (Throwable $throwable) {
            $error = $throwable;
        } finally {
            unset($GLOBALS['plpc_test_actions']['plpc_import_job_file_staged']);
        }

        $t->true($faultInjected, 'The failure injection must occur after the temporary file is flushed.');
        $t->true($error instanceof RuntimeException);
        $t->contains('verify the staged import file', (string) $error?->getMessage());
        $t->same('previous checkpoint', file_get_contents($target), 'A corrupt staged write must never replace the last complete checkpoint.');
        $t->same([], glob($target . '.tmp-*') ?: [], 'Failed temporary files must not leak into private job storage.');

        plpc_import_job_write_file($directory, $relative, 'replacement checkpoint');
        $t->same('replacement checkpoint', file_get_contents($target));
        $t->same([], glob($target . '.tmp-*') ?: []);
    },
    'a failed job creation removes its unacknowledged source directory, option, and index entry' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $expectedJobId = str_replace('-', '', sprintf('00000000-0000-4000-8000-%012d', 1));
        add_action('plpc_import_job_file_staged', static function (string $temporary, string $target): void {
            if (str_contains(str_replace('\\', '/', $target), '/source/')) {
                file_put_contents($temporary, 'corrupt');
            }
        });
        try {
            $response = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'unacknowledged.md',
                'bytes' => base64_encode('# This source must be rolled back'),
            ]));
        } finally {
            unset($GLOBALS['plpc_test_actions']['plpc_import_job_file_staged']);
        }

        $directory = plpc_import_job_directory($expectedJobId);
        $index = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);
        $t->same(400, $response->get_status());
        $t->contains('verify the staged import file', (string) ($response->get_data()['message'] ?? ''));
        $t->same(false, is_dir($directory), 'A client that never received a job id must not leave private source files behind.');
        $t->same(null, get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $expectedJobId, null));
        $t->true(!is_array($index) || !array_key_exists($expectedJobId, $index));
    },
    'file-backed browser uploads survive an interruption between staging and atomic commit' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $directory = plpc_test_private_import_job_dir() . '/external-write-fixture';
        wp_mkdir_p($directory);
        $source = $directory . '/browser-staged.bin';
        $target = $directory . '/durable-source.bin';
        file_put_contents($source, 'new immutable source bytes');
        file_put_contents($target, 'previous durable source');
        add_action('plpc_import_job_external_file_staged', static function (): void {
            throw new RuntimeException('simulated worker termination after staging');
        });

        $error = null;
        try {
            plpc_import_job_commit_external_file($source, $target, false);
        } catch (Throwable $throwable) {
            $error = $throwable;
        } finally {
            unset($GLOBALS['plpc_test_actions']['plpc_import_job_external_file_staged']);
        }

        $t->contains('simulated worker termination', (string) $error?->getMessage());
        $t->same('new immutable source bytes', file_get_contents($source), 'The selected source must remain retryable after the interrupted move.');
        $t->same('previous durable source', file_get_contents($target), 'An interrupted staged move must not replace the prior durable file.');
        $t->same([], glob($target . '.tmp-*') ?: []);

        $identity = plpc_import_job_commit_external_file($source, $target, false);
        $t->same('new immutable source bytes', file_get_contents($target));
        $t->same(strlen('new immutable source bytes'), $identity['bytes']);
        $t->same(hash('sha256', 'new immutable source bytes'), $identity['sha256']);
        $t->same(false, is_file($source), 'A committed move must remove the browser staging copy.');
    },
    'persisted import jobs return 409 under a real per-job file lock and resume from the unchanged checkpoint' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'contended.md',
            'bytes' => base64_encode("# Contended\n\nAdvance exactly once after the lock releases."),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $request = plpc_test_import_job_request([], $jobId);
        $before = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, null);
        $job = plpc_import_job_from_request($request);
        $heldLock = plpc_import_job_acquire_lock($job);

        try {
            $contended = plpc_advance_import_job($request);
        } finally {
            plpc_import_job_release_lock($heldLock);
        }

        $t->same(409, $contended->get_status());
        $t->same(false, $contended->get_data()['ok'] ?? null);
        $t->contains('already being updated', (string) ($contended->get_data()['message'] ?? ''));
        $t->same(
            $before,
            get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, null),
            'A rejected concurrent advance must not mutate the authoritative job checkpoint.'
        );

        $resumed = plpc_advance_import_job($request);
        $resumedData = $resumed->get_data();
        $t->same(200, $resumed->get_status());
        $t->same(true, $resumedData['ok'] ?? null);
        $t->true(
            in_array((string) ($resumedData['status'] ?? ''), ['ready_to_convert', 'converting'], true),
            'The fresh request must resume the queued job after the operating-system lock releases.'
        );
        $t->true(
            (int) (get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, [])['stateRevision'] ?? 0)
                > (int) ($before['stateRevision'] ?? 0),
            'Only the successful retry may advance the durable revision.'
        );
    },
    'persisted import job index serializes interleaved writers and retains both entries after bounded retry' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_import_job_index_lock_timeout_ms', static fn (mixed $milliseconds): int => 10);
        $firstCreated = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'first.md',
            'bytes' => base64_encode('# First'),
        ]))->get_data();
        $secondCreated = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'second.md',
            'bytes' => base64_encode('# Second'),
        ]))->get_data();
        $first = plpc_import_job_from_request(plpc_test_import_job_request([], (string) $firstCreated['jobId']));
        $second = plpc_import_job_from_request(plpc_test_import_job_request([], (string) $secondCreated['jobId']));
        update_option(PLPC_IMPORT_JOB_INDEX_OPTION, [], false);
        $nestedFailure = null;
        $interleaved = false;
        add_action('plpc_import_job_index_locked', static function () use (&$interleaved, &$nestedFailure, $second): void {
            if ($interleaved) {
                return;
            }
            $interleaved = true;
            try {
                plpc_import_job_update_index($second);
            } catch (PlpcImportFailure $error) {
                $nestedFailure = $error->failureCode;
            }
        });

        $started = microtime(true);
        plpc_import_job_update_index($first);
        $elapsed = microtime(true) - $started;
        $afterFirst = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);

        $t->same('job_index_lock_timeout', $nestedFailure, 'The interleaved writer must fail fast instead of overwriting a stale snapshot.');
        $t->true($elapsed < 0.5, 'Index contention must remain bounded.');
        $t->true(isset($afterFirst[$first['id']]));
        $t->true(!isset($afterFirst[$second['id']]));

        plpc_import_job_update_index($second);
        $afterRetry = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);
        $t->same([$first['id'], $second['id']], array_keys($afterRetry), 'Retrying after the lock releases must preserve both jobs.');
    },
    'persisted import job index reports an unverifiable write without discarding the job checkpoint' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'index-failure.md',
            'bytes' => base64_encode('# Index failure'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $job['status'] = 'ready_to_convert';
        $job['updatedAt'] = ((int) ($job['updatedAt'] ?? time())) + 10;
        $GLOBALS['plpc_test_update_option_failure'] = PLPC_IMPORT_JOB_INDEX_OPTION;
        $error = null;
        try {
            plpc_import_job_update_index($job);
        } catch (Throwable $throwable) {
            $error = $throwable;
        } finally {
            unset($GLOBALS['plpc_test_update_option_failure']);
        }

        $t->true($error instanceof PlpcImportFailure);
        $t->same('job_index_commit_failed', $error instanceof PlpcImportFailure ? $error->failureCode : null);
        $t->true(is_array(get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, null)), 'The authoritative per-job checkpoint must remain available.');
    },
    'persisted import jobs externalize large result trees and return bounded status snapshots' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'large-state.md',
            'bytes' => base64_encode('# Large state'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $children = [];
        for ($index = 1; $index <= 300; $index++) {
            $children[] = [
                'postId' => $index,
                'title' => 'Imported page ' . $index,
                'pageUrl' => 'https://playground.test/page-' . $index,
                'diagnostics' => [str_repeat('diagnostic-' . $index . '-', 40)],
            ];
        }
        $job['status'] = 'complete';
        $job['stage'] = 'complete';
        $job['result'] = ['batch' => true, 'postId' => 1, 'children' => $children, 'documents' => $children];
        $job['documentResults'] = [$job['result']];
        $job['documents'] = [[
            'path' => 'large-state.md',
            'completed' => true,
            'padding' => str_repeat('bounded-state-padding-', 4000),
            'result' => ['children' => $children],
        ]];
        plpc_import_job_save($job);

        $raw = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $t->true(strlen(serialize($raw)) <= PLPC_IMPORT_JOB_MAX_OPTION_BYTES, 'The transactional option must remain below 64 KiB.');
        $t->true(plpc_import_job_state_blob_descriptor($raw['result'] ?? null) !== null, 'The repeated result tree belongs in private state storage.');
        $t->true(plpc_import_job_state_blob_descriptor($raw['documents'] ?? null) !== null, 'An aggregate descriptor can safely contain another state-blob descriptor.');
        $hydrated = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $t->same(300, count($hydrated['result']['children'] ?? []));
        $t->same(300, count($hydrated['documents'][0]['result']['children'] ?? []), 'Transitive state blobs must survive stale-blob cleanup and hydrate recursively.');

        $response = plpc_import_job_status(plpc_test_import_job_request([], $jobId));
        $snapshot = $response->get_data();
        $t->true(strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) <= PLPC_IMPORT_JOB_MAX_STATUS_BYTES);
        $t->same(true, $snapshot['truncated']['result'] ?? null);
        $t->same(300, $snapshot['result']['childrenTotal'] ?? null);
        $t->true(count($snapshot['result']['children'] ?? []) <= PLPC_IMPORT_JOB_STATUS_MAX_RESULT_ITEMS);
    },
    'persisted import state remains bounded for 250 1000 and 2000 page topologies' => static function (TestRunner $t): void {
        foreach ([250, 1000, 2000] as $pageCount) {
            plpc_test_reset_import_job_state();
            $created = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'state-' . $pageCount . '.md',
                'bytes' => base64_encode('# Bounded state'),
            ]))->get_data();
            $jobId = (string) ($created['jobId'] ?? '');
            $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
            $children = [];
            for ($page = 1; $page <= $pageCount; $page++) {
                $children[] = [
                    'postId' => $page,
                    'page' => $page,
                    'sourceId' => 'pdf-page-' . $page,
                    'fingerprint' => hash('sha256', 'page-' . $page),
                ];
            }
            $job['status'] = 'complete';
            $job['stage'] = 'complete';
            $job['result'] = ['batch' => true, 'postId' => 1, 'children' => $children];
            $job['documents'] = [[
                'path' => 'state-' . $pageCount . '.md',
                'pdfPageCount' => $pageCount,
                'result' => ['children' => $children],
            ]];
            plpc_import_job_save($job);

            $raw = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
            $t->true(
                strlen(serialize($raw)) <= PLPC_IMPORT_JOB_MAX_OPTION_BYTES,
                $pageCount . '-page job state exceeded the 64 KiB option bound.'
            );
            $hydrated = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
            $t->same($pageCount, count($hydrated['result']['children'] ?? []));
            $snapshot = plpc_import_job_response($hydrated)->get_data();
            $t->true(strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) <= PLPC_IMPORT_JOB_MAX_STATUS_BYTES);
        }
    },
    'persisted import job retention removes expired unlocked storage but keeps resumable work' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $expired = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'expired.md',
            'bytes' => base64_encode('# Expired'),
        ]))->get_data();
        $active = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'active.md',
            'bytes' => base64_encode('# Active'),
        ]))->get_data();
        $expiredId = (string) ($expired['jobId'] ?? '');
        $activeId = (string) ($active['jobId'] ?? '');
        $now = time();
        $index = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);
        $index[$expiredId] = ['updatedAt' => $now - PLPC_IMPORT_JOB_RETENTION_COMPLETE_SECONDS - 10, 'status' => 'complete', 'ownerId' => 1];
        $index[$activeId] = ['updatedAt' => $now, 'status' => 'ready_to_convert', 'ownerId' => 1];
        update_option(PLPC_IMPORT_JOB_INDEX_OPTION, $index, false);
        $expiredDirectory = plpc_import_job_directory($expiredId);
        file_put_contents($expiredDirectory . '/expired.tmp', 'temporary');

        $result = plpc_cleanup_import_jobs($now);

        $t->same(1, $result['removed'] ?? null);
        $t->same(null, get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $expiredId, null));
        $t->same(false, is_dir($expiredDirectory));
        $t->true(is_array(get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $activeId, null)), 'An active resumable job must survive cleanup.');
    },
    'a stale loaded job cannot recreate private storage after retention cleanup' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'stale-lock.md',
            'bytes' => base64_encode('# Stale lock owner'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $stale = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $directory = plpc_import_job_directory($stale);
        $now = time();
        $index = get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []);
        $index[$jobId]['updatedAt'] = $now - PLPC_IMPORT_JOB_RETENTION_ACTIVE_SECONDS - 10;
        $index[$jobId]['status'] = 'queued';
        update_option(PLPC_IMPORT_JOB_INDEX_OPTION, $index, false);

        $cleaned = plpc_cleanup_import_jobs($now);
        $t->same(1, $cleaned['removed'] ?? null);
        $t->same(false, is_dir($directory));
        $error = null;
        try {
            plpc_import_job_acquire_lock($stale);
        } catch (Throwable $throwable) {
            $error = $throwable;
        }
        $t->true($error instanceof RuntimeException);
        $t->contains('no longer has private storage', (string) $error?->getMessage());
        $t->same(false, is_dir($directory), 'A stale caller must not resurrect an unindexed lock directory.');
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
        $t->same(
            hash('sha256', plpc_import_job_read_file($job, (string) ($record['storage'] ?? ''))),
            $record['sha256'] ?? null,
            'The descriptor digest must bind the exact serialized facts bytes committed to private storage.'
        );
        $t->true(!array_key_exists('pages', $record) || is_int($record['pages']), 'Browser page payloads must not be stored in the WordPress option.');
        $loaded = plpc_import_job_load_browser_facts($job, 'browser.pdf');
        $t->same(json_decode(json_encode($browserFacts, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), $loaded);

        $factsStorage = (string) ($record['storage'] ?? '');
        $factsPath = plpc_import_job_storage_path($job, $factsStorage);
        $savedFacts = (string) file_get_contents($factsPath);
        $tamperedFacts = str_replace('Browser handoff', 'Browzer handoff', $savedFacts);
        $t->same(strlen($savedFacts), strlen($tamperedFacts));
        file_put_contents($factsPath, $tamperedFacts, LOCK_EX);
        $t->same(null, plpc_import_job_load_browser_facts($job, 'browser.pdf'), 'A same-size browser-facts replacement must not enter native reconciliation.');
        plpc_import_job_write_file(plpc_import_job_directory($job), $factsStorage, $savedFacts);

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
    'persisted jobs discard browser facts whose source digest does not match the immutable upload' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $pdf = plpc_test_multipage_pdf(['Native source identity']);
        $response = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'browser-source-mismatch.pdf',
            'bytes' => base64_encode($pdf),
            'pdfBrowserFacts' => ['browser-source-mismatch.pdf' => [
                'schemaVersion' => 1,
                'provider' => 'pdfjs-v1',
                'sourceSha256' => hash('sha256', $pdf . 'different'),
                'pageCount' => 1,
                'pages' => [[
                    'pageNumber' => 1,
                    'viewport' => ['width' => 1.0, 'height' => 1.0, 'rotation' => 0, 'viewBox' => [0.0, 0.0, 1.0, 1.0]],
                    'spans' => [],
                    'markedContent' => [],
                    'styles' => [],
                    'structure' => null,
                ]],
                'failures' => [],
            ]],
        ]));
        $snapshot = $response->get_data();
        $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . (string) ($snapshot['jobId'] ?? ''));

        $t->same(201, $response->get_status());
        $t->same([], $job['browserFacts'] ?? null, 'Stale browser observations must not be checkpointed beside another source version.');
        $t->same(hash('sha256', $pdf), $job['sourceFiles'][0]['sha256'] ?? null);
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

        for ($attempt = 0; $attempt < 12 && !in_array($snapshot['status'] ?? '', ['complete', 'completed'], true); $attempt++) {
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
    'resumable pdf jobs reject a same-length source replacement before mixing it with saved page facts' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 1);
        $pdf = plpc_test_multipage_pdf([
            'IMMUTABLE FIRST PAGE',
            'IMMUTABLE SECOND PAGE',
            'IMMUTABLE THIRD PAGE',
        ]);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'immutable-source.pdf',
            'imageMode' => 'none',
            'pdfMode' => 'layout',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $request = plpc_test_import_job_request([], $jobId);
        plpc_advance_import_job($request);
        plpc_advance_import_job($request);
        $job = plpc_import_job_from_request($request);
        $document = is_array($job['documents'][0] ?? null) ? $job['documents'][0] : [];
        $t->same(1, count($document['pdfChunks'] ?? []), 'The fixture must have one authoritative page-facts checkpoint before tampering.');
        $t->same(hash('sha256', $pdf), $document['sourceSha256'] ?? null);
        $sourcePath = plpc_import_job_storage_path($job, (string) ($document['storage'] ?? ''));
        $replacement = str_replace('IMMUTABLE SECOND PAGE', 'IMMUTABLF SECOND PAGE', $pdf);
        $t->same(strlen($pdf), strlen($replacement), 'The replacement must evade a length-only checkpoint check.');
        file_put_contents($sourcePath, $replacement, LOCK_EX);

        $failed = plpc_advance_import_job($request);
        $snapshot = $failed->get_data();
        $stored = plpc_import_job_from_request($request);

        $t->same(500, $failed->get_status());
        $t->same('failed', $snapshot['status'] ?? null);
        $t->same('source_identity_mismatch', $snapshot['failure']['code'] ?? null);
        $t->same('validating_source', $snapshot['failure']['stage'] ?? null);
        $t->same(false, $snapshot['failure']['recoverable'] ?? null);
        $t->same(1, count($stored['documents'][0]['pdfChunks'] ?? []), 'No page facts from the replaced source may be appended.');
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'A mixed-version PDF must never reach publication.');
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
        $t->true(is_array($jobAfterMerge['documents'][0]['pdfDocumentProfile'] ?? null), 'A compact full-document layout profile must be durable beside bounded facts.');
        $mergedFacts = plpc_import_job_load_pdf_document_facts($jobAfterMerge, $jobAfterMerge['documents'][0]);
        $profile = $mergedFacts->structure()['documentProfile'] ?? [];
        $t->same(true, $profile['complete'] ?? null);
        $t->same([1, 2, 3], $profile['coveredPages'] ?? null);
        $t->same(
            $jobAfterMerge['documents'][0]['pdfDocumentProfile']['profileDigest'] ?? null,
            $profile['profileDigest'] ?? null,
            'Every semantic facts range must receive the exact same immutable document profile.'
        );
        $t->same(0, count($GLOBALS['plpc_test_posts']));

        $semantics = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $jobAfterSemantics = get_option($option);
        $t->same('ready_to_convert', $semantics['status'] ?? null);
        $t->true(is_array($jobAfterSemantics['documents'][0]['pdfFinalBundle'] ?? null), 'The one global semantic pass should become a durable private bundle before publication.');
        $t->same(0, count($GLOBALS['plpc_test_posts']));
        $durableBundle = plpc_import_job_load_pdf_final_bundle($jobAfterSemantics, $jobAfterSemantics['documents'][0]);
        $t->same(true, $durableBundle['semanticSourceComplete'] ?? null, 'The durable PDF bundle must carry its verified source-disposition gate.');
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

    'playground pdf visual checkpoints survive reload and continue with the next unscanned page' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 1);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'visual-checkpoints.pdf',
            'imageMode' => 'all',
            'pdfMode' => 'layout',
            'bytes' => base64_encode(plpc_test_two_page_form_xobject_pdf()),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;

        $prepared = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_convert', $prepared['status'] ?? null);
        $first = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $firstRequest = $first['renderRequests'][0] ?? [];
        $afterFirst = get_option($option);
        $t->same('awaiting_renderer', $first['status'] ?? null);
        $t->same(1, $firstRequest['page'] ?? null);
        $t->same(2, $afterFirst['documents'][0]['pdfNextPage'] ?? null);
        $t->same(1, $afterFirst['documents'][0]['pdfVisualInspectionCompleteThroughPage'] ?? null);
        $t->same(false, $afterFirst['documents'][0]['pdfVisualInventoryComplete'] ?? null);

        // A browser reload calls /advance again before returning the crop.
        // The durable awaiting_renderer state must not parse page one again.
        $reloaded = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $afterReload = get_option($option);
        $t->same($firstRequest['id'] ?? null, $reloaded['renderRequests'][0]['id'] ?? null);
        $t->same(1, count($afterReload['documents'][0]['pdfChunks'] ?? []));
        $t->same(1, count($afterReload['documents'][0]['pdfVisualOccurrences'] ?? []));
        $t->same(1, count($afterReload['documents'][0]['pdfChunkMetrics'] ?? []));

        $submitted = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $firstRequest['id'] ?? '',
            'error' => 'Test renderer acknowledgement.',
        ], $jobId))->get_data();
        $t->same('ready_to_convert', $submitted['status'] ?? null);
        $interrupted = get_option($option);
        $interrupted['status'] = 'converting';
        $interrupted['stage'] = 'reading';
        $interrupted['checkpoint'] = [
            'documentIndex' => 0,
            'stage' => 'reading',
            'deadlineYields' => 0,
            'interruptedRetries' => 0,
        ];
        update_option($option, $interrupted, false);
        $second = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $afterSecond = get_option($option);
        $t->same('awaiting_renderer', $second['status'] ?? null);
        $t->same(2, $second['renderRequests'][0]['page'] ?? null);
        $t->true(($second['renderRequests'][0]['id'] ?? '') !== ($firstRequest['id'] ?? ''));
        $t->same(3, $afterSecond['documents'][0]['pdfNextPage'] ?? null);
        $t->same(2, $afterSecond['documents'][0]['pdfVisualInspectionCompleteThroughPage'] ?? null);
        $t->same(3, $afterSecond['documents'][0]['pdfVisualInspectionNextPage'] ?? null);
        $t->same(true, $afterSecond['documents'][0]['pdfVisualInventoryComplete'] ?? null);
        $t->same(2, count($afterSecond['documents'][0]['pdfChunks'] ?? []));
        $t->same(2, count($afterSecond['documents'][0]['pdfVisualOccurrences'] ?? []));
    },
    'playground migrates older saved PDF visual facts without rescanning prior source pages' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 1);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 1);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'legacy-visual-checkpoint.pdf',
            'imageMode' => 'all',
            'bytes' => base64_encode(plpc_test_two_page_form_xobject_pdf()),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $legacy = get_option($option);
        $legacy['status'] = 'ready_to_convert';
        $legacy['stage'] = 'ready_to_convert';
        $legacy['renderRequests'] = [];
        unset(
            $legacy['documents'][0]['pdfVisualInspectionCompleteThroughPage'],
            $legacy['documents'][0]['pdfVisualInspectionNextPage'],
            $legacy['documents'][0]['pdfVisualOccurrences'],
            $legacy['documents'][0]['pdfVisualInventoryComplete'],
            $legacy['documents'][0]['pdfVisualFormObjectCounts'],
            $legacy['documents'][0]['pdfVisualRenderRequestCount']
        );
        update_option($option, $legacy, false);

        $migrated = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $after = get_option($option);
        $t->same('awaiting_renderer', $migrated['status'] ?? null);
        $t->same(1, $migrated['renderRequests'][0]['page'] ?? null);
        $t->same(2, $after['documents'][0]['pdfNextPage'] ?? null, 'Migration must not re-run source page extraction.');
        $t->same(1, count($after['documents'][0]['pdfChunks'] ?? []));
        $t->same(1, count($after['documents'][0]['pdfChunkMetrics'] ?? []));
        $t->same(1, $after['documents'][0]['pdfVisualInspectionCompleteThroughPage'] ?? null);
        $t->true(in_array('checkpoint_migrated', array_column($migrated['events'] ?? [], 'stage'), true));
    },
    'playground visual checkpoint cap emits a typed recoverable issue instead of growing the job' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_visual_occurrence_limit', static fn (mixed $limit): int => 4);
        $occurrences = [];
        for ($index = 1; $index <= 6; $index++) {
            $occurrences[] = [
                'id' => 'pdf-image-p1-n' . $index . '-o' . (20 + $index),
                'kind' => 'image-xobject',
                'page' => 1,
                'object' => 20 + $index,
                'paintOrder' => $index,
                'bbox' => ['x1' => 1.0, 'y1' => 1.0, 'x2' => 20.0, 'y2' => 20.0],
                'visible' => true,
                'placementEligible' => true,
                'disposition' => 'pending',
                'dispositionReason' => null,
            ];
        }
        $facts = \PortLibs\MarkerPDF\PdfDocumentFacts::fromArray([
            'schemaVersion' => 1,
            'provider' => 'test-visual-cap',
            'source' => ['sha256' => hash('sha256', 'visual-cap'), 'byteLength' => 10],
            'inventory' => ['totalPages' => 1, 'pageNumbers' => [1]],
            'pages' => [[
                'schemaVersion' => 1,
                'pageNumber' => 1,
                'pageObject' => 3,
                'label' => '1',
                'geometry' => ['page_number' => 1, 'bbox' => [0.0, 0.0, 100.0, 100.0]],
                'text' => ['lines' => [], 'runs' => [], 'spans' => [], 'positionedRunsLimited' => false],
                'graphics' => ['filledRectangles' => [], 'images' => [], 'forms' => [], 'visualOccurrences' => $occurrences],
                'annotations' => ['links' => [], 'text' => [], 'fileAttachments' => [], 'popups' => [], 'appearances' => []],
                'structure' => [],
                'issues' => [],
            ]],
            'structure' => [],
            'diagnostics' => [],
            'unassignedAnnotations' => [],
        ]);
        $job = [
            'imageMode' => 'all',
            'renderRequests' => [],
            'renderedForms' => [],
            'documents' => [[
                'path' => 'cap.pdf',
                'format' => 'pdf',
                'pdfVisualInspectionCompleteThroughPage' => 0,
                'pdfVisualInspectionNextPage' => 1,
                'pdfVisualOccurrences' => [],
                'pdfVisualInventoryComplete' => false,
            ]],
        ];

        plpc_import_job_checkpoint_pdf_chunk_visuals($job, 0, $facts, 1, 1, 1);
        $inventory = $job['documents'][0]['pdfVisualOccurrences'] ?? [];
        $issue = $inventory[count($inventory) - 1] ?? [];
        $t->same(4, count($inventory));
        $t->same('inspection-issue', $issue['kind'] ?? null);
        $t->same('resource-limit', $issue['issueType'] ?? null);
        $t->same(true, $issue['recoverable'] ?? null);
        $t->same('visual-occurrence-limit', $issue['reason'] ?? null);
        $t->same(3, $issue['omittedOccurrences'] ?? null);
        $t->same(true, $job['documents'][0]['pdfVisualInventoryComplete'] ?? null);
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
        $sentinels = [
            'SEGMENTED PAGE ONE SENTINEL',
            'SEGMENTED PAGE TWO SENTINEL',
            'SEGMENTED PAGE THREE SENTINEL',
            'SEGMENTED PAGE FOUR SENTINEL',
            'SEGMENTED PAGE FIVE SENTINEL',
            'SEGMENTED PAGE SIX SENTINEL',
            'SEGMENTED PAGE SEVEN SENTINEL',
            'SEGMENTED PAGE EIGHT SENTINEL',
            'SEGMENTED PAGE NINE SENTINEL',
            'SEGMENTED PAGE TEN SENTINEL',
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
        $t->same([], $segments, 'Completed segment descriptors must not be duplicated in the compact job option.');
        $t->same(true, $job['documents'][0]['durableConversionRetained'] ?? null);
        $t->same(1, $job['publicationSummary']['documents'] ?? null, 'Completed source results are summarized rather than duplicated beside the public result.');

        $postId = (int) ($snapshot['result']['postId'] ?? 0);
        $rangeContent = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');
        foreach ($sentinels as $sentinel) {
            $t->same(1, substr_count($rangeContent, $sentinel), $sentinel . ' must survive exactly once in the continuous result.');
        }
        $t->true(!str_contains($rangeContent, 'pages 1–2'), 'Internal segment boundaries must not leak into the page body.');

        // Model the assembled page committing immediately before the job
        // cursor is saved. The stable single-output identity must recover it.
        $t->true(is_array($jobBeforeFinalPublication), 'Every bounded range should have a post-ready bundle before assembly.');
        $t->same(
            [[1, 8], [9, 10]],
            array_map(
                static fn (array $segment): array => [
                    (int) ($segment['startPage'] ?? 0),
                    (int) ($segment['endPage'] ?? 0),
                ],
                $jobBeforeFinalPublication['documents'][0]['pdfSegments'] ?? []
            ),
            'One-page extraction requests must be repacked into the canonical eight-page semantic grain.'
        );
        $profileDigest = $jobBeforeFinalPublication['documents'][0]['pdfDocumentProfile']['profileDigest'] ?? null;
        foreach ($jobBeforeFinalPublication['documents'][0]['pdfSegments'] ?? [] as $segment) {
            $segmentFacts = plpc_import_job_load_pdf_facts_record(
                $jobBeforeFinalPublication,
                $segment['facts'],
                (int) $segment['startPage'],
                (int) $segment['endPage']
            );
            $t->same(
                $profileDigest,
                $segmentFacts->structure()['documentProfile']['profileDigest'] ?? null,
                'Every bounded semantic segment needs the same full-document layout evidence.'
            );
        }
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

    'playground page-tree refuses to publish an undecodable nonblank page as an intentional blank' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $content = 'BT /F1 12 Tf 72 720 Td (THIS NONBLANK TEXT MUST NOT BECOME AN EMPTY PAGE) Tj ET';
        // The raw stream contains visible PDF operators, but its declared
        // RunLength filter is deliberately malformed. Extraction therefore
        // yields no text and a page-scoped failed_content_decode issue.
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . '4 0 obj' . "\n<< /Filter /RunLengthDecode /Length " . strlen($content) . ">>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "%%EOF";
        $facts = (new \PortLibs\MarkerPDF\NativePdfFactsProvider())->extract($pdf);
        $page = $facts->page(1);
        $t->same([], $page?->text()['runs'] ?? null);
        $t->same('failed_content_decode', $page?->issues()[0]['reason'] ?? null);

        $job = ['id' => 'undecodableblank00000001'];
        $stored = plpc_import_job_store_pdf_chunk($job, 0, [
            'facts' => $facts,
            'startPage' => 1,
            'endPage' => 1,
            'pageNumbers' => [1],
        ]);
        $factsRecord = [
            'startPage' => 1,
            'endPage' => 1,
            'storage' => $stored['facts'],
            'sha256' => $stored['sha256'],
            'bytes' => $stored['bytes'],
        ];
        $allowEmpty = plpc_import_job_pdf_page_is_certified_blank($job, $factsRecord);
        $t->same(false, $allowEmpty, 'An unreadable page is not a facts-certified blank page.');

        $document = [
            'path' => 'undecodable-nonblank.pdf',
            'format' => 'pdf',
            'pdfSegments' => [['startPage' => 1, 'endPage' => 1]],
        ];
        $t->throws(PlpcImportFailure::class, static function () use ($job, $document, $pdf, $allowEmpty): void {
            plpc_import_job_finalize_pdf_document(
                $job,
                0,
                $document,
                $pdf,
                null,
                'Undecodable nonblank page',
                null,
                null,
                0,
                ['startPage' => 1, 'endPage' => 1],
                [],
                'page',
                [
                    'blocks' => '',
                    'diagnostics' => [],
                    'imageTagCount' => 0,
                    'imagesImported' => 0,
                    'mediaDisposition' => [],
                    'format' => 'pdf',
                ],
                $allowEmpty
            );
        });
        $t->same([], $GLOBALS['plpc_test_posts'], 'The empty draft guard must run before WordPress creates a page row.');
    },

    'playground pdf page-tree keeps page-scoped tagged heading list and table semantics' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        add_filter('plpc_pdf_pages_per_request', static fn (mixed $pages): int => 2);
        add_filter('plpc_pdf_adaptive_pages_per_request', static fn (mixed $pages): int => 2);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'tagged-physical-pages.pdf',
            'title' => 'Tagged physical pages',
            'imageMode' => 'none',
            'pdfMode' => 'layout',
            'pdfOutputMode' => 'pages',
            'bytes' => base64_encode(plpc_test_tagged_page_tree_pdf()),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        for ($attempt = 0; $attempt < 40 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }

        $t->same('complete', $snapshot['status'] ?? null);
        $t->same('pdf-page-tree', $snapshot['result']['kind'] ?? null);
        $children = $snapshot['result']['children'] ?? [];
        $t->same(2, count($children));
        foreach ($children as $index => $child) {
            $page = $index + 1;
            $otherPage = $page === 1 ? 2 : 1;
            $postId = (int) ($child['postId'] ?? 0);
            $blocks = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');
            $t->same(1, substr_count($blocks, 'Tagged page ' . $page . ' heading'));
            $t->same(1, substr_count($blocks, 'Tagged page ' . $page . ' item'));
            $t->same(1, substr_count($blocks, 'Tagged page ' . $page . ' entry'));
            $t->true(!str_contains($blocks, 'Tagged page ' . $otherPage), 'A child page must not duplicate another page\'s tagged source objects.');
            $t->contains('data-pdf-role="H1"', $blocks);
            $t->contains('data-pdf-role="LI"', $blocks);
            $t->contains('data-pdf-role="TH"', $blocks);
            $t->contains('data-pdf-role="TD"', $blocks);
            $t->contains('<!-- wp:table -->', $blocks);
        }
    },

    'playground pdf page-tree child failure rolls public siblings back to drafts and resumes without duplicates' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $fixture = plpc_test_pdf_page_tree_publication_job();
        $job = $fixture['job'];
        [$firstChild, $secondChild] = $fixture['childIds'];
        $rootId = $fixture['rootId'];

        plpc_import_job_publish_next_result($job);
        $t->same('publish', get_post_status($firstChild));
        $t->same('draft', get_post_status($secondChild));
        $t->same('draft', get_post_status($rootId), 'The hierarchy root is the visibility commit point and must remain a draft.');

        $failedOnce = false;
        $GLOBALS['plpc_test_wp_update_failure_injector'] = static function (int $postId, array $update) use ($secondChild, &$failedOnce): bool {
            if (!$failedOnce && $postId === $secondChild && ($update['post_status'] ?? '') === 'publish') {
                $failedOnce = true;

                return true;
            }

            return false;
        };
        $error = null;
        try {
            plpc_import_job_publish_next_result($job);
        } catch (Throwable $throwable) {
            $error = $throwable;
        }

        $t->true($error instanceof PlpcImportFailure);
        $t->same('publishing_pdf_child', $error instanceof PlpcImportFailure ? $error->failureStage : null);
        $t->same('rolled_back', $job['publicationRecovery']['status'] ?? null);
        $t->same(0, $job['publishNextResult'] ?? null);
        $t->same('draft', get_post_status($firstChild), 'A prior child must not remain public after its sibling transition fails.');
        $t->same('draft', get_post_status($secondChild));
        $t->same('draft', get_post_status($rootId));
        $t->same(3, count($GLOBALS['plpc_test_posts']));

        unset($GLOBALS['plpc_test_wp_update_failure_injector']);
        plpc_import_job_fail($job, (string) $error?->getMessage(), [
            'code' => $error instanceof PlpcImportFailure ? $error->failureCode : 'publication_update_failed',
            'stage' => $error instanceof PlpcImportFailure ? $error->failureStage : 'publishing_pdf_child',
            'recoverable' => true,
        ]);
        plpc_import_job_resume_retryable_failure($job);
        plpc_import_job_publish_next_result($job);
        plpc_import_job_publish_next_result($job);
        $t->same('publish', get_post_status($firstChild));
        $t->same('publish', get_post_status($secondChild));
        $t->same('draft', get_post_status($rootId), 'Both children may be staged, but the hierarchy remains undiscoverable until root-last commit.');
        plpc_import_job_publish_next_result($job);

        $t->same('complete', $job['status'] ?? null);
        $t->same('publish', get_post_status($rootId));
        $t->same(3, count($GLOBALS['plpc_test_posts']), 'Recovery must reuse every verified draft rather than insert duplicates.');
    },

    'playground pdf page-tree root failure rolls the complete child set back before retry' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $fixture = plpc_test_pdf_page_tree_publication_job();
        $job = $fixture['job'];
        [$firstChild, $secondChild] = $fixture['childIds'];
        $rootId = $fixture['rootId'];
        plpc_import_job_publish_next_result($job);
        plpc_import_job_publish_next_result($job);
        $t->same('publish', get_post_status($firstChild));
        $t->same('publish', get_post_status($secondChild));
        $t->same('draft', get_post_status($rootId));

        $failedRoot = false;
        $failedRollback = false;
        $GLOBALS['plpc_test_wp_update_failure_injector'] = static function (int $postId, array $update) use (
            $rootId,
            $firstChild,
            &$failedRoot,
            &$failedRollback
        ): bool {
            if (!$failedRoot && $postId === $rootId && ($update['post_status'] ?? '') === 'publish') {
                $failedRoot = true;

                return true;
            }
            if (!$failedRollback && $postId === $firstChild && ($update['post_status'] ?? '') === 'draft') {
                $failedRollback = true;

                return true;
            }

            return false;
        };
        $error = null;
        try {
            plpc_import_job_publish_next_result($job);
        } catch (Throwable $throwable) {
            $error = $throwable;
        }

        $t->true($error instanceof PlpcImportFailure);
        $t->same('publishing_pdf_root', $error instanceof PlpcImportFailure ? $error->failureStage : null);
        $t->same('rollback_incomplete', $job['publicationRecovery']['status'] ?? null);
        $t->same(1, count($job['publicationRecovery']['failedPostIds'] ?? []));
        $t->same('publish', get_post_status($firstChild), 'A failed rollback must remain explicit rather than pretending the hierarchy is private.');
        $t->same('draft', get_post_status($secondChild));
        $t->same('draft', get_post_status($rootId));
        $t->same(0, $job['publishNextResult'] ?? null);
        $t->same(3, count($GLOBALS['plpc_test_posts']));

        unset($GLOBALS['plpc_test_wp_update_failure_injector']);
        plpc_import_job_fail($job, (string) $error?->getMessage(), [
            'code' => $error instanceof PlpcImportFailure ? $error->failureCode : 'publication_update_failed',
            'stage' => $error instanceof PlpcImportFailure ? $error->failureStage : 'publishing_pdf_root',
            'recoverable' => true,
        ]);
        plpc_import_job_resume_retryable_failure($job);
        $t->same('rolled_back', $job['publicationRecovery']['status'] ?? null);
        $t->same('draft', get_post_status($firstChild));
        $t->same('draft', get_post_status($secondChild));
        $t->same('draft', get_post_status($rootId), 'Resume must finish rollback before any new publication transition.');
        $t->same('ready_to_publish', $job['status'] ?? null);
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
    'playground publication failures retain a typed recoverable cursor and retry idempotently' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'publication-retry.md',
            'bytes' => base64_encode("# Publication retry\n\nDurable body.\n"),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_convert', $snapshot['status'] ?? null);
        $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_publish', $snapshot['status'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']));
        $postId = (int) array_key_first($GLOBALS['plpc_test_posts']);
        $t->same('draft', $GLOBALS['plpc_test_posts'][$postId]['post_status'] ?? null);

        $GLOBALS['plpc_test_wp_update_failures'] = 1;
        $failed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = $failed->get_data();
        $t->same(200, $failed->get_status(), 'A persisted recoverable failure is a successful state-machine response.');
        $t->same('retryable_failure', $snapshot['status'] ?? null);
        $t->same(false, $snapshot['ok'] ?? null);
        $t->same('publication_update_failed', $snapshot['failure']['code'] ?? null);
        $t->same(true, $snapshot['failure']['recoverable'] ?? null);
        $t->same(0, $snapshot['publication']['completed'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']), 'The publication retry must keep the verified draft rather than duplicating it.');

        $resumed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('ready_to_publish', $resumed['status'] ?? null);
        $completed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $t->same('complete', $completed['status'] ?? null);
        $t->same($postId, $completed['result']['postId'] ?? null);
        $t->same(1, count($GLOBALS['plpc_test_posts']));
        $t->same('publish', $GLOBALS['plpc_test_posts'][$postId]['post_status'] ?? null);
    },
    'playground interrupted PDF ranges contract durably before parser work is retried' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $pdf = plpc_test_multipage_pdf([
            'RANGE ONE', 'RANGE TWO', 'RANGE THREE', 'RANGE FOUR',
            'RANGE FIVE', 'RANGE SIX', 'RANGE SEVEN', 'RANGE EIGHT',
        ]);
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'contract.pdf',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = get_option($option);
        $job['status'] = 'converting';
        $job['stage'] = 'reading';
        $job['documents'][0]['pdfPagesPerRequest'] = 8;
        update_option($option, $job, false);

        $first = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $afterFirst = get_option($option);
        $t->same('ready_to_convert', $first['status'] ?? null);
        $t->same(4, $afterFirst['documents'][0]['pdfPagesPerRequest'] ?? null);
        $t->same(true, $afterFirst['checkpoint']['rangeContracted'] ?? null);
        $t->same(0, $first['metrics']['pdfPagesExtracted'] ?? null, 'The smaller range must be acknowledged before extraction restarts.');

        $afterFirst['status'] = 'converting';
        $afterFirst['stage'] = 'reading';
        update_option($option, $afterFirst, false);
        $second = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        $afterSecond = get_option($option);
        $t->same('ready_to_convert', $second['status'] ?? null);
        $t->same(2, $afterSecond['documents'][0]['pdfPagesPerRequest'] ?? null);
        $t->same(1, $afterSecond['checkpoint']['rangeStartPage'] ?? null);
        $t->true(in_array('range_contracted', array_column($second['events'] ?? [], 'stage'), true));
        $t->same(0, count($GLOBALS['plpc_test_posts']));
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
    'a terminated PHP worker releases the operating-system job lock and the next request resumes once' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'terminated-worker.md',
            'bytes' => base64_encode("# Terminated worker\n\nResume from the saved cursor.\n"),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = plpc_import_job_from_request(plpc_test_import_job_request([], $jobId));
        $job['status'] = 'converting';
        $job['stage'] = 'inspecting';
        $job['progress'] = ['completed' => 0, 'total' => 1, 'label' => 'A worker held this checkpoint.'];
        update_option($option, $job, false);
        $before = get_option($option);
        $lockPath = plpc_import_job_directory($job) . DIRECTORY_SEPARATOR . '.import.lock';
        $childCode = <<<'PHP'
$handle = fopen($argv[1], 'c');
if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
    exit(71);
}
fwrite(STDOUT, "LOCKED\n");
fflush(STDOUT);
sleep(30);
PHP;
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $childCode, '--', $lockPath],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3)
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the lock-holding PHP worker.');
        }

        try {
            $ready = is_resource($pipes[1]) ? fgets($pipes[1]) : false;
            $t->same("LOCKED\n", $ready, 'The child must own the exact per-job lock before contention is tested.');
            $contended = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
            $t->same(409, $contended->get_status());
            $t->same($before, get_option($option), 'A live worker lock must leave the durable revision unchanged.');
        } finally {
            @proc_terminate($process, 9);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    @fclose($pipe);
                }
            }
            @proc_close($process);
        }

        $resumed = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = $resumed->get_data();
        $t->same(200, $resumed->get_status());
        $t->same('ready_to_convert', $snapshot['status'] ?? null);
        $t->true(in_array('resuming', array_column($snapshot['events'] ?? [], 'stage'), true));
        $t->same(0, count($GLOBALS['plpc_test_posts']), 'Lock recovery must not duplicate or prematurely publish a page.');
        $t->true((int) (get_option($option)['stateRevision'] ?? 0) > (int) ($before['stateRevision'] ?? 0));
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
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
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
    'playground converter preserves a unique content-rich full-page Form as an infographic request' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'full-page-infographic.pdf',
            'title' => 'Full-page infographic',
            'imageMode' => 'all',
            'pdfMode' => 'layout',
            'bytes' => base64_encode(plpc_test_full_page_infographic_form_xobject_pdf()),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();

        $t->same(1, count($snapshot['renderRequests'] ?? []));
        $t->contains('Full-page PDF visual', (string) ($snapshot['renderRequests'][0]['label'] ?? ''));
        $t->same(1, $snapshot['metrics']['pdfPageSizedVisualFormsRendered'] ?? null);
        $t->same(0, $snapshot['metrics']['pdfPageSizedFormsSkipped'] ?? null);
    },
    'playground migrates legacy rendered Form byte totals once and persists the counter' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'legacy-render-counter.md',
            'bytes' => base64_encode('# Legacy render counter'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = get_option($option);
        $directory = plpc_import_job_directory($job);
        plpc_import_job_write_file($directory, 'rendered/legacy-a.png', str_repeat('a', 17));
        plpc_import_job_write_file($directory, 'rendered/legacy-b.png', str_repeat('b', 23));
        $job['renderedForms'] = [
            'legacy-a' => ['storage' => 'rendered/legacy-a.png'],
            'legacy-b' => ['storage' => 'rendered/legacy-b.png'],
        ];
        unset($job['renderedFormBytes']);

        $t->same(40, plpc_import_job_rendered_form_total_bytes($job));
        $t->same(40, $job['renderedFormBytes'] ?? null, 'The one-time legacy scan must populate the durable counter.');
        update_option($option, $job, false);
        $migrated = get_option($option);
        $t->same(40, $migrated['renderedFormBytes'] ?? null);

        $firstPath = plpc_import_job_storage_path($migrated, 'rendered/legacy-a.png');
        $secondPath = plpc_import_job_storage_path($migrated, 'rendered/legacy-b.png');
        unlink($firstPath);
        unlink($secondPath);
        $t->same(
            40,
            plpc_import_job_rendered_form_total_bytes($migrated),
            'A migrated job must use its O(1) counter instead of rescanning rendered files.'
        );
    },
    'playground renderer counts successful bytes once across duplicate error and budget paths' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0oAAAAABJRU5ErkJggg==', true);
        $t->true(is_string($png) && $png !== '');
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'render-counter.md',
            'bytes' => base64_encode('# Render counter'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $requestFor = static fn (string $id, string $visualId): array => [
            'id' => $id,
            'formId' => $visualId,
            'visualId' => $visualId,
            'visualKind' => 'form-xobject',
            'path' => 'render-counter.pdf',
            'page' => 1,
            'bbox' => [0.0, 0.0, 1.0, 1.0],
        ];
        $firstId = 'form-' . str_repeat('1', 40);
        $errorId = 'form-' . str_repeat('2', 40);
        $secondId = 'form-' . str_repeat('3', 40);
        $job = get_option($option);
        $job['status'] = 'awaiting_renderer';
        $job['stage'] = 'awaiting_renderer';
        $job['renderRequests'] = [
            $requestFor($firstId, 'visual-first'),
            $requestFor($errorId, 'visual-error'),
            $requestFor($secondId, 'visual-second'),
        ];
        update_option($option, $job, false);

        $imagePayload = static fn (string $requestId): array => [
            'requestId' => $requestId,
            'bytes' => base64_encode($png),
            'mimeType' => 'image/png',
            'width' => 1,
            'height' => 1,
        ];
        $first = plpc_submit_import_rendered_media(plpc_test_import_job_request($imagePayload($firstId), $jobId));
        $t->same(200, $first->get_status());
        $afterFirst = get_option($option);
        $t->same(strlen($png), $afterFirst['renderedFormBytes'] ?? null);
        $t->same(strlen($png), $afterFirst['renderedForms'][$firstId]['bytes'] ?? null);

        $duplicate = plpc_submit_import_rendered_media(plpc_test_import_job_request($imagePayload($firstId), $jobId));
        $t->true($duplicate->get_status() >= 400);
        $t->same(strlen($png), get_option($option)['renderedFormBytes'] ?? null, 'A duplicate acknowledgement must not count twice.');

        $failed = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $errorId,
            'error' => 'The browser could not render this optional figure.',
        ], $jobId));
        $t->same(200, $failed->get_status());
        $t->same(strlen($png), get_option($option)['renderedFormBytes'] ?? null, 'A renderer error has no stored media bytes.');

        $second = plpc_submit_import_rendered_media(plpc_test_import_job_request($imagePayload($secondId), $jobId));
        $t->same(200, $second->get_status());
        $afterSecond = get_option($option);
        $t->same(strlen($png) * 2, $afterSecond['renderedFormBytes'] ?? null, 'Two successful renders must accumulate exactly once each.');

        $budgetId = 'form-' . str_repeat('4', 40);
        $afterSecond['status'] = 'awaiting_renderer';
        $afterSecond['stage'] = 'awaiting_renderer';
        $afterSecond['renderRequests'] = [$requestFor($budgetId, 'visual-budget')];
        $afterSecond['renderedFormBytes'] = PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES;
        update_option($option, $afterSecond, false);
        $budgeted = plpc_submit_import_rendered_media(plpc_test_import_job_request($imagePayload($budgetId), $jobId));
        $t->same(200, $budgeted->get_status());
        $afterBudget = get_option($option);
        $t->same(PLPC_IMPORT_JOB_MAX_FORM_RENDER_BYTES, $afterBudget['renderedFormBytes'] ?? null, 'A rejected over-budget render must not increment the counter.');
        $t->contains('budget was reached', (string) ($afterBudget['renderedForms'][$budgetId]['error'] ?? ''));
    },
    'playground job durably binds an exact whole-page PNG response to its immutable request' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0oAAAAABJRU5ErkJggg==', true);
        $t->true(is_string($png) && $png !== '');
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'page-raster-state.md',
            'bytes' => base64_encode('# Page raster state'),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $option = PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId;
        $job = get_option($option);
        $sourceSha256 = hash('sha256', 'immutable-pdf-source');
        $job['documents'][0]['path'] = 'scan.pdf';
        $job['documents'][0]['sourceSha256'] = $sourceSha256;
        $request = [
            'version' => 1,
            'method' => 'pdfjs-whole-page-raster',
            'sourceSha256' => $sourceSha256,
            'page' => 2,
            'pageObject' => 17,
            'pageBox' => [0.0, 0.0, 1.0, 1.0],
            'pageBoxSource' => 'CropBox',
            'pageRotation' => 90,
            'width' => 1,
            'height' => 1,
            'mimeType' => 'image/png',
        ];
        $request['requestDigest'] = plpc_import_job_pdf_page_raster_request_digest($request);
        $request['id'] = 'pdf-page-raster-' . substr($request['requestDigest'], 0, 32);
        $ast = new \PortLibs\Pandoc\AstNode('document', [
            'meta' => ['pdfPageRasterRequests' => [$request]],
        ]);

        $t->same(1, plpc_import_job_queue_pdf_page_raster_requests($job, 0, $job['documents'][0], $ast, 2, 2));
        $snapshot = plpc_import_job_response($job)->get_data();
        $public = $snapshot['renderRequests'][0] ?? [];
        $t->same('pdfjs-whole-page-raster', $public['method'] ?? null);
        $t->same($request['id'], $public['id'] ?? null);
        $t->same($request['requestDigest'], $public['requestDigest'] ?? null);
        $t->same($request['pageBox'], $public['pageBox'] ?? null);
        $t->same(90, $public['pageRotation'] ?? null);
        $t->true(!array_key_exists('bbox', $public));

        $job['status'] = 'awaiting_renderer';
        $job['stage'] = 'awaiting_renderer';
        update_option($option, $job, false);
        $submitted = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $request['id'],
            'bytes' => base64_encode($png),
            'mimeType' => 'image/png',
            'width' => 1,
            'height' => 1,
        ], $jobId));
        $t->same(200, $submitted->get_status());
        $stored = get_option($option);
        $t->same([], $stored['renderRequests'] ?? null);
        $t->same(strlen($png), $stored['renderedPageRasterBytes'] ?? null);
        $t->same(hash('sha256', $png), $stored['renderedPageRasters'][$request['id']]['sha256'] ?? null);
        $responses = plpc_import_job_load_rendered_page_rasters($stored, 'scan.pdf', 2, 2);
        $t->same(1, count($responses));
        $response = $responses[0];
        $t->same($png, $response['contents'] ?? null);
        $t->same($request['requestDigest'], $response['requestDigest'] ?? null);
        $t->same(
            hash('sha256', implode("\n", [
                'pdf-page-raster-proof-v1',
                'requestDigest=' . $request['requestDigest'],
                'byteLength=' . strlen($png),
                'sha256=' . hash('sha256', $png),
            ])),
            $response['proofDigest'] ?? null
        );
        $t->same(
            0,
            plpc_import_job_queue_pdf_page_raster_requests($stored, 0, $stored['documents'][0], $ast, 2, 2),
            'A reload must not enqueue an acknowledged page a second time.'
        );

        $tamperedRequest = $request;
        $tamperedRequest['width'] = 2;
        $tamperedAst = new \PortLibs\Pandoc\AstNode('document', [
            'meta' => ['pdfPageRasterRequests' => [$tamperedRequest]],
        ]);
        $t->throws(
            RuntimeException::class,
            static fn (): int => plpc_import_job_queue_pdf_page_raster_requests(
                $stored,
                0,
                $stored['documents'][0],
                $tamperedAst,
                2,
                2
            ),
            'Changing dimensions without recomputing the immutable request digest must fail.'
        );
    },
    'playground converter resumes through an exact whole-page browser raster handoff' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $pdf = plpc_test_whole_page_raster_pdf();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'image-only-page.pdf',
            'title' => 'Image-only page',
            'imageMode' => 'important',
            'pdfMode' => 'layout',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        for ($attempt = 0; $attempt < 12 && ($snapshot['renderRequests'] ?? []) === []; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $request = $snapshot['renderRequests'][0] ?? [];
        $t->same('awaiting_renderer', $snapshot['status'] ?? null);
        $t->same('pdfjs-whole-page-raster', $request['method'] ?? null);
        $t->same(1, $request['page'] ?? null);
        $t->same(3, $request['pageObject'] ?? null);
        $t->same([0.0, 0.0, 1.0, 1.0], $request['pageBox'] ?? null);
        $t->same(2, $request['width'] ?? null);
        $t->same(2, $request['height'] ?? null);
        $png = plpc_test_rgb_png(2, 2);

        $submitted = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $request['id'] ?? '',
            'bytes' => base64_encode($png),
            'mimeType' => 'image/png',
            'width' => 2,
            'height' => 2,
        ], $jobId));
        $t->same(200, $submitted->get_status());
        $snapshot = $submitted->get_data();
        for ($attempt = 0; $attempt < 12 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }

        $t->same('complete', $snapshot['status'] ?? null, (string) ($snapshot['message'] ?? ''));
        $t->same(1, $snapshot['result']['imagesImported'] ?? null);
        $t->true(count($GLOBALS['plpc_test_uploads']) >= 1);
        $t->same($png, $GLOBALS['plpc_test_uploads'][0]['bits'] ?? null);
        $postId = max(0, (int) ($snapshot['result']['postId'] ?? 0));
        $blocks = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');
        $t->contains('data-pandoc-pdf-page-raster="browser-pdfjs"', $blocks);
        $t->same(1, substr_count($blocks, '<img'));

        $stored = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $record = $stored['renderedPageRasters'][$request['id']] ?? [];
        $t->same(hash('sha256', $png), $record['sha256'] ?? null);
        $t->true(!array_key_exists('contents', $record), 'Raw page bytes belong in private file storage, not the job option.');
    },
    'playground converter completes an unavailable whole-page raster with one exact original fallback' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $pdf = plpc_test_whole_page_raster_boundary_pdf();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'unavailable-page.pdf',
            'title' => 'Unavailable page fallback',
            'imageMode' => 'important',
            'pdfMode' => 'layout',
            'bytes' => base64_encode($pdf),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        for ($attempt = 0; $attempt < 16 && ($snapshot['renderRequests'] ?? []) === []; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $request = $snapshot['renderRequests'][0] ?? [];
        $requestId = (string) ($request['id'] ?? '');

        $t->same('awaiting_renderer', $snapshot['status'] ?? null, (string) ($snapshot['message'] ?? ''));
        $t->same(1, count($snapshot['renderRequests'] ?? []));
        $t->same('pdfjs-whole-page-raster', $request['method'] ?? null);
        $t->same('unavailable-page.pdf', $request['path'] ?? null);
        $t->same(hash('sha256', $pdf), $request['sourceSha256'] ?? null);
        $t->same(2, $request['page'] ?? null);
        $t->same(5, $request['pageObject'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $request['pageBox'] ?? null);
        $t->same('MediaBox', $request['pageBoxSource'] ?? null);
        $t->same(0, $request['pageRotation'] ?? null);
        $t->same(1224, $request['width'] ?? null);
        $t->same(1584, $request['height'] ?? null);
        $t->true(preg_match('/^[a-f0-9]{64}$/D', (string) ($request['requestDigest'] ?? '')) === 1);
        $t->same([], $GLOBALS['plpc_test_uploads']);
        $t->same([], $GLOBALS['plpc_test_attachments']);

        $reload = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $t->same(200, $reload->get_status());
        $reloaded = $reload->get_data();
        $t->same('awaiting_renderer', $reloaded['status'] ?? null);
        $t->same([$requestId], array_column($reloaded['renderRequests'] ?? [], 'id'));
        $t->same([], $GLOBALS['plpc_test_uploads'], 'Reloading while awaiting the browser must not materialize media.');

        $renderError = 'Synthetic browser page renderer unavailable.';
        $submitted = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $requestId,
            'error' => $renderError,
        ], $jobId));
        $t->same(200, $submitted->get_status());
        $snapshot = $submitted->get_data();
        $t->same('ready_to_convert', $snapshot['status'] ?? null);
        $t->same([], $snapshot['renderRequests'] ?? null);

        $stored = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $record = $stored['renderedPageRasters'][$requestId] ?? [];
        $t->same(1, count($stored['renderedPageRasters'] ?? []));
        $t->same($requestId, $record['requestId'] ?? null);
        $t->same('pdfjs-whole-page-raster', $record['method'] ?? null);
        $t->same('unavailable-page.pdf', $record['path'] ?? null);
        $t->same(2, $record['page'] ?? null);
        $t->same($request['requestDigest'] ?? null, $record['requestDigest'] ?? null);
        $t->same($renderError, $record['error'] ?? null);
        $t->same(0, $stored['renderedPageRasterBytes'] ?? null);
        foreach (['storage', 'byteLength', 'sha256', 'proofDigest', 'contents'] as $successOnlyField) {
            $t->true(
                !array_key_exists($successOnlyField, $record),
                'An unavailable acknowledgement must not acquire successful raster proof field ' . $successOnlyField . '.'
            );
        }

        $duplicate = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $requestId,
            'error' => $renderError,
        ], $jobId));
        $t->same(422, $duplicate->get_status());
        $stored = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $t->same(1, count($stored['renderedPageRasters'] ?? []));
        $t->same([], $GLOBALS['plpc_test_uploads']);

        $GLOBALS['plpc_imported_media_by_hash'] = [];
        for ($attempt = 0; $attempt < 24 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }

        $t->same('complete', $snapshot['status'] ?? null, (string) ($snapshot['message'] ?? ''));
        $t->same(1, count($GLOBALS['plpc_test_posts']));
        $t->same(1, count($GLOBALS['plpc_test_uploads']));
        $t->same(1, count($GLOBALS['plpc_test_attachments']));
        $t->same($pdf, $GLOBALS['plpc_test_uploads'][0]['bits'] ?? null);
        $t->same(
            'original-' . hash('sha256', $pdf) . '.pdf',
            $GLOBALS['plpc_test_uploads'][0]['filename'] ?? null
        );
        $t->same('application/pdf', $GLOBALS['plpc_test_attachments'][1]['post_mime_type'] ?? null);
        $t->same(
            'application/pdf:' . sha1($pdf),
            $GLOBALS['plpc_test_attachments'][1]['meta_input']['_plpc_content_key'] ?? null
        );

        $postId = max(0, (int) ($snapshot['result']['postId'] ?? 0));
        $blocks = (string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? '');
        $first = strpos($blocks, 'First physical boundary page');
        $fallback = strpos($blocks, 'PDF page 2 image is unavailable.');
        $third = strpos($blocks, 'Third physical boundary page');
        $t->true($first !== false && $fallback !== false && $third !== false);
        $t->true(
            $first < $fallback && $fallback < $third,
            'The visible page-numbered fallback must remain at physical page 2 rather than moving to a gallery tail.'
        );
        $t->same(1, substr_count($blocks, 'data-pandoc-pdf-page-raster-fallback="renderer-unavailable"'));
        $t->same(1, substr_count($blocks, 'data-pandoc-pdf-page-raster-fallback-download="true"'));
        $t->same(1, substr_count($blocks, 'data-pandoc-pdf-original-download="true"'));
        $t->same(1, substr_count($blocks, 'href="https://playground.test/uploads/attachment-1.png"'));
        $t->same(1, substr_count($blocks, 'data-pandoc-pdf-page-represented="false"'));
        $t->contains('data-pandoc-pdf-page-raster-fallback-request="' . $requestId . '"', $blocks);
        $t->true(!str_contains($blocks, '<img'), 'An unavailable renderer response must not become a raster image.');
        $t->true(!str_contains($blocks, 'data-pandoc-pdf-page-raster="browser-pdfjs"'));
        $t->true(!str_contains($blocks, 'data-pandoc-pdf-page-raster-proof'));
        $t->true(!str_contains($blocks, 'proofDigest'));
        $t->true(!str_contains($blocks, 'HIDDEN-BOUNDARY-OCR'));
        $diagnostics = $snapshot['result']['diagnostics'] ?? [];
        $t->true(in_array('extract-media-pdf-page-raster-fallback-validated:page-2', $diagnostics, true));
        $t->true(in_array('extract-media-pdf-page-raster-fallback-inserted:page-2', $diagnostics, true));
        $t->same(1, count(array_filter(
            $diagnostics,
            static fn (string $diagnostic): bool => $diagnostic
                === 'extract-media-pdf-page-raster-fallback-original-attached'
        )));

        $uploadCount = count($GLOBALS['plpc_test_uploads']);
        $attachmentCount = count($GLOBALS['plpc_test_attachments']);
        $postCount = count($GLOBALS['plpc_test_posts']);
        $GLOBALS['plpc_imported_media_by_hash'] = [];
        $firstRetry = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $secondRetry = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
        $t->same(200, $firstRetry->get_status());
        $t->same(200, $secondRetry->get_status());
        $t->same('complete', $secondRetry->get_data()['status'] ?? null);
        $t->same($uploadCount, count($GLOBALS['plpc_test_uploads']));
        $t->same($attachmentCount, count($GLOBALS['plpc_test_attachments']));
        $t->same($postCount, count($GLOBALS['plpc_test_posts']));
        $t->same($blocks, $GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? null);
        $stored = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $t->same([], $stored['renderRequests'] ?? null);
        $t->same(1, count($stored['renderedPageRasters'] ?? []));
        $t->same(1, substr_count((string) ($GLOBALS['plpc_test_posts'][$postId]['post_content'] ?? ''), 'data-pandoc-pdf-original-download="true"'));
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
        $t->true(
            is_string($renderRequest['visualId'] ?? null) && str_starts_with($renderRequest['visualId'], 'pdf-form-'),
            'A renderer request must retain the source occurrence id separately from its transport request id.'
        );
        $t->same('form-xobject', $renderRequest['visualKind'] ?? null);
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

        for ($attempt = 0; $attempt < 12 && !in_array($snapshot['status'] ?? '', ['complete', 'completed'], true); $attempt++) {
            $pendingPageRaster = array_values(array_filter(
                is_array($snapshot['renderRequests'] ?? null) ? $snapshot['renderRequests'] : [],
                static fn (mixed $request): bool => is_array($request)
                    && ($request['method'] ?? null) === 'pdfjs-whole-page-raster'
            ));
            if ($pendingPageRaster !== []) {
                // This fixture's Form remains the asserted subject. A later
                // whole-page request is a separate representation handoff;
                // acknowledge it as unavailable in this legacy Form test.
                $snapshot = plpc_submit_import_rendered_media(plpc_test_import_job_request([
                    'requestId' => $pendingPageRaster[0]['id'] ?? '',
                    'error' => 'Whole-page renderer intentionally unavailable in the Form protocol test.',
                ], $jobId))->get_data();
                continue;
            }
            $response = plpc_advance_import_job(plpc_test_import_job_request([], $jobId));
            $t->same(200, $response->get_status());
            $snapshot = $response->get_data();
            plpc_test_assert_import_job_snapshot($t, $snapshot, $jobId);
        }

        $t->true(in_array($snapshot['status'] ?? '', ['complete', 'completed'], true), 'The job should continue after the browser returns its PDF.js render.');
        $t->true(is_array($snapshot['result'] ?? null), 'The completed browser-rendered import needs a page result.');
        $t->true(($snapshot['result']['imagesImported'] ?? 0) >= 1, 'The returned browser render should become imported WordPress media.');
        $sourceOccurrences = array_column(
            $snapshot['result']['mediaDisposition']['sourceOccurrences'] ?? [],
            null,
            'id'
        );
        $t->same(
            'attachment',
            $sourceOccurrences[$renderRequest['visualId']]['disposition'] ?? null,
            'The browser crop and uploaded attachment must reconcile to the same source occurrence.'
        );
        $t->true(count($GLOBALS['plpc_test_uploads']) >= 1, 'The returned browser render should be written through the normal media importer.');
        $t->true(
            in_array($png, array_column($GLOBALS['plpc_test_uploads'], 'bits'), true),
            'The Form PNG must be imported even when a later whole-page failure also attaches the original PDF.'
        );
    },
    'playground converter records a browser renderer failure against its stable PDF occurrence' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $created = plpc_create_import_job(plpc_test_import_job_request([
            'filename' => 'failed-chart.pdf',
            'title' => 'Failed browser chart',
            'imageMode' => 'all',
            'pdfMode' => 'layout',
            'bytes' => base64_encode(plpc_test_full_page_infographic_form_xobject_pdf()),
        ]))->get_data();
        $jobId = (string) ($created['jobId'] ?? '');
        $snapshot = $created;
        for ($attempt = 0; $attempt < 3 && ($snapshot['renderRequests'] ?? []) === []; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $request = $snapshot['renderRequests'][0] ?? [];
        $visualId = (string) ($request['visualId'] ?? '');
        $t->true($visualId !== '');

        $submitted = plpc_submit_import_rendered_media(plpc_test_import_job_request([
            'requestId' => $request['id'] ?? '',
            'error' => 'PDF.js could not decode this object.',
        ], $jobId));
        $t->same(200, $submitted->get_status());
        $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId);
        $inventory = array_column($job['documents'][0]['pdfVisualOccurrences'] ?? [], null, 'id');
        $t->same('unresolved', $inventory[$visualId]['disposition'] ?? null, 'The durable job inventory must record the renderer failure.');
        $t->same('browser-render-failed', $inventory[$visualId]['reason'] ?? null, 'The job inventory needs the renderer failure reason.');
        $t->same($visualId, $job['renderedForms'][$request['id']]['visualId'] ?? null);

        $snapshot = $submitted->get_data();
        for ($attempt = 0; $attempt < 8 && ($snapshot['status'] ?? '') !== 'complete'; $attempt++) {
            $snapshot = plpc_advance_import_job(plpc_test_import_job_request([], $jobId))->get_data();
        }
        $t->same(
            'complete',
            $snapshot['status'] ?? null,
            'The text import must finish even when an optional browser crop fails: ' . (string) ($snapshot['message'] ?? '')
        );
        $sourceOccurrences = array_column(
            $snapshot['result']['mediaDisposition']['sourceOccurrences'] ?? [],
            null,
            'id'
        );
        $t->same('unresolved', $sourceOccurrences[$visualId]['disposition'] ?? null, 'The final media report must retain the failed source occurrence.');
        $t->same('browser-render-failed', $sourceOccurrences[$visualId]['reason'] ?? null, 'The final media report needs the renderer failure reason.');
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
    'playground import storage defaults outside public uploads and reports its protection' => static function (TestRunner $t): void {
        $previousSoftware = $_SERVER['SERVER_SOFTWARE'] ?? null;
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.27.0';
        try {
            plpc_test_reset_import_job_state();
            $response = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'private.md',
                'bytes' => base64_encode('# Private source'),
            ]));
            $snapshot = $response->get_data();
            $jobId = (string) ($snapshot['jobId'] ?? '');
            $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, []);
            $directory = plpc_import_job_directory(is_array($job) ? $job : $jobId);
            $uploads = plpc_test_import_job_upload_dir();
            $root = dirname($directory);

            $t->same(201, $response->get_status());
            $t->same('private', $snapshot['storage']['mode'] ?? null);
            $t->same(false, $snapshot['storage']['fallback'] ?? null);
            $t->same(true, $snapshot['storage']['outsidePublicUploads'] ?? null);
            $t->same('filesystem-private', $snapshot['storage']['accessProtection'] ?? null);
            $t->same('nginx', $snapshot['storage']['serverFamily'] ?? null);
            $t->true(!plpc_import_job_path_is_within($directory, $uploads), 'Source and render files should not default to the web-served uploads tree.');
            $rules = file_get_contents($root . '/.htaccess');
            $t->true(is_string($rules), 'Private import storage should still receive defense-in-depth Apache rules.');
            $t->contains('Require all denied', (string) $rules);
            $t->true(is_file($root . '/web.config'), 'Private import storage should include the IIS deny rule when permitted.');
        } finally {
            if ($previousSoftware === null) {
                unset($_SERVER['SERVER_SOFTWARE']);
            } else {
                $_SERVER['SERVER_SOFTWARE'] = $previousSoftware;
            }
        }
    },
    'playground import storage uses and accurately reports an Apache uploads fallback' => static function (TestRunner $t): void {
        $previousSoftware = $_SERVER['SERVER_SOFTWARE'] ?? null;
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.62 (Unix)';
        try {
            plpc_test_reset_import_job_state();
            add_filter(
                'plpc_import_job_private_storage_root',
                static fn (mixed $root): string => plpc_test_import_job_upload_dir() . '/unacceptable-private-root'
            );
            $response = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'fallback.md',
                'bytes' => base64_encode('# Apache fallback'),
            ]));
            $snapshot = $response->get_data();
            $jobId = (string) ($snapshot['jobId'] ?? '');
            $job = get_option(PLPC_IMPORT_JOB_OPTION_PREFIX . $jobId, []);
            $directory = plpc_import_job_directory(is_array($job) ? $job : $jobId);
            $root = plpc_test_import_job_upload_dir() . '/' . PLPC_IMPORT_JOB_DIRECTORY;

            $t->same(201, $response->get_status());
            $t->same('uploads-fallback', $snapshot['storage']['mode'] ?? null);
            $t->same(true, $snapshot['storage']['fallback'] ?? null);
            $t->same(false, $snapshot['storage']['outsidePublicUploads'] ?? null);
            $t->same('apache-htaccess-deny', $snapshot['storage']['accessProtection'] ?? null);
            $t->same('apache', $snapshot['storage']['serverFamily'] ?? null);
            $t->true(plpc_import_job_path_is_within($directory, plpc_test_import_job_upload_dir()));
            $t->contains('Apache .htaccess deny rule', implode(' ', array_column($snapshot['events'] ?? [], 'message')));
            $t->contains('Require all denied', (string) file_get_contents($root . '/.htaccess'));
        } finally {
            if ($previousSoftware === null) {
                unset($_SERVER['SERVER_SOFTWARE']);
            } else {
                $_SERVER['SERVER_SOFTWARE'] = $previousSoftware;
            }
        }
    },
    'playground import storage uses and accurately reports an IIS uploads fallback' => static function (TestRunner $t): void {
        $previousSoftware = $_SERVER['SERVER_SOFTWARE'] ?? null;
        $_SERVER['SERVER_SOFTWARE'] = 'Microsoft-IIS/10.0';
        try {
            plpc_test_reset_import_job_state();
            add_filter(
                'plpc_import_job_private_storage_root',
                static fn (mixed $root): string => plpc_test_import_job_upload_dir() . '/unacceptable-private-root'
            );
            $response = plpc_create_import_job(plpc_test_import_job_request([
                'filename' => 'fallback.md',
                'bytes' => base64_encode('# IIS fallback'),
            ]));
            $snapshot = $response->get_data();
            $root = plpc_test_import_job_upload_dir() . '/' . PLPC_IMPORT_JOB_DIRECTORY;

            $t->same(201, $response->get_status());
            $t->same('uploads-fallback', $snapshot['storage']['mode'] ?? null);
            $t->same('iis-web-config-deny', $snapshot['storage']['accessProtection'] ?? null);
            $t->same('iis', $snapshot['storage']['serverFamily'] ?? null);
            $t->contains('IIS web.config deny rule', implode(' ', array_column($snapshot['events'] ?? [], 'message')));
            $t->contains('<deny users="*" />', (string) file_get_contents($root . '/web.config'));
        } finally {
            if ($previousSoftware === null) {
                unset($_SERVER['SERVER_SOFTWARE']);
            } else {
                $_SERVER['SERVER_SOFTWARE'] = $previousSoftware;
            }
        }
    },
    'playground import storage refuses an uploads fallback on Nginx and unknown servers' => static function (TestRunner $t): void {
        $previousSoftware = $_SERVER['SERVER_SOFTWARE'] ?? null;
        try {
            foreach (['nginx/1.27.0' => 'Nginx', 'Caddy' => 'unknown web server'] as $software => $expectedLabel) {
                $_SERVER['SERVER_SOFTWARE'] = $software;
                plpc_test_reset_import_job_state();
                add_filter(
                    'plpc_import_job_private_storage_root',
                    static fn (mixed $root): string => plpc_test_import_job_upload_dir() . '/unacceptable-private-root'
                );
                $response = plpc_create_import_job(plpc_test_import_job_request([
                    'filename' => 'must-not-be-public.md',
                    'bytes' => base64_encode('# Never public'),
                ]));
                $snapshot = $response->get_data();
                $fallbackRoot = plpc_test_import_job_upload_dir() . '/' . PLPC_IMPORT_JOB_DIRECTORY;

                $t->same(400, $response->get_status());
                $t->contains($expectedLabel, (string) ($snapshot['message'] ?? ''));
                $t->contains('No source file was saved in public uploads', (string) ($snapshot['message'] ?? ''));
                $t->same([], get_option(PLPC_IMPORT_JOB_INDEX_OPTION, []));
                $t->same(false, is_dir($fallbackRoot), 'An unsupported web server must fail before fallback storage is created.');
            }
        } finally {
            if ($previousSoftware === null) {
                unset($_SERVER['SERVER_SOFTWARE']);
            } else {
                $_SERVER['SERVER_SOFTWARE'] = $previousSoftware;
            }
        }
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
    'playground pdf prose repair preserves uncertain source syntax beside a genuine geometry table' => static function (TestRunner $t): void {
        $pageOne = 'BT /F1 11 Tf '
            . '1 0 0 1 72 740 Tm (Model2024 {) Tj '
            . '1 0 0 1 84 724 Tm (A1 + B2 = C3;) Tj '
            . '1 0 0 1 72 708 Tm (}) Tj '
            . '1 0 0 1 72 676 Tm (Keep this exact terminal-token-) Tj '
            . '1 0 0 1 72 660 Tm (The ratio 1:2 remains source notation.) Tj ET';
        $pageTwo = 'BT /F1 11 Tf '
            . '1 0 0 1 72 720 Tm (Product) Tj 1 0 0 1 260 720 Tm (Qty) Tj 1 0 0 1 340 720 Tm (Total) Tj '
            . '1 0 0 1 72 704 Tm (Alpha) Tj 1 0 0 1 260 704 Tm (2) Tj 1 0 0 1 340 704 Tm ($6.00) Tj '
            . '1 0 0 1 72 688 Tm (Beta) Tj 1 0 0 1 260 688 Tm (4) Tj 1 0 0 1 340 688 Tm ($8.00) Tj '
            . '1 0 0 1 72 672 Tm (Gamma) Tj 1 0 0 1 260 672 Tm (6) Tj 1 0 0 1 340 672 Tm ($9.00) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R 4 0 R]/Count 2>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</Font<</F1 5 0 R>>>>/Contents 6 0 R>>endobj\n"
            . "4 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</Font<</F1 5 0 R>>>>/Contents 7 0 R>>endobj\n"
            . "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica/Encoding/WinAnsiEncoding>>endobj\n"
            . '6 0 obj<</Length ' . strlen($pageOne) . ">>stream\n{$pageOne}\nendstream\nendobj\n"
            . '7 0 obj<</Length ' . strlen($pageTwo) . ">>stream\n{$pageTwo}\nendstream\nendobj\n"
            . "trailer<</Root 1 0 R>>\n%%EOF";

        $options = plpc_converter_options('pdf');
        $document = \PortLibs\Pandoc\PandocConverter::read($pdf, 'pdf', $options['readerOptions']);
        $plain = \PortLibs\Pandoc\PandocConverter::write($document, 'plain');
        $blocks = \PortLibs\Pandoc\PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfDetectedTables'] ?? null);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('Model2024 {', $plain);
        $t->true(!str_contains($plain, 'Model 2024'), 'A letter-digit source identifier needs local boundary evidence before it can be changed.');
        $t->contains('A1 + B2 = C3;', $plain);
        $t->contains("\n}\n", "\n{$plain}\n", 'A standalone source brace must not be discarded as punctuation noise.');
        $t->contains('Keep this exact terminal-token-', $plain);
        $t->contains('The ratio 1:2 remains source notation.', $plain);
        $t->same(0, $meta['pdfTextFidelity']['unresolvedSignificantCharacterCount'] ?? null);
        $t->same(0, $meta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
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

        $boundedPaths = plpc_pdf_raster_images_by_path([
            'one.pdf' => [[
                'object' => '1', 'bytes' => base64_encode('123456'),
                'mimeType' => 'image/png', 'width' => 1, 'height' => 1,
            ]],
            'two.pdf' => [[
                'object' => '2', 'bytes' => base64_encode('abcdef'),
                'mimeType' => 'image/png', 'width' => 1, 'height' => 1,
            ]],
        ], 10);
        $t->same(['one.pdf'], array_keys($boundedPaths), 'The decoded raster budget must apply to the whole collection, not once per PDF path.');
        $t->same([], plpc_pdf_raster_images_from_payload([[
            'object' => '3', 'bytes' => base64_encode('too-large'),
            'mimeType' => 'image/png', 'width' => 1, 'height' => 1,
        ]], 4));
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
        $t->true(in_array('extract-media-pdf-image-raster-loaded:17:important', $result['diagnostics'] ?? [], true));
        $t->same(1, count($GLOBALS['plpc_test_attachments']));
        $t->contains('https://playground.test/uploads/attachment-1.png', $GLOBALS['plpc_test_posts'][1]['post_content'] ?? '');
    },
    'playground media disposition accounts for repeated occurrences and replaces unresolved images' => static function (TestRunner $t): void {
        plpc_test_reset_import_job_state();
        $blocks = '<!-- wp:image --><figure><img src="media/chart.png" alt="First chart"></figure><!-- /wp:image -->'
            . '<!-- wp:image --><figure><img src="media/chart.png" alt="Repeated chart"></figure><!-- /wp:image -->';
        $collection = [
            'label' => 'Media collection',
            'files' => [['path' => 'media/chart.png', 'bytes' => 'same chart bytes']],
        ];
        $imported = plpc_import_rendered_images($blocks, ['media/chart.png'], '', 'document.md', $collection, 'document.md');
        $importedSummary = plpc_import_media_disposition_summary($blocks, $imported['blocks'], $imported['diagnostics']);

        $t->same(2, $imported['imported'] ?? null, 'One deduplicated attachment satisfies two placed occurrences.');
        $t->same(2, $importedSummary['totalOccurrences'] ?? null);
        $t->same(2, $importedSummary['attachmentOccurrences'] ?? null);
        $t->same(0, $importedSummary['unresolvedOccurrences'] ?? null);
        $t->same(2, count(plpc_rendered_media_occurrences($imported['blocks'])));
        plpc_import_assert_media_disposition($imported['blocks'], $importedSummary);

        $missing = plpc_import_rendered_images($blocks, ['media/chart.png'], '', 'document.md');
        $missingSummary = plpc_import_media_disposition_summary($blocks, $missing['blocks'], $missing['diagnostics']);
        $t->same(0, $missing['imported'] ?? null);
        $t->same(2, $missingSummary['placeholderOccurrences'] ?? null);
        $t->same(0, $missingSummary['unresolvedOccurrences'] ?? null);
        $t->true(!str_contains($missing['blocks'], '<img'), 'A successful conversion must not retain a broken local image URL.');
        $t->same(2, substr_count($missing['blocks'], 'pandoc-import-image-placeholder'));
        $t->same(2, substr_count($missing['blocks'], '<!-- wp:html -->'), 'Image placeholders must not leave invalid core/image blocks behind.');
        plpc_import_assert_media_disposition($missing['blocks'], $missingSummary);
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
        $t->true(in_array('extract-media-pdf-image-placeholder:17:jpeg2000-raster-unavailable', $result['diagnostics'] ?? [], true));
        $t->true(in_array('image-imported:media/pdf/image-17.jp2=>1', $result['diagnostics'] ?? [], true));
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
        $t->contains(
            'OCR is outside this importer; every page must remain represented by an original asset or visible placeholder.',
            implode("\n", $quality['warnings'])
        );
    },
    'playground importer blocks pdf publication when required source occurrences remain unresolved' => static function (TestRunner $t): void {
        $failureFor = static function (object $document, ?array $expectedRange = null): ?Throwable {
            try {
                plpc_import_assert_pdf_semantic_source_complete($document, 'pdf', $expectedRange);
            } catch (Throwable $throwable) {
                return $throwable;
            }

            return null;
        };
        $incomplete = new PortLibs\Pandoc\AstNode('document', [
            'meta' => [
                'pdfSemanticTextComplete' => false,
                'pdfDocumentComplete' => true,
                'pdfSourceDisposition' => [
                    'unresolvedOccurrenceCount' => 2,
                    'unclaimedEmittedTokenCount' => 1,
                ],
            ],
        ]);
        $error = $failureFor($incomplete);

        $t->true($error instanceof PlpcImportFailure);
        $t->same('pdf_semantic_source_incomplete', $error instanceof PlpcImportFailure ? $error->failureCode : null);
        $t->same('verifying_pdf_source_disposition', $error instanceof PlpcImportFailure ? $error->failureStage : null);
        $t->same(false, $error instanceof PlpcImportFailure ? $error->recoverable : null);
        $t->contains('unresolved occurrences: 2', $error instanceof Throwable ? $error->getMessage() : '');

        $diagnostics = plpc_document_diagnostics($incomplete, 'pdf');
        $quality = plpc_import_quality_report('pdf', $diagnostics);
        $t->same(['pdf-semantic-source-incomplete'], $diagnostics);
        $t->same('source_incomplete', $quality['status'] ?? null);
        $t->contains('cannot be published', implode("\n", $quality['warnings'] ?? []));

        $complete = new PortLibs\Pandoc\AstNode('document', [
            'meta' => [
                'pdfSemanticTextComplete' => true,
                'pdfDocumentComplete' => true,
            ],
        ]);
        plpc_import_assert_pdf_semantic_source_complete($complete, 'pdf');
        $t->same([], plpc_document_diagnostics($complete, 'pdf'));

        $partialDirect = new PortLibs\Pandoc\AstNode('document', [
            'meta' => [
                'pdfSemanticTextComplete' => true,
                'pdfDocumentComplete' => false,
                'pdfRangeComplete' => true,
                'pdfProcessedPageNumbers' => [2],
                'pdfPageStart' => 2,
                'pdfPageEnd' => 2,
                'pdfPageCount' => 3,
                'pdfPagesProcessed' => 1,
                'pdfHasMorePages' => true,
                'pdfNextPage' => 3,
            ],
        ]);
        $t->true($failureFor($partialDirect) instanceof PlpcImportFailure);

        $expectedSecondPage = [
            'totalPages' => 3,
            'startPage' => 2,
            'endPage' => 2,
            'pageNumbers' => [2],
            'hasMorePages' => true,
            'nextPage' => 3,
        ];
        plpc_import_assert_pdf_semantic_source_complete($partialDirect, 'pdf', $expectedSecondPage);
        $t->true($failureFor($partialDirect, ['pageNumbers' => [2, 1], 'totalPages' => 3]) instanceof PlpcImportFailure);
        $wrongTotalRange = array_replace($expectedSecondPage, ['totalPages' => 4, 'nextPage' => 3]);
        $t->true($failureFor($partialDirect, $wrongTotalRange) instanceof PlpcImportFailure);
        $wrongEndpointRange = array_replace($expectedSecondPage, ['startPage' => 1]);
        $t->true($failureFor($partialDirect, $wrongEndpointRange) instanceof PlpcImportFailure);

        $wrongProcessedPage = new PortLibs\Pandoc\AstNode('document', [
            'meta' => array_replace(
                $partialDirect->attr('meta', []),
                ['pdfProcessedPageNumbers' => [3]]
            ),
        ]);
        $t->true($failureFor($wrongProcessedPage, $expectedSecondPage) instanceof PlpcImportFailure);

        $wholeRangeWithoutDocumentProof = new PortLibs\Pandoc\AstNode('document', [
            'meta' => [
                'pdfSemanticTextComplete' => true,
                'pdfDocumentComplete' => false,
                'pdfRangeComplete' => true,
                'pdfProcessedPageNumbers' => [1, 2, 3],
                'pdfPageStart' => 1,
                'pdfPageEnd' => 3,
                'pdfPageCount' => 3,
                'pdfPagesProcessed' => 3,
                'pdfHasMorePages' => false,
                'pdfNextPage' => null,
            ],
        ]);
        $t->true($failureFor(
            $wholeRangeWithoutDocumentProof,
            [
                'totalPages' => 3,
                'startPage' => 1,
                'endPage' => 3,
                'pageNumbers' => [1, 2, 3],
                'hasMorePages' => false,
                'nextPage' => null,
            ]
        ) instanceof PlpcImportFailure);
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
    'playground importer uploads and rewrites only marked page-raster original PDF links' => static function (TestRunner $t): void {
        $blocks = <<<'HTML'
<!-- wp:html -->
<p class="pandoc-pdf-page-raster-fallback">Page 2 could not be rendered. <a href="media/pdf/original-document.pdf" data-pandoc-pdf-page-raster-fallback-download="true" data-pandoc-pdf-original-download="true">Download the original PDF.</a></p>
<p><a href="ordinary-reference.pdf">Ordinary reference</a></p>
<!-- /wp:html -->
HTML;

        $t->same(['media/pdf/original-document.pdf'], plpc_rendered_image_sources($blocks));
        $occurrences = plpc_rendered_media_occurrences($blocks);
        $t->same(1, count($occurrences));
        $t->same('original-pdf-download', $occurrences[0]['kind'] ?? null);
        $t->same('media/pdf/original-document.pdf', $occurrences[0]['source'] ?? null);

        $parsed = parse_blocks($blocks);
        $changed = false;
        plpc_replace_image_source_in_blocks(
            $parsed,
            'media/pdf/original-document.pdf',
            'https://playground.test/uploads/original-document.pdf',
            73,
            $changed
        );
        $rewritten = serialize_blocks($parsed);
        $t->true($changed);
        $t->contains('href="https://playground.test/uploads/original-document.pdf"', $rewritten);
        $t->contains('data-pandoc-pdf-original-download="true"', $rewritten);
        $t->contains('href="ordinary-reference.pdf"', $rewritten, 'Ordinary PDF links must remain untouched.');

        $missing = false;
        $unresolved = plpc_replace_unresolved_image_source_in_html(
            '<p><a href="media/pdf/original-document.pdf" data-pandoc-pdf-original-download="true">Original</a></p>',
            'media/pdf/original-document.pdf',
            $missing
        );
        $t->true($missing);
        $t->contains('Original PDF could not be stored.', $unresolved);
        $t->true(!str_contains($unresolved, '<a'));
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
        $t->contains('<img src="https://playground.test/uploads/photo.png?x=1&amp;y=2" alt="First" class="wp-image-42" data-plpc-imported-media="42">', $blocks[0]['innerContent'][0]);
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
