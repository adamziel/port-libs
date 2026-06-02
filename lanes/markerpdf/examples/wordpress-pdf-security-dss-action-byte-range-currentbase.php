<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$actionReview = $preflight['document_action_security_review'] ?? [];
$firstAction = is_array($actionReview['actions'][0] ?? null) ? $actionReview['actions'][0] : [];
$firstCoverage = is_array($firstAction['signature_byte_range_reviews'][0] ?? null)
    ? $firstAction['signature_byte_range_reviews'][0]
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawReviewMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $certPayload)
        || str_contains($encoded, $ocspPayload)
    );

echo '<!-- markerpdf-security-dss-action-byte-range-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-dss-action-byte-range-currentbase',
    'native_boundary' => 'DSS-backed certified signature metadata and PDF OpenAction rows are reviewed against signed byte ranges without validating signatures, revocation, or executing actions',
    'plain_text_imported' => $plainText === 'Signed DSS action byte range import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'post_signature_action_count' => $actionReview['post_signature_action_count'] ?? null,
    'unsigned_action_byte_range_count' => $actionReview['unsigned_action_byte_range_count'] ?? null,
    'post_signature_action_objects' => $actionReview['post_signature_action_objects'] ?? [],
    'first_action_coverage_status' => $firstAction['signature_byte_range_coverage_status'] ?? null,
    'first_action_signature_coverage_status' => $firstCoverage['coverage_status'] ?? null,
    'raw_review_material_exposed' => $rawReviewMaterialExposed,
    'executes_pdf_actions' => $preflight['executes_pdf_actions'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $preflight['executes_revocation_check'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-dss-action-byte-range-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'post_signature_action_count' => $actionReview['post_signature_action_count'] ?? null,
    'action_byte_range_statuses' => $actionReview['action_byte_range_statuses'] ?? [],
    'raw_review_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
