<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress trailing Encrypt operand text leak) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . ' /O <' . strtoupper(bin2hex($ownerValidation)) . '>'
    . ' /U <' . strtoupper(bin2hex($userValidation)) . '>'
    . " /P -44 /EncryptMetadata true >>\nendobj\n"
    . "6 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = $metadata['encryption'] ?? [];
$permissionPreflight = $report['permission_preflight'] ?? [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
        || str_contains($encoded, 'DEADBEEF')
        || str_contains($encoded, 'CAFEFEED')
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted page text to stay blocked.');
}
if (($encryption['encrypt_operand_status'] ?? null) !== 'encrypt_dictionary_trailing_operand_review') {
    throw new RuntimeException('Expected trailing /Encrypt operand to be reported before permission review.');
}
if (($permissionPreflight['permission_bits_reliable'] ?? null) !== false || ($permissionPreflight['permission_hex'] ?? null) !== null) {
    throw new RuntimeException('Expected malformed /Encrypt operand to suppress decoded Standard permissions.');
}
if (($report['review_reasons'] ?? []) !== ['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown', 'encrypt_dictionary_trailing_operand']) {
    throw new RuntimeException('Expected trailing /Encrypt operand to be a WordPress import review reason.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content and Standard auth material to remain redacted.');
}

echo '<!-- markerpdf-encrypted-trailing-encrypt-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-trailing-encrypt-preflight-currentbase',
    'native_boundary' => 'malformed multi-token PDF /Encrypt operands fail closed before WordPress import permission preflight',
    'encrypted_text_blocked' => $plainText === '',
    'malformed_encrypt_dictionary' => $encryption['malformed_encrypt_dictionary'] ?? null,
    'encrypt_dictionary_resolved' => $encryption['encrypt_dictionary_resolved'] ?? null,
    'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
    'encrypt_trailing_operand_shape' => $encryption['encrypt_trailing_operand_shape'] ?? null,
    'encrypt_trailing_operand_preview' => $encryption['encrypt_trailing_operand_preview'] ?? null,
    'policy' => $permissionPreflight['policy'] ?? null,
    'content_extraction_boundary' => $permissionPreflight['content_extraction_boundary'] ?? null,
    'permission_hex' => $permissionPreflight['permission_hex'] ?? null,
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
    'Encrypted PDF text remains blocked from import because the selected /Encrypt trailer entry contains an extra top-level operand. Standard permission bits are not decoded or trusted without a single well-formed encryption dictionary reference.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-trailing-encrypt-preflight ' . htmlspecialchars(json_encode([
    'decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'encryption' => [
        'source' => $encryption['source'] ?? null,
        'malformed_encrypt_dictionary' => $encryption['malformed_encrypt_dictionary'] ?? null,
        'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
        'trailing_operand_shape' => $encryption['encrypt_trailing_operand_shape'] ?? null,
        'trailing_operand_preview' => $encryption['encrypt_trailing_operand_preview'] ?? null,
    ],
    'permission' => [
        'hex' => $permissionPreflight['permission_hex'] ?? null,
        'policy' => $permissionPreflight['policy'] ?? null,
        'content_extraction_boundary' => $permissionPreflight['content_extraction_boundary'] ?? null,
        'permission_bits_reliable' => $permissionPreflight['permission_bits_reliable'] ?? null,
    ],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
