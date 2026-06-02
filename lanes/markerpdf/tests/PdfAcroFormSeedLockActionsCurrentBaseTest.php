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

$signatureSeedLockActionsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Seed lock action import) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /SV 31 0 R /Lock 32 0 R /Kids [8 0 R] /AA << /V 40 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 /A 41 0 R >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Locked title value) /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "11 0 obj\n<< /FT /Tx /T (internal.notes) /V (Editable note value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Seed Reviewer) /Reason (Seed lock review) /M (D:20260602185507Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
        . "31 0 obj\n<< /Type /SV /Ff 107 /Filter /Adobe.PPKLite /SubFilter [/adbe.pkcs7.detached /ETSI.CAdES.detached] /DigestMethod [/SHA256] /Reasons [(Seed lock review)] /AddRevInfo true /MDP << /P 2 >> /TimeStamp << /URL (https://timestamp.example.test/rfc3161) /Ff 1 >> >>\nendobj\n"
        . "32 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
        . "40 0 obj\n<< /S /SubmitForm /F (https://example.test/signed-submit) /Fields [(article.title) (internal.notes)] /Flags 6 /Next 42 0 R >>\nendobj\n"
        . "41 0 obj\n<< /S /ResetForm /Fields [9 0 R] /Next 43 0 R >>\nendobj\n"
        . "42 0 obj\n<< /S /ImportData /F (file://local-review.fdf) >>\nendobj\n"
        . "43 0 obj\n<< /S /Hide /T [(internal.notes)] /H true >>\nendobj\n"
        . "%%EOF";
};

return [
    'summarizes signature seed constraints lock scope and form action targets without executing them' => static function (TestRunner $t) use ($signatureSeedLockActionsPdf, $fieldsByName): void {
        $form = (new PdfAcroFormExtractor())->extractForm($signatureSeedLockActionsPdf());
        $fields = $fieldsByName($form['fields']);
        $signature = $fields['approval.signature'];
        $review = $signature['signature_seed_lock_action_review'];

        $t->same('acroform_signature_seed_lock_action_boundary', $review['source']);
        $t->same('approval.signature', $review['field_name']);
        $t->same(6, $review['field_object']);
        $t->true($review['signed']);
        $t->same(30, $review['signature_object']);
        $t->true($review['seed_value_present']);
        $t->same(31, $review['seed_value_object']);
        $t->same(['filter', 'subfilter', 'reason', 'add_revision_info', 'digest_method'], $review['seed_value_required_constraints']);
        $t->same(5, $review['seed_required_constraint_count']);
        $t->true($review['seed_constraints_required']);
        $t->same('Adobe.PPKLite', $review['seed_value_filter']);
        $t->same(['adbe.pkcs7.detached', 'ETSI.CAdES.detached'], $review['seed_value_subfilters']);
        $t->same(['SHA256'], $review['seed_value_digest_methods']);
        $t->same(1, $review['seed_value_reason_count']);
        $t->true($review['seed_value_timestamp_required']);
        $t->same('https://timestamp.example.test/rfc3161', $review['seed_value_timestamp_url']);
        $t->same(2, $review['seed_mdp_permission_level']);
        $t->same('form_fill_templates_signatures', $review['seed_mdp_permission_label']);
        $t->same(['fill_forms', 'instantiate_page_templates', 'sign'], $review['seed_mdp_allowed_changes']);

        $t->true($review['lock_present']);
        $t->same(32, $review['lock_object']);
        $t->same('Include', $review['lock_action']);
        $t->same('lock_included_fields', $review['lock_action_label']);
        $t->same(['article.title'], $review['lock_field_names']);
        $t->same(1, $review['lock_field_count']);
        $t->same('form_fill_templates_signatures', $review['lock_permission_label']);
        $t->true($review['lock_applies_after_signing']);

        $t->same(4, $review['action_count']);
        $t->same(2, $review['field_action_count']);
        $t->same(2, $review['widget_action_count']);
        $t->same(['SubmitForm', 'ImportData', 'ResetForm', 'Hide'], $review['action_types']);
        $t->same([
            'submit-form-action-review',
            'import-data-action-review',
            'reset-form-action-review',
            'hide-action-review',
        ], $review['action_safety_labels']);
        $t->same(1, $review['submit_form_action_count']);
        $t->same(1, $review['reset_form_action_count']);
        $t->same(1, $review['import_data_action_count']);
        $t->same(1, $review['hide_action_count']);
        $t->same(4, $review['unsafe_action_count']);
        $t->same(['article.title', 'internal.notes'], $review['action_field_names']);
        $t->same(['article.title', 'internal.notes'], $review['submit_action_field_names']);
        $t->same(['article.title'], $review['reset_action_field_names']);
        $t->same(['internal.notes'], $review['hide_action_field_names']);
        $t->true($review['actions_target_locked_fields']);
        $t->same(['article.title'], $review['locked_action_field_names']);
        $t->same(['article.title'], $review['locked_submit_field_names']);
        $t->same(['article.title'], $review['locked_reset_field_names']);
        $t->same([], $review['locked_hide_field_names']);
        $t->same(false, $review['seed_constraints_enforced_on_import']);
        $t->same(false, $review['lock_used_for_form_action_execution']);
        $t->same(false, $review['form_actions_execute_on_import']);
        $t->same(false, $review['executes_action']);
        $t->same(false, $review['executes_signature_validation']);
        $t->same(false, $review['executes_signing']);

        $t->true($fields['article.title']['signature_lock_state']['effective_locked']);
        $t->same(['approval.signature'], $fields['article.title']['signature_lock_state']['locked_by_signatures']);
        $t->same(false, $fields['internal.notes']['signature_lock_state']['effective_locked']);
    },
    'keeps seed lock action payloads out of visible WordPress text' => static function (TestRunner $t) use ($signatureSeedLockActionsPdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($signatureSeedLockActionsPdf());

        $t->same('Seed lock action import', $plainText);
        $t->same(false, str_contains($plainText, 'signed-submit'));
        $t->same(false, str_contains($plainText, 'local-review.fdf'));
        $t->same(false, str_contains($plainText, 'timestamp.example.test'));
        $t->same(false, str_contains($plainText, 'Seed Reviewer'));
    },
];
