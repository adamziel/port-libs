<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress reserved-bit permission text should not import) Tj ET';
$ownerKey = 'WORDPRESS_RESERVED_PERMISSION_OWNER_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_RESERVED_PERMISSION_USER_SHOULD_NOT_LEAK';
$ownerBytes = str_pad($ownerKey, 32, 'O');
$userBytes = str_pad($userKey, 32, 'U');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerBytes)) . ">"
    . " /U <" . strtoupper(bin2hex($userBytes)) . ">"
    . " /P 16 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$rawPermissions = is_array($metadata['encryption']['standard_permissions'] ?? null)
    ? $metadata['encryption']['standard_permissions']
    : [];
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$handler = is_array($preflight['permission_handler_review'] ?? null) ? $preflight['permission_handler_review'] : [];
$review = is_array($preflight['encryption'] ?? null) ? $preflight['encryption'] : [];
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, $ownerBytes)
        || str_contains($encoded, $userBytes)
        || str_contains($encoded, strtoupper(bin2hex($ownerBytes)))
        || str_contains($encoded, strtoupper(bin2hex($userBytes)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted reserved-bit permission fixture text to stay blocked.');
}
if (($permission['policy'] ?? null) !== 'permissions_malformed_blocked_without_decryption') {
    throw new RuntimeException('Expected malformed reserved bits to block permission preflight.');
}
if (($permission['copy_or_extract_allowed'] ?? null) !== null || ($permission['permission_bits_reliable'] ?? null) !== false) {
    throw new RuntimeException('Expected malformed reserved bits not to expose trusted copy permission.');
}
if (($handler['status'] ?? null) !== 'malformed_reserved_bits_review') {
    throw new RuntimeException('Expected Standard permission handler to flag malformed reserved bits.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted text and authentication material to remain review-only.');
}

echo '<!-- markerpdf-encrypted-permission-reserved-bits-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-reserved-bits-currentbase',
    'native_boundary' => 'Standard /P reserved-bit violations fail closed before WordPress import trusts copy/extract permission bits',
    'text_blocked' => $plainText === '',
    'policy' => $permission['policy'] ?? null,
    'permission_source' => $permission['source'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'raw_permission_hex' => $rawPermissions['hex'] ?? null,
    'raw_allowed_names' => $rawPermissions['allowed'] ?? [],
    'trusted_allowed_names' => $permission['allowed'] ?? [],
    'trusted_permission_bit_count' => $permission['permission_bit_review_count'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'handler_status' => $handler['status'] ?? null,
    'reserved_bit_violations' => $review['reserved_bit_violations'] ?? [],
    'raw_material_exposed' => false,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $preflight['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Reserved Bits</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records malformed Standard permission reserved bits as review metadata, not as a copy/extract grant.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-reserved-bits ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'source' => $permission['source'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'trusted_permission_bits' => $permission['permission_bits'] ?? [],
    ],
    'handler' => [
        'status' => $handler['status'] ?? null,
        'permission_word_well_formed' => $handler['permission_word_well_formed'] ?? null,
        'reserved_bits' => $handler['reserved_bits'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
