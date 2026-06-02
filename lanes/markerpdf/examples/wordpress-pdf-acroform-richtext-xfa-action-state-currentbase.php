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
      <xfa:field name="article.summary"><xfa:caption><xfa:value><xfa:text>Article summary</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <article><summary>XFA rich summary must stay metadata</summary></article>
    </xfa:data>
  </xfa:datasets>
</xdp:xdp>
XML;

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible rich text XFA action state body) Tj ET';
$richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p><b>XFA styled summary must not import</b></p></body>';
$defaultStyle = 'font: 11pt "ReviewSerif"; color:#102030';
$formatScript = "event.value = event.value; app.alert('format blocked');";
$appearanceText = 'BT /F1 10 Tf 0 0 Td (Widget appearance review only) Tj ET';

$compressedXfa = gzcompress($xdpXml);
$compressedScript = gzcompress($formatScript);
$compressedAppearance = gzcompress($appearanceText);
if (!is_string($compressedXfa) || !is_string($compressedScript) || !is_string($compressedAppearance)) {
    throw new RuntimeException('Unable to compress rich text XFA action state example streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /XFA 40 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.summary) /Ff 33554432 /V (Plain AcroForm summary) /DV (Draft AcroForm summary) /RV ({$richText}) /DS ({$defaultStyle}) /Kids [8 0 R] /AA << /F 20 0 R /V 22 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /AS /Current /AP << /N << /Current 24 0 R /Off 25 0 R >> >> /A 23 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /JavaScript /JS 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
    . "22 0 obj\n<< /S /SubmitForm /F 30 0 R /Fields [(article.summary)] /Flags 4 >>\nendobj\n"
    . "23 0 obj\n<< /S /ResetForm /Fields [(article.summary)] >>\nendobj\n"
    . "24 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 24] /Resources << /Font << /F1 50 0 R >> >> /Length " . strlen($compressedAppearance) . " /Filter /FlateDecode >>\nstream\n{$compressedAppearance}\nendstream\nendobj\n"
    . "25 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 24] /Resources << /Font << /F1 50 0 R >> >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (https://example.test/rich-submit) >>\nendobj\n"
    . "40 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[$field['name']] = $field;
}

$field = $fields['article.summary'] ?? null;
if (!is_array($field) || !is_array($field['rich_text_xfa_action_state_review'] ?? null)) {
    throw new RuntimeException('Expected rich text XFA action state review row.');
}

$review = $field['rich_text_xfa_action_state_review'];
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
if (
    ($review['current'] ?? null) !== 'Plain AcroForm summary'
    || ($review['xfa_value_used_for_current_value'] ?? true) !== false
    || ($review['rich_text_used_for_import'] ?? true) !== false
    || ($review['executes_action'] ?? true) !== false
    || str_contains($visibleText, 'XFA styled summary must not import')
    || str_contains($visibleText, 'XFA rich summary must stay metadata')
    || str_contains($visibleText, 'format blocked')
    || str_contains($visibleText, 'rich-submit')
) {
    throw new RuntimeException('Rich text XFA action state review boundary failed.');
}

echo '<!-- markerpdf:pdf-acroform-richtext-xfa-action-state-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-richtext-xfa-action-state-review',
    'native_boundary' => 'AcroForm /V and /DV remain current-base authoritative while /RV rich text, /DS default style, /XFA data, form actions, and widget appearance streams are review metadata only',
    'visible_text' => $visibleText,
    'field_name' => $review['field_name'] ?? null,
    'current_value' => $review['current'] ?? null,
    'default_value' => $review['default'] ?? null,
    'rich_text_preview' => $review['rich_text_plain_preview'] ?? null,
    'rich_text_sha256' => $review['rich_text_sha256'] ?? null,
    'default_style_sha256' => $review['default_style_sha256'] ?? null,
    'xfa_data_paths' => $review['xfa_matched_data_paths'] ?? [],
    'xfa_data_previews' => $review['xfa_matched_data_value_previews'] ?? [],
    'action_types' => $review['action_types'] ?? [],
    'action_safety_labels' => $review['action_safety_labels'] ?? [],
    'submit_targets' => $review['submit_targets'] ?? [],
    'widget_appearance_states' => $review['appearance_states'] ?? [],
    'selected_appearance_objects' => $review['selected_appearance_objects'] ?? [],
    'acroform_current_value_authoritative' => true,
    'rich_text_used_for_import' => false,
    'rich_text_used_for_submit' => false,
    'xfa_value_used_for_current_value' => false,
    'xfa_payload_text_exposed' => false,
    'payload_text_exposed' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_xfa_javascript' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('article.summary imports static AcroForm /V "' . (string) ($review['current'] ?? '') . '"; rich /RV and XFA data stay review-only.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Actions reviewed: ' . implode(', ', $review['action_types'] ?? []) . '; no PDF action or JavaScript is executed.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Selected widget appearance state ' . implode(', ', $review['appearance_states'] ?? []) . ' is hashed as metadata and not used as the field value.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
