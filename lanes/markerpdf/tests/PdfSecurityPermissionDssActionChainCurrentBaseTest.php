<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$permissionDssActionChainPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Permission DSS action chain import) Tj ET';
    $signaturePayload = 'PERMISSION_DSS_ACTION_CHAIN_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $certPayload = 'PERMISSION_DSS_ACTION_CHAIN_CERT_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'PERMISSION_DSS_ACTION_CHAIN_OCSP_BYTES_SHOULD_NOT_LEAK';

    $signedPrefix = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R /OpenAction 80 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.permissionDssChain) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed title) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 300 718] /A 82 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Permission DSS Reviewer) /M (D:20260602223633Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R << /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >> << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 33 0 R >>] >>\nendobj\n"
        . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADBEEF> /TransformParams 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [(article.title)] >>\nendobj\n"
        . "33 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn] /Signature [/Modify] /Annots [/Create] >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602223633Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n";
    $postSignatureActions = "80 0 obj\n<< /S /URI /URI (https://example.test/open-permission-review) /Next [81 0 R] >>\nendobj\n"
        . "81 0 obj\n<< /S /Launch /F (permission-dss-helper.exe) /Win << /F (permission-dss-helper.exe) /O (open) >> >>\nendobj\n"
        . "82 0 obj\n<< /S /SubmitForm /F (https://example.test/export-permission-review) /Fields [9 0 R] /Flags 4 >>\nendobj\n"
        . "%%EOF";
    $pdf = $signedPrefix . $postSignatureActions;

    $gapStart = strpos($pdf, $signatureContentsToken);
    $postSignatureOffset = strpos($pdf, "80 0 obj\n");
    if ($gapStart === false || $postSignatureOffset === false) {
        throw new RuntimeException('Unable to locate signature or post-signature action boundary.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', $postSignatureOffset - $gapEnd),
    ]);

    return [$pdf, $signaturePayload, $vriKey, hash('sha256', $certPayload), hash('sha256', $ocspPayload), $postSignatureOffset];
};

