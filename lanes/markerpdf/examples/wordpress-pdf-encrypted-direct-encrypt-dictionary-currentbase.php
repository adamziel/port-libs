<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress direct Encrypt dictionary leak) Tj ET';
$ownerValidation = str_repeat('W', 32);
$userValidation = str_repeat('P', 32);
$ownerHex = strtoupper(bin2hex($ownerValidation));
$userHex = strtoupper(bin2hex($userValidation));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt << /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <{$ownerHex}> /U <{$userHex}> /P -44 /EncryptMetadata true >> >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$encryption = is_array($report['encryption'] ?? null) ? $report['encryption'] : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected direct trailer Encrypt dictionary fixture text to stay blocked.');
}

if (($encryption['encrypt_dictionary_resolved'] ?? null) !== true
    || ($encryption['encrypt_operand_shape'] ?? null) !== 'dictionary'
    || ($encryption['encrypt_operand_status'] ?? null) !== 'encrypt_dictionary_direct_dictionary_resolved'
) {
    throw new RuntimeException('Expected direct trailer Encrypt dictionary to be resolved and reported precisely.');
}

if (($permission['policy'] ?? null) !== 'copy_extract_allowed_after_decryption') {
    throw new RuntimeException('Expected Standard permission bits to remain reviewable but blocked until decryption.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, $ownerHex)
    || str_contains($encoded, $userHex)
) {
    throw new RuntimeException('Expected encrypted text and raw Standard authentication bytes to stay redacted.');
}

echo '<!-- markerpdf-encrypted-direct-encrypt-dictionary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-direct-encrypt-dictionary-currentbase',
    'native_boundary' => 'Trailer /Encrypt direct dictionaries are resolved for Standard permission preflight while encrypted text remains blocked',
    'encrypted_text_blocked' => $plainText === '',
    'encrypt_dictionary_resolved' => $encryption['encrypt_dictionary_resolved'] ?? null,
    'encrypt_operand_shape' => $encryption['encrypt_operand_shape'] ?? null,
    'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
    'malformed_encrypt_dictionary' => $encryption['malformed_encrypt_dictionary'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    'raw_key_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight resolves the direct trailer encryption dictionary, records the Standard permission word, and waits for a future password/decryption path before import.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-direct-encrypt-dictionary-preflight ' . htmlspecialchars(json_encode([
    'encryption' => [
        'source' => $encryption['source'] ?? null,
        'encrypt_dictionary_resolved' => $encryption['encrypt_dictionary_resolved'] ?? null,
        'encrypt_operand_shape' => $encryption['encrypt_operand_shape'] ?? null,
        'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
    ],
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_hex' => $permission['permission_hex'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
