<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress trailing auth operand encrypted text leak) Tj ET';
$ownerValidation = str_repeat('O', 48);
$userValidation = str_repeat('U', 48);
$ownerEncryptionKey = str_repeat('E', 32);
$userEncryptionKey = str_repeat('K', 32);
$permissionDigest = str_repeat('P', 16);
$shadowAuth = str_repeat('S', 48);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
    . " /O " . $hex($ownerValidation) . " 30 0 R"
    . " /U " . $hex($userValidation)
    . " /OE " . $hex($ownerEncryptionKey)
    . " /UE " . $hex($userEncryptionKey)
    . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
    . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
    . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
    . "30 0 obj\n" . $hex($shadowAuth) . "\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$auth = is_array($permission['standard_authentication_review'] ?? null) ? $permission['standard_authentication_review'] : [];
$material = is_array($permission['standard_authentication_material_review'] ?? null) ? $permission['standard_authentication_material_review'] : [];
$trust = is_array($permission['permission_authentication_trust_review'] ?? null) ? $permission['permission_authentication_trust_review'] : [];
$ownerEntry = is_array($auth['entries']['owner_validation'] ?? null) ? $auth['entries']['owner_validation'] : [];
$ownerReview = is_array($ownerEntry['entry_reviews'][0] ?? null) ? $ownerEntry['entry_reviews'][0] : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawAuthMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, $ownerEncryptionKey)
        || str_contains($encoded, $userEncryptionKey)
        || str_contains($encoded, $permissionDigest)
        || str_contains($encoded, $shadowAuth)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($shadowAuth)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted trailing-auth fixture to keep text blocked.');
}
if (($ownerEntry['status'] ?? null) !== 'authentication_entry_trailing_operand_review') {
    throw new RuntimeException('Expected trailing owner authentication operand to fail closed.');
}
if (($material['ready_for_password_attempt'] ?? null) !== false || $rawAuthMaterialExposed) {
    throw new RuntimeException('Expected malformed authentication material to block password readiness without raw-byte exposure.');
}

echo '<!-- markerpdf-encrypted-auth-trailing-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-auth-trailing-operand-currentbase',
    'native_boundary' => 'Standard authentication strings with trailing operands are review-only malformed metadata and not ready for password attempts',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'standard_authentication_ready_for_password_attempt' => $permission['standard_authentication_ready_for_password_attempt'] ?? null,
    'owner_auth_status' => $ownerEntry['status'] ?? null,
    'owner_auth_bytes_resolved' => $ownerEntry['bytes_resolved'] ?? null,
    'trailing_operand_shape' => $ownerReview['trailing_operand_shape'] ?? null,
    'trailing_operand_preview' => $ownerReview['trailing_operand_preview'] ?? null,
    'permission_authentication_status' => $trust['status'] ?? null,
    'raw_auth_material_exposed' => $rawAuthMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF text remains blocked because the Standard security handler declares authentication material with a trailing operand. WordPress import records sanitized security metadata and does not attempt password validation, decryption, or permission enforcement.</p>\n";
echo "<!-- /wp:paragraph -->\n";
