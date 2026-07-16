<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (DSS signature reference transform import) Tj ET';
$signaturePayload = 'WORDPRESS_DSS_REFERENCE_TRANSFORM_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$vriKey = strtoupper(hash('sha1', $signaturePayload));
$orphanVriKey = '0123456789ABCDEF';
$certPayload = 'WORDPRESS_DSS_REFERENCE_TRANSFORM_CERT_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'WORDPRESS_DSS_REFERENCE_TRANSFORM_OCSP_BYTES_SHOULD_NOT_LEAK';
$orphanCrlPayload = 'WORDPRESS_DSS_REFERENCE_TRANSFORM_ORPHAN_CRL_BYTES_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.certification) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed title) /Kids [11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (internal.notes) /V (Internal review note) >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (DSS Reference Reviewer) /M (D:20260602205800Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R 32 0 R << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 35 0 R >>] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams 33 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 34 0 R >>\nendobj\n"
    . "33 0 obj\n<< /Type /TransformParams /V /1.2 /P 2 >>\nendobj\n"
    . "34 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [9 0 R (internal.notes)] >>\nendobj\n"
    . "35 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn /Export] /Signature [/Modify] /Annots [/Create] /EF [/Create] /Msg (Usage rights review only) >>\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R /{$orphanVriKey} 62 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602205830Z) >>\nendobj\n"
    . "62 0 obj\n<< /Type /VRI /CRL [72 0 R] /TU (D:20260602205840Z) >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
    . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
    . "72 0 obj\n<< /Length " . strlen($orphanCrlPayload) . " /Subtype /application#2Fpkix-crl >>\nstream\n{$orphanCrlPayload}\nendstream\nendobj\n"
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
$transformReview = is_array($preflight['document_security_store_signature_reference_transform_review'] ?? null)
    ? $preflight['document_security_store_signature_reference_transform_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSecurityMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $certPayload)
        || str_contains($encoded, $ocspPayload)
        || str_contains($encoded, $orphanCrlPayload)
        || str_contains($encoded, 'DEADC0DE')
    );

echo '<!-- markerpdf-security-dss-signature-reference-transform-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-dss-signature-reference-transform-currentbase',
    'native_boundary' => 'matched DSS VRI rows carry signature Reference transform summaries for review without validation, rights enforcement, signing, or action execution',
    'plain_text_imported' => $plainText === 'DSS signature reference transform import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'dss_reference_transform_count' => $preflight['document_security_store_signature_reference_transform_count'] ?? null,
    'dss_reference_transform_methods' => $preflight['document_security_store_signature_reference_transform_methods'] ?? [],
    'vri_with_reference_transform_count' => $transformReview['vri_with_reference_transform_count'] ?? null,
    'field_mdp_reference_transform_count' => $transformReview['field_mdp_reference_transform_count'] ?? null,
    'usage_rights_reference_transform_count' => $transformReview['usage_rights_reference_transform_count'] ?? null,
    'raw_security_material_exposed' => $rawSecurityMaterialExposed,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $preflight['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $preflight['executes_trust_chain_validation'] ?? null,
    'executes_signing' => $preflight['executes_signing'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-dss-signature-reference-transform-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'dss_reference_transform_methods' => $preflight['document_security_store_signature_reference_transform_methods'] ?? [],
    'vri_keys' => $transformReview['vri_keys'] ?? [],
    'raw_security_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
