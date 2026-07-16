<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$signatureXfaWidgetActionBundlePdf = static function (): string {
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

    return "%PDF-1.7\n"
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
};

return [
    'bundles mixed signature field widgets in page annotation order with inherited resources' => static function (TestRunner $t) use ($signatureXfaWidgetActionBundlePdf, $fieldsByName): void {
        $form = (new PdfAcroFormExtractor())->extractForm($signatureXfaWidgetActionBundlePdf());
        $fields = $fieldsByName($form['fields']);
        $signature = $fields['approval.bundle'];
        $review = $signature['signature_widget_review'];
        $bundle = $signature['signature_widget_action_bundle'];

        $t->same('acroform_signature_xfa_widget_action_bundle_currentbase', $bundle['source']);
        $t->same($bundle, $review['action_bundle']);
        $t->same('approval.bundle', $bundle['field_name']);
        $t->true($bundle['signed']);
        $t->true($bundle['xfa_referenced']);
        $t->same([30], $bundle['xfa_packet_objects']);
        $t->same(['approval.bundle'], $bundle['xfa_matched_field_names']);
        $t->same(['approval.bundle'], $bundle['xfa_matched_data_paths']);
        $t->same(6, $bundle['current_value_source_object']);
        $t->same(['DA', 'DR'], $bundle['inherited_field_attributes']);
        $t->same('acroform', $bundle['field_default_appearance_source']);
        $t->same(null, $bundle['field_default_appearance_source_object']);
        $t->same('Froot', $bundle['field_default_font_resource']);
        $t->true($bundle['field_default_font_resource_resolved']);
        $t->same(11, $bundle['field_default_font_resource_object']);

        $t->same(2, $bundle['widget_count']);
        $t->same(2, $bundle['page_referenced_widget_count']);
        $t->same(1, $bundle['mixed_field_widget_count']);
        $t->same([8, 6], $bundle['widget_order_objects']);
        $t->same([8, 6], $bundle['page_annotation_order_objects']);
        $t->same([6], $bundle['mixed_field_widget_objects']);
        $t->same([8, 6], $review['page_widget_objects']);
        $t->same(8, $review['primary_widget_object']);
        $t->same(0, $review['primary_widget_page_annotation_index']);
        $t->same('PageSigned', $review['appearance_state']);
        $t->same(50, $review['selected_appearance_object']);

        $pageWidget = $bundle['widgets'][0];
        $mixedWidget = $bundle['widgets'][1];
        $t->same(8, $pageWidget['widget_object']);
        $t->same(false, $pageWidget['mixed_field_widget_dictionary']);
        $t->same('widget', $pageWidget['default_appearance_source']);
        $t->same(8, $pageWidget['default_appearance_source_object']);
        $t->same('Fwidget', $pageWidget['default_font_resource']);
        $t->true($pageWidget['default_font_resource_resolved']);
        $t->same(12, $pageWidget['default_font_resource_object']);
        $t->same(6, $mixedWidget['widget_object']);
        $t->same(true, $mixedWidget['mixed_field_widget_dictionary']);
        $t->same('acroform', $mixedWidget['default_appearance_source']);
        $t->same('Froot', $mixedWidget['default_font_resource']);
    },
    'keeps bundled signature XFA and widget action operands review-only' => static function (TestRunner $t) use ($signatureXfaWidgetActionBundlePdf, $fieldsByName): void {
        $pdf = $signatureXfaWidgetActionBundlePdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $signature = $fields['approval.bundle'];
        $bundle = $signature['signature_widget_action_bundle'];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(8, $bundle['action_count']);
        $t->same(12, $bundle['action_review_row_count']);
        $t->same(4, $bundle['field_action_count']);
        $t->same(8, $bundle['widget_action_count']);
        $t->same(1, $bundle['chained_action_count']);
        $t->same(4, $bundle['duplicate_mixed_field_widget_action_count']);
        $t->same(['Launch', 'URI', 'SubmitForm', 'JavaScript', 'GoToR', 'Hide'], $bundle['action_types']);
        $t->same(['activation', 'Fo', 'V', 'C', 'Bl'], $bundle['action_triggers']);
        $t->same(['field', 'widget'], $bundle['action_sources']);
        $t->same([62, 63, 60, 61, 70, 74, 72, 71], $bundle['action_objects']);
        $t->same([62, 63, 60, 61], $bundle['field_action_objects']);
        $t->same([70, 74, 72, 71, 62, 63, 60, 61], $bundle['widget_action_objects']);
        $t->same(['https://example.test/bundle-submit'], $bundle['submit_targets']);
        $t->same(['javascript:bundleWidget()'], $bundle['unsafe_uri_targets']);
        $t->same(['approval.bundle', 'article.title'], $bundle['form_action_field_names']);
        $t->same(['approval.bundle', 'article.title'], $bundle['hide_field_names']);
        $t->same(['article.title'], $bundle['locked_action_field_names']);
        $t->same(['article.title'], $bundle['locked_submit_field_names']);
        $t->same(['article.title'], $bundle['locked_hide_field_names']);
        $t->same('field', $bundle['action_rows'][0]['source']);
        $t->same('widget', $bundle['action_rows'][4]['source']);
        $t->same(false, $bundle['executes_action']);
        $t->same(false, $bundle['executes_javascript']);
        $t->same(false, $bundle['submits_form_data']);
        $t->same(false, $bundle['changes_widget_visibility']);
        $t->same(false, $bundle['executes_signature_validation']);
        $t->same(false, $bundle['executes_signing']);
        $t->same(false, $bundle['executes_xfa_javascript']);
        $t->same(false, $bundle['executes_python_or_models']);
        $t->same(false, $bundle['executes_external_pdf_tools']);

        $t->contains('Signature bundle page body', $plainText);
        $t->contains('page order signature appearance', $plainText);
        $t->contains('mixed field widget appearance', $plainText);
        foreach ([
            'Bundled XFA signature value stays review metadata',
            'bundled detached signature bytes',
            'bundleWidget',
            'bundle-remote.pdf',
            'bundle-review.exe',
            'mixed field calculate action',
            'page ordered widget focus action',
        ] as $blockedText) {
            $t->same(false, str_contains($plainText, $blockedText));
        }
    },
];
