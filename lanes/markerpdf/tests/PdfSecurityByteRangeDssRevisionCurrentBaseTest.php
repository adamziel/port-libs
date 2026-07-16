<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$signedRevisionWithAppendedDssPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Revision DSS import) Tj ET';
    $signaturePayload = 'REVISION_DSS_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
    $signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
    $vriKey = strtoupper(hash('sha1', $signaturePayload));
    $certPayload = 'REVISION_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
    $ocspPayload = 'REVISION_DSS_OCSP_BYTES_SHOULD_NOT_LEAK';
    $timestampPayload = 'REVISION_DSS_TIMESTAMP_BYTES_SHOULD_NOT_LEAK';

    $signedRevision = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.revisionBoundary) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Revision Boundary Reviewer) /M (D:20260602194949Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

    $gapStart = strpos($signedRevision, $signatureContentsToken);
    if ($gapStart === false) {
        throw new RuntimeException('Unable to locate signature contents token in signed revision fixture.');
    }

    $gapEnd = $gapStart + strlen($signatureContentsToken);
    $signedRevisionBytes = strlen($signedRevision);
    $signedRevision = strtr($signedRevision, [
        'AAAAAAAAAA' => sprintf('%010d', $gapStart),
        'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
        'CCCCCCCCCC' => sprintf('%010d', $signedRevisionBytes - $gapEnd),
    ]);

    $dssRevision = "% markerPDF appended validation update\n"
        . "1 1 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R >>\nendobj\n"
        . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602195010Z) /TS 72 0 R >>\nendobj\n"
        . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
        . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
        . "72 0 obj\n<< /Length " . strlen($timestampPayload) . " /Subtype /application#2Ftst-info >>\nstream\n{$timestampPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 1 R /Prev 0 >>\n%%EOF";

    return [
        $signedRevision . $dssRevision,
        $signaturePayload,
        $vriKey,
        hash('sha256', $certPayload),
        hash('sha256', $ocspPayload),
        hash('sha256', $timestampPayload),
        $signedRevisionBytes,
        strlen($dssRevision),
        $certPayload,
        $ocspPayload,
        $timestampPayload,
    ];
};

