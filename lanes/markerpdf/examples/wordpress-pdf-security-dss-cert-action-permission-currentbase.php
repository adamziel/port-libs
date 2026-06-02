<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (DSS certificate action permission import) Tj ET';
$signaturePayload = 'WORDPRESS_DSS_CERT_ACTION_PERMISSION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$vriKey = strtoupper(hash('sha1', $signaturePayload));
$globalCertPayload = 'WORDPRESS_DSS_CERT_ACTION_GLOBAL_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
$vriCertPayload = 'WORDPRESS_DSS_CERT_ACTION_VRI_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'WORDPRESS_DSS_CERT_ACTION_OCSP_BYTES_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /DSS 60 0 R /OpenAction 80 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.permission) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed title) /Kids [11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (internal.notes) /V (Permission review notes) >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 300 718] /A 82 0 R /AA << /E 83 0 R >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (DSS Permission Reviewer) /M (D:20260602192222Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 33 0 R >>] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [9 0 R (internal.notes)] >>\nendobj\n"
    . "33 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn /Export] /Signature [/Modify] /Annots [/Create /Modify] /EF [/Create] /Msg (Reader rights review only) >>\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [72 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [71 0 R] /OCSP [72 0 R] /TU (D:20260602192222Z) >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($globalCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$globalCertPayload}\nendstream\nendobj\n"
    . "71 0 obj\n<< /Length " . strlen($vriCertPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$vriCertPayload}\nendstream\nendobj\n"
    . "72 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
    . "80 0 obj\n<< /S /URI /URI (https://example.com/signed-open-action) /Next 81 0 R >>\nendobj\n"
    . "81 0 obj\n<< /S /Launch /F (permission-helper.exe) /Win << /F (permission-helper.exe) /O (open) >> >>\nendobj\n"
    . "82 0 obj\n<< /S /URI /URI (javascript:permissionReview\\(\\)) >>\nendobj\n"
    . "83 0 obj\n<< /S /SubmitForm /F (https://example.test/export) /Fields [9 0 R] /Flags 4 >>\nendobj\n"
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
$actionReview = $preflight['document_action_security_review'] ?? [];
$context = is_array($actionReview['dss_certificate_action_permission_review'] ?? null)
    ? $actionReview['dss_certificate_action_permission_review']
    : [];
$permissionReview = is_array($actionReview['signature_permission_transform_review'] ?? null)
    ? $actionReview['signature_permission_transform_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSecurityMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $globalCertPayload)
        || str_contains($encoded, $vriCertPayload)
        || str_contains($encoded, $ocspPayload)
        || str_contains($encoded, 'DEADC0DE')
    );

echo '<!-- markerpdf-security-dss-cert-action-permission-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-dss-cert-action-permission-currentbase',
    'native_boundary' => 'DSS certificate hashes and FieldMDP/UR3 permission transforms are action-review metadata without signature validation, trust-chain validation, rights enforcement, or PDF action execution',
    'plain_text_imported' => $plainText === 'DSS certificate action permission import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'action_count' => $context['action_count'] ?? null,
    'unsafe_action_count' => $context['unsafe_action_count'] ?? null,
    'dss_certificate_count' => $context['dss_certificate_count'] ?? null,
    'dss_vri_signature_match_count' => $context['dss_vri_signature_match_count'] ?? null,
    'signature_permission_transform_methods' => $context['signature_permission_transform_methods'] ?? [],
    'field_mdp_field_names' => $permissionReview['field_mdp_field_names'] ?? [],
    'usage_right_categories' => $permissionReview['usage_right_categories'] ?? [],
    'raw_security_material_exposed' => $rawSecurityMaterialExposed,
    'executes_pdf_actions' => $preflight['executes_pdf_actions'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_trust_chain_validation' => $preflight['executes_trust_chain_validation'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-dss-cert-action-permission-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'dss_certificate_count' => $context['dss_certificate_count'] ?? null,
    'signature_permission_transform_methods' => $context['signature_permission_transform_methods'] ?? [],
    'raw_security_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
