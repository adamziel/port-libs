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
      <approval><signature>xfa signature bytes must stay review metadata</signature></approval>
      <article><title>XFA title should not replace AcroForm value</title></article>
    </xfa:data>
  </xfa:datasets>
  <xfa:signature xmlns:xfa="http://www.xfa.org/schema/xfa-signature/3.3/">
    <xfa:signData target="approval.signature">detached xfa signature payload</xfa:signData>
  </xfa:signature>
</xdp:xdp>
XML;
$compressed = gzcompress($xdpXml);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress XFA signature boundary fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [6 0 R 9 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 1 /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Subtype /Widget /FT /Sig /T (approval.signature) /TU (Unsigned XFA approval) /Rect [120 80 360 120] /P 3 0 R /F 4 /AS /Off /AP << /N << /Off 40 0 R /Signed 41 0 R >> >> >>\nendobj\n"
    . "9 0 obj\n<< /Subtype /Widget /FT /Tx /T (article.title) /V (Static AcroForm title) /Rect [120 140 420 164] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n"
    . $compressed
    . "\nendstream\nendobj\n"
    . "40 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "41 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) $field['name']] = $field;
}

$signature = $fields['approval.signature'] ?? [];
$title = $fields['article.title'] ?? [];
$signatureBoundary = is_array($signature['xfa_boundary'] ?? null) ? $signature['xfa_boundary'] : [];
$signatureState = is_array($signature['signature_state'] ?? null) ? $signature['signature_state'] : [];
$packet = $form['xfa_packets'][0] ?? [];

echo '<!-- markerpdf:pdf-acroform-xfa-signature-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-xfa-signature-boundary',
    'upstream_boundary' => 'marker/pdf/extract_text.py::get_text_blocks delegates static PDF text extraction to pdftext/pypdfium; native import keeps XFA form data as review metadata',
    'native_boundary' => 'AcroForm /XFA packet field/data references are linked to signature fields without treating XFA values as signed /V dictionaries or executing validation',
    'xfa_overrides_page_content' => $form['xfa_overrides_page_content'],
    'xdp_packet_names' => $packet['xdp_packet_names'] ?? [],
    'signature_field_names' => $packet['signature_field_names'] ?? [],
    'matched_signature_data_paths' => $signatureBoundary['matched_data_paths'] ?? [],
    'static_title_value' => $title['value'] ?? null,
    'signature_signed' => $signatureState['signed'] ?? null,
    'xfa_value_used_for_signature' => $signatureState['xfa_value_used_for_signature'] ?? null,
    'value_used_for_import' => $signatureBoundary['value_used_for_import'] ?? null,
    'executes_xfa_javascript' => $signatureBoundary['executes_xfa_javascript'] ?? null,
    'executes_signature_validation' => $signatureBoundary['executes_signature_validation'] ?? null,
    'executes_signing' => $signatureBoundary['executes_signing'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('approval.signature: XFA data paths '
    . implode(', ', $signatureBoundary['matched_data_paths'] ?? [])
    . '; AcroForm signature remains '
    . (($signatureState['signed'] ?? false) ? 'signed' : 'unsigned')
    . ' review metadata', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('article.title: ' . (string) ($title['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
