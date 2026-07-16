<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress legacy Perms encrypted text should not import) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);
$permissionDigest = str_repeat('P', 16);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /Perms " . $hex($permissionDigest)
    . " /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = $preflight['permission_preflight'] ?? [];
$auth = is_array($permission['standard_authentication_review'] ?? null)
    ? $permission['standard_authentication_review']
    : [];
$digest = is_array($auth['permission_digest'] ?? null) ? $auth['permission_digest'] : [];
$material = is_array($permission['standard_authentication_material_review'] ?? null)
    ? $permission['standard_authentication_material_review']
    : [];
$trust = is_array($permission['permission_authentication_trust_review'] ?? null)
    ? $permission['permission_authentication_trust_review']
    : [];
$reviewReasons = array_values(array_filter(
    $preflight['review_reasons'] ?? [],
    static fn (mixed $reason): bool => is_string($reason)
));
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);
$rawSecurityMaterialExposed = !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, $permissionDigest)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
    || str_contains($encoded, strtoupper(bin2hex($permissionDigest)));

if ($plainText !== '') {
    throw new RuntimeException('Encrypted legacy-Perms text was imported.');
}
if (($preflight['import_decision'] ?? null) !== 'block_encrypted_content_review_security_metadata') {
    throw new RuntimeException('Encrypted legacy-Perms import decision did not block content.');
}
if (!in_array('legacy_permission_digest_ignored', $reviewReasons, true)) {
    throw new RuntimeException('Legacy Perms ignore review reason missing.');
}
if (($permission['policy'] ?? null) !== 'copy_extract_allowed_after_decryption') {
    throw new RuntimeException('Legacy Perms changed Standard permission policy.');
}
if (($digest['status'] ?? null) !== 'unexpected_permission_digest_for_legacy_revision_review') {
    throw new RuntimeException('Legacy Perms digest status missing.');
}
if (($digest['applicable_for_revision'] ?? null) !== false || ($digest['unexpected_for_revision'] ?? null) !== true) {
    throw new RuntimeException('Legacy Perms digest revision boundary missing.');
}
if (($material['permission_digest_ignored_for_permission_authentication'] ?? null) !== true) {
    throw new RuntimeException('Legacy Perms digest was not marked ignored for authentication.');
}
if (($trust['status'] ?? null) !== 'permission_bits_decoded_but_password_not_validated') {
    throw new RuntimeException('Legacy Perms changed authentication trust status.');
}
if ($rawSecurityMaterialExposed) {
    throw new RuntimeException('Raw encrypted legacy-Perms material leaked into review output.');
}

echo '<!-- markerpdf-encrypted-legacy-perms-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-legacy-perms-boundary-currentbase',
    'native_boundary' => 'legacy Standard revision 4 /Perms is review-only metadata and ignored for permission authentication',
    'text_blocked' => $plainText === '',
    'import_decision' => $preflight['import_decision'] ?? null,
    'review_reasons' => $reviewReasons,
    'permission_policy' => $permission['policy'] ?? null,
    'content_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_digest_present' => $digest['present'] ?? null,
    'permission_digest_status' => $digest['status'] ?? null,
    'permission_digest_unexpected_for_revision' => $digest['unexpected_for_revision'] ?? null,
    'permission_digest_ignored_for_permission_authentication' => $digest['ignored_for_permission_authentication'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'raw_security_material_exposed' => false,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $preflight['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Legacy Permission Digest Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'WordPress import blocks encrypted text while preserving a review-only note that a legacy Standard security handler carried an unexpected /Perms digest. The digest is not used to authenticate permissions for revision 4 documents.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-legacy-perms-review ' . htmlspecialchars(json_encode([
    'permission_policy' => $permission['policy'] ?? null,
    'content_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_digest' => [
        'present' => $digest['present'] ?? null,
        'status' => $digest['status'] ?? null,
        'unexpected_for_revision' => $digest['unexpected_for_revision'] ?? null,
        'ignored_for_permission_authentication' => $digest['ignored_for_permission_authentication'] ?? null,
    ],
    'raw_security_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
