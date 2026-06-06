<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress malformed auth operand encrypted leak) Tj ET';
$ownerValidation = str_repeat('O', 48);
$userValidationName = 'WordPressUserCredentialShouldNotAuthorize';
$ownerEncryptionKey = str_repeat('E', 32);
$userEncryptionKey = str_repeat('K', 32);
$permissionDigest = str_repeat('P', 16);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
    . " /O [" . $hex($ownerValidation) . "]"
    . " /U /{$userValidationName}"
    . " /OE " . $hex($ownerEncryptionKey)
    . " /UE " . $hex($userEncryptionKey)
    . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
    . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
    . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$auth = is_array($permission['standard_authentication_review'] ?? null)
    ? $permission['standard_authentication_review']
    : [];
$material = is_array($permission['standard_authentication_material_review'] ?? null)
    ? $permission['standard_authentication_material_review']
    : [];
$trust = is_array($permission['permission_authentication_trust_review'] ?? null)
    ? $permission['permission_authentication_trust_review']
    : [];
$ownerEntry = is_array($auth['entries']['owner_validation'] ?? null)
    ? $auth['entries']['owner_validation']
    : [];
$userEntry = is_array($auth['entries']['user_validation'] ?? null)
    ? $auth['entries']['user_validation']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawAuthMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidationName)
        || str_contains($encoded, $ownerEncryptionKey)
        || str_contains($encoded, $userEncryptionKey)
        || str_contains($encoded, $permissionDigest)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected malformed encrypted authentication operand fixture text to stay blocked.');
}

if (($ownerEntry['status'] ?? null) !== 'authentication_entry_composite_operand_review') {
    throw new RuntimeException('Expected composite /O authentication operand to be classified before password readiness.');
}

if (($userEntry['status'] ?? null) !== 'authentication_entry_non_string_operand_review') {
    throw new RuntimeException('Expected non-string /U authentication operand to be classified before password readiness.');
}

if (($material['ready_for_password_attempt'] ?? null) !== false || $rawAuthMaterialExposed) {
    throw new RuntimeException('Expected malformed authentication operands to block password readiness without exposing raw bytes.');
}

echo '<!-- markerpdf-encrypted-auth-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-auth-operand-currentbase',
    'native_boundary' => 'revision six Standard authentication entries must be scalar string ciphertext before password-ready review',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'owner_validation_status' => $ownerEntry['status'] ?? null,
    'owner_validation_operand_shape' => $ownerEntry['operand_shape'] ?? null,
    'user_validation_status' => $userEntry['status'] ?? null,
    'user_validation_operand_shape' => $userEntry['operand_shape'] ?? null,
    'auth_material_status' => $material['status'] ?? null,
    'ready_for_password_attempt' => $material['ready_for_password_attempt'] ?? null,
    'permission_authentication_status' => $trust['status'] ?? null,
    'raw_auth_material_exposed' => $rawAuthMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF text remains blocked because required Standard authentication entries are malformed operands. WordPress import keeps the authentication material as sanitized review metadata and does not attempt password validation or decryption.</p>\n";
echo "<!-- /wp:paragraph -->\n";
