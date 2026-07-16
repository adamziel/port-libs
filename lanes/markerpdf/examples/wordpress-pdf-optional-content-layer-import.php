<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Base Visible Text) Tj ET '
    . '/OC /LayerOff BDC BT /F1 12 Tf 72 704 Td (Hidden Layer Text) Tj ET q /VisibleForm Do Q EMC '
    . '/OC /LayerOn BDC BT /F1 12 Tf 72 688 Td (Layer Visible Text) Tj ET q /HiddenForm Do Q EMC '
    . 'q /VisibleForm Do Q q /HiddenForm Do Q';
$visibleForm = 'BT /F1 12 Tf 12 24 Td (Visible Form Text) Tj ET';
$hiddenForm = 'BT /F1 12 Tf 12 24 Td (Hidden Form Text) Tj ET';

$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /LayerOn 20 0 R /LayerOff 21 0 R >> /XObject << /VisibleForm 8 0 R /HiddenForm 9 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /OC 20 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($visibleForm) . " >>\nstream\n{$visibleForm}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /OC 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($hiddenForm) . " >>\nstream\n{$hiddenForm}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Visible Import Layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Hidden Review Layer) >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-optional-content-layer-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog OCProperties default view state plus page Properties marked-content and Form XObject OC visibility',
    'visible_layer_imported' => str_contains($plainText, 'Layer Visible Text'),
    'visible_xobject_imported' => str_contains($plainText, 'Visible Form Text'),
    'hidden_layer_excluded' => !str_contains($plainText, 'Hidden Layer Text'),
    'hidden_xobject_excluded' => !str_contains($plainText, 'Hidden Form Text'),
    'default_base_off_on_override' => true,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
