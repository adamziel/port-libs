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

$submitResetAppearanceLockPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible submit reset lock body) Tj ET';
    $checkedAppearance = 'q 0 0 12 12 re S Q';
    $offAppearance = 'q Q';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R 13 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 650 240 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Btn /T (article.consent) /V /Yes /DV /Off /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 610 96 634] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 50 0 R /Off 51 0 R >> >> >>\nendobj\n"
        . "11 0 obj\n<< /FT /Tx /T (article.title) /V (Locked current title) /DV (Draft title) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [120 610 360 634] /P 3 0 R /F 4 >>\nendobj\n"
        . "13 0 obj\n<< /FT /Btn /T (actions.submit_reset) /Ff 65536 /Kids [14 0 R] /AA << /U 41 0 R >> >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 560 220 584] /P 3 0 R /F 4 /A 40 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Approval Reviewer) /Reason (Approved) /M (D:20260602210500Z) /ByteRange [0 128 512 64] /Contents <010203040506> >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
        . "40 0 obj\n<< /S /SubmitForm /F (https://example.test/locked-submit) /Fields [9 0 R 11 0 R] /Flags 2 >>\nendobj\n"
        . "41 0 obj\n<< /S /ResetForm /Fields [(article.consent) (article.title)] >>\nendobj\n"
        . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length " . strlen($checkedAppearance) . " >>\nstream\n{$checkedAppearance}\nendstream\nendobj\n"
        . "51 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $checkedAppearance];
};

return [
    'correlates submit reset actions with selected appearances and signed field locks' => static function (TestRunner $t) use ($submitResetAppearanceLockPdf, $fieldsByName): void {
        [$pdf, $checkedAppearance] = $submitResetAppearanceLockPdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $actionField = $fields['actions.submit_reset'];
        $review = $actionField['submit_reset_appearance_lock_review'];
        $targets = [];
        foreach ($review['target_fields'] as $row) {
            $targets[$row['field_name']] = $row;
        }

        $t->same('acroform_submit_reset_appearance_lock_currentbase_review_boundary', $review['source']);
        $t->same('actions.submit_reset', $review['field_name']);
        $t->same(13, $review['field_object']);
        $t->same(2, $review['action_count']);
        $t->same(1, $review['field_action_count']);
        $t->same(1, $review['widget_action_count']);
        $t->same(1, $review['submit_form_action_count']);
        $t->same(1, $review['reset_form_action_count']);
        $t->same([41, 40], $review['action_objects']);
        $t->same(['U', 'activation'], $review['action_triggers']);
        $t->same(['mouse_up', 'activation'], $review['action_trigger_labels']);
        $t->same(['include'], $review['fields_modes']);
        $t->same(2, $review['selected_field_count']);
        $t->same(['article.consent', 'article.title'], $review['selected_field_names']);
        $t->same(['article.consent', 'article.title'], $review['submitted_field_names']);
        $t->same(['article.consent', 'article.title'], $review['reset_field_names']);
        $t->same(['article.consent'], $review['locked_target_field_names']);
        $t->same(['article.consent'], $review['locked_submit_field_names']);
        $t->same(['article.consent'], $review['locked_reset_field_names']);
        $t->same(['article.consent'], $review['appearance_field_names']);
        $t->same([50], $review['selected_appearance_objects']);
        $t->same([], $review['stale_appearance_field_names']);
        $t->same(0, $review['stale_appearance_state_count']);
        $t->same(['article.consent', 'article.title'], $review['current_value_field_names']);
        $t->same(['article.consent', 'article.title'], $review['default_value_field_names']);
        $t->same(['article.consent', 'article.title'], $review['changed_from_default_field_names']);

        $consent = $targets['article.consent'];
        $t->same('acroform_submit_reset_appearance_lock_target_currentbase', $consent['source']);
        $t->same(9, $consent['field_object']);
        $t->same('Btn', $consent['field_type']);
        $t->same('Yes', $consent['current']);
        $t->same('Off', $consent['default']);
        $t->same('field_value', $consent['current_source']);
        $t->true($consent['changed_from_default']);
        $t->true($consent['locked_by_signed_signature']);
        $t->same(['approval.signature'], $consent['locked_by_signatures']);
        $t->same(['form_fill_templates_signatures'], $consent['signature_lock_permission_labels']);
        $t->same(1, $consent['widget_count']);
        $t->same(1, $consent['page_referenced_widget_count']);
        $t->same([10], $consent['widget_objects']);
        $t->same(['Yes', 'Off'], $consent['appearance_states']);
        $t->same([50], $consent['selected_appearance_objects']);
        $t->same([hash('sha256', $checkedAppearance)], $consent['selected_appearance_decoded_sha256']);
        $t->same(1, $consent['checked_widget_count']);
        $t->true($consent['widget_state_consistent']);
        $t->same(0, $consent['stale_appearance_state_count']);

        $title = $targets['article.title'];
        $t->same('Locked current title', $title['current']);
        $t->same('Draft title', $title['default']);
        $t->same(false, $title['locked_by_signed_signature']);
        $t->same([], $title['selected_appearance_objects']);

        $t->same(false, $review['signature_locks_enforced_on_import']);
        $t->same(false, $review['appearance_value_used_for_import']);
        $t->same(false, $review['appearance_payload_text_exposed']);
        $t->same(false, $review['submits_form_data']);
        $t->same(false, $review['resets_form_values']);
        $t->same(false, $review['executes_action']);
        $t->same(false, $review['executes_javascript']);
        $t->same(false, $review['executes_appearance_streams']);
        $t->same(false, $review['renders_appearances']);

        $t->same('Yes', $fields['article.consent']['value_state']['effective_current_state']);
        $t->true($fields['article.consent']['signature_lock_state']['effective_locked']);
        $t->same(false, $fields['article.title']['signature_lock_state']['effective_locked']);
    },
    'keeps submit reset appearance and lock payloads out of visible WordPress text' => static function (TestRunner $t) use ($submitResetAppearanceLockPdf): void {
        [$pdf] = $submitResetAppearanceLockPdf();

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Visible submit reset lock body', $plainText);
        $t->same(false, str_contains($plainText, 'locked-submit'));
        $t->same(false, str_contains($plainText, 'Approval Reviewer'));
        $t->same(false, str_contains($plainText, 'Locked current title'));
        $t->same(false, str_contains($plainText, 'Draft title'));
    },
];
