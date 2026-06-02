<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-16"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="approval">
      <xfa:field name="approval.signature"><xfa:caption><xfa:value><xfa:text>Signature</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <approval><signature>dynamic XFA value must not sign or render</signature></approval>
    </xfa:data>
  </xfa:datasets>
</xdp:xdp>
XML;

$encoded = iconv('UTF-8', 'UTF-16BE', $xdpXml);
assert(is_string($encoded));
$utf16 = "\xFE\xFF" . $encoded;
$compressed = gzcompress($utf16);
assert(is_string($compressed));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [6 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Subtype /Widget /FT /Sig /T (approval.signature) /TU (Final approval signature) /V 40 0 R /Rect [360 80 120 120] /P 3 0 R /F 36 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> >> >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Static title) /DV (Draft title) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n"
    . $compressed
    . "\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /Reason (Approved after XFA review) /M (D:20260602082400Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "50 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "51 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$signatureFields = array_values(array_filter(
    $form['fields'],
    static fn (array $field): bool => ($field['field_type'] ?? null) === 'Sig'
));
$signature = $signatureFields[0] ?? [];
$signatureMetadata = is_array($signature['signature'] ?? null) ? $signature['signature'] : [];
$widget = is_array(($signature['widgets'] ?? [])[0] ?? null) ? $signature['widgets'][0] : [];
$packet = $form['xfa_packets'][0] ?? [];

echo '<!-- markerpdf:pdf-acroform-xfa-signature-widget-state ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-xfa-signature-widget-state',
    'native_boundary' => 'AcroForm /XFA packet review plus page-referenced signature widget /F and /AS state boundaries',
    'xfa_overrides_page_content' => $form['xfa_overrides_page_content'],
    'xfa_packet_root' => $packet['xml_root'] ?? null,
    'xfa_data_nodes' => $packet['data_node_names'] ?? [],
    'signature_field_count' => count($signatureFields),
    'signature_name' => $signatureMetadata['name'] ?? null,
    'widget_visibility' => $widget['annotation_visibility'] ?? null,
    'widget_flags' => $widget['annotation_flag_names'] ?? [],
    'widget_appearance_state' => $widget['appearance_state'] ?? null,
    'widget_page_annotation_index' => $widget['page_annotation_index'] ?? null,
    'executes_signing' => false,
    'executes_xfa_javascript' => false,
    'executes_form_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(
    sprintf(
        'XFA packet %s stays review-only with data nodes: %s',
        (string) ($packet['xml_root'] ?? 'unknown'),
        implode(', ', $packet['data_node_names'] ?? [])
    ),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo '<li>' . htmlspecialchars(
    sprintf(
        '%s signature widget is %s, printable=%s, appearance=%s, signed_by=%s',
        (string) ($signature['name'] ?? 'signature'),
        (string) ($widget['annotation_visibility'] ?? 'unknown'),
        ($widget['printable'] ?? false) ? 'yes' : 'no',
        (string) ($widget['appearance_state'] ?? 'none'),
        (string) ($signatureMetadata['name'] ?? 'unknown')
    ),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
