<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Launch URI certificate permission import) Tj ET';
$signaturePayload = 'LAUNCH_URI_CERT_PERMISSION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /OpenAction 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.certifier) /V 30 0 R /Kids [7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 280 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 300 718] /A 23 0 R /AA << /E 25 0 R >> >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://example.com/open-review) /Next [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /S /Launch /F (post-import-helper.exe) /Win << /F (post-import-helper.exe) /O (open) /P (/silent) >> /NewWindow true >>\nendobj\n"
    . "22 0 obj\n<< /S /URI /URI (javascript:alert\\(1\\)) >>\nendobj\n"
    . "23 0 obj\n<< /S /URI /URI (https://example.com/annotation-review) /Next 24 0 R >>\nendobj\n"
    . "24 0 obj\n<< /S /Launch /F (annotation-helper.exe) /Win << /F (annotation-helper.exe) /O (print) >> >>\nendobj\n"
    . "25 0 obj\n<< /S /URI /URI (file:///etc/passwd) >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Certifying Reviewer) /M (D:20260602175042Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 3 /V /1.2 >> >>] >>\nendobj\n"
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
$signature = $preflight['signatures'][0] ?? [];
$docMdp = is_array($signature['reference_transforms'][0] ?? null) ? $signature['reference_transforms'][0] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSignatureMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
    );

echo '<!-- markerpdf-security-launch-uri-cert-permission-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-launch-uri-cert-permission-currentbase',
    'native_boundary' => 'catalog and annotation URI/Launch actions are security review metadata alongside DocMDP certifying permissions, without executing PDF actions or validating signatures',
    'plain_text_imported' => $plainText === 'Launch URI certificate permission import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'action_count' => $actionReview['action_count'] ?? null,
    'launch_action_count' => $actionReview['launch_action_count'] ?? null,
    'unsafe_uri_action_count' => $actionReview['unsafe_uri_action_count'] ?? null,
    'certifying_permission_labels' => $actionReview['certifying_permission_labels'] ?? [],
    'doc_mdp_permission_label' => $docMdp['permission_label'] ?? null,
    'raw_signature_material_exposed' => $rawSignatureMaterialExposed,
    'executes_pdf_actions' => $preflight['executes_pdf_actions'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-action-review ' . htmlspecialchars(json_encode([
    'action_types' => $actionReview['action_types'] ?? [],
    'safety_labels' => $actionReview['safety_labels'] ?? [],
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'doc_mdp_permission_label' => $docMdp['permission_label'] ?? null,
    'review_only' => true,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
