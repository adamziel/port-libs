<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress selected duplicate permission operand leak) Tj ET';
$ownerValidation = str_repeat('S', 32);
$userValidation = str_repeat('D', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -60 9 0 R /P -44 /EncryptMetadata true >>\nendobj\n"
    . "9 0 obj\n<< /P -4 /O <DEADBEEF> /U <CAFEFEED> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$declaration = is_array($permission['standard_permission_word_review'] ?? null)
    ? $permission['standard_permission_word_review']
    : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted duplicate permission text to remain blocked.');
}
if (($permission['policy'] ?? null) !== 'permissions_malformed_blocked_without_decryption') {
    throw new RuntimeException('Expected duplicate permission word policy to fail closed.');
}
if (($declaration['selected_entry_index'] ?? null) !== 1 || ($declaration['selected_permission_hex'] ?? null) !== 'FFFFFFD4') {
    throw new RuntimeException('Expected the later duplicate /P entry to be recorded as the selected review entry.');
}
if (($declaration['malformed_entry_count'] ?? null) !== 1 || ($declaration['malformed_entry_indexes'] ?? []) !== [0]) {
    throw new RuntimeException('Expected stale malformed duplicate /P entry review metadata.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
    || str_contains($encoded, 'DEADBEEF')
    || str_contains($encoded, 'CAFEFEED')
) {
    throw new RuntimeException('Expected raw encrypted payload and stale auth bytes to stay redacted.');
}

echo '<!-- markerpdf-encrypted-selected-duplicate-permission-operand-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-selected-duplicate-permission-operand-currentbase',
    'native_boundary' => 'stale malformed duplicate Standard /P operands are review-only while the later selected /P is recorded without trusting duplicate permissions',
    'text_blocked' => true,
    'policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'selected_entry_index' => $declaration['selected_entry_index'] ?? null,
    'selected_entry_status' => $declaration['selected_entry_status'] ?? null,
    'selected_permission_hex' => $declaration['selected_permission_hex'] ?? null,
    'malformed_entry_count' => $declaration['malformed_entry_count'] ?? null,
    'malformed_entry_indexes' => $declaration['malformed_entry_indexes'] ?? [],
    'malformed_entry_statuses' => $declaration['malformed_entry_statuses'] ?? [],
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'raw_auth_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records stale duplicate permission operands for review while refusing to trust duplicate Standard permission declarations.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-selected-duplicate-permission-operand ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    ],
    'standard_permission_word_review' => [
        'selected_entry_index' => $declaration['selected_entry_index'] ?? null,
        'selected_entry_status' => $declaration['selected_entry_status'] ?? null,
        'selected_permission_hex' => $declaration['selected_permission_hex'] ?? null,
        'malformed_entry_count' => $declaration['malformed_entry_count'] ?? null,
        'malformed_entry_indexes' => $declaration['malformed_entry_indexes'] ?? [],
        'entry_statuses' => $declaration['entry_statuses'] ?? [],
    ],
    'review_reasons' => $report['review_reasons'] ?? [],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
