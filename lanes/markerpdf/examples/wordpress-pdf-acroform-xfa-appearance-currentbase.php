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
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <approval><signature>XFA signature state remains review metadata</signature></approval>
    </xfa:data>
  </xfa:datasets>
</xdp:xdp>
XML;

$normalAppearance = 'BT /Fsig 10 Tf 0 0 Td (Normal signed appearance review only) Tj ET';
$rolloverAppearance = 'BT /Fsig 10 Tf 0 0 Td (Rollover signed appearance review only) Tj ET';
$downAppearance = 'BT /Fsig 10 Tf 0 0 Td (Down signed appearance review only) Tj ET';
$compressedXfa = gzcompress($xdpXml);
$compressedNormal = gzcompress($normalAppearance);
$compressedRollover = gzcompress($rolloverAppearance);
$compressedDown = gzcompress($downAppearance);
if (!is_string($compressedXfa) || !is_string($compressedNormal) || !is_string($compressedRollover) || !is_string($compressedDown)) {
    throw new RuntimeException('Unable to compress AcroForm XFA appearance example.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [6 0 R 9 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 /XFA 40 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Subtype /Widget /FT /Sig /T (approval.signature) /V 50 0 R /Rect [120 80 360 120] /P 3 0 R /F 4 /AS /Signed /AP << /N << /Signed 20 0 R /Off 21 0 R >> /R << /Signed 22 0 R /Off 21 0 R >> /D << /Signed 24 0 R /Off 21 0 R >> >> >>\nendobj\n"
    . "9 0 obj\n<< /Subtype /Widget /FT /Tx /T (article.title) /V (Static AcroForm title) /Rect [120 140 420 164] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Fsig 30 0 R >> >> /Length " . strlen($compressedNormal) . " /Filter /FlateDecode >>\nstream\n{$compressedNormal}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Fsig 30 0 R >> >> /Length " . strlen($compressedRollover) . " /Filter /FlateDecode >>\nstream\n{$compressedRollover}\nendstream\nendobj\n"
    . "24 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Fsig 30 0 R >> >> /Length " . strlen($compressedDown) . " /Filter /FlateDecode >>\nstream\n{$compressedDown}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "40 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /M (D:20260602174411Z) /ByteRange [0 100 200 50] /Contents <010203> >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[$field['name']] = $field;
}

$signature = $fields['approval.signature'] ?? null;
if (!is_array($signature)) {
    throw new RuntimeException('Expected approval.signature field.');
}

$widget = $signature['widgets'][0] ?? null;
$review = $signature['signature_widget_review'] ?? null;
if (!is_array($widget) || !is_array($review)) {
    throw new RuntimeException('Expected signature widget review metadata.');
}

$rollover = is_array($widget['rollover_appearance'] ?? null) ? $widget['rollover_appearance'] : [];
$down = is_array($widget['down_appearance'] ?? null) ? $widget['down_appearance'] : [];
if (($review['rollover_selected_appearance_object'] ?? null) !== 22 || ($review['down_selected_appearance_object'] ?? null) !== 24) {
    throw new RuntimeException('Expected selected rollover and down appearance objects.');
}
if (($review['imports_xfa_payload'] ?? true) !== false || ($review['interactive_appearance_value_used_for_import'] ?? true) !== false) {
    throw new RuntimeException('XFA or interactive appearance payload must remain review-only.');
}

echo '<!-- markerpdf:pdf-acroform-xfa-appearance-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-xfa-interactive-appearance-review',
    'native_boundary' => 'XFA-backed AcroForm signature widgets preserve normal, rollover, and down /AP state dictionaries as review metadata only',
    'field_name' => $signature['name'] ?? null,
    'signed' => $signature['signature_state']['signed'] ?? null,
    'xfa_dynamic_value_present' => $signature['xfa_boundary']['dynamic_value_present'] ?? null,
    'appearance_state' => $widget['appearance_state'] ?? null,
    'normal_selected_appearance_object' => $widget['normal_appearance']['selected_appearance']['object'] ?? null,
    'rollover_selected_appearance_object' => $rollover['selected_appearance']['object'] ?? null,
    'down_selected_appearance_object' => $down['selected_appearance']['object'] ?? null,
    'title_value' => $fields['article.title']['value'] ?? null,
    'interactive_appearance_value_used_for_import' => false,
    'interactive_appearance_payload_text_exposed' => false,
    'imports_xfa_payload' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_signature_validation' => false,
    'executes_signing' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s: normal=%s rollover=%s down=%s',
    (string) ($signature['name'] ?? 'signature'),
    (string) ($widget['normal_appearance']['selected_appearance']['object'] ?? 'none'),
    (string) ($rollover['selected_appearance']['object'] ?? 'none'),
    (string) ($down['selected_appearance']['object'] ?? 'none')
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('XFA data and interactive appearances are review metadata only.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
