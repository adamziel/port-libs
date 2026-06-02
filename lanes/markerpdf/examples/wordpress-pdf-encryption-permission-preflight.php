<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildEncryptedPdf = static function (string $encryptDictionary, string $visibleText): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $visibleText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";
};

$copyAllowedPdf = $buildEncryptedPdf(
    '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true >>',
    'Copy permitted encrypted text should not import before decryption'
);
$unknownPermissionPdf = $buildEncryptedPdf(
    '<< /Filter /PublicKey /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128 >>',
    'Unknown permission encrypted text should not import before decryption'
);

$preflight = new PdfSecurityPreflight();
$extractor = new PdfTextExtractor();
$copyAllowedReport = $preflight->analyze($copyAllowedPdf);
$unknownPermissionReport = $preflight->analyze($unknownPermissionPdf);

echo '<!-- markerpdf-encryption-permission-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encryption-permission-preflight',
    'native_boundary' => 'encrypted PDF Standard permission flags are classified separately from password/decryption availability before WordPress text import',
    'copy_allowed_text_blocked' => $extractor->extractPlainText($copyAllowedPdf) === '',
    'copy_allowed_policy' => $copyAllowedReport['permission_preflight']['policy'] ?? null,
    'copy_allowed_permission_hex' => $copyAllowedReport['permission_preflight']['permission_hex'] ?? null,
    'copy_allowed_native_text_now' => $copyAllowedReport['permission_preflight']['native_text_extraction_allowed_now'] ?? null,
    'unknown_permission_text_blocked' => $extractor->extractPlainText($unknownPermissionPdf) === '',
    'unknown_permission_policy' => $unknownPermissionReport['permission_preflight']['policy'] ?? null,
    'unknown_permission_source' => $unknownPermissionReport['permission_preflight']['source'] ?? null,
    'raw_key_material_exposed' => false,
    'executes_decryption' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text stays blocked until native decryption is available. Declared copy permissions are retained as review metadata for import decisions.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encryption-permission-preflight ' . htmlspecialchars(json_encode([
    'copy_allowed' => [
        'policy' => $copyAllowedReport['permission_preflight']['policy'] ?? null,
        'permission_hex' => $copyAllowedReport['permission_preflight']['permission_hex'] ?? null,
        'content_extraction_boundary' => $copyAllowedReport['permission_preflight']['content_extraction_boundary'] ?? null,
        'review_reasons' => $copyAllowedReport['review_reasons'],
    ],
    'unknown_permissions' => [
        'policy' => $unknownPermissionReport['permission_preflight']['policy'] ?? null,
        'content_extraction_boundary' => $unknownPermissionReport['permission_preflight']['content_extraction_boundary'] ?? null,
        'review_reasons' => $unknownPermissionReport['review_reasons'],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
