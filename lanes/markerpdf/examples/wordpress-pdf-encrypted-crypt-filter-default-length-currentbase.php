<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (Inherited crypt filter length WordPress import text leak) Tj ET';
$ownerValidation = str_repeat('D', 32);
$userValidation = str_repeat('L', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /EncryptMetadata true"
    . " /CF << /DocAes << /CFM /AESV2 /AuthEvent /DocOpen >> >>"
    . " /StmF /DocAes /StrF /DocAes >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : [];
$filter = is_array($encryption['crypt_filters']['DocAes'] ?? null) ? $encryption['crypt_filters']['DocAes'] : [];
$review = is_array($report['crypt_filter_content_review'] ?? null)
    ? $report['crypt_filter_content_review']
    : [];
$permission = is_array($report['permission_preflight'] ?? null)
    ? $report['permission_preflight']
    : [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text to stay blocked before decryption.');
}
if (($filter['key_length_bytes'] ?? null) !== 16) {
    throw new RuntimeException('Expected AESV2 crypt filter to inherit 16 bytes from the Standard 128-bit key length.');
}
if (($filter['key_length_source'] ?? null) !== 'standard_security_handler_length_inherited') {
    throw new RuntimeException('Expected inherited crypt-filter key length source metadata.');
}
if (($review['key_length_statuses'] ?? []) !== ['crypt_filter_key_length_supported']) {
    throw new RuntimeException('Expected inherited crypt-filter key length to pass preflight review.');
}
if (($permission['content_extraction_boundary'] ?? null) !== 'blocked_until_decryption_password_available') {
    throw new RuntimeException('Expected WordPress import to stay blocked until a decryption password is available.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
) {
    throw new RuntimeException('Expected encrypted payload and authentication bytes to stay out of review output.');
}

echo '<!-- markerpdf-encrypted-crypt-filter-default-length-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-crypt-filter-default-length-currentbase',
    'native_boundary' => 'Standard crypt filters without local Length inherit the top-level key length during preflight',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'crypt_filter' => [
        'name' => 'DocAes',
        'method' => $filter['method'] ?? null,
        'key_length_bytes' => $filter['key_length_bytes'] ?? null,
        'key_length_defaulted' => $filter['key_length_defaulted'] ?? null,
        'key_length_source' => $filter['key_length_source'] ?? null,
        'key_length_source_bits' => $filter['key_length_source_bits'] ?? null,
    ],
    'key_length_statuses' => $review['key_length_statuses'] ?? [],
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES) . " -->\n";

echo "<h2>Encrypted PDF Crypt Filter Length Preflight</h2>\n";
echo "<p>Encrypted PDF text remains blocked. The crypt filter inherits the Standard handler key length for review metadata without decrypting content or exposing authentication bytes.</p>\n";
echo '<!-- markerpdf:encrypted-crypt-filter-default-length-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    ],
    'crypt_filter_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'encrypted_filter_names' => $review['encrypted_filter_names'] ?? [],
        'key_length_statuses' => $review['key_length_statuses'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES) . " -->\n";
