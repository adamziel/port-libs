<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible widget action resource appearance body) Tj ET';
$appearance = 'q /Icon Do /Mask Do Q BT /FApp 9 Tf 0 0 Td (Selected widget appearance review) Tj ET';
$script = "app.alert('appearance resource action blocked');";
$compressedScript = gzcompress($script);
if (!is_string($compressedScript)) {
    throw new RuntimeException('Unable to compress appearance resource script.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /DA (/FApp 9 Tf 0 0 0 rg) /DR << /Font << /FApp 32 0 R >> >> >>\nendobj\n"
    . "6 0 obj\n<< /FT /Btn /T (article.appearance_resource) /V /Yes /DV /Off /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 96 664] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 30 0 R /Off 31 0 R >> >> /A 20 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://example.test/widget-activation) >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Resources << /Font << /FApp 32 0 R >> /XObject << /Icon 35 0 R /Mask 36 0 R >> >> /Length " . strlen($appearance) . " >>\nstream\n{$appearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "35 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /A 50 0 R /AA << /D 53 0 R >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "36 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>\nstream\nx\nendstream\nendobj\n"
    . "50 0 obj\n<< /S /JavaScript /JS 52 0 R /Next 51 0 R >>\nendobj\n"
    . "51 0 obj\n<< /S /URI /URI (javascript:appearanceReview()) >>\nendobj\n"
    . "52 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
    . "53 0 obj\n<< /S /Hide /T [8 0 R] /H true >>\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$field = $fields[0] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected one AcroForm field.');
}

$widget = $field['widgets'][0] ?? null;
$selected = is_array($widget)
    ? ($widget['normal_appearance']['selected_appearance'] ?? null)
    : null;
if (!is_array($selected)) {
    throw new RuntimeException('Expected a selected widget appearance review.');
}

$resourceReviews = is_array($selected['resource_xobject_reviews'] ?? null)
    ? $selected['resource_xobject_reviews']
    : [];
$iconReview = $resourceReviews[0] ?? null;
$text = (new PdfTextExtractor())->extractPlainText($pdf);

if (!is_array($iconReview) || ($iconReview['action_count'] ?? 0) !== 3) {
    throw new RuntimeException('Expected nested appearance resource action review rows.');
}
if (($selected['resource_xobject_action_count'] ?? 0) !== 3) {
    throw new RuntimeException('Expected selected appearance action summary.');
}
if (str_contains($text, 'appearance resource action blocked') || str_contains($text, 'javascript:appearanceReview')) {
    throw new RuntimeException('Appearance resource action payload leaked into visible text.');
}

echo '<!-- markerpdf:pdf-acroform-widget-action-resource-appearance-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-action-resource-appearance-currentbase',
    'native_boundary' => 'Selected AcroForm widget appearance resource XObjects expose nested /A and /AA action dictionaries as review-only metadata; field /V remains authoritative during WordPress import',
    'field_name' => $field['name'] ?? null,
    'field_value' => $field['value'] ?? null,
    'appearance_state' => $widget['appearance_state'] ?? null,
    'selected_appearance_object' => $selected['object'] ?? null,
    'resource_xobject_names' => $selected['resource_xobject_names'] ?? [],
    'resource_xobject_action_count' => $selected['resource_xobject_action_count'] ?? null,
    'resource_xobject_action_types' => $selected['resource_xobject_action_types'] ?? [],
    'resource_xobject_action_objects' => $selected['resource_xobject_action_objects'] ?? [],
    'icon_action_count' => $iconReview['action_count'] ?? null,
    'icon_action_types' => $iconReview['action_types'] ?? [],
    'visible_text' => $text,
    'field_value_authoritative' => true,
    'appearance_payload_text_exposed' => false,
    'action_payload_text_exposed' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s current value: %s',
    (string) ($field['name'] ?? 'field'),
    (string) ($field['value'] ?? '')
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Selected appearance object %d has %d review-only resource actions: %s',
    (int) ($selected['object'] ?? 0),
    (int) ($selected['resource_xobject_action_count'] ?? 0),
    implode(', ', array_map('strval', $selected['resource_xobject_action_types'] ?? []))
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
