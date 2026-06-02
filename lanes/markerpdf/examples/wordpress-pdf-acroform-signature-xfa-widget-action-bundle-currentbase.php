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
      <xfa:field name="approval.bundle"><xfa:caption><xfa:value><xfa:text>Bundled signature widget</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data><approval><bundle>Bundled XFA signature value stays review metadata</bundle></approval></xfa:data>
  </xfa:datasets>
  <xfa:signature xmlns:xfa="http://www.xfa.org/schema/xfa-signature/3.3/">
    <xfa:signData target="approval.bundle">bundled detached signature bytes</xfa:signData>
  </xfa:signature>
</xdp:xdp>
XML;

$pageText = 'BT /F1 12 Tf 72 720 Td (Signature bundle page body) Tj ET';
$pageAppearance = 'BT /Fwidget 10 Tf 0 0 Td (page order signature appearance) Tj ET';
$fieldAppearance = 'BT /Froot 10 Tf 0 0 Td (mixed field widget appearance) Tj ET';
$fieldScript = "app.alert('mixed field calculate action');";
$widgetScript = "app.alert('page ordered widget focus action');";
$compressedXfa = gzcompress($xdpXml);
$compressedPageAppearance = gzcompress($pageAppearance);
$compressedFieldAppearance = gzcompress($fieldAppearance);
$compressedFieldScript = gzcompress($fieldScript);
$compressedWidgetScript = gzcompress($widgetScript);

if (
    !is_string($compressedXfa)
    || !is_string($compressedPageAppearance)
    || !is_string($compressedFieldAppearance)
    || !is_string($compressedFieldScript)
    || !is_string($compressedWidgetScript)
) {
    throw new RuntimeException('Unable to compress AcroForm signature widget bundle fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 /XFA 30 0 R /DA (/Froot 11 Tf 0 g) /DR << /Font << /Froot 11 0 R /Fwidget 12 0 R >> >> >>\nendobj\n"
    . "6 0 obj\n<< /Subtype /Widget /FT /Sig /T (approval.bundle) /V 40 0 R /Lock 41 0 R /Kids [8 0 R] /Rect [120 80 360 120] /P 3 0 R /F 4 /AS /FieldSigned /AP << /N << /FieldSigned 54 0 R /Off 55 0 R >> >> /A 62 0 R /AA << /V 60 0 R /C 61 0 R /Fo 63 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [120 144 360 184] /P 3 0 R /F 4 /AS /PageSigned /DA (/Fwidget 9 Tf 0 g) /AP << /N << /PageSigned 50 0 R /Off 51 0 R >> >> /A 70 0 R /AA << /Fo 72 0 R /Bl 71 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Bundle target title) >>\nendobj\n"
    . "11 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "12 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Bundle Reviewer) /Reason (Mixed widget review) /M (D:20260602222604Z) /ByteRange [0 128 512 64] /Contents <010203040506> >>\nendobj\n"
    . "41 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
    . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Fwidget 12 0 R >> >> /Length " . strlen($compressedPageAppearance) . " /Filter /FlateDecode >>\nstream\n{$compressedPageAppearance}\nendstream\nendobj\n"
    . "51 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "54 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 40] /Resources << /Font << /Froot 11 0 R >> >> /Length " . strlen($compressedFieldAppearance) . " /Filter /FlateDecode >>\nstream\n{$compressedFieldAppearance}\nendstream\nendobj\n"
    . "55 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "60 0 obj\n<< /S /SubmitForm /F (https://example.test/bundle-submit) /Fields [8 0 R 9 0 R] /Flags 6 >>\nendobj\n"
    . "61 0 obj\n<< /S /JavaScript /JS 73 0 R >>\nendobj\n"
    . "62 0 obj\n<< /S /Launch /F (bundle-review.exe) >>\nendobj\n"
    . "63 0 obj\n<< /S /URI /URI (https://example.test/mixed-focus) >>\nendobj\n"
    . "70 0 obj\n<< /S /URI /URI (javascript:bundleWidget\\(\\)) /Next 74 0 R >>\nendobj\n"
    . "71 0 obj\n<< /S /Hide /T [6 0 R 9 0 R] /H false >>\nendobj\n"
    . "72 0 obj\n<< /S /JavaScript /JS 75 0 R >>\nendobj\n"
    . "73 0 obj\n<< /Length " . strlen($compressedFieldScript) . " /Filter /FlateDecode >>\nstream\n{$compressedFieldScript}\nendstream\nendobj\n"
    . "74 0 obj\n<< /S /GoToR /F (bundle-remote.pdf) /D [0 /Fit] /NewWindow true >>\nendobj\n"
    . "75 0 obj\n<< /Length " . strlen($compressedWidgetScript) . " /Filter /FlateDecode >>\nstream\n{$compressedWidgetScript}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) $field['name']] = $field;
}

$signature = $fields['approval.bundle'] ?? [];
$bundle = is_array($signature['signature_widget_action_bundle'] ?? null) ? $signature['signature_widget_action_bundle'] : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (($bundle['page_annotation_order_objects'] ?? []) !== [8, 6] || ($bundle['mixed_field_widget_objects'] ?? []) !== [6]) {
    throw new RuntimeException('Expected signature widget bundle page annotation ordering metadata.');
}

if (($bundle['executes_action'] ?? true) !== false || ($bundle['executes_javascript'] ?? true) !== false) {
    throw new RuntimeException('Bundled signature widget action review unexpectedly executed actions.');
}

foreach (['Bundled XFA signature value', 'bundled detached signature bytes', 'bundleWidget', 'bundle-review.exe', 'bundle-remote.pdf'] as $blockedText) {
    if (str_contains($plainText, $blockedText)) {
        throw new RuntimeException('Bundled signature widget payload leaked into visible text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-signature-xfa-widget-action-bundle-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-signature-xfa-widget-action-bundle',
    'native_boundary' => 'Page /Annots order wins for signature widgets while XFA, action, launch, and submit payloads remain review metadata only',
    'field_name' => $bundle['field_name'] ?? null,
    'signed' => $bundle['signed'] ?? null,
    'xfa_referenced' => $bundle['xfa_referenced'] ?? null,
    'page_annotation_order_objects' => $bundle['page_annotation_order_objects'] ?? [],
    'mixed_field_widget_objects' => $bundle['mixed_field_widget_objects'] ?? [],
    'field_default_font_resource' => $bundle['field_default_font_resource'] ?? null,
    'submit_targets' => $bundle['submit_targets'] ?? [],
    'unsafe_uri_targets' => $bundle['unsafe_uri_targets'] ?? [],
    'form_action_field_names' => $bundle['form_action_field_names'] ?? [],
    'locked_action_field_names' => $bundle['locked_action_field_names'] ?? [],
    'executes_action' => $bundle['executes_action'] ?? null,
    'executes_javascript' => $bundle['executes_javascript'] ?? null,
    'executes_signature_validation' => $bundle['executes_signature_validation'] ?? null,
    'executes_signing' => $bundle['executes_signing'] ?? null,
    'executes_xfa_javascript' => $bundle['executes_xfa_javascript'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s keeps page widget order %s for review.',
    (string) ($bundle['field_name'] ?? 'approval.bundle'),
    implode(', ', array_map('strval', $bundle['page_annotation_order_objects'] ?? []))
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Submit target %s and unsafe URI %s stay non-executing.',
    implode(', ', $bundle['submit_targets'] ?? []),
    implode(', ', $bundle['unsafe_uri_targets'] ?? [])
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
