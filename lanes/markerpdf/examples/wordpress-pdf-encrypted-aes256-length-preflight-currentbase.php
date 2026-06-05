<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress missing AES256 length encrypted leak) Tj ET';
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
    . "5 0 obj\n<< /Filter /Standard /V 5 /R 6"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /OE " . $hex($ownerEncryptionKey)
    . " /UE " . $hex($userEncryptionKey)
    . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
    . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
    . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$parameterReview = is_array($permission['standard_security_handler_parameter_review'] ?? null)
    ? $permission['standard_security_handler_parameter_review']
    : [];
$materialReview = is_array($permission['standard_authentication_material_review'] ?? null)
    ? $permission['standard_authentication_material_review']
    : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected missing AES-256 length encrypted text to stay blocked.');
}

if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_parameters') {
    throw new RuntimeException('Expected missing AES-256 /Length to be classified as malformed Standard parameters.');
}

if (!in_array('missing_standard_security_handler_key_length', $parameterReview['violations'] ?? [], true)) {
    throw new RuntimeException('Expected missing_standard_security_handler_key_length violation.');
}

if (
    !array_key_exists('copy_or_extract_allowed', $permission)
    || $permission['copy_or_extract_allowed'] !== null
    || ($permission['permission_bits_reliable'] ?? true) !== false
) {
    throw new RuntimeException('Expected permission bits to be unreliable until AES-256 key length is declared.');
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
    throw new RuntimeException('Expected encrypted content and raw Standard authentication bytes to remain redacted.');
}

echo '<!-- markerpdf-encrypted-aes256-length-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-aes256-length-preflight-currentbase',
    'native_boundary' => 'Standard V5/R6 AES-256 dictionaries without top-level Length fail closed before permission bits are trusted',
    'encrypted_text_blocked' => $plainText === '',
    'permission_source' => $permission['source'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'parameter_status' => $parameterReview['status'] ?? null,
    'parameter_violations' => $parameterReview['violations'] ?? [],
    'key_length_present' => $parameterReview['key_length_present'] ?? null,
    'key_length_status' => $parameterReview['key_length_status'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'auth_material_status' => $materialReview['status'] ?? null,
    'auth_material_ready_for_password_attempt' => $materialReview['ready_for_password_attempt'] ?? null,
    'raw_auth_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF AES-256 Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records the missing AES-256 key length before any password prompt, decryption, or permission-enforcement path can trust copy/extract flags.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-aes256-length-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'source' => $permission['source'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    ],
    'standard_security_handler_parameters' => [
        'status' => $parameterReview['status'] ?? null,
        'violations' => $parameterReview['violations'] ?? [],
        'version' => $parameterReview['version'] ?? null,
        'revision' => $parameterReview['revision'] ?? null,
        'key_length_bits' => $parameterReview['key_length_bits'] ?? null,
        'key_length_present' => $parameterReview['key_length_present'] ?? null,
        'key_length_status' => $parameterReview['key_length_status'] ?? null,
        'minimum_key_length_bits' => $parameterReview['minimum_key_length_bits'] ?? null,
        'maximum_key_length_bits' => $parameterReview['maximum_key_length_bits'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
