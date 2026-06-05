<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress escaped auth key encrypted leak) Tj ET';
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
    . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
    . " /#4F " . $hex($ownerValidation)
    . " /#55 " . $hex($userValidation)
    . " /#4F#45 " . $hex($ownerEncryptionKey)
    . " /#55#45 " . $hex($userEncryptionKey)
    . " /#50 -44 /EncryptMetadata true /#50erms " . $hex($permissionDigest)
    . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
    . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$authMaterial = is_array($permission['standard_authentication_material_review'] ?? null)
    ? $permission['standard_authentication_material_review']
    : [];
$authReview = is_array($permission['standard_authentication_review'] ?? null)
    ? $permission['standard_authentication_review']
    : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected escaped-key encrypted fixture text to stay blocked.');
}

if (($authMaterial['ready_for_password_attempt'] ?? null) !== true) {
    throw new RuntimeException('Expected escaped Standard authentication keys to be resolved for review.');
}

if (($authReview['permission_digest']['sha256'] ?? null) !== hash('sha256', $permissionDigest)) {
    throw new RuntimeException('Expected escaped /Perms digest to be fingerprinted without exposing bytes.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, $ownerEncryptionKey)
    || str_contains($encoded, $userEncryptionKey)
    || str_contains($encoded, $permissionDigest)
    || str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
) {
    throw new RuntimeException('Expected encrypted authentication material to remain redacted.');
}

echo '<!-- markerpdf-encrypted-escaped-auth-keys-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-escaped-auth-keys-currentbase',
    'native_boundary' => 'escaped Standard encryption dictionary keys are decoded before authentication-material review',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'auth_material_status' => $authMaterial['status'] ?? null,
    'ready_for_password_attempt' => $authMaterial['ready_for_password_attempt'] ?? null,
    'present_required_entries' => $authMaterial['present_required_entries'] ?? [],
    'permission_digest_status' => $authMaterial['permission_digest_status'] ?? null,
    'raw_auth_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Escaped Key Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Escaped Standard authentication keys are decoded only for safe preflight metadata before any future password-backed import path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-escaped-auth-key-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'permission_authentication_status' => $permission['permission_authentication_status'] ?? null,
    ],
    'standard_authentication_material' => [
        'status' => $authMaterial['status'] ?? null,
        'ready_for_password_attempt' => $authMaterial['ready_for_password_attempt'] ?? null,
        'present_required_entries' => $authMaterial['present_required_entries'] ?? [],
        'permission_digest_status' => $authMaterial['permission_digest_status'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
