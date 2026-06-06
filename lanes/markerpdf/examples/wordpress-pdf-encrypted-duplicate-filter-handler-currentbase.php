<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate Filter handler encrypted text leak) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /Filter /VendorSecurity /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$handler = is_array($report['permission_handler_review'] ?? null) ? $report['permission_handler_review'] : [];
$declaration = is_array($permission['security_handler_declaration_review'] ?? null)
    ? $permission['security_handler_declaration_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected duplicate security-handler Filter fixture text to stay blocked.');
}
if (($permission['content_extraction_boundary'] ?? null) !== 'blocked_encrypted_security_handler_malformed') {
    throw new RuntimeException('Expected duplicate security-handler Filter declarations to fail closed before import.');
}
$copyAllowed = array_key_exists('copy_or_extract_allowed', $permission)
    ? $permission['copy_or_extract_allowed']
    : '__missing__';
if ($copyAllowed !== null || ($permission['permission_bits_reliable'] ?? true) !== false) {
    throw new RuntimeException('Expected duplicate security-handler Filter declarations to suppress permission-bit grants.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
) {
    throw new RuntimeException('Expected encrypted payload and authentication material to remain redacted.');
}

echo '<!-- markerpdf-encrypted-duplicate-filter-handler-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-duplicate-filter-handler-currentbase',
    'native_boundary' => 'duplicate top-level Encrypt /Filter declarations are fail-closed before WordPress import trusts permission bits',
    'encrypted_text_blocked' => $plainText === '',
    'selected_handler' => $permission['handler'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'security_handler_declaration_status' => $permission['security_handler_declaration_status'] ?? null,
    'security_handler_filter_names' => $permission['security_handler_filter_names'] ?? [],
    'security_handler_duplicate_filter_entries' => $permission['security_handler_duplicate_filter_entries'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'handler_status' => $handler['status'] ?? null,
    'declaration_entry_statuses' => $declaration['entry_statuses'] ?? [],
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
    'Encrypted PDF text remains blocked. Duplicate security-handler Filter declarations are review-only metadata until a real decryption and password-validation path exists.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-duplicate-filter-handler ' . htmlspecialchars(json_encode([
    'policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'security_handler_filter_names' => $permission['security_handler_filter_names'] ?? [],
    'review_reasons' => $report['review_reasons'] ?? [],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
