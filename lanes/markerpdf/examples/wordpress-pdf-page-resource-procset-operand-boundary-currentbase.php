<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$directContent = 'BT /F1 12 Tf 72 720 Td (Direct resource operand boundary text) Tj ET';
$indirectContent = 'BT /F2 12 Tf 72 720 Td (Indirect resource operand boundary text) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 2 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [4 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($indirectContent) . " >>\nstream\n{$indirectContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "30 0 obj\n<< /ProcSet [/PDF /Text /ImageB] 99 0 R /Font << /F1 7 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /ProcSet 41 0 R /Font << /F2 7 0 R >> >>\nendobj\n"
    . "41 0 obj\n[/PDF /ImageC /Text] 98 0 R\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$firstResources = $boundary[0]['resources'] ?? [];
$secondResources = $boundary[1]['resources'] ?? [];
$expectedLines = [
    'Direct resource operand boundary text',
    'Indirect resource operand boundary text',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected visible inherited-resource page text to be preserved.');
}

if (($firstResources['procset_names'] ?? null) !== null || ($secondResources['procset_names'] ?? null) !== null) {
    throw new RuntimeException('Expected tailed ProcSet arrays to be excluded from review metadata.');
}

echo '<!-- markerpdf-page-resource-procset-operand-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-procset-operand-boundary-currentbase',
    'native_boundary' => 'tailed inherited /Resources /ProcSet arrays are rejected from review metadata while valid Font resources still drive visible WordPress text',
    'visible_text_preserved' => $lines === $expectedLines,
    'direct_procset_names_excluded' => ($firstResources['procset_names'] ?? null) === null,
    'indirect_procset_names_excluded' => ($secondResources['procset_names'] ?? null) === null,
    'font_resources_preserved' => ($firstResources['font_names'] ?? null) === ['F1']
        && ($secondResources['font_names'] ?? null) === ['F2'],
    'resource_names_excluded_from_paragraphs' => !str_contains($plainText, 'ProcSet')
        && !str_contains($plainText, 'ImageB')
        && !str_contains($plainText, 'ImageC'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
