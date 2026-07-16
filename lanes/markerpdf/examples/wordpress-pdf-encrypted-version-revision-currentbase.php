<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress version revision mismatch encrypted text leak) Tj ET';
$ownerValidation = str_repeat('O', 48);
$userValidation = str_repeat('U', 48);
$ownerEncryptionKey = str_repeat('E', 32);
$userEncryptionKey = str_repeat('K', 32);
$permissionDigest = str_repeat('P', 16);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 6 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /OE " . $hex($ownerEncryptionKey)
    . " /UE " . $hex($userEncryptionKey)
    . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
    . " >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$handler = is_array($report['permission_handler_review'] ?? null) ? $report['permission_handler_review'] : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted version/revision mismatch fixture text to stay blocked.');
}
if (($permission['standard_security_handler_version_revision_compatible'] ?? null) !== false) {
    throw new RuntimeException('Expected Standard /V and /R mismatch to be visible in permission preflight.');
}
if (($permission['policy'] ?? null) !== 'permissions_malformed_blocked_without_decryption') {
    throw new RuntimeException('Expected mismatched Standard handler parameters to fail closed.');
}
$copyAllowed = array_key_exists('copy_or_extract_allowed', $permission)
    ? $permission['copy_or_extract_allowed']
    : '__missing__';
if ($copyAllowed !== null || ($permission['permission_bits_reliable'] ?? true) !== false) {
    throw new RuntimeException('Expected mismatched Standard handler parameters to suppress bit-derived copy/extract grants.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, $ownerEncryptionKey)
    || str_contains($encoded, $userEncryptionKey)
    || str_contains($encoded, $permissionDigest)
    || str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
) {
    throw new RuntimeException('Expected encrypted content and authentication material to remain redacted.');
}

echo '<!-- markerpdf-encrypted-version-revision-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-version-revision-currentbase',
    'native_boundary' => 'Standard security-handler /V and /R compatibility is reviewed before trusting permission bits',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'version_supported' => $permission['standard_security_handler_version_supported'] ?? null,
    'revision_supported' => $permission['standard_security_handler_revision_supported'] ?? null,
    'version_revision_compatible' => $permission['standard_security_handler_version_revision_compatible'] ?? null,
    'parameter_violations' => $permission['standard_security_handler_parameter_violations'] ?? [],
    'handler_status' => $handler['status'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'raw_key_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Version Revision Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The import preflight reports incompatible Standard security-handler version and revision values before relying on permission bits.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-version-revision-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'version_revision_compatible' => $permission['standard_security_handler_version_revision_compatible'] ?? null,
        'parameter_violations' => $permission['standard_security_handler_parameter_violations'] ?? [],
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
