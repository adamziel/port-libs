<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$dssReview = is_array($preflight['document_security_store_signature_review'] ?? null)
    ? $preflight['document_security_store_signature_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawReviewMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $approvalPayload)
        || str_contains($encoded, $timestampPayload)
        || str_contains($encoded, strtoupper(bin2hex($approvalPayload)))
        || str_contains($encoded, strtoupper(bin2hex($timestampPayload)))
        || str_contains($encoded, $approvalCertPayload)
        || str_contains($encoded, $timestampTokenPayload)
        || str_contains($encoded, $orphanCrlPayload)
    );

echo '<!-- markerpdf-security-dss-signature-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-dss-signature-currentbase',
    'native_boundary' => 'catalog DSS VRI keys are correlated with signature contents digests for review without validating signatures, revocation, trust chains, or timestamps',
    'plain_text_imported' => $plainText === 'DSS multi signature import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'matched_vri_count' => $dssReview['matched_vri_count'] ?? null,
    'unmatched_vri_count' => $dssReview['unmatched_vri_count'] ?? null,
    'matched_signature_objects' => $dssReview['matched_signature_objects'] ?? [],
    'vri_match_statuses' => $dssReview['vri_match_statuses'] ?? [],
    'raw_review_material_exposed' => $rawReviewMaterialExposed,
    'executes_signature_validation' => $dssReview['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $dssReview['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $dssReview['executes_trust_chain_validation'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-dss-signature-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'matched_vri_count' => $dssReview['matched_vri_count'] ?? null,
    'unmatched_vri_keys' => $dssReview['unmatched_vri_keys'] ?? [],
    'raw_review_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
