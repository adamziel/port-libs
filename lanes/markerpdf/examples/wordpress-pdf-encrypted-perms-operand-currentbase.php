<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress malformed Perms operand encrypted leak) Tj ET';
$ownerValidation = str_repeat('O', 48);
$userValidation = str_repeat('U', 48);
$ownerEncryptionKey = str_repeat('E', 32);
$userEncryptionKey = str_repeat('K', 32);
$rawPermsBytes = 'WP_ARRAY_PERMS_BYTES_SHOULD_NOT_LEAK';

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
    . " /P -44 /EncryptMetadata true /Perms [" . $hex($rawPermsBytes) . ']'
    . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
    . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$material = is_array($permission['standard_authentication_material_review'] ?? null)
    ? $permission['standard_authentication_material_review']
    : [];
$digest = is_array($permission['standard_authentication_review']['permission_digest'] ?? null)
    ? $permission['standard_authentication_review']['permission_digest']
    : [];
$trust = is_array($permission['permission_authentication_trust_review'] ?? null)
    ? $permission['permission_authentication_trust_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawAuthMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, $ownerEncryptionKey)
        || str_contains($encoded, $userEncryptionKey)
        || str_contains($encoded, $rawPermsBytes)
        || str_contains($encoded, strtoupper(bin2hex($rawPermsBytes)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected malformed encrypted permission digest fixture text to stay blocked.');
}

if (($digest['status'] ?? null) !== 'permission_digest_composite_operand_review') {
    throw new RuntimeException('Expected composite /Perms operand to be classified before password readiness.');
}

if (($material['ready_for_password_attempt'] ?? null) !== false || $rawAuthMaterialExposed) {
    throw new RuntimeException('Expected malformed /Perms operand to block password readiness without exposing raw bytes.');
}

echo '<!-- markerpdf-encrypted-perms-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-perms-operand-currentbase',
    'native_boundary' => 'revision six Standard /Perms must be a scalar string ciphertext before password-ready review',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'permission_digest_status' => $digest['status'] ?? null,
    'permission_digest_selected_entry_operand_shape' => $digest['selected_entry_operand_shape'] ?? null,
    'auth_material_status' => $material['status'] ?? null,
    'ready_for_password_attempt' => $material['ready_for_password_attempt'] ?? null,
    'permission_authentication_status' => $trust['status'] ?? null,
    'raw_auth_material_exposed' => $rawAuthMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF text remains blocked because the revision six Standard permission digest is not scalar ciphertext. WordPress import keeps the malformed operand as sanitized review metadata and does not attempt password validation or decryption.</p>\n";
echo "<!-- /wp:paragraph -->\n";
