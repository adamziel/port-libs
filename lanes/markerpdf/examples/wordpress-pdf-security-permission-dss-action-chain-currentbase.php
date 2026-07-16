<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$actionReview = is_array($preflight['document_action_security_review'] ?? null)
    ? $preflight['document_action_security_review']
    : [];
$chainReview = is_array($actionReview['permission_dss_action_chain_review'] ?? null)
    ? $actionReview['permission_dss_action_chain_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawReviewMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $certPayload)
        || str_contains($encoded, $ocspPayload)
        || str_contains($encoded, 'DEADBEEF')
    );

if ($plainText !== 'Permission DSS action chain import') {
    throw new RuntimeException('Expected only page text in permission DSS action-chain smoke.');
}
if (($chainReview['post_signature_action_count'] ?? null) !== 3 || ($chainReview['post_signature_actions_granted_by_permissions'] ?? true) !== false) {
    throw new RuntimeException('Expected post-signature action chain to stay review-only despite DSS permission context.');
}
if ($rawReviewMaterialExposed) {
    throw new RuntimeException('Expected signature, DSS, and digest payload bytes to stay out of review output.');
}

echo '<!-- markerpdf-security-permission-dss-action-chain-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-permission-dss-action-chain-currentbase',
    'native_boundary' => 'DSS validation material and FieldMDP/DocMDP/UR3 permission transforms are correlated with post-signature OpenAction chains without executing actions or enforcing rights',
    'plain_text_imported' => true,
    'import_decision' => $preflight['import_decision'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'post_signature_action_count' => $chainReview['post_signature_action_count'] ?? null,
    'post_signature_action_objects' => $chainReview['post_signature_action_objects'] ?? [],
    'signature_permission_transform_methods' => $chainReview['signature_permission_transform_methods'] ?? [],
    'dss_certificate_count' => $chainReview['dss_certificate_count'] ?? null,
    'dss_vri_signature_match_count' => $chainReview['dss_vri_signature_match_count'] ?? null,
    'action_byte_range_statuses' => $chainReview['action_byte_range_statuses'] ?? [],
    'post_signature_actions_granted_by_permissions' => $chainReview['post_signature_actions_granted_by_permissions'] ?? null,
    'dss_validation_grants_action_execution' => $chainReview['dss_validation_grants_action_execution'] ?? null,
    'raw_review_material_exposed' => false,
    'executes_pdf_actions' => $preflight['executes_pdf_actions'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $preflight['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $preflight['executes_trust_chain_validation'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-permission-dss-action-chain-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'post_signature_action_count' => $chainReview['post_signature_action_count'] ?? null,
    'permission_methods' => $chainReview['signature_permission_transform_methods'] ?? [],
    'review_only' => $chainReview['review_only'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
