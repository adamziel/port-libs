<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ownerValidation = str_repeat('O', 48);
$userValidation = str_repeat('U', 48);
$ownerEncryptionKey = str_repeat('E', 32);
$userEncryptionKey = str_repeat('K', 32);
$permissionDigest = str_repeat('P', 16);
$content = 'BT /F1 12 Tf 72 720 Td (WordPress Standard auth material should not import) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
    . " /O <" . strtoupper(bin2hex($ownerValidation)) . ">"
    . " /U <" . strtoupper(bin2hex($userValidation)) . ">"
    . " /OE <" . strtoupper(bin2hex($ownerEncryptionKey)) . ">"
    . " /UE <" . strtoupper(bin2hex($userEncryptionKey)) . ">"
    . " /P -44 /EncryptMetadata true"
    . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
    . " /StmF /StdCF /StrF /StdCF /Perms <" . strtoupper(bin2hex($permissionDigest)) . "> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$authReview = $report['standard_authentication_review'] ?? [];
$entries = is_array($authReview['entries'] ?? null) ? $authReview['entries'] : [];
$permissionDigestReview = is_array($authReview['permission_digest'] ?? null) ? $authReview['permission_digest'] : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawAuthMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
        || str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
    );

echo '<!-- markerpdf-security-certify-permission-digest-auth-review-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-certify-permission-digest-auth-review-currentbase',
    'native_boundary' => 'Standard revision 6 authentication entries and encrypted permission digest are review metadata before WordPress text import',
    'text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'permission_policy' => $report['permission_preflight']['policy'] ?? null,
    'revision_label' => $authReview['revision_label'] ?? null,
    'auth_events' => $authReview['auth_events'] ?? [],
    'credential_entries_present' => $authReview['credential_entries_present'] ?? [],
    'owner_validation_bytes' => $entries['owner_validation']['bytes'] ?? null,
    'user_validation_bytes' => $entries['user_validation']['bytes'] ?? null,
    'owner_encryption_key_bytes' => $entries['owner_encryption_key']['bytes'] ?? null,
    'user_encryption_key_bytes' => $entries['user_encryption_key']['bytes'] ?? null,
    'permission_digest_status' => $permissionDigestReview['status'] ?? null,
    'permission_digest_bytes' => $permissionDigestReview['bytes'] ?? null,
    'raw_auth_material_exposed' => $rawAuthMaterialExposed,
    'password_validation_performed' => $authReview['password_validation_performed'] ?? null,
    'permissions_authenticated' => $authReview['permissions_authenticated'] ?? null,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $authReview['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Authentication Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Standard security-handler authentication entries and the encrypted permission digest are recorded only as sanitized review metadata.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:standard-security-auth-review ' . htmlspecialchars(json_encode([
    'revision' => $authReview['revision'] ?? null,
    'revision_label' => $authReview['revision_label'] ?? null,
    'auth_events' => $authReview['auth_events'] ?? [],
    'expected_lengths' => $authReview['expected_lengths'] ?? [],
    'entries' => [
        'owner_validation' => [
            'bytes' => $entries['owner_validation']['bytes'] ?? null,
            'sha256' => $entries['owner_validation']['sha256'] ?? null,
            'status' => $entries['owner_validation']['status'] ?? null,
        ],
        'user_validation' => [
            'bytes' => $entries['user_validation']['bytes'] ?? null,
            'sha256' => $entries['user_validation']['sha256'] ?? null,
            'status' => $entries['user_validation']['status'] ?? null,
        ],
    ],
    'permission_digest' => [
        'bytes' => $permissionDigestReview['bytes'] ?? null,
        'sha256' => $permissionDigestReview['sha256'] ?? null,
        'status' => $permissionDigestReview['status'] ?? null,
    ],
    'password_validation_performed' => false,
    'permissions_authenticated' => false,
    'executes_decryption' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
