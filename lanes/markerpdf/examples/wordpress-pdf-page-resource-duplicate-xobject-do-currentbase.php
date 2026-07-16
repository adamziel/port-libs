<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Duplicate XObject page text) Tj ET '
    . 'q /DupForm Do Q q /ValidForm Do Q';
$staleForm = 'BT /F1 12 Tf 12 24 Td (Stale duplicate XObject form leak) Tj ET';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Current duplicate XObject form leak) Tj ET';
$validForm = 'BT /F1 12 Tf 12 24 Td (Valid inherited XObject form text) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($validForm) . " >>\nstream\n{$validForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /DupForm 6 0 R /DupForm 7 0 R /ValidForm 8 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Duplicate XObject page text',
    'Valid inherited XObject form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected duplicate inherited XObject names to be excluded before WordPress paragraph import.');
}

if (str_contains($plainText, 'Stale duplicate XObject form leak')
    || str_contains($plainText, 'Current duplicate XObject form leak')
    || str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'DupForm')
) {
    throw new RuntimeException('Ambiguous inherited XObject resource name leaked into visible text or resource review metadata.');
}

echo '<!-- markerpdf-page-resource-duplicate-xobject-do-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-xobject-do-currentbase',
    'native_boundary' => 'duplicate inherited /XObject resource names are rejected before Form XObject Do expansion',
    'inherited_page_resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'xobject_names' => $resources['xobject_names'] ?? [],
    'duplicate_xobject_name_excluded' => !str_contains($plainText, 'duplicate XObject form leak')
        && !str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'DupForm'),
    'valid_xobject_name_retained' => in_array('ValidForm', $resources['xobject_names'] ?? [], true),
    'visible_paragraph_count' => count($lines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
