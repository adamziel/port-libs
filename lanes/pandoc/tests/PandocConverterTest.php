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

function pandoc_layout_fixture_page_image_object(string $extraDictionary = ''): string
{
    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBF'
        . 'RyB2ODApLCBxdWFsaXR5ID0gODAK/9sAQwAGBAUGBQQGBgUGBwcGCAoQCgoJCQoUDg8MEBcUGBgXFBYW'
        . 'Gh0lHxobIxwWFiAsICMmJykqKRkfLTAtKDAlKCko/9sAQwEHBwcKCAoTCgoTKBoWGigoKCgoKCgoKCgo'
        . 'KCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgo/8AAEQgAZABkAwEiAAIRAQMRAf/E'
        . 'AB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUS'
        . 'ITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RV'
        . 'VldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TF'
        . 'xsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgME'
        . 'BQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1Lw'
        . 'FWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKD'
        . 'hIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp'
        . '6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+qaKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooo'
        . 'oAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooo'
        . 'oAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooo'
        . 'oAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAP/9k=',
        true
    );
    if (!is_string($jpeg)) {
        throw new RuntimeException('Unable to build full-page JPEG fixture.');
    }

    return '<< /Type /XObject /Subtype /Image /Width 100 /Height 100'
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8' . $extraDictionary
        . ' /Filter /DCTDecode /Length ' . strlen($jpeg) . " >>\nstream\n"
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
function pandoc_single_page_layout_fixture(
    string $content,
    string $xObjects,
    array $extraObjects,
    string $extraResources = '',
    string $extraPageEntries = ''
): string
{
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> /XObject << {$xObjects} >> {$extraResources} >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R {$extraPageEntries} >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    foreach ($extraObjects as $number => $body) {
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF\n";
}

/** @return array{pdf:string,selected:string,decoy:string} */
function pandoc_incremental_image_decoy_fixture(): array
{
    $selected = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2w==', true);
    if (!is_string($selected)) {
        throw new RuntimeException('Unable to build selected JPEG fixture.');
    }
    $decoy = "\xff\xd8STALE-UNREFERENCED-IMAGE\xff\xd9";
    $content = "BT /F1 12 Tf 72 720 Td (Before) Tj ET\n"
        . "q 100 0 0 40 72 620 cm /A Do Q\n"
        . "BT /F1 12 Tf 72 540 Td (After) Tj ET";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 612 792] '
            . '/Resources << /Font << /F1 6 0 R >> /XObject << /A 5 1 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>',
        4 => '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream",
        5 => '<< /Type /XObject /Subtype /Image /Width 100 /Height 40 /ColorSpace /DeviceRGB '
            . '/BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($selected)
            . ">>\nstream\n{$selected}\nendstream",
        6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $generation = $number === 5 ? 1 : 0;
        $pdf .= $number . ' ' . $generation . " obj\n{$body}\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    for ($number = 1; $number <= 6; $number++) {
        $generation = $number === 5 ? 1 : 0;
        $pdf .= sprintf('%010d %05d n ', $offsets[$number], $generation) . "\n";
    }
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    // An incremental section carries a stale duplicate body but its newest
    // xref deliberately keeps the original selected offset authoritative.
    // A raw object regex sees the decoy last and used to reselect its bytes.
    $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 999 /Height 999 "
        . '/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($decoy)
        . ">>\nstream\n{$decoy}\nendstream\nendobj\n";
    $incrementalXrefOffset = strlen($pdf);
    $pdf .= "xref\n5 1\n" . sprintf('%010d 00001 n ', $offsets[5]) . "\n"
        . "trailer\n<< /Size 7 /Root 1 0 R /Prev {$xrefOffset} >>\n"
        . "startxref\n{$incrementalXrefOffset}\n%%EOF\n";

    return ['pdf' => $pdf, 'selected' => $selected, 'decoy' => $decoy];
}

