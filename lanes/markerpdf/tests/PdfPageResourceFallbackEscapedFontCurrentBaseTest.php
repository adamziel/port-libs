<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceFallbackEscapedFontCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode page-resource fallback escaped-font CMap text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceFallbackEscapedFontCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceFallbackEscapedFontPdf = static function () use ($pageResourceFallbackEscapedFontCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cmap = $pageResourceFallbackEscapedFontCMap('Escaped fallback font text');

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Ty#70e /F#6fnt /Subtype /Type0 /BaseFont /EscapedFallback /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'uses escaped single font dictionaries for page-resource fallback text extraction' => static function (TestRunner $t) use ($pageResourceFallbackEscapedFontPdf): void {
        $pdf = $pageResourceFallbackEscapedFontPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledText = implode('', array_column($styledPages[0]['blocks'][0]['lines'][0]['spans'] ?? [], 'text'));
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = ['Escaped fallback font text'];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same('Escaped fallback font text', $plainText);
        $t->same("Escaped fallback font text\n", $extractor->naiveGetText($pdf));
        $t->same('Escaped fallback font text', $styledText);
        $t->same([], $resourceMetadata);
        $t->same(false, str_contains($plainText, 'A'));
        $t->same(1, substr_count($plainText, 'Escaped fallback font text'));
    },
];
