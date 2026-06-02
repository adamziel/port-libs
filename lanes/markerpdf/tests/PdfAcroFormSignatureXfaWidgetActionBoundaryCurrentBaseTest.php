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

$pageWidgetSignatureActionBoundaryPdf = static function (): string {
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

    return "%PDF-1.7\n"
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
};

return [
    'attaches page-only signature widgets to XFA action review without executing actions' => static function (TestRunner $t) use ($pageWidgetSignatureActionBoundaryPdf, $fieldsByName): void {
        $pdf = $pageWidgetSignatureActionBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $signature = $fields['approval.signature'];
        $title = $fields['article.title'];
        $widget = $signature['widgets'][0];
        $review = $signature['signature_widget_review'];
        $actionReview = $review['action_review'];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->true($form['xfa_overrides_page_content']);
        $t->same(['approval.signature'], $form['xfa_packets'][0]['field_names']);
        $t->same(['approval.signature'], $form['xfa_packets'][0]['data_paths']);
        $t->same(false, $form['xfa_packets'][0]['signature_payload_exposed']);

        $t->same('Sig', $signature['field_type']);
        $t->same('Static page-widget title', $title['value']);
        $t->same(1, count($signature['widgets']));
        $t->same(8, $widget['object']);
        $t->same(0, $widget['page_index']);
        $t->same(3, $widget['page_object']);
        $t->same(0, $widget['page_annotation_index']);
        $t->true($widget['referenced_from_page_annots']);
        $t->same('Signed', $widget['appearance_state']);
        $t->same(50, $widget['normal_appearance']['selected_appearance']['object']);

        $t->same('acroform_xfa_signature_widget_review_boundary', $review['source']);
        $t->true($review['signed']);
        $t->true($review['xfa_referenced']);
        $t->same(1, $review['widget_count']);
        $t->same(1, $review['page_referenced_widget_count']);
        $t->same([8], $review['widget_objects']);
        $t->same([8], $review['page_widget_objects']);
        $t->same(8, $review['primary_widget_object']);
        $t->same(0, $review['primary_widget_page_index']);
        $t->same('Signed', $review['appearance_state']);
        $t->same(50, $review['selected_appearance_object']);
        $t->same(false, $review['appearance_value_used_for_import']);
        $t->same(false, $review['xfa_value_used_for_signature']);

        $t->same('acroform_xfa_signature_widget_action_review_boundary', $actionReview['source']);
        $t->same(5, $actionReview['action_count']);
        $t->same(1, $actionReview['field_action_count']);
        $t->same(4, $actionReview['widget_action_count']);
        $t->same(1, $actionReview['chained_action_count']);
        $t->same(['SubmitForm', 'URI', 'GoToR', 'JavaScript', 'Hide'], $actionReview['action_types']);
        $t->same(['V', 'activation', 'Fo', 'Bl'], $actionReview['action_triggers']);
        $t->same(['field', 'widget'], $actionReview['action_sources']);
        $t->same([60, 62, 67, 63, 64], $actionReview['action_objects']);
        $t->same(['https://example.test/page-widget-submit'], $actionReview['submit_targets']);
        $t->same(['javascript:detachedSig()'], $actionReview['unsafe_uri_targets']);
        $t->same(['approval.signature', 'article.title'], $actionReview['form_action_field_names']);
        $t->same(['article.title'], $actionReview['hide_field_names']);
        $t->same(false, $actionReview['executes_action']);
        $t->same(false, $actionReview['executes_javascript']);
        $t->same(false, $actionReview['submits_form_data']);
        $t->same(false, $actionReview['changes_widget_visibility']);
        $t->same(false, $actionReview['executes_signature_validation']);
        $t->same(false, $actionReview['executes_signing']);
        $t->same(false, $actionReview['executes_xfa_javascript']);

        $t->same("Page-only signature widget boundary body\npage-only signature appearance review", $plainText);
        foreach ([
            'Page annotation XFA value stays review metadata',
            'detached widget signature bytes',
            'detachedSig',
            'remote-widget-review.pdf',
            'page-only signature focus action',
        ] as $blockedText) {
            $t->same(false, str_contains($plainText, $blockedText));
        }
    },
];
