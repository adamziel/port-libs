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

$malformedStandardPdf = $buildEncryptedPdf(
    '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 /EncryptMetadata true >>',
    'Malformed permission encrypted text should not import'
);
$unsupportedHandlerPdf = $buildEncryptedPdf(
    '<< /Filter /PublicKey /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128 /P -44 /Recipients [<0011223344556677>] >>',
    'Unsupported handler encrypted text should not import'
);

$preflight = new PdfSecurityPreflight();
$extractor = new PdfTextExtractor();
$malformedReport = $preflight->analyze($malformedStandardPdf);
$unsupportedReport = $preflight->analyze($unsupportedHandlerPdf);
$malformedHandler = $malformedReport['permission_handler_review'] ?? [];
$unsupportedHandler = $unsupportedReport['permission_handler_review'] ?? [];

echo '<!-- markerpdf-encryption-permission-handler-review-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encryption-permission-handler-review-currentbase',
    'native_boundary' => 'Standard /P reserved-bit and unsupported security-handler permissions are review metadata before WordPress text import',
    'malformed_text_blocked' => $extractor->extractPlainText($malformedStandardPdf) === '',
    'malformed_policy' => $malformedReport['permission_preflight']['policy'] ?? null,
    'malformed_handler_status' => $malformedHandler['status'] ?? null,
    'malformed_permission_word_well_formed' => $malformedHandler['permission_word_well_formed'] ?? null,
    'malformed_reserved_violations' => $malformedHandler['reserved_bits']['violations'] ?? [],
    'unsupported_text_blocked' => $extractor->extractPlainText($unsupportedHandlerPdf) === '',
    'unsupported_policy' => $unsupportedReport['permission_preflight']['policy'] ?? null,
    'unsupported_handler' => $unsupportedHandler['handler'] ?? null,
    'unsupported_handler_status' => $unsupportedHandler['status'] ?? null,
    'unsupported_native_permission_review' => $unsupportedHandler['handler_supported_for_native_permission_review'] ?? null,
    'raw_key_material_exposed' => false,
    'recipient_bytes_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Handler Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked until native decryption exists. Permission words with malformed reserved bits or unsupported handlers are kept as review metadata, not import grants.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encryption-permission-handler-review ' . htmlspecialchars(json_encode([
    'malformed_standard' => [
        'policy' => $malformedReport['permission_preflight']['policy'] ?? null,
        'permission_hex' => $malformedReport['permission_preflight']['permission_hex'] ?? null,
        'status' => $malformedHandler['status'] ?? null,
        'reserved_bit_violations' => $malformedHandler['reserved_bits']['violations'] ?? [],
        'permission_bits_reliable' => $malformedReport['permission_preflight']['permission_bits_reliable'] ?? null,
    ],
    'unsupported_handler' => [
        'handler' => $unsupportedHandler['handler'] ?? null,
        'subfilter' => $unsupportedHandler['subfilter'] ?? null,
        'policy' => $unsupportedReport['permission_preflight']['policy'] ?? null,
        'status' => $unsupportedHandler['status'] ?? null,
        'permission_hex' => $unsupportedReport['permission_preflight']['permission_hex'] ?? null,
        'native_permission_review' => $unsupportedHandler['handler_supported_for_native_permission_review'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
