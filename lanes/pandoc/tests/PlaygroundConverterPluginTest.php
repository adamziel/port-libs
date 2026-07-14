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

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(private string $body)
        {
        }

        public function get_body(): string
        {
            return $this->body;
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
        $id = count($GLOBALS['plpc_test_posts']) + 1;
        $GLOBALS['plpc_test_posts'][$id] = $post;

        return $id;
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

require_once dirname(__DIR__, 3) . '/tools/playground-converter-plugin/port-libs-playground-converter.php';

return [
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
    'playground pdf importer keeps geometry table reconstruction enabled with prose repair by default' => static function (TestRunner $t): void {
        $options = plpc_converter_options('pdf');

        $t->same(80000, $options['readerOptions']['maxTextBytes'] ?? null);
        $t->same(true, $options['readerOptions']['pdfGeometryTables'] ?? null);
        $t->same(true, $options['readerOptions']['pdfRepairProseText'] ?? null);
        $t->same(true, $options['readerOptions']['pdfCollectImagePlacements'] ?? null);
    },
    'playground pdf importer can retry in text only mode without geometry tables' => static function (TestRunner $t): void {
        $options = plpc_converter_options('pdf', 'text-only');

        $t->same(80000, $options['readerOptions']['maxTextBytes'] ?? null);
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
    'playground importer normalizes pdf retry modes' => static function (TestRunner $t): void {
        $t->same('layout', plpc_normalize_pdf_mode(''));
        $t->same('layout', plpc_normalize_pdf_mode('layout-aware'));
        $t->same('layout', plpc_normalize_pdf_mode('geometry'));
        $t->same('text', plpc_normalize_pdf_mode('text'));
        $t->same('text', plpc_normalize_pdf_mode('text_only'));
        $t->same('text', plpc_normalize_pdf_mode('without layout'));
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
    'playground importer renders plain language import quality blocks' => static function (TestRunner $t): void {
        $blocks = plpc_prepend_import_quality_blocks(
            '<!-- wp:paragraph -->' . "\n" . '<p>Imported body.</p>' . "\n" . '<!-- /wp:paragraph -->',
            [
                'status' => 'media_missing',
                'flags' => ['media_missing', 'layout_uncertain'],
                'warnings' => [],
            ]
        );

        $t->contains('<!-- wp:group {"className":"port-libs-import-quality port-libs-import-quality-media_missing"} -->', $blocks);
        $t->contains('<strong>Import quality:</strong> The content imported, but some images or media files are missing.', $blocks);
        $t->contains('Try importing again with all images', $blocks);
        $t->true(strpos($blocks, 'Import quality:') < strpos($blocks, 'Imported body.'), 'Import quality should appear before imported body content.');
    },
    'playground importer prepends notices through parsed block data when available' => static function (TestRunner $t): void {
        $body = '<!-- wp:paragraph {"align":"center"} -->'
            . '<p class="has-text-align-center">Imported body.</p>'
            . '<!-- /wp:paragraph -->';
        $withQuality = plpc_prepend_import_quality_blocks($body, [
            'status' => 'layout_uncertain',
            'flags' => ['layout_uncertain'],
            'warnings' => [],
        ]);
        $withWarnings = plpc_prepend_conversion_warning_blocks($withQuality, 'markdown', [
            'image-not-resolved:media/missing.png',
        ]);
        $parsed = parse_blocks($withWarnings);

        $t->same('core/group', $parsed[0]['blockName'] ?? null);
        $t->same('port-libs-conversion-notice', $parsed[0]['attrs']['className'] ?? null);
        $t->same('core/group', $parsed[1]['blockName'] ?? null);
        $t->same('port-libs-import-quality port-libs-import-quality-layout_uncertain', $parsed[1]['attrs']['className'] ?? null);
        $t->same('core/paragraph', $parsed[2]['blockName'] ?? null);
        $t->same(['align' => 'center'], $parsed[2]['attrs'] ?? null);
        $t->contains('Imported body.', $parsed[2]['innerHTML'] ?? '');
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

        $blocks = plpc_prepend_import_quality_blocks(
            '<!-- wp:paragraph -->' . "\n" . '<p>Imported body.</p>' . "\n" . '<!-- /wp:paragraph -->',
            $quality
        );

        $t->contains('port-libs-import-quality-ocr_needed', $blocks);
        $t->contains('<strong>Import quality:</strong> This PDF likely needs OCR before import.', $blocks);
        $t->contains('Run OCR first, then import the searchable PDF.', $blocks);
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
