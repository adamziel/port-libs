<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress auth material policy encrypted text leak) Tj ET';
$ownerValidation = str_repeat('O', 47);
$userValidation = str_repeat('U', 48);
$ownerEncryptionKey = str_repeat('E', 32);
$userEncryptionKey = str_repeat('K', 31);
$permissionDigest = str_repeat('P', 16);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /OE " . $hex($ownerEncryptionKey)
    . " /UE " . $hex($userEncryptionKey)
    . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
    . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
    . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$material = is_array($permission['standard_authentication_material_review'] ?? null)
    ? $permission['standard_authentication_material_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, $ownerEncryptionKey)
        || str_contains($encoded, $userEncryptionKey)
        || str_contains($encoded, $permissionDigest)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
        || str_contains($encoded, strtoupper(bin2hex($ownerEncryptionKey)))
        || str_contains($encoded, strtoupper(bin2hex($userEncryptionKey)))
        || str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted authentication-material policy fixture text to stay blocked.');
}
if (($permission['standard_authentication_material_policy'] ?? null) !== 'standard_authentication_material_length_mismatch') {
    throw new RuntimeException('Expected Standard authentication material length mismatch policy.');
}
if (($material['ready_for_password_attempt'] ?? null) !== false || $rawMaterialExposed) {
    throw new RuntimeException('Expected malformed authentication material to block password readiness without raw-byte exposure.');
}

echo '<!-- markerpdf-encrypted-auth-material-policy-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-auth-material-policy-currentbase',
    'native_boundary' => 'revision six Standard authentication material summary policy before permission trust',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'standard_authentication_material_policy' => $permission['standard_authentication_material_policy'] ?? null,
    'standard_authentication_length_mismatch_required_entries' => $permission['standard_authentication_length_mismatch_required_entries'] ?? null,
    'standard_authentication_permission_digest_status' => $permission['standard_authentication_permission_digest_status'] ?? null,
    'standard_authentication_ready_for_password_attempt' => $permission['standard_authentication_ready_for_password_attempt'] ?? null,
    'permission_authentication_status' => $permission['permission_authentication_status'] ?? null,
    'permission_bits_authenticated' => $permission['permission_bits_authenticated'] ?? null,
    'authenticated_permission_bits_reliable' => $permission['authenticated_permission_bits_reliable'] ?? null,
    'raw_material_exposed' => $rawMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF text remains blocked because the Standard security handler authentication material has invalid lengths. WordPress import records sanitized permission-preflight metadata and does not attempt password validation, decryption, or permission enforcement.</p>\n";
echo "<!-- /wp:paragraph -->\n";
