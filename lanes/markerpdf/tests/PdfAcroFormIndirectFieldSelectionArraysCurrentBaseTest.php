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

$indirectFieldSelectionArraysPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible indirect field selection arrays body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 14 0 R 18 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 13 0 R 16 0 R] /SigFlags 3 /CO 60 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (invoice.total) /V (27.06) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 260 664] /P 3 0 R /F 4 /AA << /C 40 0 R >> >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (invoice.amount) /V (25.00) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "13 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 32 0 R /Kids [14 0 R] /AA << /V 41 0 R >> >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 560 260 604] /P 3 0 R /F 4 /A 42 0 R >>\nendobj\n"
        . "16 0 obj\n<< /FT /Btn /Ff 65536 /T (actions.submit) /V /Off /Kids [18 0 R] /A 43 0 R /AA << /D 47 0 R >> >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 520 260 544] /P 3 0 R /F 4 /A 44 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Indirect Selector Reviewer) /Reason (Boundary review) /M (D:20260608123531Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
        . "32 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields 70 0 R /P 2 >>\nendobj\n"
        . "40 0 obj\n<< /S /JavaScript /JS (event.value = this.getField('invoice.amount').value;) >>\nendobj\n"
        . "41 0 obj\n<< /S /SubmitForm /F (https://example.test/signature-submit) /Fields 80 0 R /Flags 6 /Next 45 0 R >>\nendobj\n"
        . "42 0 obj\n<< /S /ResetForm /Fields 81 0 R /Next 46 0 R >>\nendobj\n"
        . "43 0 obj\n<< /S /SubmitForm /F (https://example.test/button-submit) /Fields 80 0 R /Flags 6 >>\nendobj\n"
        . "44 0 obj\n<< /S /Hide /T 82 0 R /H true >>\nendobj\n"
        . "45 0 obj\n<< /S /ImportData /F (file://local-indirect-selector.fdf) >>\nendobj\n"
        . "46 0 obj\n<< /S /Hide /T 82 0 R /H false >>\nendobj\n"
        . "47 0 obj\n<< /S /SubmitForm /F (https://example.test/non-array-fields) /Fields 90 0 R /Flags 6 >>\nendobj\n"
        . "60 0 obj\n[8 0 R () [91 0 R] << /Nested 92 0 R >> % 93 0 R stays comment\n10 0 R 12 0 R]\nendobj\n"
        . "70 0 obj\n[6 0 R () [95 0 R] << /Nested 96 0 R >> % 97 0 R stays comment\n10 0 R]\nendobj\n"
        . "80 0 obj\n[6 0 R (submit.named.field) () [99 0 R] << /Nested 100 0 R >> % 101 0 R stays comment\n10 0 R]\nendobj\n"
        . "81 0 obj\n[10 0 R (reset.named.field) () [103 0 R] << /Nested 104 0 R >> % 105 0 R stays comment\n]\nendobj\n"
        . "82 0 obj\n[10 0 R (hide.named.field) () [107 0 R] << /Nested 108 0 R >> % 109 0 R stays comment\n]\nendobj\n"
        . "90 0 obj\n<< /FT /Tx /T (decoy.co.literal) /V (calculation literal decoy) >>\nendobj\n"
        . "91 0 obj\n<< /FT /Tx /T (decoy.co.nested_array) /V (calculation nested array decoy) >>\nendobj\n"
        . "92 0 obj\n<< /FT /Tx /T (decoy.co.nested_dict) /V (calculation nested dictionary decoy) >>\nendobj\n"
        . "93 0 obj\n<< /FT /Tx /T (decoy.co.comment) /V (calculation comment decoy) >>\nendobj\n"
        . "94 0 obj\n<< /FT /Tx /T (decoy.lock.literal) /V (lock literal decoy) >>\nendobj\n"
        . "95 0 obj\n<< /FT /Tx /T (decoy.lock.nested_array) /V (lock nested array decoy) >>\nendobj\n"
        . "96 0 obj\n<< /FT /Tx /T (decoy.lock.nested_dict) /V (lock nested dictionary decoy) >>\nendobj\n"
        . "97 0 obj\n<< /FT /Tx /T (decoy.lock.comment) /V (lock comment decoy) >>\nendobj\n"
        . "98 0 obj\n<< /FT /Tx /T (decoy.submit.literal) /V (submit literal decoy) >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (decoy.submit.nested_array) /V (submit nested array decoy) >>\nendobj\n"
        . "100 0 obj\n<< /FT /Tx /T (decoy.submit.nested_dict) /V (submit nested dictionary decoy) >>\nendobj\n"
        . "101 0 obj\n<< /FT /Tx /T (decoy.submit.comment) /V (submit comment decoy) >>\nendobj\n"
        . "102 0 obj\n<< /FT /Tx /T (decoy.reset.literal) /V (reset literal decoy) >>\nendobj\n"
        . "103 0 obj\n<< /FT /Tx /T (decoy.reset.nested_array) /V (reset nested array decoy) >>\nendobj\n"
        . "104 0 obj\n<< /FT /Tx /T (decoy.reset.nested_dict) /V (reset nested dictionary decoy) >>\nendobj\n"
        . "105 0 obj\n<< /FT /Tx /T (decoy.reset.comment) /V (reset comment decoy) >>\nendobj\n"
        . "106 0 obj\n<< /FT /Tx /T (decoy.hide.literal) /V (hide literal decoy) >>\nendobj\n"
        . "107 0 obj\n<< /FT /Tx /T (decoy.hide.nested_array) /V (hide nested array decoy) >>\nendobj\n"
        . "108 0 obj\n<< /FT /Tx /T (decoy.hide.nested_dict) /V (hide nested dictionary decoy) >>\nendobj\n"
        . "109 0 obj\n<< /FT /Tx /T (decoy.hide.comment) /V (hide comment decoy) >>\nendobj\n"
        . "%%EOF";
};

