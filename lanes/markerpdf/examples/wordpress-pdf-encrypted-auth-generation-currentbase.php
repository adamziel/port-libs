<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress stale-generation auth material encrypted text leak) Tj ET';
$staleOwnerValidation = str_repeat('A', 48);
$staleUserValidation = str_repeat('B', 48);
$staleOwnerEncryptionKey = str_repeat('C', 32);
$staleUserEncryptionKey = str_repeat('D', 32);
$stalePermissionDigest = str_repeat('E', 16);
$currentOwnerValidation = str_repeat('O', 48);
$currentUserValidation = str_repeat('U', 48);
$currentOwnerEncryptionKey = str_repeat('K', 32);
$currentUserEncryptionKey = str_repeat('L', 32);
$currentPermissionDigest = str_repeat('P', 16);

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber][$generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(
    5,
    0,
    '<< /Filter /Standard /V 5 /R 6 /Length 256'
        . ' /O 20 0 R /U 21 0 R /OE 22 0 R /UE 23 0 R'
        . ' /P -44 /EncryptMetadata true /Perms 24 0 R'
        . ' /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>'
        . ' /StmF /StdCF /StrF /StdCF >>'
);
$addObject(20, 0, $hex($staleOwnerValidation));
$addObject(20, 1, $hex($currentOwnerValidation));
$addObject(21, 0, $hex($staleUserValidation));
$addObject(21, 1, $hex($currentUserValidation));
$addObject(22, 0, $hex($staleOwnerEncryptionKey));
$addObject(22, 1, $hex($currentOwnerEncryptionKey));
$addObject(23, 0, $hex($staleUserEncryptionKey));
$addObject(23, 1, $hex($currentUserEncryptionKey));
$addObject(24, 0, $hex($stalePermissionDigest));
$addObject(24, 1, $hex($currentPermissionDigest));

$xrefOffset = strlen($pdf);
$row = static fn (int $offset, int $generation, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$pdf .= "xref\n0 25\n";
for ($objectNumber = 0; $objectNumber <= 24; $objectNumber++) {
    if ($objectNumber === 0) {
        $pdf .= $row(0, 65535, 'f');
        continue;
    }

    if (isset($offsets[$objectNumber][1])) {
        $pdf .= $row($offsets[$objectNumber][1], 1);
        continue;
    }

    if (isset($offsets[$objectNumber][0])) {
        $pdf .= $row($offsets[$objectNumber][0], 0);
        continue;
    }

    $pdf .= $row(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 25 /Root 1 0 R /Encrypt 5 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$material = is_array($permission['standard_authentication_material_review'] ?? null)
    ? $permission['standard_authentication_material_review']
    : [];
$auth = is_array($permission['standard_authentication_review'] ?? null)
    ? $permission['standard_authentication_review']
    : [];
$digest = is_array($auth['permission_digest'] ?? null) ? $auth['permission_digest'] : [];
$trust = is_array($permission['permission_authentication_trust_review'] ?? null)
    ? $permission['permission_authentication_trust_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawAuthMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $staleOwnerValidation)
        || str_contains($encoded, $staleUserValidation)
        || str_contains($encoded, $staleOwnerEncryptionKey)
        || str_contains($encoded, $staleUserEncryptionKey)
        || str_contains($encoded, $stalePermissionDigest)
        || str_contains($encoded, $currentOwnerValidation)
        || str_contains($encoded, $currentUserValidation)
        || str_contains($encoded, $currentOwnerEncryptionKey)
        || str_contains($encoded, $currentUserEncryptionKey)
        || str_contains($encoded, $currentPermissionDigest)
        || str_contains($encoded, strtoupper(bin2hex($staleOwnerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($currentPermissionDigest)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted fixture text to stay blocked before decryption.');
}
if (($material['ready_for_password_attempt'] ?? null) !== false) {
    throw new RuntimeException('Expected stale-generation authentication material to block password readiness.');
}
if (($digest['status'] ?? null) !== 'permission_digest_unresolved') {
    throw new RuntimeException('Expected stale-generation /Perms to remain unresolved.');
}
if ($rawAuthMaterialExposed) {
    throw new RuntimeException('Expected stale/current authentication bytes to remain redacted.');
}

echo '<!-- markerpdf-encrypted-auth-generation-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-auth-generation-currentbase',
    'native_boundary' => 'Standard authentication and permission-digest string references are generation-exact before password-readiness review',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'standard_authentication_ready_for_password_attempt' => $permission['standard_authentication_ready_for_password_attempt'] ?? null,
    'unresolved_required_entries' => $material['unresolved_required_entries'] ?? [],
    'permission_digest_status' => $digest['status'] ?? null,
    'permission_authentication_status' => $trust['status'] ?? null,
    'raw_auth_material_exposed' => $rawAuthMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF text remains blocked. Standard authentication references point at stale object generations, so WordPress import records sanitized security review metadata and does not attempt password validation or decryption.</p>\n";
echo "<!-- /wp:paragraph -->\n";
