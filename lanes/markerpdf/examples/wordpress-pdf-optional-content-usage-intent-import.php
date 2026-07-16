<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Base Current View) Tj ET '
    . '/OC /DesignOnly BDC BT /F1 12 Tf 72 704 Td (Design Layer Noise) Tj ET EMC '
    . '/OC /ViewUsageOff BDC BT /F1 12 Tf 72 688 Td (Usage Hidden Text) Tj ET EMC '
    . '/OC /ViewUsageOn BDC BT /F1 12 Tf 72 672 Td (Usage View Visible) Tj ET q /VisibleUsageForm Do Q EMC '
    . '/OC /ConfigOff BDC BT /F1 12 Tf 72 656 Td (Off Array Usage Ignored) Tj ET EMC '
    . '/OC /MixedIntent BDC BT /F1 12 Tf 72 640 Td (Mixed Intent Visible) Tj ET EMC '
    . '/OC /AllOnMembership BDC BT /F1 12 Tf 72 624 Td (Membership Hidden Text) Tj ET EMC';
$visibleForm = 'BT /F1 12 Tf 12 24 Td (Visible Usage Form) Tj ET';
$hiddenForm = 'BT /F1 12 Tf 12 24 Td (Hidden Usage Form) Tj ET';
$visibleAnnotation = 'BT /F1 12 Tf 0 0 Td (Visible Usage Annotation) Tj ET';
$hiddenAnnotation = 'BT /F1 12 Tf 0 0 Td (Hidden Usage Annotation) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R 22 0 R 24 0 R 25 0 R] /D << /Intent /View /BaseState /ON /OFF [24 0 R] /AS [<< /Event /View /Category [/View] /OCGs [21 0 R 22 0 R] >> << /Event /Print /Category [/View] /OCGs [20 0 R] >>] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /DesignOnly 20 0 R /ViewUsageOff 21 0 R /ViewUsageOn 22 0 R /ConfigOff 24 0 R /MixedIntent 25 0 R /AllOnMembership << /Type /OCMD /OCGs [20 0 R 22 0 R] /P /AllOn >> >> /XObject << /VisibleUsageForm 8 0 R /HiddenUsageForm 9 0 R >> >> /Annots [10 0 R 11 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /OC 22 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($visibleForm) . " >>\nstream\n{$visibleForm}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /OC 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($hiddenForm) . " >>\nstream\n{$hiddenForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Widget /OC 22 0 R /AP << /N 12 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Widget /OC 21 0 R /AP << /N 13 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($visibleAnnotation) . " >>\nstream\n{$visibleAnnotation}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($hiddenAnnotation) . " >>\nstream\n{$hiddenAnnotation}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Design Only Layer) /Intent /Design /Usage << /View << /ViewState /ON >> >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (View Usage Hidden) /Intent /View /Usage << /View << /ViewState /OFF >> >> >>\nendobj\n"
    . "22 0 obj\n<< /Type /OCG /Name (View Usage Visible) /Intent /View /Usage << /View << /ViewState /ON >> >> >>\nendobj\n"
    . "24 0 obj\n<< /Type /OCG /Name (Config Off Layer) /Intent /View /Usage << /View << /ViewState /ON >> >> >>\nendobj\n"
    . "25 0 obj\n<< /Type /OCG /Name (Mixed Intent Layer) /Intent [/Design /View] >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-optional-content-usage-intent-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog OCProperties default config /Intent, /AS current-view usage application state, OCG /Usage /ViewState, OCMD membership, Form XObject /OC, and annotation /OC visibility',
    'visible_usage_layer_imported' => str_contains($plainText, 'Usage View Visible'),
    'visible_xobject_imported' => str_contains($plainText, 'Visible Usage Form'),
    'visible_annotation_imported' => str_contains($plainText, 'Visible Usage Annotation'),
    'design_intent_excluded' => !str_contains($plainText, 'Design Layer Noise'),
    'usage_off_excluded' => !str_contains($plainText, 'Usage Hidden Text'),
    'unused_usage_does_not_override_off_array' => !str_contains($plainText, 'Off Array Usage Ignored'),
    'membership_policy_honors_intent_state' => !str_contains($plainText, 'Membership Hidden Text'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