return [
    'resolves indirect AcroForm field selection arrays across calculation locks and actions' => static function (TestRunner $t) use ($indirectFieldSelectionArraysPdf, $fieldsByName): void {
        $form = (new PdfAcroFormExtractor())->extractForm($indirectFieldSelectionArraysPdf());
        $fields = $fieldsByName($form['fields']);
        $signature = $fields['approval.signature'];
        $submitButton = $fields['actions.submit'];
        $signatureReview = $signature['signature_seed_lock_action_review'];
        $buttonActions = $submitButton['actions'];

        $t->same([
            ['object' => 8, 'field_name' => 'invoice.total'],
            ['object' => 10, 'field_name' => 'invoice.amount'],
            ['object' => 12, 'field_name' => 'invoice.amount'],
        ], $form['calculation_order']);
        $t->same(['widget', 'field', 'widget'], array_column($form['calculation_order_review'], 'target_kind'));
        $t->same(['invoice.total', 'invoice.amount', 'invoice.amount'], array_column($form['calculation_order_review'], 'field_name'));
        $t->same([8, null, 12], array_column($form['calculation_order_review'], 'widget_object'));
        $t->same([6, 10, 10], array_column($form['calculation_order_review'], 'field_object'));

        $totalState = $fields['invoice.total']['calculation_state'];
        $amountState = $fields['invoice.amount']['calculation_state'];
        $t->true($totalState['in_calculation_order']);
        $t->same(0, $totalState['calculation_order_index']);
        $t->same('widget', $totalState['calculation_order_target_kind']);
        $t->same(8, $totalState['calculation_order_widget_object']);
        $t->true($amountState['in_calculation_order']);
        $t->same(1, $amountState['calculation_order_index']);
        $t->same('field', $amountState['calculation_order_target_kind']);
        $t->same(10, $amountState['calculation_order_field_object']);

        $t->same(['invoice.total', 'invoice.amount'], $signature['signature_lock']['field_names']);
        $t->same('lock_included_fields', $signature['signature_lock']['action_label']);
        $t->same('form_fill_templates_signatures', $signature['signature_lock']['permission_label']);
        $t->true($fields['invoice.total']['signature_lock_state']['effective_locked']);
        $t->true($fields['invoice.amount']['signature_lock_state']['effective_locked']);
        $t->same(['approval.signature'], $fields['invoice.total']['signature_lock_state']['locked_by_signatures']);
        $t->same(['approval.signature'], $fields['invoice.amount']['signature_lock_state']['locked_by_signatures']);

        $t->same(['invoice.total', 'invoice.amount', 'submit.named.field'], $signatureReview['submit_action_field_names']);
        $t->same(['invoice.amount', 'reset.named.field'], $signatureReview['reset_action_field_names']);
        $t->same(['invoice.amount', 'hide.named.field'], $signatureReview['hide_action_field_names']);
        $t->same(['invoice.total', 'invoice.amount'], $signatureReview['locked_submit_field_names']);
        $t->same(['invoice.amount'], $signatureReview['locked_reset_field_names']);
        $t->same(['invoice.amount'], $signatureReview['locked_hide_field_names']);
        $t->true($signatureReview['actions_target_locked_fields']);
        $t->same(false, $signatureReview['form_actions_execute_on_import']);
        $t->same(false, $signatureReview['lock_used_for_form_action_execution']);
        $t->same(false, $signatureReview['executes_action']);
        $t->same(false, $signatureReview['executes_javascript']);
        $t->same(false, $signatureReview['executes_signature_validation']);
        $t->same(false, $signatureReview['executes_signing']);

        $t->same('SubmitForm', $buttonActions[0]['action_type']);
        $t->same('include', $buttonActions[0]['fields_mode']);
        $t->same([6, 10], $buttonActions[0]['field_objects']);
        $t->same(['invoice.total', 'invoice.amount', 'submit.named.field'], $buttonActions[0]['field_names']);
        $t->same([], $buttonActions[0]['unresolved_field_objects']);
        $t->same(false, $buttonActions[0]['executes_action']);

        $t->same('SubmitForm', $buttonActions[1]['action_type']);
        $t->same('all_exportable', $buttonActions[1]['fields_mode']);
        $t->same([], $buttonActions[1]['field_objects']);
        $t->same([], $buttonActions[1]['field_names']);
        $t->same([], $buttonActions[1]['unresolved_field_objects']);
        $t->same(false, $buttonActions[1]['executes_action']);

        $hideAction = $submitButton['widgets'][0]['actions'][0];
        $t->same('Hide', $hideAction['action_type']);
        $t->same([10], $hideAction['field_objects']);
        $t->same(['invoice.amount', 'hide.named.field'], $hideAction['field_names']);
        $t->same([], $hideAction['unresolved_field_objects']);
        $t->same(false, $hideAction['executes_action']);

        foreach ($fields as $name => $field) {
            $t->same(false, str_starts_with((string) $name, 'decoy.'));
            $t->same(false, str_contains((string) ($field['value'] ?? ''), 'decoy'));
        }
    },
    'keeps indirect field selection array payloads out of visible WordPress text' => static function (TestRunner $t) use ($indirectFieldSelectionArraysPdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($indirectFieldSelectionArraysPdf());

        $t->same('Visible indirect field selection arrays body', $plainText);
        $t->same(false, str_contains($plainText, 'signature-submit'));
        $t->same(false, str_contains($plainText, 'button-submit'));
        $t->same(false, str_contains($plainText, 'non-array-fields'));
        $t->same(false, str_contains($plainText, 'local-indirect-selector.fdf'));
        $t->same(false, str_contains($plainText, 'Indirect Selector Reviewer'));
        $t->same(false, str_contains($plainText, 'decoy.'));
    },
];
