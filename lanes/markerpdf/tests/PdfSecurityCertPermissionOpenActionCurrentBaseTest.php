<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$certPermissionOpenActionPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Certificate permission OpenAction import) Tj ET';
    $signaturePayload = 'CERT_PERMISSION_OPENACTION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $certificatePayload = 'CERT_PERMISSION_OPENACTION_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R /OpenAction 40 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.openaction) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Certified title) /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (OpenAction Permission Reviewer) /M (D:20260602200039Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 1 /V /1.2 >> >> 31 0 R << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 33 0 R >>] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /TransformParams 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /All >>\nendobj\n"
        . "33 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn /Export] /Msg (Usage rights review only) >>\nendobj\n"
        . "40 0 obj\n<< /S /URI /URI (https://example.com/certified-open-action) /Next [41 0 R 42 0 R] >>\nendobj\n"
        . "41 0 obj\n<< /S /JavaScript /JS (app.alert\\('certified open action review only'\\)) >>\nendobj\n"
        . "42 0 obj\n<< /S /Launch /F (open-action-helper.exe) /Win << /F (open-action-helper.exe) /O (open) >> >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /TU (D:20260602200039Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($certificatePayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certificatePayload}\nendstream\nendobj\n"
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

    return [$pdf, $signaturePayload, $certificatePayload, hash('sha256', $certificatePayload)];
};

return [
    'classifies certified catalog OpenAction rows as review-only permission metadata' => static function (TestRunner $t) use ($certPermissionOpenActionPdf): void {
        [$pdf, $signaturePayload, $certificatePayload, $certificateHash] = $certPermissionOpenActionPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $openActionReview = $actionReview['cert_permission_open_action_review'];
        $permissionReview = $actionReview['signature_permission_transform_review'];
        $actions = $actionReview['actions'];

        $t->same('Certificate permission OpenAction import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->true(in_array('signed_signature_present', $report['review_reasons'], true));
        $t->true(in_array('signature_reference_transforms_present', $report['review_reasons'], true));
        $t->true(in_array('document_security_store_present', $report['review_reasons'], true));
        $t->true(in_array('launch_actions_present', $report['review_reasons'], true));
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation', 'pdf_action_execution'], $report['blocked_operations']);

        $t->same(3, $actionReview['action_count']);
        $t->same(3, $actionReview['open_action_count']);
        $t->same(3, $actionReview['cert_permission_open_action_count']);
        $t->same(1, $actionReview['launch_action_count']);
        $t->same(1, $actionReview['javascript_action_count']);
        $t->same(2, $actionReview['unsafe_action_count']);
        $t->same(['URI', 'JavaScript', 'Launch'], $actionReview['action_types']);
        $t->same(['review-uri', 'blocked-javascript', 'blocked-launch'], $actionReview['safety_labels']);

        $t->same('signature_permission_transform_review', $permissionReview['source']);
        $t->same(['DocMDP', 'FieldMDP', 'UR3'], $permissionReview['methods']);
        $t->same(['no_changes'], $permissionReview['doc_mdp_permission_labels']);
        $t->same([], $permissionReview['doc_mdp_allowed_changes']);
        $t->same(['locks_all_fields'], $permissionReview['field_mdp_action_labels']);
        $t->same(true, $permissionReview['field_mdp_locks_all_fields']);
        $t->same(['document', 'form'], $permissionReview['usage_right_categories']);
        $t->same(3, $permissionReview['usage_right_count']);

        $t->same('cert_permission_open_action_review', $openActionReview['source']);
        $t->same(true, $openActionReview['present']);
        $t->same(3, $openActionReview['open_action_count']);
        $t->same(2, $openActionReview['unsafe_open_action_count']);
        $t->same([40, 41, 42], $openActionReview['open_action_objects']);
        $t->same(['URI', 'JavaScript', 'Launch'], $openActionReview['open_action_types']);
        $t->same(['review-uri', 'blocked-javascript', 'blocked-launch'], $openActionReview['open_action_safety_labels']);
        $t->same(['catalog_open_action_review_only_not_granted_by_cert_permissions'], $openActionReview['open_action_permission_statuses']);
        $t->same(['no_changes'], $openActionReview['doc_mdp_permission_labels']);
        $t->same([], $openActionReview['doc_mdp_allowed_changes']);
        $t->same(['locks_all_fields'], $openActionReview['field_mdp_action_labels']);
        $t->same(['document', 'form'], $openActionReview['usage_right_categories']);
        $t->same(['DocMDP', 'FieldMDP', 'UR3'], $openActionReview['signature_permission_transform_methods']);
        $t->same(false, $openActionReview['cert_permissions_grant_open_action_execution']);
        $t->same(false, $openActionReview['cert_permissions_allow_catalog_open_action_mutation']);
        $t->same(false, $openActionReview['executes_rights_enforcement']);
        $t->same(false, $openActionReview['executes_signature_validation']);

        $t->same(['URI', 'JavaScript', 'Launch'], array_column($actions, 'action_type'));
        foreach ($actions as $action) {
            $t->same('catalog_open_action', $action['source']);
            $t->same('catalog_open_action_review_only_not_granted_by_cert_permissions', $action['open_action_permission_status']);
            $t->same(false, $action['open_action_allowed_by_cert_permissions']);
            $t->same(true, $action['open_action_requires_security_review']);
            $t->same(false, $action['cert_permissions_grant_action_execution']);
            $t->same(['no_changes'], $action['doc_mdp_permission_labels']);
            $t->same(['DocMDP', 'FieldMDP', 'UR3'], $action['signature_permission_transform_methods']);
            $t->same([$certificateHash], $action['dss_certificate_hashes']);
            $t->same(false, $action['executes_rights_enforcement']);
            $t->same(false, $action['executes_trust_chain_validation']);
            $t->same('covered_by_all_signature_byte_ranges', $action['signature_byte_range_coverage_status']);
        }

        $t->same('https://example.com/certified-open-action', $actions[0]['uri']);
        $t->same('blocked-javascript', $actions[1]['safety']);
        $t->same('open-action-helper.exe', $actions[2]['file']);
        $t->same(false, $actionReview['executes_actions_on_import']);
        $t->same(false, $report['executes_pdf_actions']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_trust_chain_validation']);

        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, $certificatePayload));
    },
    'keeps certificate OpenAction operands out of visible WordPress text' => static function (TestRunner $t) use ($certPermissionOpenActionPdf): void {
        [$pdf] = $certPermissionOpenActionPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Certificate permission OpenAction import', $plainText);
        $t->true(!str_contains($plainText, 'certified-open-action'));
        $t->true(!str_contains($plainText, 'certified open action review only'));
        $t->true(!str_contains($plainText, 'open-action-helper.exe'));
        $t->true(!str_contains($plainText, 'CERT_PERMISSION_OPENACTION_CERTIFICATE_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'CERT_PERMISSION_OPENACTION_SIGNATURE_BYTES_SHOULD_NOT_LEAK'));
    },
];
