<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Unsigned permission encrypted text leak) Tj ET';
$ownerKey = 'WORDPRESS_UNSIGNED_PERMISSION_OWNER_KEY_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_UNSIGNED_PERMISSION_USER_KEY_SHOULD_NOT_LEAK';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
    . " /U <" . strtoupper(bin2hex($userKey)) . ">"
    . " /P 4294967252 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permissions = $metadata['encryption']['standard_permissions'] ?? [];
$permissionPreflight = $report['permission_preflight'] ?? [];

echo '<!-- markerpdf-encrypted-unsigned-permission-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-unsigned-permission-preflight-currentbase',
    'native_boundary' => 'unsigned decimal Standard /P values are normalized to signed 32-bit permission words before encrypted WordPress import preflight',
    'encrypted_text_blocked' => $plainText === '',
    'declared_permission_word' => $permissions['declared'] ?? null,
    'permission_word_form' => $permissions['declared_form'] ?? null,
    'normalized_from_unsigned_decimal' => $permissions['normalized_from_unsigned_decimal'] ?? null,
    'permission_signed' => $permissionPreflight['permission_signed'] ?? null,
    'permission_unsigned' => $permissionPreflight['permission_unsigned'] ?? null,
    'permission_hex' => $permissionPreflight['permission_hex'] ?? null,
    'policy' => $permissionPreflight['policy'] ?? null,
    'content_extraction_boundary' => $permissionPreflight['content_extraction_boundary'] ?? null,
    'copy_or_extract_allowed' => $permissionPreflight['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permissionPreflight['permission_bits_reliable'] ?? null,
    'raw_key_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked from import. The unsigned permission word is normalized and kept as review metadata until native decryption is available.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-unsigned-permission-preflight ' . htmlspecialchars(json_encode([
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
