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

$signatureWidgetLockResourcePdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Signature widget lock resource body) Tj ET';
    $normalAppearance = 'q /Seal Do Q BT /Fsig 10 Tf 0 0 Td (Signed appearance resource normal) Tj ET';
    $rolloverAppearance = 'q /Audit Do Q BT /Froll 9 Tf 0 0 Td (Rollover signature resource review) Tj ET';
    $downAppearance = 'q /PressedSeal Do Q BT /Fdown 9 Tf 0 0 Td (Down signature resource review) Tj ET';
    $script = "app.alert('signature appearance resource action blocked');";
    $compressedScript = gzcompress($script);
    if (!is_string($compressedScript)) {
        throw new RuntimeException('Unable to compress signature appearance resource script.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R] /SigFlags 3 /DA (/Fsig 10 Tf 0 g) /DR << /Font << /Fsig 70 0 R /Froll 71 0 R /Fdown 72 0 R >> >> >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.locked_resource) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 684] /P 3 0 R /F 4 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> /R << /Signed 52 0 R >> /D << /Signed 54 0 R >> >> >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Locked article title) /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 590 320 614] /P 3 0 R /F 4 >>\nendobj\n"
        . "11 0 obj\n<< /FT /Ch /T (article.section) /V (review) /Opt [(review) (publish)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 550 320 574] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Lock Resource Reviewer) /Reason (Resource lock review) /M (D:20260602225113Z) /ByteRange [0 128 512 64] /Contents <010203040506> >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R 11 0 R] /P 2 >>\nendobj\n"
        . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Resources << /Font << /Fsig 70 0 R >> /XObject << /Seal 80 0 R >> >> /Length " . strlen($normalAppearance) . " >>\nstream\n{$normalAppearance}\nendstream\nendobj\n"
        . "51 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "52 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Resources << /Font << /Froll 71 0 R >> /XObject << /Audit 81 0 R >> >> /Length " . strlen($rolloverAppearance) . " >>\nstream\n{$rolloverAppearance}\nendstream\nendobj\n"
        . "54 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 248 44] /Resources << /Font << /Fdown 72 0 R >> /XObject << /PressedSeal 82 0 R >> >> /Length " . strlen($downAppearance) . " >>\nstream\n{$downAppearance}\nendstream\nendobj\n"
        . "70 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n"
        . "71 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>\nendobj\n"
        . "72 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>\nendobj\n"
        . "80 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /A 90 0 R /AA << /D 91 0 R >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "81 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "82 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>\nstream\nx\nendstream\nendobj\n"
        . "90 0 obj\n<< /S /JavaScript /JS 92 0 R >>\nendobj\n"
        . "91 0 obj\n<< /S /URI /URI (javascript:signatureResource()) >>\nendobj\n"
        . "92 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $normalAppearance, $rolloverAppearance, $downAppearance, $script];
};

return [
    'summarizes signed signature widget lock scope with selected appearance resources' => static function (TestRunner $t) use ($signatureWidgetLockResourcePdf, $fieldsByName): void {
        [$pdf, $normalAppearance, $rolloverAppearance, $downAppearance, $script] = $signatureWidgetLockResourcePdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $signature = $fields['approval.locked_resource'];
        $review = $signature['signature_widget_lock_resource_review'];
        $appearanceRows = $review['selected_appearance_resource_rows'];

        $t->same('acroform_signature_widget_lock_resource_currentbase_review_boundary', $review['source']);
        $t->same('approval.locked_resource', $review['field_name']);
        $t->same(6, $review['field_object']);
        $t->true($review['signed']);
        $t->same(30, $review['signature_object']);
        $t->true($review['lock_present']);
        $t->same(31, $review['lock_object']);
        $t->same('Include', $review['lock_action']);
        $t->same('lock_included_fields', $review['lock_action_label']);
        $t->same(['article.title', 'article.section'], $review['lock_field_names']);
        $t->same(2, $review['lock_field_count']);
        $t->same('form_fill_templates_signatures', $review['lock_permission_label']);
        $t->true($review['lock_applies_after_signing']);
        $t->same(1, $review['widget_count']);
        $t->same(1, $review['page_referenced_widget_count']);
        $t->same(8, $review['primary_widget_object']);
        $t->same('Signed', $review['primary_widget_appearance_state']);
        $t->same(['normal', 'rollover', 'down'], $review['selected_appearance_modes']);
        $t->same([50, 52, 54], $review['selected_appearance_objects']);
        $t->same(['Fsig', 'Froll', 'Fdown'], $review['appearance_resource_font_names']);
        $t->same(['Seal', 'Audit', 'PressedSeal'], $review['appearance_resource_xobject_names']);
        $t->same(2, $review['appearance_resource_xobject_action_count']);
        $t->same(['JavaScript', 'URI'], $review['appearance_resource_xobject_action_types']);
        $t->same([90, 91], $review['appearance_resource_xobject_action_objects']);

        $t->same('normal', $appearanceRows[0]['appearance_mode']);
        $t->same(50, $appearanceRows[0]['appearance_object']);
        $t->same(hash('sha256', $normalAppearance), $appearanceRows[0]['decoded_sha256']);
        $t->same(['Fsig'], $appearanceRows[0]['resource_font_names']);
        $t->same(['Seal'], $appearanceRows[0]['resource_xobject_names']);
        $t->same(2, $appearanceRows[0]['resource_xobject_action_count']);
        $t->same(['JavaScript', 'URI'], $appearanceRows[0]['resource_xobject_action_types']);
        $t->same([90, 91], $appearanceRows[0]['resource_xobject_action_objects']);
        $t->same('acroform_widget_appearance_resource_xobject_review_boundary', $appearanceRows[0]['resource_xobject_reviews'][0]['source']);
        $t->same($script, $appearanceRows[0]['resource_xobject_reviews'][0]['actions'][0]['script_preview']);

        $t->same('rollover', $appearanceRows[1]['appearance_mode']);
        $t->same(52, $appearanceRows[1]['appearance_object']);
        $t->same(hash('sha256', $rolloverAppearance), $appearanceRows[1]['decoded_sha256']);
        $t->same(['Froll'], $appearanceRows[1]['resource_font_names']);
        $t->same(['Audit'], $appearanceRows[1]['resource_xobject_names']);
        $t->same(0, $appearanceRows[1]['resource_xobject_action_count']);

        $t->same('down', $appearanceRows[2]['appearance_mode']);
        $t->same(54, $appearanceRows[2]['appearance_object']);
        $t->same(hash('sha256', $downAppearance), $appearanceRows[2]['decoded_sha256']);
        $t->same(['Fdown'], $appearanceRows[2]['resource_font_names']);
        $t->same(['PressedSeal'], $appearanceRows[2]['resource_xobject_names']);
        $t->same(0, $appearanceRows[2]['resource_xobject_action_count']);

        $t->same(['approval.locked_resource'], $fields['article.title']['signature_lock_state']['locked_by_signatures']);
        $t->true($fields['article.title']['signature_lock_state']['effective_locked']);
        $t->true($fields['article.section']['signature_lock_state']['effective_locked']);
        $t->same(false, $review['signature_locks_enforced_on_import']);
        $t->same(false, $review['appearance_resources_used_for_import']);
        $t->same(false, $review['appearance_resource_payload_text_exposed']);
        $t->same(false, $review['executes_action']);
        $t->same(false, $review['executes_javascript']);
        $t->same(false, $review['executes_appearance_streams']);
        $t->same(false, $review['renders_appearances']);
        $t->same(false, $review['executes_signature_validation']);
        $t->same(false, $review['executes_signing']);
    },
    'keeps signature widget lock resource action payloads out of visible WordPress text' => static function (TestRunner $t) use ($signatureWidgetLockResourcePdf): void {
        [$pdf] = $signatureWidgetLockResourcePdf();

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Signature widget lock resource body', $plainText);
        $t->contains('Signed appearance resource normal', $plainText);
        $t->same(false, str_contains($plainText, 'signature appearance resource action blocked'));
        $t->same(false, str_contains($plainText, 'javascript:signatureResource'));
        $t->same(false, str_contains($plainText, 'Lock Resource Reviewer'));
        $t->same(false, str_contains($plainText, 'Locked article title'));
    },
];
