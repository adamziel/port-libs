<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress low resolution print encrypted leak) Tj ET';
$ownerKey = 'WORDPRESS_LOW_RES_PRINT_OWNER_KEY_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_LOW_RES_PRINT_USER_KEY_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
    . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
    . " /P -2092 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$printReview = is_array($permission['standard_permission_print_quality_review'] ?? null)
    ? $permission['standard_permission_print_quality_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))))
        || str_contains($encoded, strtoupper(bin2hex(str_pad($userKey, 32, 'U'))))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted page text to stay blocked.');
}
if (($printReview['print_quality'] ?? null) !== 'low_resolution') {
    throw new RuntimeException('Expected low-resolution print quality review.');
}
if (($printReview['limited_to_low_resolution'] ?? null) !== true) {
    throw new RuntimeException('Expected print review to mark low-resolution limit.');
}
if (($printReview['status'] ?? null) !== 'low_resolution_print_pending_authentication') {
    throw new RuntimeException('Expected print review to remain pending authentication.');
}
if (($permission['copy_or_extract_allowed'] ?? null) !== true || ($permission['native_text_extraction_allowed_now'] ?? null) !== false) {
    throw new RuntimeException('Expected copy permission to remain gated by decryption.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content and authentication material to stay redacted.');
}

$summary = [
    'scenario' => 'wordpress-pdf-encrypted-print-quality-currentbase',
    'native_boundary' => 'Standard encrypted /P low-resolution print permission review before WordPress import',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    'print_quality' => $printReview['print_quality'] ?? null,
    'print_review_status' => $printReview['status'] ?? null,
    'limited_to_low_resolution' => $printReview['limited_to_low_resolution'] ?? null,
    'print_permission_boundary' => $printReview['permission_boundary'] ?? null,
    'raw_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-encrypted-print-quality-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Print Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The Standard permission preflight records low-resolution print metadata as review-only security information before any password validation, decryption, or permission enforcement.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-print-quality ' . htmlspecialchars(json_encode([
    'print_quality' => $printReview['print_quality'] ?? null,
    'status' => $printReview['status'] ?? null,
    'limited_to_low_resolution' => $printReview['limited_to_low_resolution'] ?? null,
    'permission_boundary' => $printReview['permission_boundary'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
