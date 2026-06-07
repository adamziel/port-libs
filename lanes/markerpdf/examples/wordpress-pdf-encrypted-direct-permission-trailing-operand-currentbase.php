<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress direct trailing permission encrypted leak) Tj ET';
$ownerValidation = str_repeat('W', 32);
$userValidation = str_repeat('P', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 6 0 R /EncryptMetadata true >>\nendobj\n"
    . "6 0 obj\n<< /P 16 /O <DEADBEEF> /U <CAFEFEED> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$declaration = is_array($permission['standard_permission_word_review'] ?? null)
    ? $permission['standard_permission_word_review']
    : [];
$entry = is_array($declaration['entries'][0] ?? null) ? $declaration['entries'][0] : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
        || str_contains($encoded, 'DEADBEEF')
        || str_contains($encoded, 'CAFEFEED')
        || str_contains($encoded, 'FFFFFFD4')
        || str_contains($encoded, 'copy_extract_allowed_after_decryption')
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted fixture text to stay blocked.');
}
if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_permissions') {
    throw new RuntimeException('Expected direct trailing /P operand to own malformed permission preflight.');
}
if (($permission['permission_bits_reliable'] ?? null) !== false || ($permission['copy_or_extract_allowed'] ?? null) !== null) {
    throw new RuntimeException('Expected direct trailing /P operand to suppress decoded Standard permission bits.');
}
if (($entry['trailing_operand_preview'] ?? null) !== '6 0 R') {
    throw new RuntimeException('Expected direct trailing /P operand review to retain the first unsafe operand boundary.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content, auth material, and decoy permission bytes to remain redacted.');
}

echo '<!-- markerpdf-encrypted-direct-permission-trailing-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-direct-permission-trailing-operand-currentbase',
    'native_boundary' => 'Direct Standard /P operands with trailing values are malformed review metadata and do not authorize WordPress text import',
    'encrypted_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'permission_source' => $permission['source'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'permission_word_status' => $declaration['status'] ?? null,
    'entry_statuses' => $declaration['entry_statuses'] ?? [],
    'trailing_operand_shape' => $entry['trailing_operand_shape'] ?? null,
    'trailing_operand_preview' => $entry['trailing_operand_preview'] ?? null,
    'trailing_operand_object_number' => $entry['trailing_operand_object_number'] ?? null,
    'raw_material_exposed' => false,
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
    'Encrypted PDF text remains blocked. A malformed Standard permission word with trailing operands is recorded for review, but copy/extract permission bits are not trusted for WordPress import.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-direct-permission-trailing-operand ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_hex' => $permission['permission_hex'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    ],
    'permission_word_review' => [
        'status' => $declaration['status'] ?? null,
        'entry_statuses' => $declaration['entry_statuses'] ?? [],
        'trailing_operand_shape' => $entry['trailing_operand_shape'] ?? null,
        'trailing_operand_preview' => $entry['trailing_operand_preview'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
