<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode page-resource category-tail CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceCategoryTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /TailActual BDC <42> Tj EMC ET q /TailForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Valid category-tail font text',
    '42' => 'Physical category-tail property glyph',
    '43' => 'Malformed category-tail form leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CategoryTailFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "8 0 obj\n<< /ActualText (Malformed category-tail ActualText leak) >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /TailForm 7 0 R >> 99 0 R /Properties << /TailActual 8 0 R >> 98 0 R >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expectedLines = [
    'Valid category-tail font text',
    'Physical category-tail property glyph',
];

if ($lines !== $expectedLines
    || ($resources['resource_object'] ?? null) !== 10
    || ($resources['font_names'] ?? null) !== ['F1']
    || array_key_exists('xobject_names', $resources)
    || array_key_exists('properties_names', $resources)
) {
    throw new RuntimeException('Expected malformed resource category tails to fail closed before WordPress paragraph import.');
}

echo '<!-- markerpdf-page-resource-category-tail-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-category-tail-boundary-currentbase',
    'native_boundary' => 'valid inherited page /Resources font categories remain usable while direct XObject and Properties category dictionaries with trailing non-name tokens fail closed',
    'resource_object' => $resources['resource_object'] ?? null,
    'resource_inherited' => $resources['inherited'] ?? null,
    'font_category_preserved' => ($resources['font_names'] ?? null) === ['F1'],
    'xobject_category_tail_rejected' => !array_key_exists('xobject_names', $resources),
    'properties_category_tail_rejected' => !array_key_exists('properties_names', $resources),
    'category_tail_form_excluded' => !str_contains($plainText, 'Malformed category-tail form leak'),
    'category_tail_actual_text_excluded' => !str_contains($plainText, 'Malformed category-tail ActualText leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
