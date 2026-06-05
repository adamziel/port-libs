<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress out-of-range permission text should not import) Tj ET';
$ownerKey = 'WORDPRESS_PERMISSION_RANGE_OWNER_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_PERMISSION_RANGE_USER_SHOULD_NOT_LEAK';
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
    . " /P 4294967296 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$handler = is_array($preflight['permission_handler_review'] ?? null) ? $preflight['permission_handler_review'] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
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
    throw new RuntimeException('Expected encrypted out-of-range permission fixture text to stay blocked.');
}
if (($permission['permission_word_range_valid'] ?? null) !== false) {
    throw new RuntimeException('Expected out-of-range Standard /P word to be marked invalid.');
}
$permissionHex = array_key_exists('permission_hex', $permission) ? $permission['permission_hex'] : 'missing';
$copyOrExtractAllowed = array_key_exists('copy_or_extract_allowed', $permission) ? $permission['copy_or_extract_allowed'] : 'missing';

if ($permissionHex !== null) {
    throw new RuntimeException('Expected out-of-range Standard /P word to avoid synthetic 32-bit hex projection.');
}
if ($copyOrExtractAllowed !== null) {
    throw new RuntimeException('Expected out-of-range Standard /P word not to expose bit-derived copy permission.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted text and authentication material to remain review-only.');
}

echo '<!-- markerpdf-encrypted-permission-word-range-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-word-range-currentbase',
    'native_boundary' => 'Standard /P words outside signed/unsigned 32-bit range are malformed review metadata before WordPress text import',
    'text_blocked' => $plainText === '',
    'policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'permission_word_range_valid' => $permission['permission_word_range_valid'] ?? null,
    'permission_word_range_status' => $permission['permission_word_range_status'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'handler_status' => $handler['status'] ?? null,
    'raw_material_exposed' => false,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $preflight['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Word Range</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. A Standard security-handler permission word outside the 32-bit domain is recorded as malformed review metadata, not as a copy/extract grant.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-word-range ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'range_valid' => $permission['permission_word_range_valid'] ?? null,
        'range_status' => $permission['permission_word_range_status'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    ],
    'handler' => [
        'status' => $handler['status'] ?? null,
        'permission_word_well_formed' => $handler['permission_word_well_formed'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
