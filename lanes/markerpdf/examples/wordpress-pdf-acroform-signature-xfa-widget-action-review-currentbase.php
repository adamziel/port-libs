<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="approval">
      <xfa:field name="approval.signature"><xfa:caption><xfa:value><xfa:text>Approval signature</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data><approval><signature>XFA action payload stays review metadata</signature></approval></xfa:data>
  </xfa:datasets>
  <xfa:signature xmlns:xfa="http://www.xfa.org/schema/xfa-signature/3.3/">
    <xfa:signData target="approval.signature">detached XFA signature bytes</xfa:signData>
  </xfa:signature>
</xdp:xdp>
XML;
$compressedXfa = gzcompress($xdpXml);
$appearance = 'BT /Fsig 10 Tf 0 0 Td (signature appearance action review only) Tj ET';
$compressedAppearance = gzcompress($appearance);
$script = "app.alert('signature widget action review only');";
$compressedScript = gzcompress($script);
$pageText = 'BT /F1 12 Tf 72 720 Td (Signature action review body) Tj ET';

if (!is_string($compressedXfa) || !is_string($compressedAppearance) || !is_string($compressedScript)) {
    throw new RuntimeException('Unable to compress AcroForm signature widget action review fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 40 0 R /Lock 41 0 R /Kids [8 0 R] /AA << /V 60 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [120 96 360 136] /P 3 0 R /F 4 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> >> /A 62 0 R /AA << /Fo 63 0 R /Bl 64 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Static signed title) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedXfa
    . "\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Action Reviewer) /Reason (Signed action review) /M (D:20260602172101Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "41 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
    . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Fsig 52 0 R >> >> /Length " . strlen($compressedAppearance) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedAppearance
    . "\nendstream\nendobj\n"
    . "51 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "52 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "60 0 obj\n<< /S /SubmitForm /F (https://example.test/signed-submit) /Fields [9 0 R] /Flags 6 /Next 65 0 R >>\nendobj\n"
    . "62 0 obj\n<< /S /URI /URI (javascript:signatureImport\\(\\)) /Next 66 0 R >>\nendobj\n"
    . "63 0 obj\n<< /S /JavaScript /JS 70 0 R >>\nendobj\n"
    . "64 0 obj\n<< /S /ResetForm /Fields [(article.title)] >>\nendobj\n"
    . "65 0 obj\n<< /S /ImportData /F (file://local-review.fdf) >>\nendobj\n"
    . "66 0 obj\n<< /S /Hide /T [9 0 R] /H false >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedScript
    . "\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) $field['name']] = $field;
}

$signature = $fields['approval.signature'] ?? [];
$title = $fields['article.title'] ?? [];
$review = is_array($signature['signature_widget_review'] ?? null) ? $signature['signature_widget_review'] : [];
$actionReview = is_array($review['action_review'] ?? null) ? $review['action_review'] : [];
$text = (new PdfTextExtractor())->extractPlainText($pdf);

if (($actionReview['action_count'] ?? 0) !== 6 || ($actionReview['unsafe_uri_action_count'] ?? 0) !== 1) {
    throw new RuntimeException('Expected signature widget action review summary.');
}
if (($actionReview['executes_action'] ?? true) !== false || ($actionReview['executes_javascript'] ?? true) !== false) {
    throw new RuntimeException('Action review unexpectedly executed form or JavaScript actions.');
}
if (str_contains($text, 'signatureImport') || str_contains($text, 'local-review.fdf') || str_contains($text, 'signature widget action review only')) {
    throw new RuntimeException('Signature action payload leaked into visible text.');
}

echo '<!-- markerpdf:pdf-acroform-signature-xfa-widget-action-review-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-signature-xfa-widget-action-review',
    'native_boundary' => 'Signed XFA-backed AcroForm widget /A /AA /Next action policy is review metadata only during WordPress import',
    'field_name' => $review['field_name'] ?? null,
    'signature_signed' => $review['signed'] ?? null,
    'xfa_referenced' => $review['xfa_referenced'] ?? null,
    'widget_appearance_state' => $review['appearance_state'] ?? null,
    'static_title_value' => $title['value'] ?? null,
    'action_types' => $actionReview['action_types'] ?? [],
    'action_triggers' => $actionReview['action_triggers'] ?? [],
    'submit_targets' => $actionReview['submit_targets'] ?? [],
    'unsafe_uri_targets' => $actionReview['unsafe_uri_targets'] ?? [],
    'form_action_field_names' => $actionReview['form_action_field_names'] ?? [],
    'hide_field_names' => $actionReview['hide_field_names'] ?? [],
    'appearance_value_used_for_import' => $review['appearance_value_used_for_import'] ?? null,
    'xfa_value_used_for_signature' => $review['xfa_value_used_for_signature'] ?? null,
    'executes_action' => $actionReview['executes_action'] ?? null,
    'executes_javascript' => $actionReview['executes_javascript'] ?? null,
    'imports_form_data' => $actionReview['imports_form_data'] ?? null,
    'submits_form_data' => $actionReview['submits_form_data'] ?? null,
    'resets_form_values' => $actionReview['resets_form_values'] ?? null,
    'changes_widget_visibility' => $actionReview['changes_widget_visibility'] ?? null,
    'executes_signature_validation' => $actionReview['executes_signature_validation'] ?? null,
    'executes_signing' => $actionReview['executes_signing'] ?? null,
    'executes_xfa_javascript' => $actionReview['executes_xfa_javascript'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s signed by %s; %d action rows require review.',
    (string) ($review['field_name'] ?? 'signature'),
    (string) ($review['signature_name'] ?? 'unknown'),
    (int) ($actionReview['action_review_row_count'] ?? 0)
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Submit target %s and unsafe URI %s stay non-executing; %s remains %s.',
    implode(', ', $actionReview['submit_targets'] ?? []),
    implode(', ', $actionReview['unsafe_uri_targets'] ?? []),
    (string) ($title['name'] ?? 'article.title'),
    (string) ($title['value'] ?? '')
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
