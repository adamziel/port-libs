<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$signedDssWithPostSignatureActionsPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Signed DSS action byte range import) Tj ET';
    $signaturePayload = 'DSS_ACTION_BYTE_RANGE_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $certPayload = 'DSS_ACTION_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'DSS_ACTION_OCSP_BYTES_SHOULD_NOT_LEAK';

    $signedPrefix = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R /OpenAction 80 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.actionBoundary) /V 30 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Action Boundary Reviewer) /M (D:20260602181423Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 1 /V /1.2 >> >>] >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602181423Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n";
    $postSignatureActions = "80 0 obj\n<< /S /Launch /F (unsigned-post-signature-helper.exe) /Win << /F (unsigned-post-signature-helper.exe) /O (open) >> /Next 81 0 R >>\nendobj\n"
        . "81 0 obj\n<< /S /URI /URI (javascript:postSignature\\(\\)) >>\nendobj\n"
        . "%%EOF";
    $pdf = $signedPrefix . $postSignatureActions;

    $gapStart = strpos($pdf, $signatureContentsToken);
    $postSignatureOffset = strpos($pdf, "80 0 obj\n");
    if ($gapStart === false || $postSignatureOffset === false) {
        throw new RuntimeException('Unable to locate signature or post-signature action fixture boundary.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', $postSignatureOffset - $gapEnd),
    ]);

    return [
        $pdf,
        $signaturePayload,
        $vriKey,
        hash('sha256', $certPayload),
        hash('sha256', $ocspPayload),
        $postSignatureOffset,
    ];
};

return [
    'flags DSS certified OpenAction objects appended outside the signature byte range' => static function (TestRunner $t) use ($signedDssWithPostSignatureActionsPdf): void {
        [$pdf, $signaturePayload, $vriKey, $certHash, $ocspHash, $postSignatureOffset] = $signedDssWithPostSignatureActionsPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $signature = $report['signatures'][0] ?? [];
        $signatureReview = $signature['signature_security_review'] ?? [];
        $actionReview = $report['document_action_security_review'];
        $actions = $actionReview['actions'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('Signed DSS action byte range import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_boundary', $report['import_decision']);
        $t->same([
            'signed_signature_present',
            'signature_reference_transforms_present',
            'signature_byte_range_invalid',
            'document_security_store_present',
            'unsafe_pdf_actions_present',
            'launch_actions_present',
            'unsafe_uri_actions_present',
            'post_signature_pdf_actions_present',
        ], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation', 'pdf_action_execution'], $report['blocked_operations']);

        $t->same(false, $signature['byte_range']['valid']);
        $t->same('incomplete_file_coverage', $signature['byte_range']['status']);
        $t->same($postSignatureOffset, $signature['byte_range']['segments'][1]['end']);
        $t->same(true, $signatureReview['dss_present']);
        $t->same('matched_signature_contents_sha1', $signatureReview['dss_vri_match_status']);
        $t->same($vriKey, $signatureReview['dss_vri_key']);
        $t->same($certHash, $signatureReview['dss_vri_validation_hashes']['certificates'][0]);
        $t->same($ocspHash, $signatureReview['dss_vri_validation_hashes']['ocsps'][0]);
        $t->same('review_required_signature_boundary', $signatureReview['review_decision']);

        $t->same('pdf_document_action_security_review', $actionReview['source']);
        $t->same(2, $actionReview['action_count']);
        $t->same(2, $actionReview['open_action_count']);
        $t->same(1, $actionReview['launch_action_count']);
        $t->same(1, $actionReview['unsafe_uri_action_count']);
        $t->same(2, $actionReview['unsafe_action_count']);
        $t->same(2, $actionReview['action_byte_range_review_count']);
        $t->same(2, $actionReview['post_signature_action_count']);
        $t->same(2, $actionReview['unsigned_action_byte_range_count']);
        $t->same([80, 81], $actionReview['post_signature_action_objects']);
        $t->same(['outside_all_signature_byte_ranges'], $actionReview['action_byte_range_statuses']);
        $t->same(true, $actionReview['has_post_signature_actions']);

        $t->same(['Launch', 'URI'], array_column($actions, 'action_type'));
        $t->same([80, 81], array_column($actions, 'action_object'));
        foreach ($actions as $action) {
            $t->same('outside_all_signature_byte_ranges', $action['signature_byte_range_coverage_status']);
            $t->same(false, $action['covered_by_all_signature_byte_ranges']);
            $t->same(true, $action['outside_any_signature_byte_range']);
            $t->same(0, $action['signature_byte_range_signed_coverage_count']);
            $t->same(1, $action['signature_byte_range_unsigned_coverage_count']);
            $t->true(($action['action_object_span']['offset'] ?? 0) >= $postSignatureOffset);
            $t->same('outside_signed_revision', $action['signature_byte_range_reviews'][0]['coverage_status']);
            $t->same('approval.actionBoundary', $action['signature_byte_range_reviews'][0]['field_name']);
            $t->same(30, $action['signature_byte_range_reviews'][0]['signature_object']);
            $t->same('incomplete_file_coverage', $action['signature_byte_range_reviews'][0]['byte_range_status']);
        }

        $t->same(false, $actionReview['executes_actions_on_import']);
        $t->same(false, $report['executes_pdf_actions']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_revocation_check']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'DSS_ACTION_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_ACTION_OCSP_BYTES_SHOULD_NOT_LEAK'));
    },
    'keeps unsigned post-signature action targets out of visible WordPress text' => static function (TestRunner $t) use ($signedDssWithPostSignatureActionsPdf): void {
        [$pdf] = $signedDssWithPostSignatureActionsPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Signed DSS action byte range import', $plainText);
        $t->true(!str_contains($plainText, 'unsigned-post-signature-helper.exe'));
        $t->true(!str_contains($plainText, 'javascript:postSignature'));
    },
];
