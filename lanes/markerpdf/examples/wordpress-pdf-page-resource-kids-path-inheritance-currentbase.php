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
            throw new RuntimeException('Unable to encode catalog-path resource inheritance CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CatalogPathResourceInheritanceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /BranchForm Do Q q /RootForm Do Q';
$branchForm = 'BT /F1 12 Tf 12 24 Td (Catalog path inherited form text) Tj ET';
$rootForm = 'BT /F1 12 Tf 12 24 Td (Root resource form leak) Tj ET';
$branchCMap = $toUnicodeCMap([
    '41' => 'Catalog path inherited font text',
]);
$rootCMap = $toUnicodeCMap([
    '41' => 'Root resource font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CatalogPathInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($branchCMap) . " >>\nstream\n{$branchCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($branchForm) . " >>\nstream\n{$branchForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /RootResourceLeak /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($rootForm) . " >>\nstream\n{$rootForm}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($rootCMap) . " >>\nstream\n{$rootCMap}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /BranchForm 7 0 R >> >>\nendobj\n"
    . "30 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /RootForm 9 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Catalog path inherited font text',
    'Catalog path inherited form text',
];

if ($lines !== $expected || ($resources['resource_owner_object'] ?? null) !== 10) {
    throw new RuntimeException('Expected catalog /Kids path resources to be inherited before WordPress paragraph rendering.');
}

echo '<!-- markerpdf-page-resource-kids-path-inheritance-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-kids-path-inheritance-currentbase',
    'native_boundary' => 'catalog /Kids traversal supplies inherited /Resources when a reachable page omits /Parent',
    'catalog_kids_path_inherits_resources' => $lines === $expected,
    'nearest_pages_resource_owner_selected' => ($resources['resource_owner_object'] ?? null) === 10,
    'nearest_resource_object_selected' => ($resources['resource_object'] ?? null) === 20,
    'root_resource_decoy_excluded' => !str_contains($plainText, 'Root resource'),
    'root_xobject_decoy_uninvoked' => !str_contains($plainText, 'RootForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
