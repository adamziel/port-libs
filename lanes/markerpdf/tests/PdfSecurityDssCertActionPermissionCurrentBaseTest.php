<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$dssCertActionPermissionPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (DSS certificate action permission import) Tj ET';
    $signaturePayload = 'DSS_CERT_ACTION_PERMISSION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $globalCertPayload = 'DSS_CERT_ACTION_GLOBAL_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $vriCertPayload = 'DSS_CERT_ACTION_VRI_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'DSS_CERT_ACTION_OCSP_BYTES_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R /OpenAction 80 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.permission) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed title) /Kids [11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (internal.notes) /V (Permission review notes) >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 300 718] /A 82 0 R /AA << /E 83 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (DSS Permission Reviewer) /M (D:20260602192222Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 33 0 R >>] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [9 0 R (internal.notes)] >>\nendobj\n"
        . "33 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn /Export] /Signature [/Modify] /Annots [/Create /Modify] /EF [/Create] /Msg (Reader rights review only) >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [72 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [71 0 R] /OCSP [72 0 R] /TU (D:20260602192222Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($globalCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$globalCertPayload}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($vriCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$vriCertPayload}\nendstream\nendobj\n"
        . "72 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
        . "80 0 obj\n<< /S /URI /URI (https://example.com/signed-open-action) /Next 81 0 R >>\nendobj\n"
        . "81 0 obj\n<< /S /Launch /F (permission-helper.exe) /Win << /F (permission-helper.exe) /O (open) >> >>\nendobj\n"
        . "82 0 obj\n<< /S /URI /URI (javascript:permissionReview\\(\\)) >>\nendobj\n"
        . "83 0 obj\n<< /S /SubmitForm /F (https://example.test/export) /Fields [9 0 R] /Flags 4 >>\nendobj\n"
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

    return [
        $pdf,
        $signaturePayload,
        hash('sha256', $globalCertPayload),
        hash('sha256', $vriCertPayload),
        hash('sha256', $ocspPayload),
    ];
};

