<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale encrypted trailer leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current trailer clear page) Tj T* (Encrypt null wins) Tj ET';
$currentPermanentId = 'Current Trailer Permanent';
$currentChangingId = 'Current Trailer Changing';
$stalePermanentId = 'Stale Trailer Permanent';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Title (Stale Encrypted Info Title) /Producer (Stale Producer) >>');
$addObject(30, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata true >>');
$addObject(31, 0, '<< /Type /XRef /Size 32 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R /ID [(Stale\040XRef\040Permanent) <' . strtoupper(bin2hex('Stale XRef Changing')) . '>] /W [0 0 0] /Length 0 >>' . "\nstream\n\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 7\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . $xrefRow($offsets['6:0'])
    . "30 2\n"
    . $xrefRow($offsets['30:0'])
    . $xrefRow($offsets['31:0'])
    . "trailer\n<< /Size 32 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R /ID [(Stale\\040Trailer\\040Permanent) <" . strtoupper(bin2hex('Stale Trailer Changing')) . ">] >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
$addObject(4, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 1, '<< /Title (Current Clear Info Title) /Author (Current Reviewer) /Producer (Current Producer) >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n"
    . $xrefRow(0, 65535, 'f')
    . "1 4\n"
    . $xrefRow($offsets['1:1'], 1)
    . $xrefRow($offsets['2:1'], 1)
    . $xrefRow($offsets['3:1'], 1)
    . $xrefRow($offsets['4:1'], 1)
    . "6 1\n"
    . $xrefRow($offsets['6:1'], 1)
    . "trailer\n<< /Size 32 /Root 1 1 R /Info 6 1 R /Encrypt null /ID [(Current\\040Trailer\\040Permanent) <" . strtoupper(bin2hex($currentChangingId)) . ">] /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = $extractor->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedPreflight = json_encode($preflight, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-trailer-encrypt-id-precedence-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_decryption' => false,
    'native_boundary' => 'latest startxref trailer /Encrypt null and /ID override stale encrypted Prev trailer metadata before WordPress import',
    'source' => $metadata['source'],
    'encrypted' => $preflight['encrypted'],
    'text_policy' => $preflight['text_extraction_policy'],
    'current_text_imported' => str_contains($plainText, 'Current trailer clear page'),
    'stale_text_excluded' => !str_contains($plainText, 'Stale encrypted trailer leak'),
    'current_id_selected' => ($metadata['trailer_ids']['permanent']['hex'] ?? null) === bin2hex($currentPermanentId),
    'changed_since_creation' => $metadata['trailer_ids']['changed_since_creation'] ?? null,
    'stale_id_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, $stalePermanentId),
    'stale_encryption_suppressed' => $preflight['permission_preflight']['source'] === 'unencrypted_document',
    'raw_key_material_exposed' => is_string($encodedPreflight) && (str_contains($encodedPreflight, 'DEADBEEF') || str_contains($encodedPreflight, 'CAFEFEED')),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-fingerprint ' . htmlspecialchars(json_encode([
    'fingerprint' => $metadata['document_fingerprint'] ?? null,
    'fingerprint_source' => $metadata['document_fingerprint_source'] ?? null,
    'permanent_id_hex' => $metadata['trailer_ids']['permanent']['hex'] ?? null,
    'changing_id_hex' => $metadata['trailer_ids']['changing']['hex'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
