<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$multiSignatureDssPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (DSS multi signature import) Tj ET';
    $approvalPayload = 'DSS_SIGNATURE_APPROVAL_BYTES_SHOULD_NOT_LEAK';
    $timestampPayload = 'DSS_SIGNATURE_TIMESTAMP_BYTES_SHOULD_NOT_LEAK';
    $approvalContentsToken = '<' . strtoupper(bin2hex($approvalPayload)) . '>';
    $timestampContentsToken = '<' . strtoupper(bin2hex($timestampPayload)) . '>';
    $approvalVriKey = strtoupper(hash('sha1', $approvalPayload));
    $timestampVriKey = strtoupper(hash('sha256', $timestampPayload));
    $orphanVriKey = 'DEADBEEFCAFEBABE';
    $approvalCertPayload = 'DSS_APPROVAL_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $approvalOcspPayload = 'DSS_APPROVAL_OCSP_BYTES_SHOULD_NOT_LEAK';
    $timestampCertPayload = 'DSS_TIMESTAMP_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $timestampTokenPayload = 'DSS_TIMESTAMP_TOKEN_BYTES_SHOULD_NOT_LEAK';
    $orphanCrlPayload = 'DSS_ORPHAN_CRL_BYTES_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 7 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /FT /Sig /T (timestamp.signature) /V 31 0 R /Kids [9 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /Subtype /Widget /Parent 7 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Approval Reviewer) /M (D:20260602184300Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$approvalContentsToken} >>\nendobj\n"
        . "31 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.RFC3161 /Name (Timestamp Reviewer) /M (D:20260602184400Z) /ByteRange [0 DDDDDDDDDD EEEEEEEEEE FFFFFFFFFF] /Contents {$timestampContentsToken} >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R 72 0 R] /OCSPs [71 0 R] /CRLs [74 0 R] /VRI << /{$approvalVriKey} 61 0 R /{$timestampVriKey} 62 0 R /{$orphanVriKey} 63 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602184330Z) >>\nendobj\n"
        . "62 0 obj\n<< /Type /VRI /Cert [72 0 R] /TS 73 0 R /TU (D:20260602184430Z) >>\nendobj\n"
        . "63 0 obj\n<< /Type /VRI /CRL [74 0 R] /TU (D:20260602184500Z) >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($approvalCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$approvalCertPayload}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($approvalOcspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$approvalOcspPayload}\nendstream\nendobj\n"
        . "72 0 obj\n<< /Length " . strlen($timestampCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$timestampCertPayload}\nendstream\nendobj\n"
        . "73 0 obj\n<< /Length " . strlen($timestampTokenPayload) . " /Subtype /application#2Ftst-info >>\nstream\n{$timestampTokenPayload}\nendstream\nendobj\n"
        . "74 0 obj\n<< /Length " . strlen($orphanCrlPayload) . " /Subtype /application#2Fpkix-crl >>\nstream\n{$orphanCrlPayload}\nendstream\nendobj\n"
        . "%%EOF";

    $approvalGapStart = strpos($pdf, $approvalContentsToken);
    $timestampGapStart = strpos($pdf, $timestampContentsToken);
    if ($approvalGapStart === false || $timestampGapStart === false) {
        throw new RuntimeException('Unable to locate signature contents tokens in focused fixture.');
    }

    $approvalGapEnd = $approvalGapStart + strlen($approvalContentsToken);
    $timestampGapEnd = $timestampGapStart + strlen($timestampContentsToken);
    $pdf = strtr($pdf, [
        'AAAAAAAAAA' => sprintf('%010d', $approvalGapStart),
        'BBBBBBBBBB' => sprintf('%010d', $approvalGapEnd),
        'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $approvalGapEnd),
        'DDDDDDDDDD' => sprintf('%010d', $timestampGapStart),
        'EEEEEEEEEE' => sprintf('%010d', $timestampGapEnd),
        'FFFFFFFFFF' => sprintf('%010d', strlen($pdf) - $timestampGapEnd),
    ]);

    return [
        $pdf,
        $approvalPayload,
        $timestampPayload,
        $approvalVriKey,
        $timestampVriKey,
        $orphanVriKey,
        hash('sha256', $approvalCertPayload),
        hash('sha256', $approvalOcspPayload),
        hash('sha256', $timestampCertPayload),
        hash('sha256', $timestampTokenPayload),
        hash('sha256', $orphanCrlPayload),
    ];
};

