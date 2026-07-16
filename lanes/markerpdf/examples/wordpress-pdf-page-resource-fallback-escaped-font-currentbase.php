<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode escaped fallback font CMap text.');
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
        . "CMapName currentdict /WordPressEscapedFallbackFontCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pdfWithEscapedFallbackFont = static function () use ($toUnicodeCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cmap = $toUnicodeCMap('Escaped fallback font text');

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Ty#70e /F#6fnt /Subtype /Type0 /BaseFont /EscapedFallback /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$pdf = $pdfWithEscapedFallbackFont();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$resourceMetadata = $boundary[0]['resources'] ?? [];
$expectedLines = ['Escaped fallback font text'];

if ($lines !== $expectedLines || $plainText !== 'Escaped fallback font text' || str_contains($plainText, 'A')) {
    throw new RuntimeException('Expected escaped /Type /Font fallback CMap text before WordPress paragraph extraction.');
}

echo '<!-- markerpdf-page-resource-fallback-escaped-font-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-fallback-escaped-font-currentbase',
    'native_boundary' => 'single-font fallback decodes escaped /Type /Font names when page resources are absent',
    'escaped_type_font_fallback_mapped' => $lines === $expectedLines,
    'page_resources_absent' => $resourceMetadata === [],
    'raw_source_glyph_excluded' => !str_contains($plainText, 'A'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
