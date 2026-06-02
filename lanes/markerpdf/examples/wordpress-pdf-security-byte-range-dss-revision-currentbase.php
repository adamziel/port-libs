<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$pdf = $signedRevision . $dssRevision;
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$signature = $preflight['signatures'][0] ?? [];
$byteRange = is_array($signature['byte_range'] ?? null) ? $signature['byte_range'] : [];
$revisionReview = is_array($preflight['signature_byte_range_revision_review'] ?? null)
    ? $preflight['signature_byte_range_revision_review']
    : [];
$dssReview = is_array($preflight['document_security_store_signature_review'] ?? null)
    ? $preflight['document_security_store_signature_review']
    : [];
$vriRow = is_array($dssReview['vri_signature_rows'][0] ?? null) ? $dssReview['vri_signature_rows'][0] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawReviewMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $certPayload)
        || str_contains($encoded, $ocspPayload)
        || str_contains($encoded, $timestampPayload)
    );

echo '<!-- markerpdf-security-byte-range-dss-revision-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-byte-range-dss-revision-currentbase',
    'native_boundary' => 'signature ByteRange signed-revision extents and appended DSS VRI material are review metadata for WordPress imports',
    'plain_text_imported' => $plainText === 'Revision DSS import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'byte_range_status' => $byteRange['status'] ?? null,
    'byte_range_revision_status' => $byteRange['revision_status'] ?? null,
    'signed_revision_end' => $byteRange['signed_revision_end'] ?? null,
    'current_revision_tail_bytes' => $byteRange['current_revision_tail_bytes'] ?? null,
    'prior_revision_signature_count' => $revisionReview['prior_revision_signature_count'] ?? null,
    'dss_vri_revision_status' => $vriRow['vri_revision_status'] ?? null,
    'dss_vri_after_signed_revision_count' => $dssReview['vri_after_signed_revision_count'] ?? null,
    'raw_review_material_exposed' => $rawReviewMaterialExposed,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $preflight['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $preflight['executes_trust_chain_validation'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-byte-range-dss-revision-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'byte_range_revision_status' => $byteRange['revision_status'] ?? null,
    'dss_vri_revision_statuses' => $dssReview['vri_revision_statuses'] ?? [],
    'raw_review_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
