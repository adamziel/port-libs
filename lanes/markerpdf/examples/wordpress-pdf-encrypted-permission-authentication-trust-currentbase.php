<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$buildRevisionSixPdf = static function (bool $includePerms, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} WordPress permission trust encrypted leak) Tj ET";
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $perms = $includePerms ? ' /Perms ' . $hex($permissionDigest) : '';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true{$perms}"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

[$completePdf, $completeContent, $completeOwner, $completeUser, $completeOwnerKey, $completeUserKey, $completePerms] = $buildRevisionSixPdf(true, 'COMPLETE_R6');
[$missingPdf, $missingContent, $missingOwner, $missingUser, $missingOwnerKey, $missingUserKey, $missingPerms] = $buildRevisionSixPdf(false, 'MISSING_R6_PERMS');

$preflight = new PdfSecurityPreflight();
$extractor = new PdfTextExtractor();
$completeReport = $preflight->analyze($completePdf);
$missingReport = $preflight->analyze($missingPdf);
$completeTrust = $completeReport['permission_preflight']['permission_authentication_trust_review'] ?? [];
$missingTrust = $missingReport['permission_preflight']['permission_authentication_trust_review'] ?? [];
$encoded = json_encode([$completeReport, $missingReport], JSON_UNESCAPED_SLASHES);
$rawAuthMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $completeContent)
        || str_contains($encoded, $missingContent)
        || str_contains($encoded, $completeOwner)
        || str_contains($encoded, $completeUser)
        || str_contains($encoded, $completeOwnerKey)
        || str_contains($encoded, $completeUserKey)
        || str_contains($encoded, $completePerms)
        || str_contains($encoded, strtoupper(bin2hex($completePerms)))
        || str_contains($encoded, $missingOwner)
        || str_contains($encoded, $missingUser)
        || str_contains($encoded, $missingOwnerKey)
        || str_contains($encoded, $missingUserKey)
        || str_contains($encoded, $missingPerms)
        || str_contains($encoded, strtoupper(bin2hex($missingPerms)))
    );

if ($extractor->extractPlainText($completePdf) !== '' || $extractor->extractPlainText($missingPdf) !== '') {
    throw new RuntimeException('Expected encrypted permission trust fixtures to keep text extraction blocked.');
}

if (
    ($completeReport['permission_preflight']['permission_bits_authenticated'] ?? null) !== false
    || ($completeReport['permission_preflight']['authenticated_permission_bits_reliable'] ?? null) !== false
    || ($completeTrust['status'] ?? null) !== 'permission_bits_decoded_but_unauthenticated_ready_for_password_attempt'
    || ($missingTrust['status'] ?? null) !== 'required_permission_digest_missing_before_permission_authentication'
    || $rawAuthMaterialExposed
) {
    throw new RuntimeException('Expected encrypted permission trust review to remain unauthenticated and payload-safe.');
}

echo '<!-- markerpdf-encrypted-permission-authentication-trust-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-authentication-trust-currentbase',
    'native_boundary' => 'Standard permission bits are decoded for review but remain unauthenticated until password validation, /Perms authentication, and decryption exist',
    'complete_text_blocked' => $extractor->extractPlainText($completePdf) === '',
    'complete_permission_bits_reliable' => $completeReport['permission_preflight']['permission_bits_reliable'] ?? null,
    'complete_permission_bits_authenticated' => $completeReport['permission_preflight']['permission_bits_authenticated'] ?? null,
    'complete_authenticated_permission_bits_reliable' => $completeReport['permission_preflight']['authenticated_permission_bits_reliable'] ?? null,
    'complete_authentication_status' => $completeTrust['status'] ?? null,
    'complete_trust_boundary' => $completeTrust['trust_boundary'] ?? null,
    'missing_perms_text_blocked' => $extractor->extractPlainText($missingPdf) === '',
    'missing_perms_authentication_status' => $missingTrust['status'] ?? null,
    'missing_perms_digest_status' => $missingTrust['permission_digest_status'] ?? null,
    'raw_auth_material_exposed' => $rawAuthMaterialExposed,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Trust</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Permission flags are decoded for review, but WordPress import treats them as unauthenticated until a native password-validation and decryption path can authenticate the encrypted permission digest.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-authentication-trust ' . htmlspecialchars(json_encode([
    'complete_revision_six' => [
        'policy' => $completeReport['permission_preflight']['policy'] ?? null,
        'permission_hex' => $completeReport['permission_preflight']['permission_hex'] ?? null,
        'permission_bits_reliable' => $completeReport['permission_preflight']['permission_bits_reliable'] ?? null,
        'permission_bits_authenticated' => $completeReport['permission_preflight']['permission_bits_authenticated'] ?? null,
        'authenticated_permission_bits_reliable' => $completeReport['permission_preflight']['authenticated_permission_bits_reliable'] ?? null,
        'authentication_status' => $completeTrust['status'] ?? null,
        'trust_boundary' => $completeTrust['trust_boundary'] ?? null,
    ],
    'missing_revision_six_perms' => [
        'policy' => $missingReport['permission_preflight']['policy'] ?? null,
        'permission_hex' => $missingReport['permission_preflight']['permission_hex'] ?? null,
        'permission_digest_status' => $missingTrust['permission_digest_status'] ?? null,
        'authentication_status' => $missingTrust['status'] ?? null,
    ],
    'security' => [
        'executes_decryption' => false,
        'executes_permission_enforcement' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
