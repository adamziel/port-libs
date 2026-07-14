<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;

function pandoc_positioned_pdf_image_fixture(): string
{
    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2w==', true);
    if (!is_string($jpeg)) {
        throw new RuntimeException('Unable to build JPEG fixture.');
    }
    $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
        . "q 120 0 0 30 72 660 cm /Second Do Q\n"
        . "BT /F1 12 Tf 72 620 Td (Middle) Tj ET\n"
        . "q 120 0 0 30 72 580 cm /First Do Q\n"
        . "BT /F1 12 Tf 72 540 Td (After) Tj ET\n"
        . "q 8 0 0 8 72 500 cm /Mask Do Q";

    $image = static fn (int $width, int $height, bool $mask = false): string =>
        '<< /Type /XObject /Subtype /Image /Width ' . $width . ' /Height ' . $height
        . ($mask ? ' /ImageMask true /BitsPerComponent 1' : ' /ColorSpace /DeviceRGB /BitsPerComponent 8')
        . ' /Filter /DCTDecode /Length ' . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 9 0 R >> /XObject << /First 7 0 R /Second 8 0 R /Unused 10 0 R /Mask 11 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n"
        . "7 0 obj\n" . $image(120, 30) . "\nendobj\n"
        . "8 0 obj\n" . $image(120, 30) . "\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n" . $image(200, 100) . "\nendobj\n"
        . "11 0 obj\n" . $image(8, 8, true) . "\nendobj\n%%EOF\n";
}

function pandoc_layout_fixture_image_object(int $width = 100, int $height = 100): string
{
    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2w==', true);
    if (!is_string($jpeg)) {
        throw new RuntimeException('Unable to build JPEG fixture.');
    }

    return '<< /Type /XObject /Subtype /Image /Width ' . $width . ' /Height ' . $height
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpeg) . " >>\nstream\n"
        . $jpeg . "\nendstream";
}

function pandoc_avif_raster_fixture(int $width, int $height): string
{
    // A minimal bounded AVIF container for media-path verification. The
    // extractor only needs the ftyp brand and ispe dimensions; browser
    // decoding remains the final validation boundary for real raster bytes.
    return pack('N', 20) . 'ftyp' . 'avif' . pack('N', 0) . 'avif'
        . pack('N', 20) . 'ispe' . pack('N', 0) . pack('N', $width) . pack('N', $height);
}

function pandoc_png_raster_fixture(int $width, int $height): string
{
    return "\x89PNG\r\n\x1a\n"
        . pack('N', 13) . 'IHDR' . pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)
        . pack('N', 0);
}

/**
 * @param array<int, string> $extraObjects
 */
