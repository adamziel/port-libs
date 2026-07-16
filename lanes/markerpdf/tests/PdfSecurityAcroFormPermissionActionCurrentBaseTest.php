<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$signedAcroFormPermissionActionPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (AcroForm permission action import) Tj ET';
    $signaturePayload = 'ACROFORM_PERMISSION_ACTION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $scriptPayload = "app.alert('locked field validation action should not execute');";

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Final locked title) /Kids [10 0 R] /AA << /V 40 0 R /K 41 0 R >> >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 580 320 606] /P 3 0 R /F 4 /A 45 0 R /AA << /Fo 46 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Permission Reviewer) /M (D:20260602182900Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
        . "40 0 obj\n<< /S /SubmitForm /F (https://example.test/signed-submit) /Fields [9 0 R] /Flags 6 /Next [42 0 R 43 0 R] >>\nendobj\n"
        . "41 0 obj\n<< /S /JavaScript /JS ({$scriptPayload}) >>\nendobj\n"
        . "42 0 obj\n<< /S /ImportData /F (file://local-review.fdf) >>\nendobj\n"
        . "43 0 obj\n<< /S /Hide /T [10 0 R] /H false >>\nendobj\n"
        . "45 0 obj\n<< /S /URI /URI (javascript:signatureImport\\(\\)) >>\nendobj\n"
        . "46 0 obj\n<< /S /ResetForm /Fields [(article.title)] /Next 47 0 R >>\nendobj\n"
        . "47 0 obj\n<< /S /Launch /F (acroform-helper.exe) /NewWindow true >>\nendobj\n"
        . "%%EOF";

    $gapStart = strpos($pdf, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in focused fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
    ]);

    return [$pdf, $signaturePayload, $scriptPayload];
};

