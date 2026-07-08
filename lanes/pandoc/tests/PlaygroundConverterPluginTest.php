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
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hookName, callable $callback): void
    {
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hookName, callable $callback): void
    {
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

if (!function_exists('wp_insert_post')) {
    function wp_insert_post(array $post, bool $wpError = false): int
    {
        $GLOBALS['plpc_test_posts'] ??= [];
        $id = count($GLOBALS['plpc_test_posts']) + 1;
        $GLOBALS['plpc_test_posts'][$id] = $post;

        return $id;
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

require_once dirname(__DIR__, 3) . '/tools/playground-converter-plugin/port-libs-playground-converter.php';

return [
    'playground pdf importer keeps geometry table reconstruction enabled with prose repair' => static function (TestRunner $t): void {
        $options = plpc_converter_options('pdf');

        $t->same(80000, $options['readerOptions']['maxTextBytes'] ?? null);
        $t->same(true, $options['readerOptions']['pdfGeometryTables'] ?? null);
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
    'playground importer marks imported wp image blocks with attachment metadata' => static function (TestRunner $t): void {
        $blocks = <<<'HTML'
<!-- wp:image -->
<figure class="wp-block-image"><img src="media/photo &amp; one.png" alt="First"/></figure>
<!-- /wp:image -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="media/other.png" alt="Other"/></figure>
<!-- /wp:image -->

<!-- wp:html -->
<div><img src="media/photo &amp; one.png" alt="Raw"></div>
<!-- /wp:html -->
HTML;

        $rewritten = plpc_replace_image_source(
            $blocks,
            'media/photo & one.png',
            'https://playground.test/uploads/photo.png?x=1&y=2',
            42
        );

        $t->contains('<!-- wp:image {"id":42} -->', $rewritten);
        $t->contains('<img src="https://playground.test/uploads/photo.png?x=1&amp;y=2" alt="First" class="wp-image-42"/>', $rewritten);
        $t->contains('<!-- wp:image -->' . "\n" . '<figure class="wp-block-image"><img src="media/other.png" alt="Other"/></figure>', $rewritten);
        $t->contains('<div><img src="https://playground.test/uploads/photo.png?x=1&amp;y=2" alt="Raw"></div>', $rewritten);
        $t->true(!str_contains($rewritten, 'alt="Raw" class="wp-image-42"'), 'Raw HTML images should not be marked as native wp:image attachments.');
    },
    'playground importer preserves existing image block attrs and classes when marking attachments' => static function (TestRunner $t): void {
        $blocks = '<!-- wp:image {"sizeSlug":"large","id":9} -->'
            . "\n" . '<figure class="wp-block-image"><img src="media/logo.png" alt="Logo" class="alignnone"/></figure>'
            . "\n" . '<!-- /wp:image -->';

        $rewritten = plpc_replace_image_source($blocks, 'media/logo.png', 'https://playground.test/uploads/logo.png', 77);

        $t->contains('<!-- wp:image {"id":77,"sizeSlug":"large"} -->', $rewritten);
        $t->contains('class="alignnone wp-image-77"', $rewritten);
        $t->true(!str_contains($rewritten, 'wp-image-77 wp-image-77'), 'Attachment class should not be duplicated.');
    },
    'playground importer turns actionable diagnostics into page warning blocks' => static function (TestRunner $t): void {
        $blocks = '<!-- wp:paragraph -->' . "\n" . '<p>Imported body.</p>' . "\n" . '<!-- /wp:paragraph -->';
        $rewritten = plpc_prepend_conversion_warning_blocks($blocks, 'markdown', [
            'extract-media-image-mode:important',
            'image-imported:media/photo.png=>42',
            'extract-media-package-loaded:media-photo.png',
            'image-not-resolved:media/missing.png',
            'extract-media-data-uri-invalid',
        ]);

        $t->contains('<!-- wp:group {"className":"port-libs-conversion-notice"} -->', $rewritten);
        $t->contains('<h2 class="wp-block-heading">Conversion notes</h2>', $rewritten);
        $t->contains('An image reference could not be found in the uploaded file or folder: media/missing.png', $rewritten);
        $t->contains('One embedded data URI image was invalid and was not imported.', $rewritten);
        $t->true(!str_contains($rewritten, 'image-imported'), 'Routine image import diagnostics should not be shown as warnings.');
        $t->true(strpos($rewritten, 'Conversion notes') < strpos($rewritten, 'Imported body.'), 'Warnings should be prepended to the imported page.');
    },
    'playground importer warns that pdf imports are best effort' => static function (TestRunner $t): void {
        $messages = plpc_conversion_warning_messages('pdf', ['extract-media-image-mode:important']);

        $t->same([
            'PDF layout was reconstructed from page geometry. Reading order, columns, tables, and image placement may need review.',
        ], $messages);
    },
    'playground collection index includes conversion failures as visible warnings' => static function (TestRunner $t): void {
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

        $t->contains('One document in the upload could not be converted: Corrupt package', $blocks);
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
