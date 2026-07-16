<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$buildMalformedLegacyPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (WordPress malformed auth encrypted leak) Tj ET';
    $ownerValidation = 'WP_OWNER_SHORT_SHOULD_NOT_LEAK';
    $userValidation = 'WP_USER_SHORT_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $ownerValidation, $userValidation];
};

$buildMissingPermsPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (WordPress missing Perms encrypted leak) Tj ET';
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);

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
        . " /P -44 /EncryptMetadata true"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey];
};

[$malformedPdf, $malformedOwner, $malformedUser] = $buildMalformedLegacyPdf();
[$missingPermsPdf, $r6Owner, $r6User, $r6OwnerKey, $r6UserKey] = $buildMissingPermsPdf();

$preflight = new PdfSecurityPreflight();
$extractor = new PdfTextExtractor();
$malformedReport = $preflight->analyze($malformedPdf);
$missingPermsReport = $preflight->analyze($missingPermsPdf);
$malformedMaterial = $malformedReport['permission_preflight']['standard_authentication_material_review'] ?? [];
$missingPermsMaterial = $missingPermsReport['permission_preflight']['standard_authentication_material_review'] ?? [];
$encoded = json_encode([$malformedReport, $missingPermsReport], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-encrypted-permissions-auth-boundary-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permissions-auth-boundary-currentbase',
    'native_boundary' => 'Standard permission flags remain review metadata while malformed authentication material blocks password-ready import review',
    'malformed_text_blocked' => $extractor->extractPlainText($malformedPdf) === '',
    'malformed_policy' => $malformedReport['permission_preflight']['policy'] ?? null,
    'malformed_auth_status' => $malformedMaterial['status'] ?? null,
    'malformed_ready_for_password_attempt' => $malformedMaterial['ready_for_password_attempt'] ?? null,
    'malformed_length_mismatch_entries' => $malformedMaterial['length_mismatch_required_entries'] ?? [],
    'missing_perms_text_blocked' => $extractor->extractPlainText($missingPermsPdf) === '',
    'missing_perms_policy' => $missingPermsReport['permission_preflight']['policy'] ?? null,
    'missing_perms_auth_status' => $missingPermsMaterial['status'] ?? null,
    'missing_perms_ready_for_password_attempt' => $missingPermsMaterial['ready_for_password_attempt'] ?? null,
    'missing_perms_digest_status' => $missingPermsMaterial['permission_digest_status'] ?? null,
    'raw_auth_material_exposed' => is_string($encoded)
        && (
            str_contains($encoded, $malformedOwner)
            || str_contains($encoded, $malformedUser)
            || str_contains($encoded, $r6Owner)
            || str_contains($encoded, $r6User)
            || str_contains($encoded, $r6OwnerKey)
            || str_contains($encoded, $r6UserKey)
        ),
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Authentication Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Permission flags are retained for review, and malformed Standard authentication material is surfaced before any future password prompt or native decryption path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permissions-auth-boundary ' . htmlspecialchars(json_encode([
    'malformed_legacy_standard' => [
        'policy' => $malformedReport['permission_preflight']['policy'] ?? null,
        'permission_hex' => $malformedReport['permission_preflight']['permission_hex'] ?? null,
        'auth_material_status' => $malformedMaterial['status'] ?? null,
        'ready_for_password_attempt' => $malformedMaterial['ready_for_password_attempt'] ?? null,
        'length_mismatch_required_entries' => $malformedMaterial['length_mismatch_required_entries'] ?? [],
    ],
    'missing_revision_six_perms' => [
        'policy' => $missingPermsReport['permission_preflight']['policy'] ?? null,
        'permission_hex' => $missingPermsReport['permission_preflight']['permission_hex'] ?? null,
        'auth_material_status' => $missingPermsMaterial['status'] ?? null,
        'ready_for_password_attempt' => $missingPermsMaterial['ready_for_password_attempt'] ?? null,
        'permission_digest_status' => $missingPermsMaterial['permission_digest_status'] ?? null,
    ],
    'blocked_operations' => $missingPermsReport['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