return [
    'summarizes AcroForm permission scoped actions in security preflight without executing them' => static function (TestRunner $t) use ($signedAcroFormPermissionActionPdf): void {
        [$pdf] = $signedAcroFormPermissionActionPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $actions = $actionReview['actions'];

        $t->same('AcroForm permission action import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->true(in_array('signed_signature_present', $report['review_reasons'], true));
        $t->true(in_array('signature_reference_transforms_present', $report['review_reasons'], true));
        $t->true(in_array('signed_field_locks_present', $report['review_reasons'], true));
        $t->true(in_array('acroform_actions_present', $report['review_reasons'], true));
        $t->true(in_array('signed_locked_field_actions_present', $report['review_reasons'], true));
        $t->true(in_array('form_data_actions_present', $report['review_reasons'], true));
        $t->true(in_array('unsafe_pdf_actions_present', $report['review_reasons'], true));
        $t->same(['signature_validation', 'signing', 'pdf_action_execution', 'form_action_execution'], $report['blocked_operations']);

        $t->same('pdf_document_action_security_review', $actionReview['source']);
        $t->true($actionReview['present']);
        $t->same(7, $actionReview['action_count']);
        $t->same(4, $actionReview['acroform_action_count']);
        $t->same(4, $actionReview['acroform_field_action_count']);
        $t->same(0, $actionReview['acroform_widget_action_count']);
        $t->same(7, $actionReview['signed_locked_field_action_count']);
        $t->same(1, $actionReview['form_submit_action_count']);
        $t->same(1, $actionReview['form_reset_action_count']);
        $t->same(1, $actionReview['import_data_action_count']);
        $t->same(1, $actionReview['hide_action_count']);
        $t->same(1, $actionReview['javascript_action_count']);
        $t->same(1, $actionReview['launch_action_count']);
        $t->same(1, $actionReview['unsafe_uri_action_count']);
        $t->same(7, $actionReview['unsafe_action_count']);
        $t->same(['URI', 'ResetForm', 'Launch', 'JavaScript', 'SubmitForm', 'ImportData', 'Hide'], $actionReview['action_types']);
        $t->same(['blocked-unsafe-uri', 'reset-form-action-review', 'blocked-launch', 'blocked-javascript', 'submit-form-action-review', 'import-data-action-review', 'hide-action-review'], $actionReview['safety_labels']);
        $t->same(['page_annotation_action', 'page_annotation_additional_action', 'page_annotation_additional_action', 'acroform_field_action', 'acroform_field_action', 'acroform_field_action', 'acroform_field_action'], array_column($actions, 'source'));
        $t->same([null, null, null, 'K', 'V', 'V', 'V'], array_column($actions, 'trigger'));
        $t->same([null, null, null, 'keystroke', 'validate', 'validate', 'validate'], array_column($actions, 'trigger_label'));
        $t->same(['article.title'], $actionReview['acroform_action_field_names']);
        $t->same(['form_fill_templates_signatures'], $actionReview['signed_locked_field_permission_labels']);
        $t->same(['approval.signature'], $actionReview['signed_locked_by_signatures']);
        $t->same(false, $actionReview['executes_actions_on_import']);
        $t->same(false, $report['executes_pdf_actions']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_external_pdf_tools']);

        $t->same('URI', $actions[0]['action_type']);
        $t->same('article.title', $actions[0]['field_name']);
        $t->same(10, $actions[0]['widget_object']);
        $t->same(true, $actions[0]['field_locked_by_signed_signature']);
        $t->same('javascript:signatureImport()', $actions[0]['uri']);
        $t->same(false, $actions[0]['is_safe_uri']);
        $t->same('ResetForm', $actions[1]['action_type']);
        $t->same(['article.title'], $actions[1]['action_field_names']);
        $t->same('Launch', $actions[2]['action_type']);
        $t->same('acroform-helper.exe', $actions[2]['file']);
        $t->same(true, $actions[2]['new_window']);
        $t->same('JavaScript', $actions[3]['action_type']);
        $t->same('blocked-javascript', $actions[3]['safety']);

        $submit = $actions[4];
        $t->same('SubmitForm', $submit['action_type']);
        $t->same('article.title', $submit['field_name']);
        $t->same(9, $submit['field_object']);
        $t->same(true, $submit['field_locked_by_signed_signature']);
        $t->same(['approval.signature'], $submit['locked_by_signatures']);
        $t->same(['form_fill_templates_signatures'], $submit['permission_labels']);
        $t->same(['article.title'], $submit['action_field_names']);
        $t->same('https://example.test/signed-submit', $submit['target']);
        $t->same('https', $submit['target_scheme']);
        $t->same('html', $submit['submit_format']);

        $t->same('ImportData', $actions[5]['action_type']);
        $t->same('file://local-review.fdf', $actions[5]['target']);
        $t->same('file', $actions[5]['target_scheme']);
        $t->same('Hide', $actions[6]['action_type']);
        $t->same('show', $actions[6]['operation']);
        $t->same(['article.title'], $actions[6]['action_field_names']);
    },
    'keeps AcroForm action payloads and signature bytes out of visible WordPress text and security JSON' => static function (TestRunner $t) use ($signedAcroFormPermissionActionPdf): void {
        [$pdf, $signaturePayload, $scriptPayload] = $signedAcroFormPermissionActionPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode((new PdfSecurityPreflight())->analyze($pdf), JSON_UNESCAPED_SLASHES);

        $t->contains('AcroForm permission action import', $plainText);
        $t->true(!str_contains($plainText, 'signed-submit'));
        $t->true(!str_contains($plainText, 'local-review.fdf'));
        $t->true(!str_contains($plainText, 'signatureImport'));
        $t->true(!str_contains($plainText, 'acroform-helper.exe'));
        $t->true(!str_contains($plainText, $scriptPayload));
        $t->true(is_string($encoded));
        $t->true(!str_contains((string) $encoded, $signaturePayload));
        $t->true(!str_contains((string) $encoded, strtoupper(bin2hex($signaturePayload))));
        $t->true(!str_contains((string) $encoded, $scriptPayload));
    },
];
