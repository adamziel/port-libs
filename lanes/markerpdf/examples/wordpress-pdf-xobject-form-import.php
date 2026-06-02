<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Page Before Form) Tj ET q /Fm1 Do Q BT /F1 12 Tf 72 672 Td (Page After Form) Tj ET';
$formContent = 'BT /F1 12 Tf 12 24 Td (Reusable Form Block) Tj T* (Imported Once) Tj ET';
$unusedFormContent = 'BT /F1 12 Tf 72 720 Td (Dormant Form Text) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> /XObject << /Fm1 5 0 R /FmUnused 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($unusedFormContent) . " >>\nstream\n{$unusedFormContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xobject-form-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page /Contents referenced /Subtype /Form XObject invocation before Gutenberg paragraph rendering',
    'invoked_form_text_imported' => str_contains($plainText, 'Reusable Form Block'),
    'excluded_unreferenced_form_text' => !str_contains($plainText, 'Dormant Form Text'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
