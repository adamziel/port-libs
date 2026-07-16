<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (AcroForm permission action import) Tj ET';
$signaturePayload = 'ACROFORM_PERMISSION_ACTION_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$scriptPayload = "app.alert('locked field validation action should not execute');";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Lock 31 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Final locked title) /Kids [10 0 R] /AA << /V 40 0 R /K 41 0 R >> >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 580 320 606] /P 3 0 R /F 4 /A 45 0 R /AA << /Fo 46 0 R >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Permission Reviewer) /M (D:20260602182900Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [9 0 R] /P 2 >>\nendobj\n"
    . "40 0 obj\n<< /S /SubmitForm /F (https://example.test/signed-submit) /Fields [9 0 R] /Flags 6 /Next [42 0 R 43 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /S /JavaScript /JS ({$scriptPayload}) >>\nendobj\n"
    . "42 0 obj\n<< /S /ImportData /F (file://local-review.fdf) >>\nendobj\n"
    . "43 0 obj\n<< /S /Hide /T [10 0 R] /H false >>\nendobj\n"
    . "45 0 obj\n<< /S /URI /URI (javascript:signatureImport\\(\\)) >>\nendobj\n"
    . "46 0 obj\n<< /S /ResetForm /Fields [(article.title)] /Next 47 0 R >>\nendobj\n"
    . "47 0 obj\n<< /S /Launch /F (acroform-helper.exe) /NewWindow true >>\nendobj\n"
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
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawReviewMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $scriptPayload)
    );

echo '<!-- markerpdf-security-acroform-permission-action-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-acroform-permission-action-currentbase',
    'native_boundary' => 'DocMDP locked AcroForm field and widget actions are security-review metadata only',
    'plain_text_imported' => $plainText === 'AcroForm permission action import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'action_count' => $actionReview['action_count'] ?? null,
    'acroform_action_count' => $actionReview['acroform_action_count'] ?? null,
    'signed_locked_field_action_count' => $actionReview['signed_locked_field_action_count'] ?? null,
    'form_submit_action_count' => $actionReview['form_submit_action_count'] ?? null,
    'unsafe_uri_action_count' => $actionReview['unsafe_uri_action_count'] ?? null,
    'raw_review_material_exposed' => $rawReviewMaterialExposed,
    'executes_actions_on_import' => $actionReview['executes_actions_on_import'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-acroform-permission-action-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'action_types' => $actionReview['action_types'] ?? [],
    'signed_locked_field_permission_labels' => $actionReview['signed_locked_field_permission_labels'] ?? [],
    'raw_review_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
