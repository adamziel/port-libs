<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Base resource wrapper text) Tj ET '
    . '/OC /HiddenWrapped BDC BT /F1 12 Tf 72 704 Td (Hidden wrapped layer text) Tj ET q /InheritedForm Do Q EMC '
    . '/OC /VisibleWrapped BDC BT /F1 12 Tf 72 688 Td (Visible wrapped layer text) Tj ET EMC '
    . 'q /InheritedForm Do Q';
$formContent = '/OC /HiddenWrapped BDC BT /F1 12 Tf 12 24 Td (Hidden wrapped form text) Tj ET EMC '
    . '/OC /VisibleWrapped BDC BT /F1 12 Tf 12 12 Td (Visible wrapped form text) Tj ET EMC';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /InheritedForm 6 0 R >> /Properties << /VisibleWrapped 30 0 R /HiddenWrapped 31 0 R >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Visible Wrapped Layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Hidden Wrapped Layer) >>\nendobj\n"
    . "30 0 obj\n20 0 R\nendobj\n"
    . "31 0 obj\n21 0 R\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Base resource wrapper text',
    'Visible wrapped layer text',
    'Visible wrapped form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected wrapped optional-content page resource properties to hide review-only layer text.');
}

echo '<!-- markerpdf-page-resource-optional-content-wrapper-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-optional-content-wrapper-currentbase',
    'native_boundary' => 'inherited /Resources /Properties entries unwrap exact OCG references before page and Form marked-content visibility filtering',
    'wrapped_visible_layer_imported' => str_contains($plainText, 'Visible wrapped layer text'),
    'wrapped_visible_form_imported' => str_contains($plainText, 'Visible wrapped form text'),
    'wrapped_hidden_layer_excluded' => !str_contains($plainText, 'Hidden wrapped layer text'),
    'wrapped_hidden_form_excluded' => !str_contains($plainText, 'Hidden wrapped form text'),
    'resource_property_names_excluded_from_paragraphs' => !str_contains($plainText, 'VisibleWrapped')
        && !str_contains($plainText, 'HiddenWrapped'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
