<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$xfaAppearancePdf = static function (): array {
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
        throw new RuntimeException('Unable to compress AcroForm XFA appearance fixture.');
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

    return [$pdf, $xdpXml, $normalAppearance, $rolloverAppearance, $downAppearance];
};

return [
    'reviews XFA backed AcroForm rollover and down appearance states without importing dynamic form payloads' => static function (TestRunner $t) use ($xfaAppearancePdf, $fieldsByName): void {
        [$pdf, $xdpXml, $normalAppearance, $rolloverAppearance, $downAppearance] = $xfaAppearancePdf();

        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $packet = $form['xfa_packets'][0];
        $signature = $fields['approval.signature'];
        $widget = $signature['widgets'][0];
        $normal = $widget['normal_appearance'];
        $rollover = $widget['rollover_appearance'];
        $down = $widget['down_appearance'];
        $review = $signature['signature_widget_review'];

        $t->true($form['xfa_overrides_page_content']);
        $t->same(hash('sha256', trim($xdpXml)), $packet['xml_sha256']);
        $t->same(['approval.signature'], $packet['field_names']);
        $t->same(['approval.signature'], $packet['data_paths']);
        $t->true($packet['has_signature_field']);
        $t->same(false, $packet['signature_payload_exposed']);

        $t->same('Sig', $signature['field_type']);
        $t->true($signature['signature_state']['signed']);
        $t->same(false, $signature['signature_state']['xfa_value_used_for_signature']);
        $t->true($signature['xfa_boundary']['dynamic_value_present']);
        $t->same(false, $signature['xfa_boundary']['value_used_for_import']);
        $t->same('Static AcroForm title', $fields['article.title']['value']);

        $t->same('Signed', $widget['appearance_state']);
        $t->same(['Signed', 'Off'], $widget['appearance_states']);

        $t->same('state_dictionary', $normal['normal_appearance_type']);
        $t->same('Signed', $normal['selected_state']);
        $t->same(20, $normal['selected_appearance']['object']);
        $t->same(hash('sha256', $normalAppearance), $normal['selected_appearance']['decoded_sha256']);
        $t->same(false, $normal['appearance_value_used_for_import']);

        $t->same('rollover', $rollover['appearance_mode']);
        $t->same('R', $rollover['appearance_key']);
        $t->same('state_dictionary', $rollover['appearance_type']);
        $t->same(['Signed', 'Off'], $rollover['available_states']);
        $t->same('Signed', $rollover['selected_state']);
        $t->same(22, $rollover['selected_appearance']['object']);
        $t->same(hash('sha256', $rolloverAppearance), $rollover['selected_appearance']['decoded_sha256']);
        $t->true($rollover['state_matches_appearance']);
        $t->same(false, $rollover['appearance_value_used_for_import']);
        $t->same(false, $rollover['payload_text_exposed']);
        $t->same(false, $rollover['executes_appearance_streams']);

        $t->same('down', $down['appearance_mode']);
        $t->same('D', $down['appearance_key']);
        $t->same('state_dictionary', $down['appearance_type']);
        $t->same(['Signed', 'Off'], $down['available_states']);
        $t->same('Signed', $down['selected_state']);
        $t->same(24, $down['selected_appearance']['object']);
        $t->same(hash('sha256', $downAppearance), $down['selected_appearance']['decoded_sha256']);
        $t->true($down['state_matches_appearance']);
        $t->same(false, $down['appearance_value_used_for_import']);
        $t->same(false, $down['payload_text_exposed']);
        $t->same(false, $down['renders_appearances']);

        $t->same(22, $review['rollover_selected_appearance_object']);
        $t->same(24, $review['down_selected_appearance_object']);
        $t->same(hash('sha256', $rolloverAppearance), $review['rollover_selected_appearance_decoded_sha256']);
        $t->same(hash('sha256', $downAppearance), $review['down_selected_appearance_decoded_sha256']);
        $t->same(false, $review['interactive_appearance_value_used_for_import']);
        $t->same(false, $review['interactive_appearance_payload_text_exposed']);
        $t->same(false, $review['executes_appearance_streams']);
        $t->same(false, $review['renders_appearances']);
        $t->same(false, $review['imports_xfa_payload']);
    },
];