/** @param list<string> $pageContents */
function pandoc_repeated_image_pages_fixture(array $pageContents): string
{
    $pageObjects = [];
    $objects = [];
    $nextObject = 3;
    foreach ($pageContents as $content) {
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;
        $pageObjects[] = $pageObject . ' 0 R';
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /Contents ' . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
    }
    $imageObject = $nextObject;
    $imageBody = pandoc_layout_fixture_image_object(100, 40);
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageObjects) . '] /Count ' . count($pageObjects)
        . ' /MediaBox [0 0 612 792] /Resources << /XObject << /A ' . $imageObject . ' 0 R >> >> >>';
    $objects[$imageObject] = $imageBody;
    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n{$body}\nendobj\n";
    }

    return $pdf . "%%EOF\n";
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
        $t->same(false, $result['sourceIntegrity']['complete'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfDocumentComplete'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfTextComplete'] ?? null);
        $t->same(false, $result['sourceIntegrity']['pdfSemanticTextComplete'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfSourceBindingComplete'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfSourceEdgeMappingComplete'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfOrderedSignificantCharactersPreserved'] ?? null);
        $t->same(0, $result['sourceIntegrity']['pdfUnresolvedSourceOccurrences'] ?? null);
        $t->same('incomplete', $result['sourceIntegrity']['pdfTextLayerStatus'] ?? null);
        $t->same([1], $result['sourceIntegrity']['pdfTextRepresentedPageNumbers'] ?? null);
        $t->same([], $result['sourceIntegrity']['pdfPagesNeedingImageRepresentation'] ?? null);
        $t->same([1], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
    },
    'selects painted image bytes from the active xref rather than a trailing stale object' => static function (TestRunner $t): void {
        $fixture = pandoc_incremental_image_decoy_fixture();
        $result = PandocConverter::convertWithMedia($fixture['pdf'], 'pdf', 'wordpress', [
            'extractMedia' => ['destination' => 'media', 'imageMode' => 'all'],
        ]);

        $t->same(1, count($result['media']));
        $t->same(sha1($fixture['selected']), $result['media'][0]['sha1'] ?? null);
        $t->true(($result['media'][0]['sha1'] ?? null) !== sha1($fixture['decoy']));
        $t->contains('data-pandoc-pdf-occurrence-id="pdf-image-p1-n1-o', $result['output']);
        $t->contains('data-pandoc-pdf-page="1"', $result['output']);
        $t->contains('data-pandoc-pdf-paint-order="1"', $result['output']);
        $t->contains('data-pandoc-pdf-image-x1="72"', $result['output']);
        $occurrenceDiagnostics = array_values(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_starts_with($diagnostic, 'extract-media-pdf-occurrence:')
        ));
        $t->same(1, count($occurrenceDiagnostics));
        $t->contains(':bbox-72-620-172-660:', $occurrenceDiagnostics[0]);
        $t->contains(':resolved:placed-media-attachment', $occurrenceDiagnostics[0]);
    },
    'keeps repeated cross-page paintings distinct and records no-caption and missing-page occurrences' => static function (TestRunner $t): void {
        $content = 'q 100 0 0 40 72 620 cm /A Do Q';
        $pdf = pandoc_repeated_image_pages_fixture([$content, $content, $content, $content]);
        $imageObject = 11;
        $placements = [];
        for ($page = 1; $page <= 4; $page++) {
            $placements[] = [
                'id' => 'pdf-image-p' . $page . '-n1-o' . $imageObject,
                'kind' => 'image-xobject',
                'page' => $page,
                'paintOrder' => 1,
                'object' => $imageObject,
                'bbox' => ['x1' => 72.0, 'y1' => 620.0, 'x2' => 172.0, 'y2' => 660.0],
                'visible' => true,
                'confidence' => 'high',
                'placementEligible' => true,
                'precedingText' => null,
                'followingText' => $page < 3 ? 'Repeated caption' : null,
                'disposition' => 'pending',
                'dispositionReason' => null,
            ];
        }
        $document = new AstNode('document', ['meta' => [
            'pdfImagePlacements' => $placements,
            'pdfProcessedPageNumbers' => [1, 2, 3],
        ]], [
            new AstNode('paragraph', ['text' => 'Repeated caption']),
            new AstNode('paragraph', ['text' => 'Repeated caption']),
        ]);

        $result = (new \PortLibs\Pandoc\PandocMediaExtractor())->extract(
            $document,
            $pdf,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'all']
        );
        $html = PandocConverter::write($result['document'], 'html');
        $meta = $result['document']->attr('meta', []);
        $dispositions = $meta['pdfMediaOccurrenceDispositions'] ?? [];

        $t->same(1, count($result['entries']));
        $t->same(2, substr_count($html, '<img'));
        $t->same([
            'pdf-image-p1-n1-o11',
            'pdf-image-p2-n1-o11',
            'pdf-image-p3-n1-o11',
            'pdf-image-p4-n1-o11',
        ], array_column($dispositions, 'id'));
        $t->same(['resolved', 'resolved', 'unresolved', 'unresolved'], array_column($dispositions, 'disposition'));
        $t->same('image-placement-unanchored', $dispositions[2]['reason'] ?? null);
        $t->same('missing-page-occurrence', $dispositions[3]['reason'] ?? null);
        $t->same(false, $meta['pdfMediaOccurrenceComplete'] ?? null);
        $t->contains('data-pandoc-pdf-occurrence-id="pdf-image-p1-n1-o11"', $html);
        $t->contains('data-pandoc-pdf-occurrence-id="pdf-image-p2-n1-o11"', $html);
        $t->same(4, count(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_starts_with($diagnostic, 'extract-media-pdf-occurrence:')
        )));
    },
    'maps repeated captions by source page and uses page-region geometry for captionless siblings' => static function (TestRunner $t): void {
        $content = "q 100 0 0 40 72 650 cm /A Do Q\nq 100 0 0 40 72 570 cm /A Do Q";
        $pdf = pandoc_repeated_image_pages_fixture([$content, $content]);
        $placements = [];
        foreach ([1, 2] as $page) {
            foreach ([1 => 650.0, 2 => 570.0] as $paintOrder => $y) {
                $placements[] = [
                    'id' => 'pdf-image-p' . $page . '-n' . $paintOrder . '-o7',
                    'kind' => 'image-xobject',
                    'page' => $page,
                    'paintOrder' => $paintOrder,
                    'object' => 7,
                    'bbox' => ['x1' => 72.0, 'y1' => $y, 'x2' => 172.0, 'y2' => $y + 40.0],
                    'visible' => true,
                    'confidence' => 'high',
                    'placementEligible' => true,
                    'precedingText' => $paintOrder === 1 ? 'Repeated caption' : null,
                    'followingText' => null,
                    'disposition' => 'pending',
                    'dispositionReason' => null,
                ];
            }
        }
        $document = new AstNode('document', ['meta' => [
            'pdfImagePlacements' => $placements,
            'pdfProcessedPageNumbers' => [1, 2],
        ]], [
            new AstNode('paragraph', ['text' => 'Repeated caption']),
            new AstNode('paragraph', ['text' => 'Repeated caption']),
        ]);

        $result = (new \PortLibs\Pandoc\PandocMediaExtractor())->extract(
            $document,
            $pdf,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'all']
        );
        $html = PandocConverter::write($result['document'], 'html');
        $dispositions = $result['document']->attr('meta', [])['pdfMediaOccurrenceDispositions'] ?? [];

        $t->same(1, count($result['entries']));
        $t->same(4, substr_count($html, '<img'));
        $t->same(['resolved', 'resolved', 'resolved', 'resolved'], array_column($dispositions, 'disposition'));
        $t->same([0, 0, 1, 1], array_column($dispositions, 'anchorIndex'));
        $t->same(2, count(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_starts_with(
                $diagnostic,
                'extract-media-pdf-image-region-fallback:7:page-'
            )
        )));
    },
    'important mode keeps strong semantic image anchors and explicitly omits weak or unsafe occurrences' => static function (TestRunner $t): void {
        $content = "q 100 0 0 100 72 620 cm /A Do Q\n"
            . "q 100 0 0 100 72 480 cm /A Do Q\n"
            . 'q 100 0 0 100 72 340 cm /A Do Q';
        $pdf = pandoc_single_page_layout_fixture(
            $content,
            '/A 7 0 R',
            [7 => pandoc_layout_fixture_image_object(100, 100)]
        );
        $base = [
            'kind' => 'image-xobject',
            'page' => 1,
            'object' => 7,
            'visible' => true,
            'confidence' => 'high',
        ];
        $placements = [
            array_replace($base, [
                'id' => 'pdf-image-p1-n1-o7',
                'paintOrder' => 1,
                'bbox' => ['x1' => 72.0, 'y1' => 620.0, 'x2' => 172.0, 'y2' => 720.0],
                'placementEligible' => true,
                'precedingText' => 'Strong caption',
                'followingText' => null,
            ]),
            array_replace($base, [
                'id' => 'pdf-image-p1-n2-o7',
                'paintOrder' => 2,
                'bbox' => ['x1' => 72.0, 'y1' => 480.0, 'x2' => 172.0, 'y2' => 580.0],
                'placementEligible' => true,
                'precedingText' => 'weak fragment',
                'followingText' => null,
            ]),
            array_replace($base, [
                'id' => 'pdf-image-p1-n3-o7',
                'paintOrder' => 3,
                'bbox' => ['x1' => 72.0, 'y1' => 340.0, 'x2' => 172.0, 'y2' => 440.0],
                'placementEligible' => false,
                'disposition' => 'unresolved',
                'dispositionReason' => 'image-placement-uncertain',
            ]),
        ];
        $document = new AstNode('document', ['meta' => ['pdfImagePlacements' => $placements]], [
            new AstNode('paragraph', ['text' => 'Strong caption extended']),
            new AstNode('paragraph', ['text' => 'A larger sentence with weak fragment inside']),
        ]);

        $result = (new \PortLibs\Pandoc\PandocMediaExtractor())->extract(
            $document,
            $pdf,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'important']
        );
        $html = PandocConverter::write($result['document'], 'html');
        $dispositions = $result['document']->attr('meta', [])['pdfMediaOccurrenceDispositions'] ?? [];

        $t->same(1, count($result['entries']));
        $t->same(1, substr_count($html, '<img'));
        $t->same(['resolved', 'intentional_omission', 'intentional_omission'], array_column($dispositions, 'disposition'));
        $t->same([
            'placed-media-attachment',
            'image-mode-weak-semantic-anchor',
            'image-mode-placement-ineligible',
        ], array_column($dispositions, 'reason'));
        $t->same(true, $result['document']->attr('meta', [])['pdfMediaOccurrenceComplete'] ?? null);
    },
    'records ineligible conflict and decoder failures against their exact painted occurrences' => static function (TestRunner $t): void {
        $content = "q 20 0 0 20 72 680 cm /A Do Q\n"
            . "q 20 0 0 20 72 620 cm /A Do Q\n"
            . 'q 20 0 0 20 72 560 cm /A Do Q';
        $opaque = 'abc';
        $image = '<< /Type /XObject /Subtype /Image /Width 100 /Height 100 /ColorSpace /DeviceGray '
            . '/BitsPerComponent 1 /Filter /JBIG2Decode /Length ' . strlen($opaque)
            . ">>\nstream\n{$opaque}\nendstream";
        $pdf = pandoc_single_page_layout_fixture($content, '/A 7 0 R', [7 => $image]);
        $base = [
            'kind' => 'image-xobject',
            'page' => 1,
            'object' => 7,
            'visible' => true,
            'confidence' => 'high',
            'bbox' => ['x1' => 72.0, 'y1' => 560.0, 'x2' => 92.0, 'y2' => 580.0],
        ];
        $placements = [
            array_replace($base, [
                'id' => 'pdf-image-p1-n1-o7',
                'paintOrder' => 1,
                'placementEligible' => false,
                'disposition' => 'unresolved',
                'dispositionReason' => 'image-placement-uncertain',
            ]),
            array_replace($base, [
                'id' => 'pdf-image-p1-n2-o7',
                'paintOrder' => 2,
                'placementEligible' => true,
                'precedingText' => 'After',
                'followingText' => 'Before',
                'disposition' => 'pending',
                'dispositionReason' => null,
            ]),
            array_replace($base, [
                'id' => 'pdf-image-p1-n3-o7',
                'paintOrder' => 3,
                'placementEligible' => true,
                'precedingText' => 'Before',
                'followingText' => 'After',
                'disposition' => 'pending',
                'dispositionReason' => null,
            ]),
        ];
        $document = new AstNode('document', ['meta' => ['pdfImagePlacements' => $placements]], [
            new AstNode('paragraph', ['text' => 'Before']),
            new AstNode('paragraph', ['text' => 'After']),
        ]);

        $result = (new \PortLibs\Pandoc\PandocMediaExtractor())->extract(
            $document,
            $pdf,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'all']
        );
        $dispositions = $result['document']->attr('meta', [])['pdfMediaOccurrenceDispositions'] ?? [];

        $t->same(0, count($result['entries']));
        $t->same([
            'image-placement-uncertain',
            'image-anchor-order-conflict',
            'image-decoder-unavailable-JBIG2Decode',
        ], array_column($dispositions, 'reason'));
        $t->same(['unresolved', 'unresolved', 'unresolved'], array_column($dispositions, 'disposition'));
        $t->same(3, count(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_starts_with($diagnostic, 'extract-media-pdf-occurrence:')
        )));
    },
    'accounts for every one of fifty painted occurrences when the page placement cap is reached' => static function (TestRunner $t): void {
        $commands = [];
        $placements = [];
        for ($paintOrder = 1; $paintOrder <= 50; $paintOrder++) {
            $y = 740 - ($paintOrder * 5);
            $commands[] = 'q 30 0 0 20 72 ' . $y . ' cm /A Do Q';
            $placements[] = [
                'id' => 'pdf-image-p1-n' . $paintOrder . '-o5',
                'kind' => 'image-xobject',
                'page' => 1,
                'paintOrder' => $paintOrder,
                'object' => 5,
                'bbox' => ['x1' => 72.0, 'y1' => (float) $y, 'x2' => 102.0, 'y2' => (float) ($y + 20)],
                'visible' => true,
                'confidence' => 'high',
                'placementEligible' => true,
                'precedingText' => 'Before',
                'followingText' => 'After',
                'disposition' => 'pending',
                'dispositionReason' => null,
            ];
        }
        $pdf = pandoc_repeated_image_pages_fixture([implode("\n", $commands)]);
        $document = new AstNode('document', ['meta' => ['pdfImagePlacements' => $placements]], [
            new AstNode('paragraph', ['text' => 'Before']),
            new AstNode('paragraph', ['text' => 'After']),
        ]);

        $result = (new \PortLibs\Pandoc\PandocMediaExtractor())->extract(
            $document,
            $pdf,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'all']
        );
        $html = PandocConverter::write($result['document'], 'html');
        $dispositions = $result['document']->attr('meta', [])['pdfMediaOccurrenceDispositions'] ?? [];
        $reasons = array_count_values(array_column($dispositions, 'reason'));

        $t->same(50, count($dispositions));
        $t->same(16, count(array_filter($dispositions, static fn (array $record): bool => ($record['disposition'] ?? '') === 'resolved')));
        $t->same(34, $reasons['image-placement-page-limit'] ?? 0);
        $t->same(16, substr_count($html, '<img'));
        $t->same(50, count(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_starts_with($diagnostic, 'extract-media-pdf-occurrence:')
        )));
        $t->true(in_array('extract-media-pdf-image-placement-page-limit:1', $result['diagnostics'], true));
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
    'represents a single image-backed page without exposing its non-painting text layer' => static function (TestRunner $t): void {
        $content = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
            . "BT /F1 12 Tf 3 Tr 72 720 Td (SECRET-INVISIBLE) Tj ET";
        $pageImage = pandoc_layout_fixture_page_image_object();
        $pdf = pandoc_single_page_layout_fixture(
            $content,
            '/Scan 7 0 R',
            [7 => $pageImage]
        );
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $meta = $document->attr('meta', []);

        $t->same(0, count($document->children));
        $t->same('unsupported_no_text', $meta['pdfTextLayerStatus'] ?? null);
        $t->same(true, $meta['pdfNeedsOcr'] ?? null);
        $t->same(1, $meta['pdfTextVisibility']['suppressedNonPaintingRuns'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], array_values($meta['pdfImagePlacements'][0]['pageBox'] ?? []));
        $t->same('MediaBox', $meta['pdfImagePlacements'][0]['pageBoxSource'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['sourceImageHasSoftMask'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['sourceImageHasExplicitMask'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['sourceImageHasOptionalContent'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['sourceImageHasIntent'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['sourceImageHasInterpolate'] ?? null);
        $t->same(1.0, $meta['pdfImagePlacements'][0]['nonStrokingAlpha'] ?? null);
        $t->same(true, $meta['pdfImagePlacements'][0]['graphicsStateBlendModeNormal'] ?? null);
        $t->same(true, $meta['pdfImagePlacements'][0]['pageImageGraphicsStateSafe'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['pageHasGroup'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['pageHasDefaultDeviceColorSpace'] ?? null);
        $t->same(false, $meta['pdfImagePlacements'][0]['requiresCompositing'] ?? null);
        $t->same([], $meta['pdfTextRepresentedPageNumbers'] ?? null);
        $t->same([1], $meta['pdfPagesNeedingImageRepresentation'] ?? null);
        $t->same([], $meta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $meta['pdfPageRepresentationComplete'] ?? null);

        $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
            'extractMedia' => ['destination' => 'media', 'imageMode' => 'important'],
        ]);

        $t->same(1, count($result['media']));
        $t->contains('src="media/pdf/image-7.jpg"', $result['output']);
        $t->contains('alt="PDF page 1 image; editable text unavailable"', $result['output']);
        $t->contains('data-pandoc-pdf-image-placement="page"', $result['output']);
        $t->true(!str_contains($result['output'], 'SECRET-INVISIBLE'));
        $t->true(in_array('extract-media-pdf-page-image-anchor:7:page-1', $result['diagnostics'], true));
        $t->true(in_array('extract-media-pdf-page-image-jpeg-sanitized:7', $result['diagnostics'], true));
        preg_match('/stream\n(.*)\nendstream/s', $pageImage, $sourceImageMatch);
        $sourceImageBytes = $sourceImageMatch[1] ?? '';
        $t->contains('CREATOR', $sourceImageBytes);
        $t->true(($result['media'][0]['sha1'] ?? '') !== sha1($sourceImageBytes));
        $extractedPage = (new \PortLibs\Pandoc\PandocMediaExtractor())->extract(
            $document,
            $pdf,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'important']
        );
        $t->true(!str_contains($extractedPage['entries'][0]['contents'] ?? '', 'CREATOR'));
        $t->same(true, $result['sourceIntegrity']['complete'] ?? null);
        $t->same('unsupported_no_text', $result['sourceIntegrity']['pdfTextLayerStatus'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfNeedsOcr'] ?? null);
        $t->same([1], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
    },
    'keeps a sparse visible stamp after an OCR-backed page image' => static function (TestRunner $t): void {
        $content = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
            . "BT /F1 12 Tf 3 Tr 72 720 Td (HIDDEN-OCR-ONE) Tj "
            . "0 -16 Td (HIDDEN-OCR-TWO) Tj 0 -16 Td (HIDDEN-OCR-THREE) Tj "
            . "0 -16 Td (HIDDEN-OCR-FOUR) Tj 0 Tr 0 -680 Td (Visible source stamp) Tj ET";
        $pdf = pandoc_single_page_layout_fixture(
            $content,
            '/Scan 7 0 R',
            [7 => pandoc_layout_fixture_page_image_object()]
        );
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $meta = $document->attr('meta', []);
        $t->same([1], $meta['pdfTextRepresentedPageNumbers'] ?? null);
        $t->same([1], $meta['pdfPagesNeedingImageRepresentation'] ?? null);
        $t->same([], $meta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $meta['pdfPageRepresentationComplete'] ?? null);

        $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
            'extractMedia' => ['destination' => 'media', 'imageMode' => 'important'],
        ]);
        $output = $result['output'];
        $image = strpos($output, 'data-pandoc-pdf-image-placement="page"');
        $stamp = strpos($output, 'Visible source stamp');

        $t->same(1, count($result['media']));
        $t->true($image !== false && $stamp !== false && $image < $stamp);
        $t->true(!str_contains($output, 'HIDDEN-OCR'));
        $t->true(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_ends_with(
                $diagnostic,
                ':resolved:page-image-with-sparse-visible-overlay'
            )
        ) !== []);
        $t->same('unsupported_no_text', $result['sourceIntegrity']['pdfTextLayerStatus'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfNeedsOcr'] ?? null);
        $t->same([1], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
    },
    'fails closed for page images whose raw stream cannot reproduce page compositing' => static function (TestRunner $t): void {
        $safeImage = pandoc_layout_fixture_page_image_object();
        $invalidJpeg = 'not-a-jpeg';
        $cases = [
            'image-soft-mask' => [
                'image' => pandoc_layout_fixture_page_image_object(' /SMask 8 0 R'),
                'objects' => [8 => pandoc_layout_fixture_page_image_object()],
                'placementKey' => 'sourceImageHasSoftMask',
                'placementValue' => true,
            ],
            'image-explicit-mask' => [
                'image' => pandoc_layout_fixture_page_image_object(' /Mask [0 255 0 255 0 255]'),
                'placementKey' => 'sourceImageHasExplicitMask',
                'placementValue' => true,
            ],
            'image-optional-content-and-visual-overrides' => [
                'image' => pandoc_layout_fixture_page_image_object(
                    ' /OC 8 0 R /Intent /RelativeColorimetric /Interpolate true'
                ),
                'objects' => [8 => '<< /Type /OCG /Name (Optional page raster) >>'],
                'placementKey' => 'sourceImageHasOptionalContent',
                'placementValue' => true,
                'requiresCompositingValue' => false,
            ],
            'graphics-alpha' => [
                'image' => $safeImage,
                'resources' => '/ExtGState << /Unsafe << /ca 0.001 >> >>',
                'graphicsState' => '/Unsafe gs ',
                'placementKey' => 'nonStrokingAlpha',
                'placementValue' => 0.001,
            ],
            'graphics-blend-mode' => [
                'image' => $safeImage,
                'resources' => '/ExtGState << /Unsafe << /BM /Multiply >> >>',
                'graphicsState' => '/Unsafe gs ',
                'placementKey' => 'graphicsStateBlendModeNormal',
                'placementValue' => false,
            ],
            'graphics-transfer-and-overprint' => [
                'image' => $safeImage,
                'resources' => '/ExtGState << /Unsafe << /OP true /TR /Identity >> >>',
                'graphicsState' => '/Unsafe gs ',
                'placementKey' => 'pageImageGraphicsStateSafe',
                'placementValue' => false,
                'requiresCompositingValue' => false,
            ],
            'nondefault-decode' => [
                'image' => pandoc_layout_fixture_page_image_object(' /Decode [1 0 1 0 1 0]'),
            ],
            'decode-parameters' => [
                'image' => pandoc_layout_fixture_page_image_object(
                    ' /DecodeParms << /Predictor 2 /Colors 3 /BitsPerComponent 8 /Columns 100 >>'
                ),
            ],
            'device-cmyk' => [
                'image' => str_replace('/DeviceRGB', '/DeviceCMYK', $safeImage),
            ],
            'invalid-dct-payload' => [
                'image' => '<< /Type /XObject /Subtype /Image /Width 100 /Height 100'
                    . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                    . strlen($invalidJpeg) . ">>\nstream\n" . $invalidJpeg . "\nendstream",
            ],
            'page-annotation' => [
                'image' => $safeImage,
                'objects' => [
                    8 => '<< /Type /Annot /Subtype /Link /Rect [0 0 612 792]'
                        . ' /A << /S /URI /URI (https://example.test/) >> >>',
                ],
                'pageEntries' => '/Annots [8 0 R]',
            ],
            'page-group' => [
                'image' => $safeImage,
                'pageEntries' => '/Group << /S /Transparency /I true /K false >>',
                'placementKey' => 'pageHasGroup',
                'placementValue' => true,
                'requiresCompositingValue' => false,
            ],
            'page-default-device-color-space' => [
                'image' => $safeImage,
                'resources' => '/ColorSpace << /DefaultRGB /DeviceRGB >>',
                'placementKey' => 'pageHasDefaultDeviceColorSpace',
                'placementValue' => true,
                'requiresCompositingValue' => false,
            ],
        ];

        foreach ($cases as $name => $case) {
            $content = 'q ' . ($case['graphicsState'] ?? '') . "612 0 0 792 0 0 cm /Scan Do Q\n"
                . 'BT /F1 12 Tf 3 Tr 72 720 Td (NONPAINTING-OCR) Tj ET';
            $pdf = pandoc_single_page_layout_fixture(
                $content,
                '/Scan 7 0 R',
                [7 => $case['image']] + ($case['objects'] ?? []),
                (string) ($case['resources'] ?? ''),
                (string) ($case['pageEntries'] ?? '')
            );
            $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
            $placement = $document->attr('meta', [])['pdfImagePlacements'][0] ?? [];
            if (isset($case['placementKey'])) {
                $t->same(
                    $case['placementValue'],
                    $placement[$case['placementKey']] ?? null,
                    $name . ' must retain its exact compositing fact.'
                );
                $t->same(
                    $case['requiresCompositingValue'] ?? true,
                    $placement['requiresCompositing'] ?? null,
                    $name . ' must retain the independent image-compositing fact.'
                );
            }

            $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
                'extractMedia' => ['destination' => 'media', 'imageMode' => 'important'],
            ]);

            $t->same([], $result['media'], $name . ' must not expose the raw XObject as a page image.');
            $t->true(
                !str_contains($result['output'], 'data-pandoc-pdf-image-placement="page"'),
                $name . ' must not claim page-image placement.'
            );
            $t->same(false, $result['sourceIntegrity']['complete'] ?? null, $name . ' must fail source integrity.');
            $t->same([], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null, $name . ' has no represented page.');
            $t->same(
                false,
                $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null,
                $name . ' must leave page representation incomplete.'
            );
        }
    },
    'revalidates full page-image occurrence geometry instead of trusting a matching id and object' => static function (TestRunner $t): void {
        $content = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
            . 'BT /F1 12 Tf 3 Tr 72 720 Td (NONPAINTING-OCR) Tj ET';
        $pdf = pandoc_single_page_layout_fixture(
            $content,
            '/Scan 7 0 R',
            [7 => pandoc_layout_fixture_page_image_object()]
        );
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $attrs = $document->attrs;
        $meta = is_array($attrs['meta'] ?? null) ? $attrs['meta'] : [];
        $original = $meta['pdfImagePlacements'][0] ?? [];
        $meta['pdfImagePlacements'][0]['matrix'][0] = 611.9;
        $meta['pdfImagePlacements'][0]['bbox']['x2'] = 611.9;
        $attrs['meta'] = $meta;
        $tampered = new AstNode($document->type, $attrs, $document->children);

        $t->same($original['id'] ?? null, $meta['pdfImagePlacements'][0]['id'] ?? null);
        $t->same($original['object'] ?? null, $meta['pdfImagePlacements'][0]['object'] ?? null);
        $t->true(($original['matrix'][0] ?? null) !== ($meta['pdfImagePlacements'][0]['matrix'][0] ?? null));
        $t->true(($original['bbox']['x2'] ?? null) !== ($meta['pdfImagePlacements'][0]['bbox']['x2'] ?? null));

        $result = (new \PortLibs\Pandoc\PandocMediaExtractor())->extract(
            $tampered,
            $pdf,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'important']
        );
        $resultMeta = $result['document']->attr('meta', []);

        $t->same([], $result['entries']);
        $t->true(!str_contains(PandocConverter::write($result['document'], 'wordpress'), '<img'));
        $t->true(!in_array('extract-media-pdf-page-image-anchor:7:page-1', $result['diagnostics'], true));
        $t->same([], $resultMeta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $resultMeta['pdfPageRepresentationComplete'] ?? null);
    },
    'does not expose a raw page image when later vector paint can hide its pixels' => static function (TestRunner $t): void {
        $content = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
            . "0 0 612 792 re f\n"
            . "BT /F1 12 Tf 3 Tr 72 720 Td (SECRET-BEHIND-PAINT) Tj ET";
        $pdf = pandoc_single_page_layout_fixture(
            $content,
            '/Scan 7 0 R',
            [7 => pandoc_layout_fixture_page_image_object()]
        );
        $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
            'extractMedia' => ['destination' => 'media', 'imageMode' => 'important'],
        ]);

        $t->same([], $result['media']);
        $t->same('', $result['output']);
        $t->true(!in_array('extract-media-pdf-page-image-anchor:7:page-1', $result['diagnostics'], true));
        $t->same(false, $result['sourceIntegrity']['complete'] ?? null);
        $t->same([], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
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
                        'object' => '00007',
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
