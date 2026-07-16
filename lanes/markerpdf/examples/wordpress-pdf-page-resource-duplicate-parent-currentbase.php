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
            throw new RuntimeException('Unable to encode duplicate Parent resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressDuplicateParentResourceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q q /DetachedForm Do Q';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Duplicate Parent current form text) Tj ET';
$detachedForm = 'BT /F1 12 Tf 12 24 Td (Duplicate Parent detached form leak) Tj ET';
$currentCMap = $toUnicodeCMap([
    '41' => 'Duplicate Parent current font text',
]);
$detachedCMap = $toUnicodeCMap([
    '41' => 'Duplicate Parent detached font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 99 0 R /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DuplicateParentCurrentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DuplicateParentDetachedFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($detachedCMap) . " >>\nstream\n{$detachedCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CurrentForm 7 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($detachedForm) . " >>\nstream\n{$detachedForm}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /DetachedForm 11 0 R >> >>\nendobj\n"
    . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$resources = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf)[0]['resources'] ?? [];
$expected = [
    'Duplicate Parent current font text',
    'Duplicate Parent current form text',
];

if ($lines !== $expected || ($resources['resource_owner_object'] ?? null) !== 2) {
    throw new RuntimeException('Expected last duplicate page Parent to resolve inherited page resources for WordPress import.');
}

echo '<!-- markerpdf-page-resource-duplicate-parent-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-parent-currentbase',
    'native_boundary' => 'last duplicate page Parent selects the catalog page-tree resource owner before WordPress paragraph rendering',
    'last_parent_lineage_selected' => ($resources['resource_owner_object'] ?? null) === 2,
    'current_resource_object_selected' => ($resources['resource_object'] ?? null) === 10,
    'current_font_and_form_selected' => $lines === $expected,
    'detached_parent_resource_excluded' => !str_contains($plainText, 'Duplicate Parent detached'),
    'detached_form_uninvoked' => !str_contains($plainText, 'DetachedForm'),
    'payload_in_visible_text' => str_contains($plainText, 'PageResourceDuplicateParentCurrentBaseCMap'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
