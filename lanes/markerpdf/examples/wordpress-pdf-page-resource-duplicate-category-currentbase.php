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
            throw new RuntimeException('Unable to encode WordPress duplicate resource category CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceDuplicateCategoryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /DupActual BDC <42> Tj EMC ET q /CurrentForm Do Q q /StaleForm Do Q';
$staleForm = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
$currentForm = 'BT /F1 12 Tf 12 24 Td <44> Tj ET';
$staleCMap = $toUnicodeCMap([
    '41' => 'Stale duplicate category font leak',
    '42' => 'Stale duplicate category actual glyph leak',
    '43' => 'Stale duplicate category form leak',
]);
$currentCMap = $toUnicodeCMap([
    '41' => 'Current duplicate category font text',
    '42' => 'Current duplicate category actual glyph',
    '44' => 'Current duplicate category form text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleDuplicateCategoryFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /ActualText (Stale duplicate category ActualText leak) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateCategoryFont /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "13 0 obj\n<< /ActualText (Current duplicate category ActualText) >>\nendobj\n"
    . "10 0 obj\n<< "
    . "/Font << /F1 5 0 R >> "
    . "/Font << /F1 9 0 R >> "
    . "/XObject << /CurrentForm 7 0 R /StaleForm 7 0 R >> "
    . "/XObject << /CurrentForm 11 0 R >> "
    . "/Properties << /DupActual 8 0 R >> "
    . "/Properties << /DupActual 13 0 R >> "
    . ">>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Current duplicate category font text',
    'Current duplicate category ActualText',
    'Current duplicate category form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected current duplicate resource categories to drive WordPress paragraphs.');
}

echo '<!-- markerpdf-page-resource-duplicate-category-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-category-currentbase',
    'native_boundary' => 'current duplicate /Resources category dictionaries are selected before inherited text, marked-content, and Form XObject lookup',
    'page_resource_inherited' => ($resources['inherited'] ?? null) === true,
    'page_resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'current_font_category_selected' => ($resources['font_names'] ?? []) === ['F1'],
    'current_xobject_category_selected' => ($resources['xobject_names'] ?? []) === ['CurrentForm'],
    'current_properties_category_selected' => ($resources['properties_names'] ?? []) === ['DupActual'],
    'stale_category_text_excluded' => !str_contains($plainText, 'Stale duplicate category'),
    'stale_category_resource_name_excluded' => !str_contains($plainText, 'StaleForm'),
    'raw_actual_glyph_excluded' => !str_contains($plainText, 'Current duplicate category actual glyph'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
