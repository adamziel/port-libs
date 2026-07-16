<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Valid resource dict font text) Tj ET '
    . 'q /CurrentForm Do Q q /StaleForm Do Q';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Valid resource dict form text) Tj ET';
$staleForm = 'BT /F1 12 Tf 12 24 Td (Top-level Resources decoy form leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CurrentForm 6 0 R >> "
    . "/Resources << /Font << /F1 7 0 R >> /XObject << /StaleForm 8 0 R >> >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Valid resource dict font text',
    'Valid resource dict form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected inherited page resources to render valid WordPress paragraphs.');
}

if (($resources['categories'] ?? []) !== ['Font', 'XObject']) {
    throw new RuntimeException('Expected decoy /Resources key to be excluded from page resource review categories.');
}

echo '<!-- markerpdf-page-resource-decoy-resources-category-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-decoy-resources-category-currentbase',
    'native_boundary' => 'inherited page resource dictionaries ignore invalid top-level /Resources decoys in review categories while valid Font and XObject categories drive WordPress paragraphs',
    'page_resource_inherited' => ($resources['inherited'] ?? null) === true,
    'page_resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'page_resource_object' => $resources['resource_object'] ?? null,
    'resource_categories' => $resources['categories'] ?? [],
    'decoy_resources_category_excluded' => !in_array('Resources', $resources['categories'] ?? [], true),
    'current_xobject_selected' => ($resources['xobject_names'] ?? []) === ['CurrentForm'],
    'stale_form_text_excluded' => !str_contains($plainText, 'Top-level Resources decoy form leak'),
    'stale_form_resource_name_excluded' => !str_contains($plainText, 'StaleForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
