<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (FieldMDP byte range import) Tj ET';
$signaturePayload = 'WORDPRESS_FIELDMDP_BYTE_RANGE_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$unsignedFieldValue = 'WORDPRESS_FIELDMDP_POST_SIGNATURE_FIELD_VALUE_SHOULD_NOT_LEAK';

$signedPrefix = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 10 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.fieldPermissions) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Signed title) /Kids [11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Field Permission Reviewer) /M (D:20260602203531Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /DigestMethod /SHA256 /DigestValue <DEADC0DE> /TransformParams 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [(article.title) (post.signature.notes)] >>\nendobj\n";
$postSignatureObjects = "10 0 obj\n<< /FT /Tx /T (post.signature.notes) /V ({$unsignedFieldValue}) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";
$pdf = $signedPrefix . $postSignatureObjects;

$gapStart = strpos($pdf, $signatureContentsToken);
$postSignatureFieldOffset = strpos($pdf, "10 0 obj\n");
if ($gapStart === false || $postSignatureFieldOffset === false) {
    throw new RuntimeException('Unable to locate signature or post-signature field fixture boundary.');
}

$gapEnd = $gapStart + strlen($signatureContentsToken);
$pdf = strtr($pdf, [
    'AAAAAAAAAA' => sprintf('%010d', $gapStart),
    'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
    'CCCCCCCCCC' => sprintf('%010d', $postSignatureFieldOffset - $gapEnd),
]);

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldReview = is_array($preflight['field_mdp_byte_range_review'] ?? null)
    ? $preflight['field_mdp_byte_range_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSecurityMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $signaturePayload)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $unsignedFieldValue)
        || str_contains($encoded, 'DEADC0DE')
    );

echo '<!-- markerpdf-security-permission-byte-range-fieldmdp-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-permission-byte-range-fieldmdp-currentbase',
    'native_boundary' => 'FieldMDP target field permissions are correlated with signature ByteRange coverage as review metadata only',
    'plain_text_imported' => $plainText === 'FieldMDP byte range import',
    'import_decision' => $preflight['import_decision'] ?? null,
    'field_mdp_target_field_count' => $fieldReview['target_field_count'] ?? null,
    'field_mdp_target_not_covered_count' => $fieldReview['target_not_covered_count'] ?? null,
    'field_mdp_target_statuses' => $fieldReview['target_statuses'] ?? [],
    'field_mdp_target_field_names' => $fieldReview['target_field_names'] ?? [],
    'raw_security_material_exposed' => $rawSecurityMaterialExposed,
    'field_permissions_enforced' => $fieldReview['field_permissions_enforced'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_signing' => $preflight['executes_signing'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-permission-byte-range-fieldmdp-review ' . htmlspecialchars(json_encode([
    'decision' => $preflight['import_decision'] ?? null,
    'blocked_operations' => $preflight['blocked_operations'] ?? [],
    'field_mdp_target_statuses' => $fieldReview['target_statuses'] ?? [],
    'raw_security_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
