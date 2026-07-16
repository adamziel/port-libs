<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="approval">
      <xfa:field name="approval.signature"><xfa:caption><xfa:value><xfa:text>Signature</xfa:text></xfa:value></xfa:caption></xfa:field>
      <xfa:field name="article.title"><xfa:caption><xfa:value><xfa:text>Title</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <approval><signature>XFA signature text remains review metadata</signature></approval>
      <article><title>XFA title does not replace static AcroForm title</title></article>
    </xfa:data>
  </xfa:datasets>
  <xfa:signature xmlns:xfa="http://www.xfa.org/schema/xfa-signature/3.3/">
    <xfa:signData target="approval.signature">detached XFA packet signature bytes</xfa:signData>
  </xfa:signature>
</xdp:xdp>
XML;
$compressedXfa = gzcompress($xdpXml);
$appearance = 'BT /Fsig 10 Tf 0 0 Td (visual signature appearance review only) Tj ET';
$compressedAppearance = gzcompress($appearance);
$focusScript = "app.alert('signature focus review only');";
$compressedFocusScript = gzcompress($focusScript);
if (!is_string($compressedXfa) || !is_string($compressedAppearance) || !is_string($compressedFocusScript)) {
    throw new RuntimeException('Unable to compress AcroForm signature widget review fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [6 0 R 9 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Subtype /Widget /FT /Sig /T (approval.signature) /TU (Final approval signature) /V 40 0 R /SV 31 0 R /Lock 32 0 R /Rect [360 80 120 120] /P 3 0 R /F 36 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> >> /AA << /Fo 60 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Subtype /Widget /FT /Tx /T (article.title) /V (Static AcroForm title) /Rect [120 140 420 164] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedXfa
    . "\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /SV /Ff 75 /Filter /Adobe.PPKLite /SubFilter [/adbe.pkcs7.detached] /DigestMethod [/SHA256] /Reasons [(Approved for import)] /MDP << /P 2 >> >>\nendobj\n"
    . "32 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [(article.title)] /P 2 >>\nendobj\n"
    . "40 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /Reason (Approved after XFA review) /M (D:20260602160514Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Fsig 52 0 R >> >> /Length " . strlen($compressedAppearance) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedAppearance
    . "\nendstream\nendobj\n"
    . "51 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "52 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "60 0 obj\n<< /S /JavaScript /JS 61 0 R >>\nendobj\n"
    . "61 0 obj\n<< /Length " . strlen($compressedFocusScript) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedFocusScript
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

echo '<!-- markerpdf:pdf-acroform-xfa-signature-widget-review ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-xfa-signature-widget-review',
    'native_boundary' => 'AcroForm signature widget review combines XFA references, /V signature state, /SV, /Lock, /F flags, selected /AP /N, and widget actions without executing them',
    'field_name' => $review['field_name'] ?? null,
    'signature_signed' => $review['signed'] ?? null,
    'signature_name' => $review['signature_name'] ?? null,
    'widget_visibility' => $review['primary_widget_visibility'] ?? null,
    'widget_appearance_state' => $review['appearance_state'] ?? null,
    'selected_appearance_object' => $review['selected_appearance_object'] ?? null,
    'xfa_matched_data_paths' => $review['xfa_matched_data_paths'] ?? [],
    'seed_required_constraints' => $review['seed_value_required_constraints'] ?? [],
    'lock_field_names' => $review['lock_field_names'] ?? [],
    'static_title_value' => $title['value'] ?? null,
    'title_locked_by_signature' => $title['signature_lock_state']['effective_locked'] ?? null,
    'appearance_value_used_for_import' => $review['appearance_value_used_for_import'] ?? null,
    'xfa_value_used_for_signature' => $review['xfa_value_used_for_signature'] ?? null,
    'executes_action' => $review['executes_action'] ?? null,
    'executes_javascript' => $review['executes_javascript'] ?? null,
    'executes_appearance_streams' => $review['executes_appearance_streams'] ?? null,
    'executes_signature_validation' => $review['executes_signature_validation'] ?? null,
    'executes_signing' => $review['executes_signing'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(
    sprintf(
        '%s signed by %s; widget %s with appearance %s',
        (string) ($review['field_name'] ?? 'signature'),
        (string) ($review['signature_name'] ?? 'unknown'),
        (string) ($review['primary_widget_visibility'] ?? 'unknown'),
        (string) ($review['appearance_state'] ?? 'none')
    ),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo '<li>' . htmlspecialchars(
    sprintf(
        'Static title %s is locked by signed field scope while XFA paths %s stay review-only.',
        (string) ($title['value'] ?? ''),
        implode(', ', $review['xfa_matched_data_paths'] ?? [])
    ),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
