<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = "BT /F1 12 Tf 72 720 Td (Page before malformed form) Tj ET\n"
    . "q /BrokenForm Do Q\n"
    . "BT /F1 12 Tf 72 700 Td (Page after malformed form) Tj ET";
$brokenForm = 'q /PrivateForm Do Q';
$privateForm = 'BT /F1 12 Tf 12 24 Td (Private malformed form resource leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> /XObject << /BrokenForm 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 99 0 R /XObject << /PrivateForm 9 0 R >> /Length " . strlen($brokenForm) . " >>\nstream\n{$brokenForm}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = [
    'Page before malformed form',
    'Page after malformed form',
];

if ($lines !== $expected || str_contains($plainText, 'Private malformed form resource leak')) {
    throw new RuntimeException('Expected malformed Form XObject /Resources to block decoy private resources.');
}

echo '<!-- markerpdf-page-resource-form-malformed-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Form XObject declared malformed /Resources fail closed before top-level decoy resource keys are promoted',
    'malformed_form_resources_blocked' => true,
    'private_form_resource_promoted' => str_contains($plainText, 'Private malformed form resource leak'),
    'paragraph_count' => count($lines),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