function pandoc_single_page_layout_fixture(string $content, string $xObjects, array $extraObjects, string $extraResources = ''): string
{
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> /XObject << {$xObjects} >> {$extraResources} >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    foreach ($extraObjects as $number => $body) {
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF\n";
}

return [
    'converts markdown to wordpress block markup through registered reader and local block writer alias' => static function (TestRunner $t): void {
        $blocks = PandocConverter::convert(
            "# Converter Demo\n\nA **bold** [link](https://example.test/).",
            'md',
            'blocks'
        );

        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<a href="https://example.test/">link</a>', $blocks);
    },
    'round trips markdown through pandoc json and native formats' => static function (TestRunner $t): void {
        $markdown = "# Round Trip\n\n- One\n- Two\n";

        $json = PandocConverter::convert($markdown, 'markdown', 'json');
        $jsonDocument = PandocConverter::read($json, 'json');
        $jsonBlocks = PandocConverter::write($jsonDocument, 'wp');

        $t->contains('"pandoc-api-version"', $json);
        $t->same('heading', $jsonDocument->children[0]->type);
        $t->same('Round Trip', $jsonDocument->children[0]->attr('text'));
        $t->contains('<!-- wp:list -->', $jsonBlocks);

        $native = PandocConverter::write($jsonDocument, 'native', ['standalone' => true]);
        $nativeDocument = PandocConverter::read($native, 'native');
        $nativeBlocks = PandocConverter::write($nativeDocument, 'wordpress');

        $t->contains("Pandoc\n", $native);
        $t->same('heading', $nativeDocument->children[0]->type);
        $t->same('Round Trip', $nativeDocument->children[0]->attr('text'));
        $t->contains('<!-- wp:list -->', $nativeBlocks);
    },
    'uses registry aliases for supported output formats' => static function (TestRunner $t): void {
        $html = PandocConverter::convert('# Alias Demo', 'markdown', 'html5');

        $t->same('markdown', PandocConverter::canonicalInputFormat('md'));
        $t->same('html', PandocConverter::canonicalOutputFormat('html5'));
        $t->contains('<h1', $html);
        $t->contains('Alias Demo', $html);
    },
    'reads html through the dedicated registered reader path' => static function (TestRunner $t): void {
        $document = PandocConverter::read('<html lang="en"><head><title>HTML Dispatch</title></head><body><main><h1>HTML Dispatch</h1><p>Ready.</p></main></body></html>', 'html');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('HTML Dispatch', $meta['title']);
        $t->same('html', $meta['sourceFormat']);
        $t->same(\PortLibs\Pandoc\PandocHtmlTagSoupReader::class, $meta['reader']);
        $t->same('tagsoup-pandoc-html-reader-port', $meta['readerScope']);
        $t->same(\PortLibs\Pandoc\TagSoupParser::class, $meta['htmlTokenizer']);
        $t->same(null, $meta['htmlReaderDelegate'] ?? null);
        $t->same(null, $meta['htmlNativeDivs'] ?? null);
        $t->contains('<h1 id="html-dispatch">HTML Dispatch</h1>', $blocks);
        $t->contains('<p>Ready.</p>', $blocks);
    },
    'passes canonical markdown family formats through converter reader dispatch' => static function (TestRunner $t): void {
        $source = "---\ntitle: Converter Probe\n---\n\n# Head\n";
        $markdown = PandocConverter::read($source, 'markdown');
        $commonmark = PandocConverter::read($source, 'commonmark');
        $strict = PandocConverter::read($source, 'markdown-strict');
        $mmd = PandocConverter::read($source, 'markdown-mmd');

        $t->same('Converter Probe', $markdown->attr('meta')['title'] ?? null);
        $t->same(['heading'], array_map(static fn (AstNode $node): string => $node->type, $markdown->children));
        $t->same(null, $commonmark->attr('meta'));
        $t->same(['horizontal_rule', 'heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $commonmark->children));
        $t->same(null, $strict->attr('meta'));
        $t->same(['horizontal_rule', 'heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $strict->children));
        $t->same(null, $mmd->attr('meta'));
        $t->same(['horizontal_rule', 'heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $mmd->children));
    },
    'preserves GitHub Markdown list semantics without losing modern GFM extensions through converter dispatch' => static function (TestRunner $t): void {
        $document = PandocConverter::read("- first\n\n* second\n+ third\n- fourth\n", 'markdown_github');
        $list = $document->children[0] ?? new AstNode('missing');
        $html = PandocConverter::convert(
            "https://github.com/openai\n\n\$E = mc^2\$\n",
            'markdown_github',
            'html',
            ['writerOptions' => ['writerHTMLMathMethod' => 'mathml']]
        );

        $t->same(['bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same(false, (bool) $list->attr('loose'));
        $t->same(4, count($list->children));
        $t->contains('<a href="https://github.com/openai"', $html);
        $t->contains('<math', $html);
    },
    'dispatches markdown extension profiles through the converter format string' => static function (TestRunner $t): void {
        $document = PandocConverter::read('==marked==', 'markdown+mark');
        $gfm = PandocConverter::read('<table><tr><td>raw</td></tr></table>', 'gfm+raw_html');

        $t->true(PandocConverter::canRead('markdown+mark'));
        $t->true(PandocConverter::canRead('gfm+raw_html'));
        $t->same('span', $document->children[0]->children[0]->type);
        $t->same(['mark'], $document->children[0]->children[0]->attr('classes'));
        $t->same('raw_html', $gfm->children[0]->type);
    },
    'converts rtf through the registered reader path' => static function (TestRunner $t): void {
        $blocks = PandocConverter::convert('{\\rtf1\\ansi\\pard RTF {\\b bold} import.\\par}', 'rtf', 'blocks');

        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->contains('<p>RTF <strong>bold</strong> import.</p>', $blocks);
    },
    'converts markdown to plain through the registered plain writer' => static function (TestRunner $t): void {
        $plain = PandocConverter::convert('Plain **writer** output.', 'markdown', 'plain', [
            'writerOptions' => ['columns' => 80],
        ]);

        $t->same('Plain writer output.', $plain);
    },
    'reports supported and unsupported formats from registry state' => static function (TestRunner $t): void {
        $t->true(PandocConverter::canRead('markdown'));
        $t->true(PandocConverter::canRead('bibtex'));
        $t->true(PandocConverter::canRead('biblatex'));
        $t->true(PandocConverter::canRead('csljson'));
        $t->true(PandocConverter::canRead('json'));
        $t->true(PandocConverter::canRead('csv'));
        $t->true(PandocConverter::canRead('endnotexml'));
        $t->true(PandocConverter::canRead('ris'));
        $t->true(PandocConverter::canRead('tsv'));
        $t->true(PandocConverter::canRead('doc'));
        $t->true(PandocConverter::canRead('docbook'));
        $t->true(PandocConverter::canRead('docx'));
        $t->true(PandocConverter::canRead('epub'));
        $t->true(PandocConverter::canRead('fb2'));
        $t->true(PandocConverter::canRead('ipynb'));
        $t->true(PandocConverter::canRead('jira'));
        $t->true(PandocConverter::canRead('latex'));
        $t->true(PandocConverter::canRead('odt'));
        $t->true(PandocConverter::canRead('opml'));
        $t->true(PandocConverter::canRead('pptx'));
        $t->true(PandocConverter::canRead('rtf'));
        $t->true(PandocConverter::canRead('pdf'));
        $t->true(PandocConverter::canRead('xml'));
        $t->true(PandocConverter::canRead('jats'));
        $t->true(PandocConverter::canRead('bits'));
        $t->true(PandocConverter::canRead('xlsx'));
        $t->true(PandocConverter::canWrite('blocks'));
        $t->true(PandocConverter::canWrite('native'));
        $t->true(PandocConverter::canWrite('opml'));
        $t->true(PandocConverter::canWrite('epub'));
        $t->true(PandocConverter::canWrite('epub3'));
        $t->true(!PandocConverter::canWrite('markdown'));
        $t->true(!PandocConverter::canWrite('gfm'));
        $t->true(!PandocConverter::canWrite('commonmark'));
        $t->true(!PandocConverter::canWrite('latex'));
        $t->true(!PandocConverter::canWrite('epub2'));
        $t->true(!PandocConverter::canWrite('pdf'));
    },
    'keeps Markdown and LaTeX as input-only formats' => static function (TestRunner $t): void {
        $markdown = PandocConverter::read('# Input remains supported', 'markdown');
        $latex = PandocConverter::read('\\section{Input remains supported}', 'latex');

        $t->same('heading', $markdown->children[0]->type);
        $t->same('heading', $latex->children[0]->type);
        foreach ([
            'commonmark',
            'commonmark_x',
            'gfm',
            'latex',
            'markdown',
            'markdown_github',
            'markdown_mmd',
            'markdown_phpextra',
            'markdown_strict',
        ] as $format) {
            $t->throws(
                \InvalidArgumentException::class,
                static fn (): string => PandocConverter::write($markdown, $format),
            );
        }
    },
    'converts markdown sections to opml through the registered writer path' => static function (TestRunner $t): void {
        $opml = PandocConverter::convert("# Root\n\nIntro **note**.\n\n## Child\n", 'markdown', 'opml', [
            'writerOptions' => ['standalone' => false],
        ]);

        $t->contains('<outline text="Root" _note="Intro note.">', $opml);
        $t->contains('  <outline text="Child">', $opml);
    },
    'extracts and rewrites package media beside converted output' => static function (TestRunner $t): void {
        $root = dirname(__DIR__, 3);
        $epub = $root . '/pandoc-showcase/samples/epub-picture-epub2_picture.epub';
        $docx = $root . '/pandoc-showcase/samples/docx-inline-images-inline_images.docx';
        $tmp = sys_get_temp_dir() . '/pandoc-extract-media-' . bin2hex(random_bytes(6));
        mkdir($tmp, 0777, true);
        $cleanup = static function (string $path) use (&$cleanup): void {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $child = $path . '/' . $entry;
                is_dir($child) ? $cleanup($child) : unlink($child);
            }
            rmdir($path);
        };

        try {
            $epubResult = PandocConverter::convertFileWithMedia($epub, 'epub', 'html', [
                'extractMedia' => ['destination' => 'media', 'outputDirectory' => $tmp . '/epub'],
            ]);
            $t->contains('src="media/image/image.jpg"', $epubResult['output']);
            $t->same(1, count($epubResult['media']));
            $t->same('media/image/image.jpg', $epubResult['media'][0]['path'] ?? null);
            $t->true(is_file($tmp . '/epub/image/image.jpg'));
            $t->true((int) filesize($tmp . '/epub/image/image.jpg') > 1000);

            $docxResult = PandocConverter::convertFileWithMedia($docx, 'docx', 'wordpress', [
                'extractMedia' => ['destination' => 'media', 'outputDirectory' => $tmp . '/docx'],
            ]);
            $t->contains('src="media/media/image1.jpg"', $docxResult['output']);
            $t->true(count($docxResult['media']) >= 2);
            $t->true(is_file($tmp . '/docx/media/image1.jpg'));
            $t->true(is_file($tmp . '/docx/media/image2.jpg'));
        } finally {
            $cleanup($tmp);
        }
    },
    'places painted pdf images between their surrounding text and ignores technical objects' => static function (TestRunner $t): void {
        $result = PandocConverter::convertWithMedia(pandoc_positioned_pdf_image_fixture(), 'pdf', 'wordpress', [
            'extractMedia' => [
                'destination' => 'media',
                'imageMode' => 'all',
            ],
        ]);

        $output = $result['output'];
        $before = strpos($output, 'Before');
        $image8 = strpos($output, 'media/pdf/image-8.jpg');
        $middle = strpos($output, 'Middle');
        $image7 = strpos($output, 'media/pdf/image-7.jpg');
        $after = strpos($output, 'After');

        $t->same(2, count($result['media']));
        $t->true($before !== false && $image8 !== false && $middle !== false && $image7 !== false && $after !== false);
        $t->true($before < $image8 && $image8 < $middle && $middle < $image7 && $image7 < $after);
        $t->true(!str_contains($output, 'image-10.jpg'));
        $t->true(!str_contains($output, 'image-11.jpg'));
        $t->true(!str_contains($output, 'pandoc-pdf-extracted-images'));
        $t->contains('data-pandoc-width="120pt"', $output);
        $t->contains('style="width:120pt; height:30pt"', $output);
        $t->true(in_array('extract-media-pdf-image-loaded:7:small', $result['diagnostics'], true));
        $t->true(in_array('extract-media-pdf-image-loaded:8:small', $result['diagnostics'], true));
    },
    'collects image placement anchors when geometry tables and prose repair are disabled' => static function (TestRunner $t): void {
        $document = PandocConverter::read(pandoc_positioned_pdf_image_fixture(), 'pdf', [
            'pdfCollectImagePlacements' => true,
            'geometryTables' => false,
            'pdfRepairProseText' => false,
        ]);
        $meta = $document->attr('meta', []);

        $t->true(is_array($meta));
        $t->same(2, $meta['pdfPlacedImageCandidates'] ?? null);
    },
    'keeps text painted over a direct PDF image unsafe for placement' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before image) Tj ET\n"
            . "q 120 0 0 50 72 620 cm /A Do Q\n"
            // Unlike a Form label, this is page text over a raster image.
            . "BT /F1 9 Tf 88 642 Td (Overlay text) Tj ET\n"
            . "BT /F1 12 Tf 72 580 Td (After image) Tj ET";
        $document = PandocConverter::read(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => pandoc_layout_fixture_image_object()]),
            'pdf',
            [
                'pdfCollectImagePlacements' => true,
                'pdfGeometryTables' => false,
                'pdfRepairProseText' => false,
            ]
        );
        $placements = $document->attr('meta', [])['pdfImagePlacements'] ?? [];

        $t->same(1, count($placements));
        $t->same(null, $placements[0]['precedingText']);
        $t->same(null, $placements[0]['followingText']);
    },
    'places a clipped image under a benign graphics state through the full media path' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
            . "q 72 640 120 40 re W n q /GS1 gs 120 0 0 40 72 640 cm /A Do Q Q\n"
            . "BT /F1 12 Tf 72 580 Td (After) Tj ET";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture(
                $content,
                '/A 7 0 R',
                [7 => pandoc_layout_fixture_image_object()],
                '/ExtGState << /GS1 << /OP true /op false /OPM 1 /SM 0.001 >> >>'
            ),
            'pdf',
            'wordpress',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'all']]
        );

        $before = strpos($result['output'], 'Before');
        $image = strpos($result['output'], 'media/pdf/image-7.jpg');
        $after = strpos($result['output'], 'After');

        $t->same(1, count($result['media']));
        $t->true($before !== false && $image !== false && $after !== false);
        $t->true($before < $image && $image < $after);
    },
    'uses a dimension-validated AVIF supplemental raster for a painted PDF image' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
            . "q 100 0 0 100 72 580 cm /A Do Q\n"
            . "BT /F1 12 Tf 72 440 Td (After) Tj ET";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => pandoc_layout_fixture_image_object()]),
            'pdf',
            'wordpress',
            [
                'extractMedia' => [
                    'destination' => 'media',
                    'imageMode' => 'all',
                    'pdfRasterImages' => [[
                        'object' => '7',
                        'contents' => pandoc_avif_raster_fixture(100, 100),
                        'mimeType' => 'image/avif',
                        'width' => 100,
                        'height' => 100,
                    ]],
                ],
            ]
        );

        $t->same(1, count($result['media']));
        $t->same('image/avif', $result['media'][0]['mimeType'] ?? null);
        $t->same('media/pdf/image-7.avif', $result['media'][0]['path'] ?? null);
        $t->contains('src="media/pdf/image-7.avif"', $result['output']);
        $t->true(in_array('extract-media-pdf-image-raster-loaded:7:important', $result['diagnostics'], true));
    },
    'keeps source-important PDF images when a replacement raster is smaller' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
            . "q 146 0 0 68 72 580 cm /A Do Q\n"
            . "BT /F1 12 Tf 72 480 Td (After) Tj ET";
        $jpx = str_repeat('J', 8500);
        $image = '<< /Type /XObject /Subtype /Image /Width 146 /Height 68 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /Length '
            . strlen($jpx) . " >>\nstream\n" . $jpx . "\nendstream";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => $image]),
            'pdf',
            'wordpress',
            [
                'extractMedia' => [
                    'destination' => 'media',
                    'imageMode' => 'important',
                    'pdfRasterImages' => [[
                        'object' => '7',
                        'contents' => pandoc_png_raster_fixture(146, 68),
                        'mimeType' => 'image/png',
                        'width' => 146,
                        'height' => 68,
                    ]],
                ],
            ]
        );

        $t->same(1, count($result['media']));
        $t->same('media/pdf/image-7.png', $result['media'][0]['path'] ?? null);
        $t->contains('src="media/pdf/image-7.png"', $result['output']);
        $t->true(in_array('extract-media-pdf-image-raster-loaded:7:important', $result['diagnostics'], true));
    },
    'retains JPEG 2000 PDF media behind a placeholder when no raster decoder is available' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
            . "q 146 0 0 68 72 580 cm /A Do Q\n"
            . "BT /F1 12 Tf 72 480 Td (After) Tj ET";
        $jpx = str_repeat('J', 8500);
        $image = '<< /Type /XObject /Subtype /Image /Width 146 /Height 68 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /Length '
            . strlen($jpx) . " >>\nstream\n" . $jpx . "\nendstream";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => $image]),
            'pdf',
            'wordpress',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'important']]
        );

        $t->same(1, count($result['media']));
        $t->same('image/jp2', $result['media'][0]['mimeType'] ?? null);
        $t->same('media/pdf/image-7.jp2', $result['media'][0]['path'] ?? null);
        $t->same(strlen($jpx), $result['media'][0]['byteLength'] ?? null);
        $t->same(sha1($jpx), $result['media'][0]['sha1'] ?? null);
        $t->contains('pandoc-pdf-image-placeholder', $result['output']);
        $t->contains('no JPEG 2000 decoder was available for a preview', $result['output']);
        $t->contains('href="media/pdf/image-7.jp2"', $result['output']);
        $t->true(!str_contains($result['output'], '<img'));
        $t->true(in_array('extract-media-pdf-image-placeholder:7:jpeg2000-raster-unavailable', $result['diagnostics'], true));

        $html = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => $image]),
            'pdf',
            'html',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'important']]
        );
        $t->contains('pandoc-pdf-image-placeholder', $html['output']);
        $t->contains('href="media/pdf/image-7.jp2"', $html['output']);
        $t->true(!str_contains($html['output'], '<img'));
    },
    'keeps image anchors in their own visual column' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
            . "q 100 0 0 100 72 500 cm /A Do Q\n"
            . "BT /F1 12 Tf 400 501 Td (Right nearby) Tj ET\n"
            . "BT /F1 12 Tf 72 300 Td (After) Tj ET";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => pandoc_layout_fixture_image_object()]),
            'pdf',
            'wordpress',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'all']]
        );
        $output = $result['output'];
        $before = strpos($output, 'Before');
        $right = strpos($output, 'Right nearby');
        $image = strpos($output, 'media/pdf/image-7.jpg');
        $after = strpos($output, 'After');

        $t->same(1, count($result['media']));
        $t->true($before !== false && $right !== false && $image !== false && $after !== false);
        $t->true($before < $right && $right < $image && $image < $after);
    },
    'keeps legitimate repeated paintings of the same PDF image object' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
            . "q 100 0 0 40 72 650 cm /A Do Q\n"
            . "q 100 0 0 40 72 570 cm /A Do Q\n"
            . "BT /F1 12 Tf 72 500 Td (After) Tj ET";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => pandoc_layout_fixture_image_object()]),
            'pdf',
            'wordpress',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'all']]
        );

        $t->same(1, count($result['media']));
        $t->same(2, substr_count($result['output'], '<img src="media/pdf/image-7.jpg"'));
    },
    'does not place an image when source-order anchors contradict visual order' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 540 Td (After) Tj ET\n"
            . "q 100 0 0 40 72 600 cm /A Do Q\n"
            . "BT /F1 12 Tf 72 720 Td (Before) Tj ET";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => pandoc_layout_fixture_image_object()]),
            'pdf',
            'wordpress',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'all']]
        );

        $t->same(0, count($result['media']));
        $t->true(!str_contains($result['output'], 'media/pdf/image-7.jpg'));
        $t->true(in_array('extract-media-pdf-image-anchor-order-conflict:7', $result['diagnostics'], true));
    },
    'uses Form and enclosing matrices before anchoring a direct image beside Form text' => static function (TestRunner $t): void {
        $formContent = 'BT /F1 12 Tf 0 0 Td (Form text) Tj ET';
        $form = '<< /Type /XObject /Subtype /Form /BBox [0 0 612 792] /Matrix [2 0 0 3 10 20] /Resources << /Font << /F1 9 0 R >> >> /Length '
            . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream";
        $content = "q 4 0 0 5 100 200 cm /F Do Q\nq 100 0 0 40 72 220 cm /A Do Q";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/F 5 0 R /A 7 0 R', [
                5 => $form,
                7 => pandoc_layout_fixture_image_object(),
            ]),
            'pdf',
            'wordpress',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'all']]
        );
        $formText = strpos($result['output'], 'Form text');
        $image = strpos($result['output'], 'media/pdf/image-7.jpg');

        $t->same(1, count($result['media']));
        $t->true($formText !== false && $image !== false);
        $t->true($formText < $image);
    },
    'caps repeated page image placements before they become a gallery' => static function (TestRunner $t): void {
        $placements = '';
        for ($index = 0; $index < 17; $index++) {
            $placements .= 'q 30 0 0 20 72 ' . (700 - ($index * 10)) . " cm /A Do Q\n";
        }
        $content = "BT /F1 12 Tf 72 750 Td (Before) Tj ET\n" . $placements . "BT /F1 12 Tf 72 500 Td (After) Tj ET";
        $result = PandocConverter::convertWithMedia(
            pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => pandoc_layout_fixture_image_object()]),
            'pdf',
            'wordpress',
            ['extractMedia' => ['destination' => 'media', 'imageMode' => 'all']]
        );

        $t->same(1, count($result['media']));
        $t->same(16, substr_count($result['output'], '<img src="media/pdf/image-7.jpg"'));
        $t->true(in_array('extract-media-pdf-image-placement-page-limit:1', $result['diagnostics'], true));
    },
    'does not extract unpainted pdf image streams for media hosting review' => static function (TestRunner $t): void {
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2w==', true);
        $t->true(is_string($jpeg));
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n"
            . '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen((string) $jpeg) . " >>\n"
            . "stream\n"
            . (string) $jpeg . "\n"
            . "endstream\n"
            . "endobj\n"
            . "2 0 obj\n"
            . '<< /Type /XObject /Subtype /Image /Width 128 /Height 128 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen((string) $jpeg) . " >>\n"
            . "stream\n"
            . (string) $jpeg . "\n"
            . "endstream\n"
            . "endobj\n"
            . "%%EOF\n";

        $extractor = new \PortLibs\Pandoc\PandocMediaExtractor();
        $result = $extractor->extract(new AstNode('document'), $pdf, 'pdf', ['destination' => 'media', 'imageMode' => 'all']);

        $t->same(0, count($result['entries']));
        $t->true(in_array('extract-media-image-mode:all', $result['diagnostics'], true));
        $t->true(in_array('extract-media-pdf-placement-unanchored-scan:0', $result['diagnostics'], true));
        $html = PandocConverter::write($result['document'], 'html');
        $t->true(!str_contains($html, '<img'));

        $important = $extractor->extract(new AstNode('document'), $pdf, 'pdf', ['destination' => 'media', 'imageMode' => 'important']);
        $t->same(0, count($important['entries']));

        $documentWithImage = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', ['url' => 'picture.jpg'], [new AstNode('text', ['text' => 'Picture'])]),
            ]),
        ]);
        $withoutImages = $extractor->extract($documentWithImage, $pdf, 'pdf', ['destination' => 'media', 'imageMode' => 'none']);
        $t->same(0, count($withoutImages['entries']));
        $t->same(0, count($withoutImages['document']->children));
        $t->true(in_array('extract-media-image-mode:none', $withoutImages['diagnostics'], true));
    },
    'does not transcode unpainted or mask-only pdf image streams' => static function (TestRunner $t): void {
        $grayPixels = "\x00\xff";
        $grayStream = gzcompress($grayPixels);
        $oneBitPixels = "\x80";
        $oneBitStream = gzcompress($oneBitPixels);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n"
            . '<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($grayStream) . " >>\n"
            . "stream\n"
            . $grayStream . "\n"
            . "endstream\n"
            . "endobj\n"
            . "2 0 obj\n"
            . '<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Length ' . strlen($oneBitStream) . " >>\n"
            . "stream\n"
            . $oneBitStream . "\n"
            . "endstream\n"
            . "endobj\n"
            . "%%EOF\n";

        $extractor = new \PortLibs\Pandoc\PandocMediaExtractor();
        $result = $extractor->extract(new AstNode('document'), $pdf, 'pdf', ['destination' => 'media', 'imageMode' => 'all']);

        $t->same(0, count($result['entries']));
        $t->same(0, count($result['document']->children));
        $t->true(in_array('extract-media-pdf-placement-unanchored-scan:0', $result['diagnostics'], true));
    },
    'does not use supplemental rasters for unpainted pdf image streams' => static function (TestRunner $t): void {
        $png = "\x89PNG\r\n\x1a\n"
            . pack('N', 13) . 'IHDR' . pack('NNCCCCC', 100, 100, 1, 0, 0, 0, 0)
            . pack('N', 0);
        $pdf = "%PDF-1.4\n"
            . "00017 0 obj\n"
            . "<< /Type /XObject /Subtype /Image /Width 100 /Height 100 /BitsPerComponent 1 /Filter /JBIG2Decode /Length 3 >>\n"
            . "stream\nabc\nendstream\nendobj\n%%EOF\n";

        $extractor = new \PortLibs\Pandoc\PandocMediaExtractor();
        $result = $extractor->extract(new AstNode('document'), $pdf, 'pdf', [
            'destination' => 'media',
            'imageMode' => 'important',
            'pdfRasterImages' => [[
                'object' => '17',
                'contents' => $png,
                'mimeType' => 'image/png',
                'width' => 100,
                'height' => 100,
            ]],
        ]);

        $t->same(0, count($result['entries']));
        $t->same(0, count($result['document']->children));
        $t->true(in_array('extract-media-pdf-placement-unanchored-scan:0', $result['diagnostics'], true));

        $mismatched = $extractor->extract(new AstNode('document'), $pdf, 'pdf', [
            'destination' => 'media',
            'imageMode' => 'important',
            'pdfRasterImages' => [[
                'object' => '17',
                'contents' => $png,
                'mimeType' => 'image/png',
                'width' => 99,
                'height' => 100,
            ]],
        ]);
        $t->same(0, count($mismatched['entries']));
        $t->true(in_array('extract-media-pdf-placement-unanchored-scan:0', $mismatched['diagnostics'], true));
    },
    'fails explicitly for unsupported registry formats' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('unsupported input', 'asciidoc');
        });
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::write(new AstNode('document'), 'pdf');
        });
    },
];
