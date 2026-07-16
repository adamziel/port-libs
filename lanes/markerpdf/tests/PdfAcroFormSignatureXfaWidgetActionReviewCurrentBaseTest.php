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

$signatureXfaWidgetActionReviewPdf = static function (): string {
    $xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="approval">
      <xfa:field name="approval.signature"><xfa:caption><xfa:value><xfa:text>Approval signature</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data><approval><signature>XFA signature action data remains review metadata</signature></approval></xfa:data>
  </xfa:datasets>
  <xfa:signature xmlns:xfa="http://www.xfa.org/schema/xfa-signature/3.3/">
    <xfa:signData target="approval.signature">detached XFA signature bytes</xfa:signData>
  </xfa:signature>
</xdp:xdp>
XML;
    $compressedXfa = gzcompress($xdpXml);
    $appearance = 'BT /Fsig 10 Tf 0 0 Td (signature appearance action review only) Tj ET';
    $compressedAppearance = gzcompress($appearance);
    $focusScript = "app.alert('signature widget action review only');";
    $compressedFocusScript = gzcompress($focusScript);
    $calculateScript = "event.value = 'review only';";
    $compressedCalculateScript = gzcompress($calculateScript);
    $pageText = 'BT /F1 12 Tf 72 720 Td (Signature action review body) Tj ET';

    if (
        !is_string($compressedXfa)
        || !is_string($compressedAppearance)
        || !is_string($compressedFocusScript)
        || !is_string($compressedCalculateScript)
    ) {
        throw new RuntimeException('Unable to compress AcroForm signature widget action review fixture.');
    }

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 /XFA 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 40 0 R /Lock 41 0 R /Kids [8 0 R] /AA << /V 60 0 R /C 61 0 R >> >>\nendobj\n"
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
        . "60 0 obj\n<< /S /SubmitForm /F (https://example.test/signed-submit) /Fields [9 0 R] /Flags 6 /Next [65 0 R 66 0 R] >>\nendobj\n"
        . "61 0 obj\n<< /S /JavaScript /JS 70 0 R >>\nendobj\n"
        . "62 0 obj\n<< /S /URI /URI (javascript:signatureImport\\(\\)) /Next 67 0 R >>\nendobj\n"
        . "63 0 obj\n<< /S /JavaScript /JS 71 0 R >>\nendobj\n"
        . "64 0 obj\n<< /S /ResetForm /Fields [(article.title)] >>\nendobj\n"
        . "65 0 obj\n<< /S /ImportData /F (file://local-review.fdf) >>\nendobj\n"
        . "66 0 obj\n<< /S /Hide /T [9 0 R] /H false >>\nendobj\n"
        . "67 0 obj\n<< /S /GoToR /F (remote-review.pdf) /D [0 /Fit] /NewWindow true >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($compressedCalculateScript) . " /Filter /FlateDecode >>\nstream\n"
        . $compressedCalculateScript
        . "\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($compressedFocusScript) . " /Filter /FlateDecode >>\nstream\n"
        . $compressedFocusScript
        . "\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'summarizes signature XFA widget action review policy without executing form or PDF actions' => static function (TestRunner $t) use ($signatureXfaWidgetActionReviewPdf, $fieldsByName): void {
        $pdf = $signatureXfaWidgetActionReviewPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $signature = $fields['approval.signature'];
        $review = $signature['signature_widget_review'];
        $actionReview = $review['action_review'];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->true($form['xfa_overrides_page_content']);
        $t->true($review['signed']);
        $t->true($review['xfa_referenced']);
        $t->same('Signed', $review['appearance_state']);
        $t->same(false, $review['appearance_value_used_for_import']);
        $t->same(false, $review['xfa_value_used_for_signature']);
        $t->same('Static signed title', $fields['article.title']['value']);

        $t->same('acroform_xfa_signature_widget_action_review_boundary', $actionReview['source']);
        $t->same(8, $actionReview['action_count']);
        $t->same(4, $actionReview['field_action_count']);
        $t->same(4, $actionReview['widget_action_count']);
        $t->same(3, $actionReview['chained_action_count']);
        $t->same(['SubmitForm', 'ImportData', 'Hide', 'JavaScript', 'URI', 'GoToR', 'ResetForm'], $actionReview['action_types']);
        $t->same(['V', 'C', 'activation', 'Fo', 'Bl'], $actionReview['action_triggers']);
        $t->same(['field', 'widget'], $actionReview['action_sources']);
        $t->same([60, 65, 66, 61, 62, 67, 63, 64], $actionReview['action_objects']);
        $t->same(2, $actionReview['javascript_action_count']);
        $t->same(1, $actionReview['submit_form_action_count']);
        $t->same(1, $actionReview['reset_form_action_count']);
        $t->same(1, $actionReview['import_data_action_count']);
        $t->same(1, $actionReview['hide_action_count']);
        $t->same(1, $actionReview['unsafe_uri_action_count']);
        $t->same(1, $actionReview['remote_goto_action_count']);
        $t->same(['https://example.test/signed-submit'], $actionReview['submit_targets']);
        $t->same(['https', 'file', 'javascript'], $actionReview['action_target_schemes']);
        $t->same(['javascript:signatureImport()'], $actionReview['unsafe_uri_targets']);
        $t->same(['article.title'], $actionReview['form_action_field_names']);
        $t->same(['article.title'], $actionReview['hide_field_names']);
        $t->same(false, $actionReview['executes_action']);
        $t->same(false, $actionReview['executes_javascript']);
        $t->same(false, $actionReview['imports_form_data']);
        $t->same(false, $actionReview['submits_form_data']);
        $t->same(false, $actionReview['resets_form_values']);
        $t->same(false, $actionReview['changes_widget_visibility']);
        $t->same(false, $actionReview['executes_signature_validation']);
        $t->same(false, $actionReview['executes_signing']);
        $t->same(false, $actionReview['executes_xfa_javascript']);

        $t->same(8, $review['action_count']);
        $t->same(['SubmitForm', 'ImportData', 'Hide', 'JavaScript', 'URI', 'GoToR', 'ResetForm'], $review['action_types']);
        $t->same(['V', 'C', 'activation', 'Fo', 'Bl'], $review['action_triggers']);
        $t->same(['https://example.test/signed-submit'], $review['action_review']['submit_targets']);
        $t->same("Signature action review body\nsignature appearance action review only", $plainText);
        $t->same(false, str_contains($plainText, 'signatureImport'));
        $t->same(false, str_contains($plainText, 'local-review.fdf'));
        $t->same(false, str_contains($plainText, 'signature widget action review only'));
    },
];
