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

$signatureFieldActionStatePdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 10 0 R 12 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] /A 40 0 R /AA << /Fo << /S /Named /N /Print >> /Bl << /S /Hide /T [9 0 R] /H true >> /V 41 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 /AS /Signed /AP << /N << /Signed 50 0 R /Off 51 0 R >> >> /A << /S /GoTo /D [3 0 R /Fit] >> >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Final title) /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "11 0 obj\n<< /FT /Tx /T (internal.notes) /V (Editable note) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 560 300 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /Reason (Signed before import) /M (D:20260602155600Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
        . "40 0 obj\n<< /S /URI /URI (javascript:app.alert\\('signature activation blocked'\\)) >>\nendobj\n"
        . "41 0 obj\n<< /S /Launch /F (signed-review.exe) /NewWindow true >>\nendobj\n"
        . "50 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 44] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "51 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'summarizes signed signature field actions current value appearance and lock state without executing them' => static function (TestRunner $t) use ($signatureFieldActionStatePdf, $fieldsByName): void {
        $form = (new PdfAcroFormExtractor())->extractForm($signatureFieldActionStatePdf());
        $fields = $fieldsByName($form['fields']);
        $signature = $fields['approval.signature'];
        $state = $signature['signature_action_state'];

        $t->same('acroform_signature_field_action_state_boundary', $state['source']);
        $t->same('approval.signature', $state['field_name']);
        $t->same(6, $state['field_object']);
        $t->true($state['signed']);
        $t->same(30, $state['signature_object']);
        $t->same('D:20260602155600Z', $state['signed_at']);
        $t->same('signature_dictionary_not_field_value', $state['value_state_source']);
        $t->same(false, $state['field_value_used_for_signature']);
        $t->same(false, $state['field_value_used_for_import']);
        $t->same(false, $state['appearance_value_used_for_signature']);
        $t->same(false, $state['appearance_value_used_for_import']);
        $t->same(5, $state['action_count']);
        $t->same(4, $state['field_action_count']);
        $t->same(1, $state['widget_action_count']);
        $t->same(['URI', 'Named', 'Hide', 'Launch', 'GoTo'], $state['action_types']);
        $t->same(['activation', 'Fo', 'Bl', 'V'], $state['action_triggers']);
        $t->same([
            'blocked-unsafe-uri',
            'named-action-review',
            'hide-action-review',
            'launch-action-review',
            'local-destination-review',
        ], $state['action_safety_labels']);
        $t->same(1, $state['blocked_unsafe_action_count']);
        $t->same(1, $state['launch_action_count']);
        $t->same(5, $state['review_only_action_count']);
        $t->same(false, $state['executes_action']);
        $t->same(false, $state['executes_javascript']);
        $t->same(false, $state['executes_signature_validation']);
        $t->same(false, $state['executes_signing']);
        $t->same(1, $state['widget_count']);
        $t->same([8], $state['widget_objects']);
        $t->same(['Signed'], $state['appearance_states']);
        $t->same([50], $state['selected_appearance_objects']);
        $t->same(0, $state['stale_appearance_state_count']);
        $t->same('Include', $state['signature_lock_action']);
        $t->same(['article.title'], $state['signature_lock_field_names']);
        $t->true($state['signature_lock_applies_after_signing']);
        $t->same(false, $state['signature_lock_effective_locked']);
        $t->same(0, $state['locked_by_signature_count']);

        $titleLock = $fields['article.title']['signature_lock_state'];
        $t->true($titleLock['effective_locked']);
        $t->same(['approval.signature'], $titleLock['locked_by_signatures']);
        $t->same(false, $titleLock['executes_action']);
        $t->same('Final title', $fields['article.title']['value']);
    },
];
