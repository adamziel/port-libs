<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress plus permission encrypted text leak) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V +4 /R +4 /Length +128"
    . " /O <" . strtoupper(bin2hex($ownerValidation)) . ">"
    . " /U <" . strtoupper(bin2hex($userValidation)) . ">"
    . " /P +4294967252 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permissions = $metadata['encryption']['standard_permissions'] ?? [];
$permissionPreflight = $report['permission_preflight'] ?? [];
$parameterReview = $metadata['encryption']['standard_security_handler_parameter_review'] ?? [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted page text to stay blocked.');
}
if (($parameterReview['parameters_well_formed'] ?? null) !== true) {
    throw new RuntimeException('Expected plus-signed Standard /V, /R, and /Length operands to parse as integers.');
}
if (($permissionPreflight['permission_hex'] ?? null) !== 'FFFFFFD4') {
    throw new RuntimeException('Expected plus-signed unsigned /P to normalize before permission preflight.');
}
if (($permissionPreflight['copy_or_extract_allowed'] ?? null) !== true || ($permissionPreflight['permission_bits_reliable'] ?? null) !== true) {
    throw new RuntimeException('Expected normalized plus-signed /P permissions to be reliable review metadata.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content and Standard auth material to remain redacted.');
}

echo '<!-- markerpdf-encrypted-plus-permission-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-plus-permission-preflight-currentbase',
    'native_boundary' => 'plus-signed PDF integer operands in Standard encryption dictionaries are normalized before WordPress import permission preflight',
    'encrypted_text_blocked' => $plainText === '',
    'standard_parameters_well_formed' => $parameterReview['parameters_well_formed'] ?? null,
    'permission_word_form' => $permissions['declared_form'] ?? null,
    'normalized_from_unsigned_decimal' => $permissions['normalized_from_unsigned_decimal'] ?? null,
    'permission_signed' => $permissionPreflight['permission_signed'] ?? null,
    'permission_unsigned' => $permissionPreflight['permission_unsigned'] ?? null,
    'permission_hex' => $permissionPreflight['permission_hex'] ?? null,
    'policy' => $permissionPreflight['policy'] ?? null,
    'content_extraction_boundary' => $permissionPreflight['content_extraction_boundary'] ?? null,
    'copy_or_extract_allowed' => $permissionPreflight['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permissionPreflight['permission_bits_reliable'] ?? null,
    'raw_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked from import. Plus-signed Standard encryption integers are normalized as review metadata before any decryption or permission-enforcement path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-plus-permission-preflight ' . htmlspecialchars(json_encode([
    'decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'permission' => [
        'signed' => $permissionPreflight['permission_signed'] ?? null,
        'unsigned' => $permissionPreflight['permission_unsigned'] ?? null,
        'hex' => $permissionPreflight['permission_hex'] ?? null,
        'word_form' => $permissionPreflight['permission_word_form'] ?? null,
        'normalized_from_unsigned_decimal' => $permissionPreflight['permission_normalized_from_unsigned_decimal'] ?? null,
        'policy' => $permissionPreflight['policy'] ?? null,
        'content_extraction_boundary' => $permissionPreflight['content_extraction_boundary'] ?? null,
    ],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
