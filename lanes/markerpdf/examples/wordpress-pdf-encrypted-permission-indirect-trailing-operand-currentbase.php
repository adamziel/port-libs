<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress indirect trailing permission encrypted leak) Tj ET';
$ownerValidation = str_repeat('T', 32);
$userValidation = str_repeat('R', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P 18 0 R /EncryptMetadata true >>\nendobj\n"
    . "18 0 obj\n-44 /P -64\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : [];
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$declaration = is_array($permission['standard_permission_word_review'] ?? null)
    ? $permission['standard_permission_word_review']
    : [];
$entry = is_array($declaration['entries'][0] ?? null) ? $declaration['entries'][0] : [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text to stay blocked before decryption.');
}

if (($report['import_decision'] ?? null) !== 'block_encrypted_content_review_security_metadata') {
    throw new RuntimeException('Expected encrypted PDF preflight to block WordPress import text extraction.');
}

if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_permissions'
    || ($permission['policy'] ?? null) !== 'permissions_malformed_blocked_without_decryption'
    || ($permission['content_extraction_boundary'] ?? null) !== 'blocked_encrypted_permissions_malformed'
) {
    throw new RuntimeException('Expected malformed indirect Standard /P helper to fail closed.');
}

if (($declaration['status'] ?? null) !== 'malformed_standard_permission_word_review'
    || ($entry['status'] ?? null) !== 'permission_word_trailing_operand_review'
    || ($entry['trailing_operand_name'] ?? null) !== 'P'
) {
    throw new RuntimeException('Expected trailing top-level /P operand to be recorded in permission review.');
}

if (isset($encryption['standard_permissions'])
    || ($permission['standard_permission_bits_decoded'] ?? null) !== false
    || ($permission['copy_or_extract_allowed'] ?? null) !== null
) {
    throw new RuntimeException('Expected malformed Standard permission word not to produce decoded permission bits.');
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

echo '<!-- markerpdf-encrypted-permission-indirect-trailing-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-indirect-trailing-operand-currentbase',
    'native_boundary' => 'indirect Standard /P helper objects must resolve to one integer operand before WordPress import permission review',
    'encrypted_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'permission_source' => $permission['source'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_word_status' => $declaration['status'] ?? null,
    'permission_entry_statuses' => $declaration['entry_statuses'] ?? [],
    'trailing_operand_shape' => $entry['trailing_operand_shape'] ?? null,
    'trailing_operand_name' => $entry['trailing_operand_name'] ?? null,
    'permission_bits_decoded' => $permission['standard_permission_bits_decoded'] ?? null,
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
    'Encrypted PDF text remains blocked. Native preflight rejects an indirect Standard permission helper that hides another top-level operand after the integer, so WordPress import does not trust truncated permission bits.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-indirect-trailing-operand ' . htmlspecialchars(json_encode([
    'permission' => [
        'source' => $permission['source'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    ],
    'standard_permission_word' => [
        'status' => $declaration['status'] ?? null,
        'entry_statuses' => $declaration['entry_statuses'] ?? [],
        'integer_entry_count' => $declaration['integer_entry_count'] ?? null,
        'trailing_operand_shape' => $entry['trailing_operand_shape'] ?? null,
        'trailing_operand_name' => $entry['trailing_operand_name'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
