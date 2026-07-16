<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$buildPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (WordPress malformed permission trust encrypted leak) Tj ET';
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 6 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
        . " >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

[$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $buildPdf();
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$trust = is_array($permission['permission_authentication_trust_review'] ?? null)
    ? $permission['permission_authentication_trust_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, $ownerEncryptionKey)
        || str_contains($encoded, $userEncryptionKey)
        || str_contains($encoded, $permissionDigest)
        || str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected malformed encrypted permission trust text to stay blocked.');
}
if (($permission['standard_authentication_ready_for_password_attempt'] ?? null) !== true) {
    throw new RuntimeException('Expected raw Standard authentication material to remain review-ready.');
}
if (($permission['permission_bits_authentication_material_usable'] ?? null) !== false) {
    throw new RuntimeException('Expected malformed Standard parameters to keep permission trust material unusable.');
}
if (($permission['permission_bits_trust_blocker'] ?? null) !== 'decoded_permission_bits_not_syntactically_reliable') {
    throw new RuntimeException('Expected malformed permission bits to own the permission trust blocker.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content and authentication material to remain redacted.');
}

$summary = [
    'scenario' => 'wordpress-pdf-encrypted-permission-trust-boundary-currentbase',
    'text_blocked' => $plainText === '',
    'policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'standard_authentication_ready_for_password_attempt' => $permission['standard_authentication_ready_for_password_attempt'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'permission_bits_authentication_material_usable' => $permission['permission_bits_authentication_material_usable'] ?? null,
    'permission_bits_trust_requires_password_validation' => $permission['permission_bits_trust_requires_password_validation'] ?? null,
    'permission_bits_trust_blocker' => $permission['permission_bits_trust_blocker'] ?? null,
    'permission_authentication_status' => $permission['permission_authentication_status'] ?? null,
    'trust_boundary' => $trust['trust_boundary'] ?? null,
    'raw_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-encrypted-permission-trust-boundary-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Trust Boundary</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Complete Standard authentication bytes are recorded as password-attempt material, while malformed permission parameters keep decoded permission bits unusable for import trust.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-trust-boundary ' . htmlspecialchars(json_encode([
    'standard_authentication_ready_for_password_attempt' => $summary['standard_authentication_ready_for_password_attempt'],
    'permission_bits_authentication_material_usable' => $summary['permission_bits_authentication_material_usable'],
    'permission_bits_trust_blocker' => $summary['permission_bits_trust_blocker'],
    'trust_boundary' => $summary['trust_boundary'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