return [
    'summarizes DSS VRI material appended after a signed ByteRange revision' => static function (TestRunner $t) use ($signedRevisionWithAppendedDssPdf): void {
        [
            $pdf,
            $signaturePayload,
            $vriKey,
            $certHash,
            $ocspHash,
            $timestampHash,
            $signedRevisionBytes,
            $dssRevisionBytes,
        ] = $signedRevisionWithAppendedDssPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $signature = $report['signatures'][0] ?? [];
        $byteRange = is_array($signature['byte_range'] ?? null) ? $signature['byte_range'] : [];
        $revisionReview = $report['signature_byte_range_revision_review'];
        $revisionRow = $revisionReview['rows'][0] ?? [];
        $dssReview = $report['document_security_store_signature_review'];
        $vriRow = $dssReview['vri_signature_rows'][0] ?? [];
        $vriCoverage = $vriRow['revision_coverage_reviews'][0] ?? [];

        $t->same('Revision DSS import', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('review_required_signature_boundary', $report['import_decision']);
        $t->same(1, $report['signature_byte_range_count']);
        $t->same(0, $report['valid_signature_byte_range_count']);
        $t->same(1, $report['signature_byte_range_revision_review_count']);
        $t->same(1, $report['signature_prior_revision_count']);
        $t->same(0, $report['signature_current_revision_count']);
        $t->same(0, $report['signature_invalid_revision_boundary_count']);
        $t->same(['covers_prior_revision_except_signature_contents'], $report['signature_byte_range_revision_statuses']);

        $t->same(false, $byteRange['valid']);
        $t->same('incomplete_file_coverage', $byteRange['status']);
        $t->same(true, $byteRange['signed_revision_valid']);
        $t->same('covers_prior_revision_except_signature_contents', $byteRange['revision_status']);
        $t->same($signedRevisionBytes, $byteRange['signed_revision_end']);
        $t->same($signedRevisionBytes, $byteRange['signed_revision_length']);
        $t->same(false, $byteRange['covers_current_revision']);
        $t->same($dssRevisionBytes, $byteRange['current_revision_tail_bytes']);
        $t->same(true, $byteRange['has_signature_contents_gap']);

        $t->same('signature_byte_range_revision_review', $revisionReview['source']);
        $t->same(true, $revisionReview['present']);
        $t->same(1, $revisionReview['byte_range_count']);
        $t->same(1, $revisionReview['prior_revision_signature_count']);
        $t->same(0, $revisionReview['invalid_revision_boundary_count']);
        $t->same('approval.revisionBoundary', $revisionRow['field_name'] ?? null);
        $t->same(30, $revisionRow['signature_object'] ?? null);
        $t->same($signedRevisionBytes, $revisionRow['signed_revision_end'] ?? null);
        $t->same($dssRevisionBytes, $revisionRow['current_revision_tail_bytes'] ?? null);
        $t->same(false, $revisionRow['revision_tail_imported_as_signed'] ?? null);
        $t->same(false, $revisionReview['executes_signature_validation']);

        $t->same('document_security_store_signature_review', $dssReview['source']);
        $t->same(1, $dssReview['matched_vri_count']);
        $t->same(1, $dssReview['signature_vri_match_count']);
        $t->same(1, $dssReview['vri_revision_review_count']);
        $t->same(1, $dssReview['vri_after_signed_revision_count']);
        $t->same(0, $dssReview['vri_in_signed_revision_count']);
        $t->same(['vri_after_signed_revision'], $dssReview['vri_revision_statuses']);
        $t->same($vriKey, $vriRow['key'] ?? null);
        $t->same('matched_signature_contents_sha1', $vriRow['match_status'] ?? null);
        $t->same('vri_after_signed_revision', $vriRow['vri_revision_status'] ?? null);
        $t->same(true, $vriRow['vri_after_signed_revision'] ?? null);
        $t->same(false, $vriRow['vri_in_signed_revision'] ?? null);
        $t->same(1, $vriRow['revision_coverage_review_count'] ?? null);
        $t->same($certHash, $vriRow['validation_hashes']['certificates'][0] ?? null);
        $t->same($ocspHash, $vriRow['validation_hashes']['ocsps'][0] ?? null);
        $t->same($timestampHash, $vriRow['validation_hashes']['timestamp_token'] ?? null);

        $t->same('dss_vri_signed_revision_coverage_review', $vriCoverage['source'] ?? null);
        $t->same('outside_signed_revision', $vriCoverage['coverage_status'] ?? null);
        $t->same(false, $vriCoverage['covered_by_signed_revision'] ?? null);
        $t->same(true, $vriCoverage['outside_signed_revision'] ?? null);
        $t->same($signedRevisionBytes, $vriCoverage['signed_revision_end'] ?? null);
        $t->true(($vriCoverage['vri_object_span']['offset'] ?? 0) >= $signedRevisionBytes);
        $t->same(false, $vriCoverage['executes_signature_validation'] ?? null);
        $t->same(false, $vriCoverage['executes_revocation_check'] ?? null);
        $t->same(false, $vriCoverage['executes_trust_chain_validation'] ?? null);

        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $signaturePayload)
            && !str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
            && !str_contains($encoded, 'REVISION_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'REVISION_DSS_OCSP_BYTES_SHOULD_NOT_LEAK')
            && !str_contains($encoded, 'REVISION_DSS_TIMESTAMP_BYTES_SHOULD_NOT_LEAK'));
    },
    'keeps appended DSS validation revision bytes out of visible WordPress text' => static function (TestRunner $t) use ($signedRevisionWithAppendedDssPdf): void {
        [
            $pdf,
            $signaturePayload,
            ,
            ,
            ,
            ,
            ,
            ,
            $certPayload,
            $ocspPayload,
            $timestampPayload,
        ] = $signedRevisionWithAppendedDssPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Revision DSS import', $plainText);
        $t->true(!str_contains($plainText, $signaturePayload));
        $t->true(!str_contains($plainText, $certPayload));
        $t->true(!str_contains($plainText, $ocspPayload));
        $t->true(!str_contains($plainText, $timestampPayload));
    },
];