return [
    'summarizes DSS certificates and signature permission transforms on document actions without execution' => static function (TestRunner $t) use ($dssCertActionPermissionPdf): void {
        [$pdf, $signaturePayload, $globalCertHash, $vriCertHash, $ocspHash] = $dssCertActionPermissionPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $permissionReview = $actionReview['signature_permission_transform_review'];
        $certificateReview = $actionReview['dss_certificate_review'];
        $context = $actionReview['dss_certificate_action_permission_review'];
        $actions = $actionReview['actions'];

        $t->same('DSS certificate action permission import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same([
            'signed_signature_present',
            'signature_reference_transforms_present',
            'document_security_store_present',
            'form_data_actions_present',
            'unsafe_pdf_actions_present',
            'launch_actions_present',
            'unsafe_uri_actions_present',
        ], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation', 'pdf_action_execution'], $report['blocked_operations']);

        $t->same('pdf_document_action_security_review', $actionReview['source']);
        $t->same(4, $actionReview['action_count']);
        $t->same(2, $actionReview['open_action_count']);
        $t->same(2, $actionReview['annotation_action_count']);
        $t->same(1, $actionReview['launch_action_count']);
        $t->same(1, $actionReview['form_submit_action_count']);
        $t->same(1, $actionReview['unsafe_uri_action_count']);
        $t->same(3, $actionReview['unsafe_action_count']);
        $t->same(['URI', 'Launch', 'SubmitForm'], $actionReview['action_types']);
        $t->same(['review-uri', 'blocked-launch', 'blocked-unsafe-uri', 'submit-form-action-review'], $actionReview['safety_labels']);

        $t->same('document_security_store_certificate_review', $certificateReview['source']);
        $t->same(true, $certificateReview['present']);
        $t->same(2, $certificateReview['certificate_count']);
        $t->same(1, $certificateReview['global_certificate_count']);
        $t->same(1, $certificateReview['vri_certificate_count']);
        $t->same([70, 71], $certificateReview['certificate_objects']);
        $t->same([$globalCertHash, $vriCertHash], $certificateReview['certificate_hashes']);
        $t->same(1, $certificateReview['matched_signature_count']);
        $t->same([30], $certificateReview['matched_signature_objects']);
        $t->same(false, $certificateReview['raw_certificate_bytes_exposed']);
        $t->same(false, $certificateReview['executes_trust_chain_validation']);

        $t->same('signature_permission_transform_review', $permissionReview['source']);
        $t->same(true, $permissionReview['present']);
        $t->same(2, $permissionReview['transform_count']);
        $t->same(['FieldMDP', 'UR3'], $permissionReview['methods']);
        $t->same(1, $permissionReview['field_mdp_transform_count']);
        $t->same(['locks_included_fields'], $permissionReview['field_mdp_action_labels']);
        $t->same(['article.title', 'internal.notes'], $permissionReview['field_mdp_field_names']);
        $t->same(['article.title', 'internal.notes'], $permissionReview['field_mdp_included_fields']);
        $t->same([], $permissionReview['field_mdp_excluded_fields']);
        $t->same(1, $permissionReview['usage_rights_transform_count']);
        $t->same(['document', 'form', 'signature', 'annotations', 'embedded_files'], $permissionReview['usage_right_categories']);
        $t->same(7, $permissionReview['usage_right_count']);
        $t->same(['FillIn', 'Export'], $permissionReview['usage_rights']['form']);
        $t->same(false, $permissionReview['executes_rights_enforcement']);

        $t->same('dss_certificate_action_permission_review', $context['source']);
        $t->same(4, $context['action_count']);
        $t->same(3, $context['unsafe_action_count']);
        $t->same(2, $context['dss_certificate_count']);
        $t->same(1, $context['dss_vri_signature_match_count']);
        $t->same(2, $context['signature_permission_transform_count']);
        $t->same(['FieldMDP', 'UR3'], $context['signature_permission_transform_methods']);
        $t->same(false, $context['executes_pdf_actions']);
        $t->same(false, $context['executes_signature_validation']);
        $t->same(false, $context['executes_trust_chain_validation']);

        $t->same(['URI', 'Launch', 'URI', 'SubmitForm'], array_column($actions, 'action_type'));
        $t->same([80, 81, 82, 83], array_column($actions, 'action_object'));
        foreach ($actions as $action) {
            $t->same(2, $action['dss_certificate_count']);
            $t->same([$globalCertHash, $vriCertHash], $action['dss_certificate_hashes']);
            $t->same(1, $action['dss_vri_signature_match_count']);
            $t->same(['FieldMDP', 'UR3'], $action['signature_permission_transform_methods']);
            $t->same(['locks_included_fields'], $action['field_mdp_action_labels']);
            $t->same(['article.title', 'internal.notes'], $action['field_mdp_field_names']);
            $t->same(['document', 'form', 'signature', 'annotations', 'embedded_files'], $action['usage_right_categories']);
            $t->same(true, $action['signature_permission_review_only']);
            $t->same(true, $action['dss_validation_review_only']);
            $t->same(false, $action['executes_rights_enforcement']);
            $t->same(false, $action['executes_trust_chain_validation']);
            $t->same('covered_by_all_signature_byte_ranges', $action['signature_byte_range_coverage_status']);
        }

        $submit = $actions[3];
        $t->same('https://example.test/export', $submit['target']);
        $t->same(['article.title'], $submit['action_field_names']);
        $t->same($ocspHash, $report['document_security_store']['global_ocsps'][0]['sha256']);

        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'DSS_CERT_ACTION_GLOBAL_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_CERT_ACTION_VRI_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_CERT_ACTION_OCSP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DEADC0DE'));
    },
    'keeps DSS certificate and action permission payloads out of visible WordPress text' => static function (TestRunner $t) use ($dssCertActionPermissionPdf): void {
        [$pdf] = $dssCertActionPermissionPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('DSS certificate action permission import', $plainText);
        $t->true(!str_contains($plainText, 'permission-helper.exe'));
        $t->true(!str_contains($plainText, 'permissionReview'));
        $t->true(!str_contains($plainText, 'example.test/export'));
        $t->true(!str_contains($plainText, 'DSS_CERT_ACTION_GLOBAL_CERTIFICATE_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'DSS_CERT_ACTION_VRI_CERTIFICATE_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'DSS_CERT_ACTION_SIGNATURE_BYTES_SHOULD_NOT_LEAK'));
    },
];
