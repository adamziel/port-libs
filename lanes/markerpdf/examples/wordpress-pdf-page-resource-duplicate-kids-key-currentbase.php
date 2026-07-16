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
            throw new RuntimeException('Unable to encode duplicate Kids key smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressDuplicateKidsKeyResourceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$staleContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET q /StaleForm Do Q';
$currentContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Current duplicate Kids form text) Tj ET';
$staleForm = 'BT /F1 12 Tf 12 24 Td (Stale duplicate Kids form leak) Tj ET';
$currentCMap = $toUnicodeCMap([
    '41' => 'Current duplicate Kids inherited text',
]);
$staleCMap = $toUnicodeCMap([
    '42' => 'Stale duplicate Kids resource leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Kids [20 0 R] /Count 1 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [4 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateKidsFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleDuplicateKidsFont /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Font << /F1 11 0 R >> /XObject << /StaleForm 13 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /CurrentForm 9 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

$expected = [
    'Current duplicate Kids inherited text',
    'Current duplicate Kids form text',
];
if ($lines !== $expected) {
    throw new RuntimeException('Expected the current duplicate page-tree Kids branch to provide WordPress paragraphs.');
}

if (($resources['resource_owner_object'] ?? null) !== 20 || ($resources['resource_object'] ?? null) !== 40) {
    throw new RuntimeException('Expected inherited resources from the current duplicate Kids branch.');
}

echo '<!-- markerpdf-page-resource-duplicate-kids-key-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-kids-key-currentbase',
    'native_boundary' => 'duplicate top-level page-tree /Kids keys use the current branch before inherited resource lookup',
    'last_kids_branch_selected' => ($resources['resource_owner_object'] ?? null) === 20,
    'current_resource_object_selected' => ($resources['resource_object'] ?? null) === 40,
    'current_text_imported' => $lines === $expected,
    'stale_first_kids_branch_excluded' => !str_contains($plainText, 'Stale duplicate Kids resource leak'),
    'stale_first_form_excluded' => !str_contains($plainText, 'Stale duplicate Kids form leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
