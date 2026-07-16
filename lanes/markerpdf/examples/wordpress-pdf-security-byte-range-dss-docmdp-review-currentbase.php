<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Certified DSS review import) Tj ET';
$signaturePayload = 'DSS_DOCMDP_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$vriKey = strtoupper(hash('sha1', $signaturePayload));
$certPayload = 'DOCMDP_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'DOCMDP_OCSP_RESPONSE_SHOULD_NOT_LEAK';
$timestampPayload = 'DOCMDP_TIMESTAMP_TOKEN_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Certifying Reviewer) /M (D:20260602171754Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602171754Z) /TS 73 0 R >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
    . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
    . "73 0 obj\n<< /Length " . strlen($timestampPayload) . " /Subtype /application#2Ftst-info >>\nstream\n{$timestampPayload}\nendstream\nendobj\n"
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

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$signature = $preflight['signatures'][0] ?? [];
$review = is_array($signature['signature_security_review'] ?? null) ? $signature['signature_security_review'] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawReviewMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $certPayload)
        || str_contains($encoded, $ocspPayload)
        || str_contains($encoded, $timestampPayload)
    );

echo '<!-- markerpdf-security-byte-range-dss-docmdp-review-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-byte-range-dss-docmdp-review-currentbase',
    'native_boundary' => 'signature ByteRange, DSS VRI, and DocMDP certification are correlated for WordPress security review without validating signatures or revocation',
    'plain_text_imported' => $plainText === 'Certified DSS review import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'byte_range_status' => $review['byte_range_status'] ?? null,
    'dss_vri_match_status' => $review['dss_vri_match_status'] ?? null,
    'dss_vri_key' => $review['dss_vri_key'] ?? null,
    'doc_mdp_permission_label' => $review['doc_mdp_permission_label'] ?? null,
    'dss_vri_validation_stream_count' => $review['dss_vri_validation_stream_count'] ?? null,
    'raw_review_material_exposed' => $rawReviewMaterialExposed,
    'executes_signature_validation' => $review['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $review['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $review['executes_trust_chain_validation'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-byte-range-dss-docmdp-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'signature_review' => [
        'byte_range_status' => $review['byte_range_status'] ?? null,
        'dss_vri_match_status' => $review['dss_vri_match_status'] ?? null,
        'doc_mdp_permission_label' => $review['doc_mdp_permission_label'] ?? null,
        'validation_stream_count' => $review['dss_vri_validation_stream_count'] ?? null,
    ],
    'raw_review_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
