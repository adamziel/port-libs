<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Certificate permission OpenAction import) Tj ET';
$signaturePayload = 'WORDPRESS_CERT_PERMISSION_OPENACTION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$vriKey = strtoupper(hash('sha1', $signaturePayload));
$certificatePayload = 'WORDPRESS_CERT_PERMISSION_OPENACTION_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R /OpenAction 40 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.openaction) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Certified title) /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (OpenAction Permission Reviewer) /M (D:20260602200039Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 1 /V /1.2 >> >> 31 0 R << /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 33 0 R >>] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /TransformParams 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /All >>\nendobj\n"
    . "33 0 obj\n<< /Type /TransformParams /V /2.2 /Document [/FullSave] /Form [/FillIn /Export] /Msg (Usage rights review only) >>\nendobj\n"
    . "40 0 obj\n<< /S /URI /URI (https://example.com/certified-open-action) /Next [41 0 R 42 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /S /JavaScript /JS (app.alert\\('certified open action review only'\\)) >>\nendobj\n"
    . "42 0 obj\n<< /S /Launch /F (open-action-helper.exe) /Win << /F (open-action-helper.exe) /O (open) >> >>\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /TU (D:20260602200039Z) >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($certificatePayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certificatePayload}\nendstream\nendobj\n"
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
$actionReview = is_array($preflight['document_action_security_review'] ?? null)
    ? $preflight['document_action_security_review']
    : [];
$openActionReview = is_array($actionReview['cert_permission_open_action_review'] ?? null)
    ? $actionReview['cert_permission_open_action_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSecurityMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $certificatePayload)
    );

echo '<!-- markerpdf-security-cert-permission-openaction-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-cert-permission-openaction-currentbase',
    'native_boundary' => 'certifying signature permission transforms and DSS certificate hashes classify catalog OpenAction rows as review-only metadata without granting action execution or rights enforcement',
    'plain_text_imported' => $plainText === 'Certificate permission OpenAction import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'open_action_count' => $openActionReview['open_action_count'] ?? null,
    'unsafe_open_action_count' => $openActionReview['unsafe_open_action_count'] ?? null,
    'open_action_permission_statuses' => $openActionReview['open_action_permission_statuses'] ?? [],
    'doc_mdp_permission_labels' => $openActionReview['doc_mdp_permission_labels'] ?? [],
    'signature_permission_transform_methods' => $openActionReview['signature_permission_transform_methods'] ?? [],
    'cert_permissions_grant_open_action_execution' => $openActionReview['cert_permissions_grant_open_action_execution'] ?? null,
    'raw_security_material_exposed' => $rawSecurityMaterialExposed,
    'executes_pdf_actions' => $preflight['executes_pdf_actions'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_trust_chain_validation' => $preflight['executes_trust_chain_validation'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-cert-permission-openaction-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'open_action_permission_statuses' => $openActionReview['open_action_permission_statuses'] ?? [],
    'doc_mdp_permission_labels' => $openActionReview['doc_mdp_permission_labels'] ?? [],
    'review_only' => true,
    'executes_pdf_actions' => false,
    'executes_signature_validation' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
