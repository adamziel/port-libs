<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate trailer Encrypt encrypted leak) Tj ET';
$ownerValidation = str_repeat('D', 32);
$userValidation = str_repeat('E', 32);
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(
    5,
    0,
    '<< /Filter /Standard /V 4 /R 4 /Length 128'
        . ' /O ' . $hex($ownerValidation)
        . ' /U ' . $hex($userValidation)
        . ' /P -44 /EncryptMetadata true >>'
);

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . "trailer\n<< /Size 6 /Root 1 0 R /Encrypt null /Encrypt 5 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : [];
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)));

if ($plainText !== '') {
    throw new RuntimeException('Expected duplicate trailer /Encrypt content to stay blocked.');
}
if (($report['encrypted'] ?? null) !== true || ($report['import_decision'] ?? null) !== 'block_encrypted_content_review_security_metadata') {
    throw new RuntimeException('Expected duplicate trailer /Encrypt to fail closed as encrypted.');
}
if (($encryption['encrypt_operand_status'] ?? null) !== 'duplicate_encrypt_dictionary_entries_review') {
    throw new RuntimeException('Expected duplicate trailer /Encrypt declaration to be recorded as malformed review metadata.');
}
if (($permission['policy'] ?? null) !== 'permissions_unknown_blocked_without_decryption') {
    throw new RuntimeException('Expected duplicate trailer /Encrypt permissions to stay unknown and blocked.');
}
if ($rawMaterialExposed || (is_string($encoded) && str_contains($encoded, 'copy_extract_allowed_after_decryption'))) {
    throw new RuntimeException('Expected encrypted payload and duplicate-derived permission grant to stay out of review output.');
}

echo '<!-- markerpdf-encrypted-duplicate-trailer-encrypt-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-duplicate-trailer-encrypt-currentbase',
    'native_boundary' => 'duplicate selected trailer Encrypt entries fail closed before text or permission import',
    'plain_text_blocked' => $plainText === '',
    'encrypted' => $report['encrypted'] ?? null,
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'encryption_source' => $encryption['source'] ?? null,
    'duplicate_encrypt_dictionary_entries' => $encryption['duplicate_encrypt_dictionary_entries'] ?? null,
    'encrypt_dictionary_declared_entry_count' => $encryption['encrypt_dictionary_declared_entry_count'] ?? null,
    'encrypt_dictionary_entry_statuses' => $encryption['encrypt_dictionary_entry_statuses'] ?? [],
    'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'duplicate_permission_grant_suppressed' => is_string($encoded) && !str_contains($encoded, 'copy_extract_allowed_after_decryption'),
    'raw_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF content remains blocked when the selected trailer declares duplicate /Encrypt entries. WordPress import records sanitized security metadata and does not trust duplicate-derived permission grants.</p>\n";
echo "<!-- /wp:paragraph -->\n";
