<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress commented indirect permission encrypted leak) Tj ET';
$ownerValidation = str_repeat('C', 32);
$userValidation = str_repeat('I', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 7 0 R /R 8 0 R /Length 9 0 R"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P 18 0 R /EncryptMetadata 19 0 R"
    . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 25 0 R >> >>"
    . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
    . "7 0 obj\n% version comment before scalar\n4 % version comment after scalar\nendobj\n"
    . "8 0 obj\n% revision comment before scalar\n4 % revision comment after scalar\nendobj\n"
    . "9 0 obj\n% key-length comment before scalar\n128 % key-length comment after scalar\nendobj\n"
    . "18 0 obj\n% permission comment before scalar\n-44 % permission comment after scalar\nendobj\n"
    . "19 0 obj\n% EncryptMetadata comment before scalar\ntrue % EncryptMetadata comment after scalar\nendobj\n"
    . "25 0 obj\n% crypt-filter length comment before scalar\n16 % crypt-filter length comment after scalar\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : [];
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$parameterReview = is_array($permission['standard_security_handler_parameter_review'] ?? null)
    ? $permission['standard_security_handler_parameter_review']
    : [];
$permissionReview = is_array($permission['standard_permission_word_review'] ?? null)
    ? $permission['standard_permission_word_review']
    : [];
$cryptFilterReview = is_array($report['crypt_filter_content_review'] ?? null)
    ? $report['crypt_filter_content_review']
    : [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text to stay blocked before decryption.');
}

if (($report['import_decision'] ?? null) !== 'block_encrypted_content_review_security_metadata') {
    throw new RuntimeException('Expected encrypted PDF preflight to block WordPress import text extraction.');
}

if (($permission['source'] ?? null) !== 'standard_security_handler_permissions'
    || ($permission['policy'] ?? null) !== 'copy_extract_allowed_after_decryption'
    || ($permission['content_extraction_boundary'] ?? null) !== 'blocked_until_decryption_password_available'
) {
    throw new RuntimeException('Expected resolved Standard permission bits to define the encrypted boundary.');
}

if (($parameterReview['parameters_well_formed'] ?? null) !== true
    || ($permissionReview['status'] ?? null) !== 'well_formed_standard_permissions'
    || ($permission['copy_or_extract_allowed'] ?? null) !== true
) {
    throw new RuntimeException('Expected comments around indirect Standard scalar operands to be stripped.');
}

if (($encryption['version'] ?? null) !== 4
    || ($encryption['revision'] ?? null) !== 4
    || ($encryption['key_length_bits'] ?? null) !== 128
    || ($encryption['encrypt_metadata_status'] ?? null) !== 'well_formed_encrypt_metadata_boolean'
) {
    throw new RuntimeException('Expected commented indirect encryption metadata operands to resolve.');
}

if (($cryptFilterReview['roles'][0]['key_length_bytes'] ?? null) !== 16
    || ($cryptFilterReview['roles'][0]['key_length_status'] ?? null) !== 'crypt_filter_key_length_supported'
) {
    throw new RuntimeException('Expected commented indirect crypt-filter key length to resolve.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
) {
    throw new RuntimeException('Expected encrypted content and raw Standard authentication bytes to remain redacted.');
}

echo '<!-- markerpdf-encrypted-commented-indirect-operands-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-commented-indirect-operands-currentbase',
    'native_boundary' => 'commented indirect Standard permission operands are resolved before WordPress import permission review',
    'encrypted_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'permission_source' => $permission['source'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'permission_word_status' => $permissionReview['status'] ?? null,
    'standard_parameters_well_formed' => $parameterReview['parameters_well_formed'] ?? null,
    'encryption_version' => $encryption['version'] ?? null,
    'encryption_revision' => $encryption['revision'] ?? null,
    'key_length_bits' => $encryption['key_length_bits'] ?? null,
    'encrypt_metadata_status' => $encryption['encrypt_metadata_status'] ?? null,
    'crypt_filter_key_length_bytes' => $cryptFilterReview['roles'][0]['key_length_bytes'] ?? null,
    'raw_auth_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked until decryption is available. Native preflight still resolves commented indirect Standard permission operands so WordPress can report whether copy/extract would be allowed after credentials are supplied.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-commented-indirect-permission-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'source' => $permission['source'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_hex' => $permission['permission_hex'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    ],
    'standard_security_handler_parameters' => [
        'status' => $parameterReview['status'] ?? null,
        'parameters_well_formed' => $parameterReview['parameters_well_formed'] ?? null,
        'key_length_bits' => $parameterReview['key_length_bits'] ?? null,
        'key_length_status' => $parameterReview['key_length_status'] ?? null,
    ],
    'standard_permission_word' => [
        'status' => $permissionReview['status'] ?? null,
        'unsigned_values' => $permissionReview['unsigned_values'] ?? [],
        'hex_values' => $permissionReview['hex_values'] ?? [],
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
