<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="article">
      <xfa:field name="article.summary"><xfa:caption><xfa:value><xfa:text>Summary</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <article><summary>XFA dynamic summary must stay review metadata</summary></article>
    </xfa:data>
  </xfa:datasets>
</xdp:xdp>
XML;

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible widget XFA action appearance value body) Tj ET';
$appearance = 'BT /FApp 10 Tf 0 0 Td (Selected widget appearance review text) Tj ET';
$script = "app.alert('focus action stays review only');";

$compressedXfa = gzcompress($xdpXml);
$compressedAppearance = gzcompress($appearance);
$compressedScript = gzcompress($script);
if (!is_string($compressedXfa) || !is_string($compressedAppearance) || !is_string($compressedScript)) {
    throw new RuntimeException('Unable to compress AcroForm widget XFA action appearance fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.summary) /V (Static AcroForm summary) /DV (Draft AcroForm summary) /Kids [8 0 R] /AA << /V 20 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /AS /Ready /AP << /N << /Ready 40 0 R /Off 41 0 R >> >> /A 21 0 R /AA << /Fo 22 0 R >> >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (javascript:fieldValidate\\(\\)) /Next << /S /Hide /T [8 0 R] /H true >> >>\nendobj\n"
    . "21 0 obj\n<< /S /SubmitForm /F << /Type /Filespec /F (summary-submit.fdf) >> /Fields [6 0 R] /Flags 4 >>\nendobj\n"
    . "22 0 obj\n<< /S /JavaScript /JS 24 0 R >>\nendobj\n"
    . "24 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 24] /Resources << /Font << /FApp 50 0 R >> >> /Length " . strlen($compressedAppearance) . " /Filter /FlateDecode >>\nstream\n{$compressedAppearance}\nendstream\nendobj\n"
    . "41 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$field = $form['fields'][0] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected one AcroForm field.');
}

$review = is_array($field['widget_xfa_action_appearance_value_review'] ?? null)
    ? $field['widget_xfa_action_appearance_value_review']
    : null;
if ($review === null) {
    throw new RuntimeException('Expected widget XFA action appearance value review metadata.');
}

$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$actionOperandsExcluded = !str_contains($visibleText, 'focus action stays review only')
    && !str_contains($visibleText, 'summary-submit.fdf')
    && !str_contains($visibleText, 'fieldValidate');
$xfaPayloadExcluded = !str_contains($visibleText, 'XFA dynamic summary must stay review metadata');

if (
    ($review['current'] ?? null) !== 'Static AcroForm summary'
    || ($review['xfa_value_used_for_current_value'] ?? true) !== false
    || ($review['submits_form_data'] ?? true) !== false
    || ($review['executes_action'] ?? true) !== false
    || !$actionOperandsExcluded
    || !$xfaPayloadExcluded
) {
    throw new RuntimeException('Widget XFA action appearance value boundary failed.');
}

echo '<!-- markerpdf:pdf-acroform-widget-xfa-action-appearance-value-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-xfa-action-appearance-value-currentbase',
    'native_boundary' => 'AcroForm field /V and /DV remain authoritative while XFA data, widget actions, and selected appearance metadata are reviewed before WordPress import',
    'field_name' => $review['field_name'] ?? null,
    'current_value' => $review['current'] ?? null,
    'default_value' => $review['default'] ?? null,
    'xfa_dynamic_value_present' => $review['dynamic_value_present'] ?? null,
    'xfa_value_preview' => $review['xfa_matched_data_value_previews'][0] ?? null,
    'appearance_state' => $review['primary_widget_appearance_state'] ?? null,
    'selected_appearance_object' => $review['selected_appearance_object'] ?? null,
    'appearance_visible_text_present' => str_contains($visibleText, 'Selected widget appearance review text'),
    'action_types' => $review['action_types'] ?? [],
    'action_safety_labels' => $review['action_safety_labels'] ?? [],
    'action_targets' => $review['action_targets'] ?? [],
    'visible_text' => $visibleText,
    'field_value_authoritative' => $review['acroform_current_value_authoritative'] ?? null,
    'xfa_value_used_for_current_value' => false,
    'appearance_value_used_for_import' => false,
    'form_actions_review_only' => true,
    'action_operands_excluded_from_visible_text' => $actionOperandsExcluded,
    'xfa_payload_excluded_from_visible_text' => $xfaPayloadExcluded,
    'executes_action' => false,
    'executes_javascript' => false,
    'executes_xfa_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('article.summary imports current AcroForm value: ' . (string) ($review['current'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('XFA dataset value is review metadata only: ' . (string) ($review['xfa_matched_data_value_previews'][0] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Widget actions stay review-only: ' . implode(', ', array_map('strval', $review['action_safety_labels'] ?? [])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
