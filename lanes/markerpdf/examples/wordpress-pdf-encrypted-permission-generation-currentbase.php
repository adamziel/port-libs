<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress generation-selected encrypted text leak) Tj ET';
$staleOwner = str_repeat('S', 32);
$staleUser = str_repeat('T', 32);
$currentOwner = str_repeat('C', 32);
$currentUser = str_repeat('U', 32);

$pdf = "%PDF-1.7\n";
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
    '<< /Filter /Standard /V 4 /R 4 /Length 128'
        . ' /O <' . strtoupper(bin2hex($staleOwner)) . '>'
        . ' /U <' . strtoupper(bin2hex($staleUser)) . '>'
        . ' /P -64 /EncryptMetadata true >>'
);
$addObject(
    5,
    1,
    '<< /Filter /Standard /V 4 /R 4 /Length 128'
        . ' /O <' . strtoupper(bin2hex($currentOwner)) . '>'
        . ' /U <' . strtoupper(bin2hex($currentUser)) . '>'
        . ' /P -44 /EncryptMetadata true >>'
);

$xrefOffset = strlen($pdf);
$xrefRow = static fn (int $offset, int $generation, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$pdf .= "xref\n0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1][0], 0)
    . $xrefRow($offsets[2][0], 0)
    . $xrefRow($offsets[3][0], 0)
    . $xrefRow($offsets[4][0], 0)
    . $xrefRow($offsets[5][0], 0)
    . "trailer\n<< /Size 6 /Root 1 0 R /Encrypt 5 1 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : [];
$reviewEncryption = is_array($report['encryption'] ?? null) ? $report['encryption'] : [];
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text to stay blocked before decryption.');
}
if (($encryption['object_generation'] ?? null) !== 1 || ($reviewEncryption['object_generation'] ?? null) !== 1) {
    throw new RuntimeException('Expected trailer /Encrypt generation 1 to drive permission preflight.');
}
if (($permission['policy'] ?? null) !== 'copy_extract_allowed_after_decryption') {
    throw new RuntimeException('Expected current generation permission word to allow copy only after decryption.');
}
if (($permission['content_extraction_boundary'] ?? null) !== 'blocked_until_decryption_password_available') {
    throw new RuntimeException('Expected encrypted content to remain blocked until a password/decryption path is available.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $staleOwner)
    || str_contains($encoded, $staleUser)
    || str_contains($encoded, $currentOwner)
    || str_contains($encoded, $currentUser)
    || str_contains($encoded, strtoupper(bin2hex($staleOwner)))
    || str_contains($encoded, strtoupper(bin2hex($staleUser)))
    || str_contains($encoded, strtoupper(bin2hex($currentOwner)))
    || str_contains($encoded, strtoupper(bin2hex($currentUser)))
) {
    throw new RuntimeException('Expected encrypted payload and authentication bytes to stay out of review output.');
}

echo '<!-- markerpdf-encrypted-permission-generation-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-generation-currentbase',
    'native_boundary' => 'latest trailer Encrypt generation is selected before Standard permission preflight',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'encrypt_object_number' => $encryption['object_number'] ?? null,
    'encrypt_object_generation' => $encryption['object_generation'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF content remains blocked. The latest trailer encryption dictionary generation is reviewed before WordPress import and is not treated as permission to decrypt content.</p>\n";
echo "<!-- /wp:paragraph -->\n";
