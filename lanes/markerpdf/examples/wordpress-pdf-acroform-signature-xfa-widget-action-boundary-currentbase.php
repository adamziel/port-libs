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
      <xfa:field name="approval.signature"><xfa:caption><xfa:value><xfa:text>Detached approval signature</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data><approval><signature>Page annotation XFA value stays review metadata</signature></approval></xfa:data>
  </xfa:datasets>
  <xfa:signature xmlns:xfa="http://www.xfa.org/schema/xfa-signature/3.3/">
    <xfa:signData target="approval.signature">detached widget signature bytes</xfa:signData>
  </xfa:signature>
</xdp:xdp>
XML;

$pageText = 'BT /F1 12 Tf 72 720 Td (Page-only signature widget boundary body) Tj ET';
$appearance = 'BT /Fsig 10 Tf 0 0 Td (page-only signature appearance review) Tj ET';
$focusScript = "app.alert('page-only signature focus action');";
$compressedXfa = gzcompress($xdpXml);
$compressedAppearance = gzcompress($appearance);
$compressedScript = gzcompress($focusScript);
if (!is_string($compressedXfa) || !is_string($compressedAppearance) || !is_string($compressedScript)) {
    throw new RuntimeException('Unable to compress page-widget signature action fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 40 0 R /Lock 41 0 R /AA << /V 60 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [120 96 360 136] /P 3 0 R /F 4 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> >> /A 62 0 R /AA << /Fo 63 0 R /Bl 64 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Static page-widget title) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Page Widget Reviewer) /Reason (Detached page widget review) /M (D:20260602213841Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "41 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
    . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Fsig 52 0 R >> >> /Length " . strlen($compressedAppearance) . " /Filter /FlateDecode >>\nstream\n{$compressedAppearance}\nendstream\nendobj\n"
    . "51 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "52 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "60 0 obj\n<< /S /SubmitForm /F (https://example.test/page-widget-submit) /Fields [8 0 R 9 0 R] /Flags 6 >>\nendobj\n"
    . "62 0 obj\n<< /S /URI /URI (javascript:detachedSig\\(\\)) /Next 67 0 R >>\nendobj\n"
    . "63 0 obj\n<< /S /JavaScript /JS 70 0 R >>\nendobj\n"
    . "64 0 obj\n<< /S /Hide /T [9 0 R] /H true >>\nendobj\n"
    . "67 0 obj\n<< /S /GoToR /F (remote-widget-review.pdf) /D [0 /Fit] /NewWindow true >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) $field['name']] = $field;
}

$signature = $fields['approval.signature'] ?? [];
$review = is_array($signature['signature_widget_review'] ?? null) ? $signature['signature_widget_review'] : [];
$actionReview = is_array($review['action_review'] ?? null) ? $review['action_review'] : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (($review['page_referenced_widget_count'] ?? 0) !== 1 || ($actionReview['widget_action_count'] ?? 0) !== 4) {
    throw new RuntimeException('Expected page-only signature widget action review metadata.');
}
if (($actionReview['executes_action'] ?? true) !== false || ($actionReview['executes_javascript'] ?? true) !== false) {
    throw new RuntimeException('Page-only signature widget action unexpectedly executed.');
}
foreach (['detachedSig', 'remote-widget-review.pdf', 'Page annotation XFA value', 'page-only signature focus action'] as $blockedText) {
    if (str_contains($plainText, $blockedText)) {
        throw new RuntimeException('Page-only widget action or XFA payload leaked into visible text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-signature-xfa-widget-action-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-page-widget-parent-boundary',
    'native_boundary' => 'Page /Annots widget with /Parent signature field is attached for XFA/action review when field /Kids is absent',
    'field_name' => $review['field_name'] ?? null,
    'signature_signed' => $review['signed'] ?? null,
    'xfa_referenced' => $review['xfa_referenced'] ?? null,
    'page_widget_objects' => $review['page_widget_objects'] ?? [],
    'page_referenced_widget_count' => $review['page_referenced_widget_count'] ?? null,
    'action_types' => $actionReview['action_types'] ?? [],
    'action_triggers' => $actionReview['action_triggers'] ?? [],
    'submit_targets' => $actionReview['submit_targets'] ?? [],
    'unsafe_uri_targets' => $actionReview['unsafe_uri_targets'] ?? [],
    'form_action_field_names' => $actionReview['form_action_field_names'] ?? [],
    'appearance_value_used_for_import' => $review['appearance_value_used_for_import'] ?? null,
    'xfa_value_used_for_signature' => $review['xfa_value_used_for_signature'] ?? null,
    'executes_action' => $actionReview['executes_action'] ?? null,
    'executes_javascript' => $actionReview['executes_javascript'] ?? null,
    'submits_form_data' => $actionReview['submits_form_data'] ?? null,
    'changes_widget_visibility' => $actionReview['changes_widget_visibility'] ?? null,
    'executes_signature_validation' => $actionReview['executes_signature_validation'] ?? null,
    'executes_signing' => $actionReview['executes_signing'] ?? null,
    'executes_xfa_javascript' => $actionReview['executes_xfa_javascript'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s uses page widget %s for signed XFA review.',
    (string) ($review['field_name'] ?? 'approval.signature'),
    implode(', ', array_map('strval', $review['page_widget_objects'] ?? []))
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Submit target %s and unsafe URI %s stay non-executing.',
    implode(', ', $actionReview['submit_targets'] ?? []),
    implode(', ', $actionReview['unsafe_uri_targets'] ?? [])
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