return [
    'summarizes DSS permission context for post-signature action chains without granting execution' => static function (TestRunner $t) use ($permissionDssActionChainPdf): void {
        [$pdf, $signaturePayload, $vriKey, $certHash, $ocspHash, $postSignatureOffset] = $permissionDssActionChainPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $actionReview = $report['document_action_security_review'];
        $context = $actionReview['dss_certificate_action_permission_review'];
        $chainReview = $actionReview['permission_dss_action_chain_review'];
        $actions = $actionReview['actions'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('Permission DSS action chain import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_boundary', $report['import_decision']);
        $t->same([
            'signed_signature_present',
            'signature_reference_transforms_present',
            'signature_byte_range_invalid',
            'document_security_store_present',
            'form_data_actions_present',
            'unsafe_pdf_actions_present',
            'launch_actions_present',
            'post_signature_pdf_actions_present',
        ], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation', 'pdf_action_execution'], $report['blocked_operations']);

        $t->same(false, $report['signatures'][0]['byte_range']['valid']);
        $t->same('incomplete_file_coverage', $report['signatures'][0]['byte_range']['status']);
        $t->same($postSignatureOffset, $report['signatures'][0]['byte_range']['segments'][1]['end']);
        $t->same('matched_signature_contents_sha1', $report['document_security_store_signature_review']['vri_signature_rows'][0]['match_status']);
        $t->same($vriKey, $report['document_security_store_signature_review']['vri_signature_rows'][0]['key']);
        $t->same($certHash, $report['document_security_store_signature_review']['vri_signature_rows'][0]['validation_hashes']['certificates'][0]);
        $t->same($ocspHash, $report['document_security_store_signature_review']['vri_signature_rows'][0]['validation_hashes']['ocsps'][0]);

        $t->same('dss_certificate_action_permission_review', $context['source']);
        $t->same(3, $context['action_count']);
        $t->same(2, $context['unsafe_action_count']);
        $t->same(3, $context['post_signature_action_count']);
        $t->same(3, $context['unsigned_action_byte_range_count']);
        $t->same(2, $context['post_signature_unsafe_action_count']);
        $t->same([80, 81, 82], $context['post_signature_action_objects']);
        $t->same(['outside_all_signature_byte_ranges'], $context['action_byte_range_statuses']);
        $t->same(['URI', 'Launch', 'SubmitForm'], $context['post_signature_action_types']);
        $t->same(['review-uri', 'blocked-launch', 'submit-form-action-review'], $context['post_signature_safety_labels']);
        $t->same(1, $context['dss_certificate_count']);
        $t->same(1, $context['dss_vri_signature_match_count']);
        $t->same(3, $context['signature_permission_transform_count']);
        $t->same(['FieldMDP', 'DocMDP', 'UR3'], $context['signature_permission_transform_methods']);
        $t->same(['locks_included_fields'], $context['field_mdp_action_labels']);
        $t->same(['article.title'], $context['field_mdp_field_names']);
        $t->same(['document', 'form', 'signature', 'annotations'], $context['usage_right_categories']);
        $t->same(false, $context['post_signature_actions_granted_by_permissions']);
        $t->same(false, $context['dss_validation_grants_action_execution']);
        $t->same(false, $context['executes_pdf_actions']);
        $t->same(false, $context['executes_rights_enforcement']);

        $t->same('permission_dss_action_chain_review', $chainReview['source']);
        $t->same(true, $chainReview['present']);
        $t->same(3, $chainReview['action_count']);
        $t->same(3, $chainReview['post_signature_action_count']);
        $t->same(2, $chainReview['post_signature_unsafe_action_count']);
        $t->same([80, 81, 82], $chainReview['post_signature_action_objects']);
        $t->same(['outside_all_signature_byte_ranges'], $chainReview['action_byte_range_statuses']);
        $t->same(['URI', 'Launch', 'SubmitForm'], $chainReview['post_signature_action_types']);
        $t->same(['review-uri', 'blocked-launch', 'submit-form-action-review'], $chainReview['post_signature_safety_labels']);
        $t->same(['FieldMDP', 'DocMDP', 'UR3'], $chainReview['signature_permission_transform_methods']);
        $t->same(true, $chainReview['dss_present']);
        $t->same(1, $chainReview['dss_certificate_count']);
        $t->same(false, $chainReview['post_signature_actions_granted_by_permissions']);
        $t->same(false, $chainReview['dss_validation_grants_action_execution']);
        $t->same(false, $chainReview['executes_pdf_actions']);
        $t->same(false, $chainReview['executes_signature_validation']);
        $t->same(false, $chainReview['executes_trust_chain_validation']);

        $t->same(['URI', 'Launch', 'SubmitForm'], array_column($actions, 'action_type'));
        $t->same([80, 81, 82], array_column($actions, 'action_object'));
        foreach ($actions as $action) {
            $t->same('outside_all_signature_byte_ranges', $action['signature_byte_range_coverage_status']);
            $t->same(true, $action['outside_any_signature_byte_range']);
            $t->same(0, $action['signature_byte_range_signed_coverage_count']);
            $t->same(1, $action['signature_byte_range_unsigned_coverage_count']);
            $t->same(['FieldMDP', 'DocMDP', 'UR3'], $action['signature_permission_transform_methods']);
            $t->same(false, $action['cert_permissions_grant_action_execution']);
            $t->same(false, $action['executes_rights_enforcement']);
            $t->same('outside_signed_revision', $action['signature_byte_range_reviews'][0]['coverage_status']);
        }

        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'PERMISSION_DSS_ACTION_CHAIN_CERT_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'PERMISSION_DSS_ACTION_CHAIN_OCSP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DEADBEEF'));
    },
    'keeps permission DSS action-chain operands out of visible WordPress text' => static function (TestRunner $t) use ($permissionDssActionChainPdf): void {
        [$pdf] = $permissionDssActionChainPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Permission DSS action chain import', $plainText);
        $t->true(!str_contains($plainText, 'permission-dss-helper.exe'));
        $t->true(!str_contains($plainText, 'export-permission-review'));
        $t->true(!str_contains($plainText, 'PERMISSION_DSS_ACTION_CHAIN_CERT_BYTES_SHOULD_NOT_LEAK'));
    },
];
