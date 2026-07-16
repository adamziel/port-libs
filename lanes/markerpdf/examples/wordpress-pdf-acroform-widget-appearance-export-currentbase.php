<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible button export review body) Tj ET';
$fastAppearance = 'BT /FApp 9 Tf 0 0 Td (Fast appearance stream text) Tj ET';
$yesAppearance = 'BT /FApp 9 Tf 0 0 Td (Consent appearance stream text) Tj ET';
$offAppearance = 'q Q';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 14 0 R 18 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Btn /T (shipping.speed) /Ff 49152 /V /Fast /DV /Ground /Opt [(Ground delivery export) (Express delivery export)] /Kids [8 0 R 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 96 664] /P 3 0 R /F 4 /AS /Off /AP << /N << /Ground 31 0 R /Off 33 0 R >> >> >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [108 640 132 664] /P 3 0 R /F 4 /AS /Fast /AP << /N << /Fast 30 0 R /Off 33 0 R >> >> >>\nendobj\n"
    . "14 0 obj\n<< /FT /Btn /T (newsletter.consent) /DV /Yes /Opt [(Newsletter consent export)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 600 96 624] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 32 0 R /Off 33 0 R >> >> >>\nendobj\n"
    . "18 0 obj\n<< /FT /Btn /T (actions.export) /Ff 65536 /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 560 210 584] /P 3 0 R /F 4 /A << /S /SubmitForm /F (https://example.test/form-export) /Fields [6 0 R 14 0 R] /Flags 4 >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /FApp 40 0 R >> >> /Length " . strlen($fastAppearance) . " >>\nstream\n{$fastAppearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /FApp 40 0 R >> >> /Length " . strlen($yesAppearance) . " >>\nstream\n{$yesAppearance}\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$fields = [];
foreach ((new PdfAcroFormExtractor())->extractFields($pdf) as $field) {
    $fields[(string) ($field['name'] ?? '')] = $field;
}

$shipping = $fields['shipping.speed'] ?? null;
$consent = $fields['newsletter.consent'] ?? null;
$action = $fields['actions.export']['widgets'][0]['actions'][0] ?? null;
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (!is_array($shipping) || !is_array($consent) || !is_array($action)) {
    throw new RuntimeException('Expected AcroForm export fields and submit action.');
}

$shippingExport = $shipping['value_state']['effective_export_value'] ?? null;
$consentExport = $consent['value_state']['effective_export_value'] ?? null;
$submitRows = [];
foreach ($action['field_value_review']['field_rows'] ?? [] as $row) {
    $submitRows[(string) ($row['field_name'] ?? '')] = $row;
}

if ($shippingExport !== 'Express delivery export' || $consentExport !== 'Newsletter consent export') {
    throw new RuntimeException('Expected button /Opt export values in current-base review.');
}
if (($submitRows['shipping.speed']['submit_value'] ?? null) !== 'Express delivery export') {
    throw new RuntimeException('Expected SubmitForm review to use selected radio export value.');
}
if (str_contains($plainText, 'Express delivery export') || str_contains($plainText, 'Newsletter consent export') || str_contains($plainText, 'form-export')) {
    throw new RuntimeException('Export labels or SubmitForm target leaked into visible text.');
}

echo '<!-- markerpdf:pdf-acroform-widget-appearance-export-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-appearance-export-currentbase',
    'native_boundary' => 'AcroForm button /Opt export values are review and submit metadata; field /V and widget /AS remain separate current-base state evidence',
    'shipping_current_state' => $shipping['value_state']['effective_current_state'] ?? null,
    'shipping_export_value' => $shippingExport,
    'shipping_export_source' => $shipping['value_state']['export_value_source'] ?? null,
    'consent_current_state' => $consent['value_state']['effective_current_state'] ?? null,
    'consent_export_value' => $consentExport,
    'submit_shipping_value' => $submitRows['shipping.speed']['submit_value'] ?? null,
    'visible_text' => $plainText,
    'export_labels_excluded_from_visible_text' => true,
    'appearance_value_used_for_import' => false,
    'export_value_used_for_import' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Shipping current state %s exports as %s',
    (string) ($shipping['value_state']['effective_current_state'] ?? ''),
    (string) $shippingExport
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Consent widget exports as %s without leaking export labels into visible text',
    (string) $consentExport
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
