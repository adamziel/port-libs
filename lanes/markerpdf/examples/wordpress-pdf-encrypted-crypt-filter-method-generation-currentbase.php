<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress AES256 legacy crypt filter encrypted leak) Tj ET';
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
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /OE " . $hex($ownerEncryptionKey)
    . " /UE " . $hex($userEncryptionKey)
    . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
    . " /CF <<"
    . " /LegacyDoc << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
    . " /EmbeddedAes256 << /CFM /AESV3 /AuthEvent /EFOpen /Length 32 >>"
    . " >>"
    . " /StmF /LegacyDoc /StrF /LegacyDoc /EFF /EmbeddedAes256 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$review = is_array($report['crypt_filter_content_review'] ?? null) ? $report['crypt_filter_content_review'] : [];
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected AES-256 encrypted text to stay blocked.');
}

if (($permission['content_extraction_boundary'] ?? null) !== 'blocked_by_incompatible_document_crypt_filter_method') {
    throw new RuntimeException('Expected incompatible Standard crypt-filter method to block native text import.');
}

if (($permission['permission_bits_reliable'] ?? null) !== true || ($permission['copy_or_extract_allowed'] ?? null) !== true) {
    throw new RuntimeException('Expected syntactically decoded copy permission to remain review-only metadata.');
}

if (($review['text_content_policy'] ?? null) !== 'crypt_filter_method_generation_mismatch_fail_closed') {
    throw new RuntimeException('Expected method-generation mismatch fail-closed policy.');
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

echo '<!-- markerpdf-encrypted-crypt-filter-method-generation-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-crypt-filter-method-generation-currentbase',
    'native_boundary' => 'Standard AES-256 encrypted document roles selecting legacy AESV2 filters fail closed before WordPress text import',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'crypt_filter_text_policy' => $permission['crypt_filter_text_policy'] ?? null,
    'method_generation_statuses' => $review['method_generation_statuses'] ?? [],
    'method_generation_fail_closed_role_names' => $review['method_generation_fail_closed_role_names'] ?? [],
    'method_generation_fail_closed_filter_names' => $review['method_generation_fail_closed_filter_names'] ?? [],
    'fail_closed_role_names' => $review['fail_closed_role_names'] ?? [],
    'fail_closed_filter_names' => $review['fail_closed_filter_names'] ?? [],
    'raw_auth_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Crypt Filter Generation Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked when an AES-256 Standard security handler selects legacy document crypt filters. Permission bits are recorded as review metadata only; no password validation, decryption, or permission enforcement runs during import preflight.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-crypt-filter-method-generation ' . htmlspecialchars(json_encode([
    'permission' => [
        'source' => $permission['source'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    ],
    'crypt_filter_content_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'method_generation_statuses' => $review['method_generation_statuses'] ?? [],
        'method_generation_fail_closed_role_names' => $review['method_generation_fail_closed_role_names'] ?? [],
        'method_generation_fail_closed_filter_names' => $review['method_generation_fail_closed_filter_names'] ?? [],
        'roles' => $review['roles'] ?? [],
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
