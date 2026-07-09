<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;

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
        $t->true(!PandocConverter::canWrite('epub2'));
        $t->true(!PandocConverter::canWrite('pdf'));
    },
    'converts markdown sections to opml through the registered writer path' => static function (TestRunner $t): void {
        $opml = PandocConverter::convert("# Root\n\nIntro **note**.\n\n## Child\n", 'markdown', 'opml', [
            'writerOptions' => ['standalone' => false],
        ]);

        $t->contains('<outline text="Root" _note="Intro **note**.">', $opml);
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
    'extracts browser friendly pdf image streams for media hosting review' => static function (TestRunner $t): void {
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

        $t->same(2, count($result['entries']));
        $t->same('media/pdf/image-1.jpg', $result['entries'][0]['path'] ?? null);
        $t->same('image/jpeg', $result['entries'][0]['mimeType'] ?? null);
        $t->same($jpeg, $result['entries'][0]['contents'] ?? null);
        $t->true(in_array('extract-media-image-mode:all', $result['diagnostics'], true));
        $t->true(in_array('extract-media-pdf-image-loaded:1:tiny', $result['diagnostics'], true));
        $t->same('div', $result['document']->children[0]->type ?? null);
        $t->same(['pandoc-pdf-extracted-images'], $result['document']->children[0]->attr('classes'));
        $t->same('paragraph', $result['document']->children[0]->children[2]->type ?? null);
        $t->same(['pandoc-pdf-image-block'], $result['document']->children[0]->children[2]->attr('classes'));
        $t->same('image', $result['document']->children[0]->children[2]->children[0]->type ?? null);
        $t->same('media/pdf/image-1.jpg', $result['document']->children[0]->children[2]->children[0]->attr('url'));
        $html = PandocConverter::write($result['document'], 'html');
        $t->contains('<img src="media/pdf/image-1.jpg"', $html);

        $important = $extractor->extract(new AstNode('document'), $pdf, 'pdf', ['destination' => 'media', 'imageMode' => 'important']);
        $t->same(1, count($important['entries']));
        $t->same('media/pdf/image-2.jpg', $important['entries'][0]['path'] ?? null);
        $t->true(in_array('extract-media-pdf-image-unimportant:1:tiny', $important['diagnostics'], true));
        $t->true(in_array('extract-media-pdf-image-loaded:2:important', $important['diagnostics'], true));

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
    'transcodes simple flate encoded pdf image streams to png media' => static function (TestRunner $t): void {
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

        $t->same(2, count($result['entries']));
        $t->same('media/pdf/image-1.png', $result['entries'][0]['path'] ?? null);
        $t->same('image/png', $result['entries'][0]['mimeType'] ?? null);
        $t->same("\x89PNG\r\n\x1a\n", substr((string) ($result['entries'][0]['contents'] ?? ''), 0, 8));
        $t->same('media/pdf/image-2.png', $result['entries'][1]['path'] ?? null);
        $t->same('image/png', $result['entries'][1]['mimeType'] ?? null);
        $t->true(in_array('extract-media-pdf-image-loaded:1:tiny', $result['diagnostics'], true));
        $t->true(in_array('extract-media-pdf-image-loaded:2:mask', $result['diagnostics'], true));
        $t->same('media/pdf/image-1.png', $result['document']->children[0]->children[2]->children[0]->attr('url'));
        $t->same('media/pdf/image-2.png', $result['document']->children[0]->children[3]->children[0]->attr('url'));
    },
    'uses validated supplemental rasters for otherwise non-embeddable pdf image streams' => static function (TestRunner $t): void {
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

        $t->same(1, count($result['entries']));
        $t->same('media/pdf/image-00017.png', $result['entries'][0]['path'] ?? null);
        $t->same('image/png', $result['entries'][0]['mimeType'] ?? null);
        $t->same($png, $result['entries'][0]['contents'] ?? null);
        $t->true(in_array('extract-media-pdf-image-raster-loaded:00017:important', $result['diagnostics'], true));
        $t->same('media/pdf/image-00017.png', $result['document']->children[0]->children[2]->children[0]->attr('url'));

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
        $t->true(in_array('extract-media-pdf-image-skipped:JBIG2Decode', $mismatched['diagnostics'], true));
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
