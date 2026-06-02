<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Page Before Matrix Form) Tj ET q 1 0 0 1 24 0 cm /FmScaled Do Q BT /F1 12 Tf 72 672 Td (Page After Matrix Form) Tj ET';
$formContent = 'BT /F1 12 Tf (Origin Hidden) Tj 1 0 0 1 0 24 Tm (Data) Tj 1 0 0 1 34 24 Tm (base) Tj 1 0 0 1 10 90 Tm (BBox Noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> /XObject << /FmScaled 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [-5 1 80 50] /Matrix [2 0 0 1 0 0] /Resources << /Font << /F1 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xobject-form-matrix-bbox-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'invoked /Subtype /Form XObject current graphics-state matrix, form /Matrix, and form-local /BBox clipping before Gutenberg paragraph rendering',
    'form_matrix_spacing_preserved' => str_contains($plainText, 'Data base') && !str_contains($plainText, 'Database'),
    'form_bbox_clipped_hidden_text' => !str_contains($plainText, 'BBox Noise') && !str_contains($plainText, 'Origin Hidden'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
