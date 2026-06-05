<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Revision two permission bit text leak) Tj ET';
$ownerKey = 'WORDPRESS_REVISION_TWO_PERMISSION_OWNER_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_REVISION_TWO_PERMISSION_USER_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 1 /R 2 /Length 40"
    . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
    . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
    . " /P -44 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = $report['permission_preflight'] ?? [];
$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted revision-two permission-bit fixture text to stay blocked.');
}

if (($permission['not_applicable_permission_names'] ?? []) !== [
    'fill_form_fields',
    'extract_for_accessibility',
    'assemble_document',
    'high_quality_print',
]) {
    throw new RuntimeException('Expected revision-two permission bits 9-12 to be reported as not applicable.');
}

echo '<!-- markerpdf-encrypted-permission-bit-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-bit-preflight-currentbase',
    'native_boundary' => 'Standard security-handler permission bits are reported with revision applicability before encrypted WordPress import decisions',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'permission_bit_review_count' => $permission['permission_bit_review_count'] ?? null,
    'permission_bit_statuses' => $permission['permission_bit_statuses'] ?? [],
    'applicable_permission_names' => $permission['applicable_permission_names'] ?? [],
    'not_applicable_permission_names' => $permission['not_applicable_permission_names'] ?? [],
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    'raw_key_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Bits</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The import preflight records which Standard permission bits apply to this security-handler revision and which later-revision bits are review-only.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-bit-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'hex' => $permission['permission_hex'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'applicable_permission_names' => $permission['applicable_permission_names'] ?? [],
        'not_applicable_permission_names' => $permission['not_applicable_permission_names'] ?? [],
        'permission_bit_statuses' => $permission['permission_bit_statuses'] ?? [],
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
