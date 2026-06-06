<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (V2 default key length WordPress text leak) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 2 /R 3"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = $metadata['encryption'] ?? [];
$permission = $report['permission_preflight'] ?? [];
$parameterReview = $permission['standard_security_handler_parameter_review'] ?? [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted V2 default-length fixture text to stay blocked.');
}

if (($encryption['key_length_bits'] ?? null) !== 40 || ($encryption['key_length_defaulted'] ?? null) !== true) {
    throw new RuntimeException('Expected omitted Standard /V 2 Length to default to 40-bit review metadata.');
}

if (($parameterReview['key_length_status'] ?? null) !== 'standard_security_handler_key_length_default_40_bit') {
    throw new RuntimeException('Expected Standard /V 2 omitted Length to carry the default 40-bit status.');
}

if (($permission['policy'] ?? null) !== 'copy_extract_allowed_after_decryption') {
    throw new RuntimeException('Expected copy/extract permission to remain blocked until decryption.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
) {
    throw new RuntimeException('Expected raw encrypted authentication material to stay redacted.');
}

echo '<!-- markerpdf-encrypted-v2-default-length-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-v2-default-length-currentbase',
    'native_boundary' => 'Standard V2 omitted Length defaults to 40-bit review metadata before encrypted WordPress import',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    'key_length_bits' => $encryption['key_length_bits'] ?? null,
    'key_length_explicit' => $encryption['key_length_explicit'] ?? null,
    'key_length_defaulted' => $encryption['key_length_defaulted'] ?? null,
    'key_length_source' => $encryption['key_length_source'] ?? null,
    'parameter_key_length_present' => $parameterReview['key_length_present'] ?? null,
    'parameter_key_length_status' => $parameterReview['key_length_status'] ?? null,
    'parameter_violations' => $parameterReview['violations'] ?? [],
    'raw_auth_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF V2 Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The security preflight records that this Standard V2 handler omitted /Length and uses the PDF default 40-bit key length before any password or decryption step.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-v2-default-length ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    ],
    'standard_security_handler' => [
        'version' => $encryption['version'] ?? null,
        'revision' => $encryption['revision'] ?? null,
        'key_length_bits' => $encryption['key_length_bits'] ?? null,
        'key_length_explicit' => $encryption['key_length_explicit'] ?? null,
        'key_length_defaulted' => $encryption['key_length_defaulted'] ?? null,
        'key_length_source' => $encryption['key_length_source'] ?? null,
        'key_length_status' => $parameterReview['key_length_status'] ?? null,
        'parameter_violations' => $parameterReview['violations'] ?? [],
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
