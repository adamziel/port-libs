<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress Standard handler SubFilter encrypted text should not import) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);
$ownerHex = strtoupper(bin2hex($ownerValidation));
$userHex = strtoupper(bin2hex($userValidation));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /SubFilter /adbe.pkcs7.s5 /V 4 /R 4 /Length 128"
    . " /O <{$ownerHex}> /U <{$userHex}> /P -44 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, $ownerHex)
        || str_contains($encoded, $userHex)
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted Standard SubFilter fixture text to stay blocked.');
}
if (($permission['source'] ?? null) !== 'security_handler_subfilter_declaration_malformed') {
    throw new RuntimeException('Expected Standard handler with SubFilter to fail closed before trusting /P.');
}
if (($permission['security_handler_standard_subfilter_incompatible'] ?? null) !== true) {
    throw new RuntimeException('Expected Standard/SubFilter incompatibility to be recorded.');
}
if (($permission['copy_or_extract_allowed'] ?? null) !== null) {
    throw new RuntimeException('Expected copy permission bits to be withheld for incompatible security handler.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content and Standard authentication bytes to stay out of review output.');
}

echo '<!-- markerpdf-encrypted-standard-subfilter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-standard-subfilter-boundary-currentbase',
    'native_boundary' => 'Standard handler declaring public-key SubFilter fails closed before permission-bit trust',
    'text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'subfilter_declaration_status' => $permission['security_handler_subfilter_declaration_status'] ?? null,
    'standard_subfilter_incompatible' => $permission['security_handler_standard_subfilter_incompatible'] ?? null,
    'copy_permission_withheld' => ($permission['copy_or_extract_allowed'] ?? null) === null,
    'raw_material_exposed' => false,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $preflight['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Standard SubFilter Boundary</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked when a Standard security handler declares a public-key SubFilter. The importer records the incompatible security-handler boundary and withholds copy-permission trust until decryption support can authenticate the document.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-standard-subfilter-boundary ' . htmlspecialchars(json_encode([
    'permission_policy' => $permission['policy'] ?? null,
    'content_boundary' => $permission['content_extraction_boundary'] ?? null,
    'subfilter_declaration' => [
        'status' => $permission['security_handler_subfilter_declaration_status'] ?? null,
        'fail_closed' => $permission['security_handler_subfilter_declaration_fail_closed'] ?? null,
        'standard_incompatible' => $permission['security_handler_standard_subfilter_incompatible'] ?? null,
        'subfilter_names' => $permission['security_handler_subfilter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