return [
    'correlates catalog DSS VRI keys with multiple signature contents digests without validating signatures' => static function (TestRunner $t) use ($multiSignatureDssPdf): void {
        [
            $pdf,
            $approvalPayload,
            $timestampPayload,
            $approvalVriKey,
            $timestampVriKey,
            $orphanVriKey,
            $approvalCertHash,
            $approvalOcspHash,
            $timestampCertHash,
            $timestampTokenHash,
            $orphanCrlHash,
        ] = $multiSignatureDssPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $dssReview = $report['document_security_store_signature_review'];
        $rows = $dssReview['vri_signature_rows'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('DSS multi signature import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_metadata', $report['import_decision']);
        $t->same(['signed_signature_present', 'document_security_store_present'], $report['review_reasons']);
        $t->same(['signature_validation', 'signing', 'revocation_check', 'trust_chain_validation'], $report['blocked_operations']);
        $t->same(2, $report['signature_field_count']);
        $t->same(2, $report['signed_signature_count']);

        $t->same('document_security_store_signature_review', $dssReview['source']);
        $t->same(true, $dssReview['present']);
        $t->same(3, $dssReview['vri_count']);
        $t->same(2, $dssReview['matched_vri_count']);
        $t->same(1, $dssReview['unmatched_vri_count']);
        $t->same(2, $dssReview['signature_vri_match_count']);
        $t->same([30, 31], $dssReview['matched_signature_objects']);
        $t->same(['approval.signature', 'timestamp.signature'], $dssReview['matched_field_names']);
        $t->same([$orphanVriKey], $dssReview['unmatched_vri_keys']);
        $t->same(['matched_signature_contents_sha1', 'matched_signature_contents_sha256', 'no_matching_signature_contents_digest'], $dssReview['vri_match_statuses']);
        $t->same(false, $dssReview['executes_signature_validation']);
        $t->same(false, $dssReview['executes_revocation_check']);
        $t->same(false, $dssReview['raw_validation_bytes_exposed']);

        $t->same($approvalVriKey, $rows[0]['key']);
        $t->same('matched_signature_contents_sha1', $rows[0]['match_status']);
        $t->same('sha1', $rows[0]['signature_digest_algorithm']);
        $t->same([30], $rows[0]['matched_signature_objects']);
        $t->same(['approval.signature'], $rows[0]['matched_field_names']);
        $t->same(2, $rows[0]['validation_stream_count']);
        $t->same($approvalCertHash, $rows[0]['validation_hashes']['certificates'][0]);
        $t->same($approvalOcspHash, $rows[0]['validation_hashes']['ocsps'][0]);

        $t->same($timestampVriKey, $rows[1]['key']);
        $t->same('matched_signature_contents_sha256', $rows[1]['match_status']);
        $t->same('sha256', $rows[1]['signature_digest_algorithm']);
        $t->same([31], $rows[1]['matched_signature_objects']);
        $t->same(['timestamp.signature'], $rows[1]['matched_field_names']);
        $t->same(2, $rows[1]['validation_stream_count']);
        $t->same($timestampCertHash, $rows[1]['validation_hashes']['certificates'][0]);
        $t->same($timestampTokenHash, $rows[1]['validation_hashes']['timestamp_token']);

        $t->same($orphanVriKey, $rows[2]['key']);
        $t->same('no_matching_signature_contents_digest', $rows[2]['match_status']);
        $t->same(null, $rows[2]['signature_digest_algorithm']);
        $t->same([], $rows[2]['matched_signature_objects']);
        $t->same([], $rows[2]['matched_field_names']);
        $t->same(1, $rows[2]['validation_stream_count']);
        $t->same($orphanCrlHash, $rows[2]['validation_hashes']['crls'][0]);
        $t->same(false, $rows[2]['executes_signature_validation']);

        $t->same(2, $report['document_security_store_signature_match_count']);
        $t->same(1, $report['document_security_store_unmatched_vri_count']);
        $t->same(false, $report['executes_signature_validation']);
        $t->same(false, $report['executes_revocation_check']);
        $t->same(false, $report['executes_trust_chain_validation']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $approvalPayload)
            && !str_contains($encoded, $timestampPayload)
            && !str_contains($encoded, strtoupper(bin2hex($approvalPayload)))
            && !str_contains($encoded, strtoupper(bin2hex($timestampPayload)))
            && !str_contains($encoded, 'DSS_APPROVAL_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_TIMESTAMP_TOKEN_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'DSS_ORPHAN_CRL_BYTES_SHOULD_NOT_LEAK'));
    },
    'keeps DSS signature validation payloads out of visible WordPress text' => static function (TestRunner $t) use ($multiSignatureDssPdf): void {
        [$pdf] = $multiSignatureDssPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('DSS multi signature import', $plainText);
        $t->true(!str_contains($plainText, 'DSS_SIGNATURE_APPROVAL_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'DSS_SIGNATURE_TIMESTAMP_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'DSS_APPROVAL_CERTIFICATE_BYTES_SHOULD_NOT_LEAK'));
        $t->true(!str_contains($plainText, 'DSS_ORPHAN_CRL_BYTES_SHOULD_NOT_LEAK'));
    },
];
