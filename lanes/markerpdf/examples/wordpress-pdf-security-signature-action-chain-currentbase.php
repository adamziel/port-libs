<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Signed inline action chain import) Tj ET';
$signaturePayload = 'INLINE_ACTION_CHAIN_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

$signedPrefix = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [90 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.inlineAction) /V 30 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Inline Action Reviewer) /M (D:20260602204117Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
$postSignatureAnnotation = "90 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 360 718] /A << /S /URI /URI (https://example.test/signed-inline-review) /Next [<< /S /Launch /F (inline-helper.exe) /Win << /F (inline-helper.exe) /O (open) >> >> << /S /URI /URI (javascript:inlineSignature\\(\\)) >>] >> >>\nendobj\n"
    . "%%EOF";
$pdf = $signedPrefix . $postSignatureAnnotation;

$gapStart = strpos($pdf, $signatureContentsToken);
$postSignatureOffset = strpos($pdf, "90 0 obj\n");
if ($gapStart === false || $postSignatureOffset === false) {
    throw new RuntimeException('Unable to locate signature or appended inline action fixture boundary.');
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
$actions = array_values(array_filter(
    $actionReview['actions'] ?? [],
    static fn (mixed $action): bool => is_array($action)
));
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$payloadLeaked = str_contains($plainText, 'signed-inline-review')
    || str_contains($plainText, 'inline-helper.exe')
    || str_contains($plainText, 'inlineSignature')
    || (is_string($encoded) && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
    ));

if ($plainText !== 'Signed inline action chain import') {
    throw new RuntimeException('Unexpected visible text import for inline action chain fixture.');
}
if (($actionReview['post_signature_action_count'] ?? null) !== 3 || ($actionReview['post_signature_action_objects'] ?? []) !== [90]) {
    throw new RuntimeException('Expected inline action chain to be reviewed against appended annotation container object.');
}
if (($actions[0]['action_byte_range_review_source'] ?? null) !== 'action_container_object' || $payloadLeaked) {
    throw new RuntimeException('Inline action chain byte-range review boundary failed.');
}

echo '<!-- markerpdf-security-signature-action-chain-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-signature-action-chain-currentbase',
    'native_boundary' => 'inline annotation /A /Next action chains use the annotation container byte span for signature ByteRange review without executing actions or signature validation',
    'plain_text_imported' => true,
    'import_decision' => $preflight['import_decision'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'action_types' => $actionReview['action_types'] ?? [],
    'safety_labels' => $actionReview['safety_labels'] ?? [],
    'post_signature_action_count' => $actionReview['post_signature_action_count'] ?? null,
    'post_signature_action_objects' => $actionReview['post_signature_action_objects'] ?? [],
    'byte_range_review_sources' => array_column($actions, 'action_byte_range_review_source'),
    'action_container_objects' => array_column($actions, 'action_container_object'),
    'action_byte_range_statuses' => $actionReview['action_byte_range_statuses'] ?? [],
    'action_payloads_excluded_from_visible_text' => true,
    'raw_signature_bytes_exposed' => false,
    'executes_pdf_actions' => $preflight['executes_pdf_actions'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-signature-action-chain ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'post_signature_inline_action_count' => $actionReview['post_signature_action_count'] ?? null,
    'inline_action_review_source' => 'action_container_object',
    'all_actions_review_only' => $actionReview['all_actions_review_only'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
