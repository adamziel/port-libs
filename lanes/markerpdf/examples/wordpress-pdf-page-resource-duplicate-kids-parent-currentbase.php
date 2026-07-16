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
            throw new RuntimeException('Unable to encode duplicate Kids parent-chain CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressDuplicateKidsParentCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q q /DecoyForm Do Q';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Current duplicate parent form text) Tj ET';
$decoyForm = 'BT /F1 12 Tf 12 24 Td (First duplicate parent form leak) Tj ET';
$currentCMap = $toUnicodeCMap([
    '41' => 'Current duplicate parent font text',
]);
$decoyCMap = $toUnicodeCMap([
    '41' => 'First duplicate parent font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 1 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FirstDuplicateParentFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($decoyCMap) . " >>\nstream\n{$decoyCMap}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($decoyForm) . " >>\nstream\n{$decoyForm}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /DecoyForm 11 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CurrentForm 7 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$resources = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf)[0]['resources'] ?? [];
$expected = [
    'Current duplicate parent font text',
    'Current duplicate parent form text',
];

if ($lines !== $expected || ($resources['resource_owner_object'] ?? null) !== 20) {
    throw new RuntimeException('Expected explicit page Parent lineage to win over earlier duplicate catalog Kids branch.');
}

echo '<!-- markerpdf-page-resource-duplicate-kids-parent-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-kids-parent-currentbase',
    'native_boundary' => 'duplicate catalog Kids entries are resolved through the explicit page Parent chain before WordPress paragraph rendering',
    'explicit_parent_lineage_selected' => ($resources['resource_owner_object'] ?? null) === 20,
    'current_resource_object_selected' => ($resources['resource_object'] ?? null) === 40,
    'current_font_and_form_selected' => $lines === $expected,
    'first_duplicate_parent_resource_excluded' => !str_contains($plainText, 'First duplicate parent'),
    'decoy_form_uninvoked' => !str_contains($plainText, 'DecoyForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
