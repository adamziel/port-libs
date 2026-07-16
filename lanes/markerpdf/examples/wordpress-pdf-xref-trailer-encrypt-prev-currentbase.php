<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Previous encrypted page leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current inherited encrypted text leak) Tj ET';
$currentPermanentId = 'Current Prev Permanent';
$currentChangingId = 'Current Prev Changing';
$previousPermanentId = 'Previous Encrypted Permanent';
$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Prev Encrypt XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Current xref trailer inherits encryption from Prev</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-02T18:18:06Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress xref trailer Encrypt Prev XMP fixture.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $offset, int $generation = 0): string => chr(1) . pack('N', $offset) . chr($generation);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Title (Previous Encrypted Info Title) /Author (Previous Author) /Producer (Previous Producer) >>');
$addObject(30, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata false >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 7\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . $xrefTableRow($offsets['4:0'])
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow($offsets['6:0'])
    . "30 1\n"
    . $xrefTableRow($offsets['30:0'])
    . "trailer\n<< /Size 41 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R /ID [(Previous\\040Encrypted\\040Permanent) <" . strtoupper(bin2hex('Previous Encrypted Changing')) . ">] >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(11, 0, '<< /Type /Catalog /Pages 12 0 R /Metadata 15 0 R >>');
$addObject(12, 0, '<< /Type /Pages /Kids [13 0 R] /Count 1 >>');
$addObject(13, 0, '<< /Type /Page /Parent 12 0 R /Resources << /Font << /F1 16 0 R >> >> /Contents 14 0 R >>');
$addObject(14, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(15, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream");
$addObject(16, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$rows = '';
foreach ([11, 12, 13, 14, 15, 16] as $objectNumber) {
    $rows .= $xrefStreamRow($offsets[$objectNumber . ':0']);
}
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress xref trailer Encrypt Prev rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 11 0 R /Prev ' . $previousXrefOffset
    . ' /ID [(Current\040Prev\040Permanent) <' . strtoupper(bin2hex($currentChangingId)) . '>] /Index [11 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedPreflight = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$policy = $metadata['encryption']['metadata_source_policy'] ?? [];

echo '<!-- markerpdf-xref-trailer-encrypt-prev-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_decryption' => false,
    'native_boundary' => 'current xref-stream trailer inherits /Encrypt through /Prev and blocks visible text before WordPress import',
    'source' => $metadata['source'],
    'encryption_source' => $metadata['encryption']['source'] ?? null,
    'encrypted' => $preflight['encrypted'],
    'text_policy' => $preflight['text_extraction_policy'],
    'xmp_preserved' => in_array('xmp', $policy['preserved_sources'] ?? [], true),
    'current_id_selected' => ($metadata['trailer_ids']['permanent']['hex'] ?? null) === bin2hex($currentPermanentId),
    'previous_id_suppressed' => is_string($encodedMetadata) && !str_contains($encodedMetadata, $previousPermanentId),
    'encrypted_text_blocked' => $plainText === '',
    'raw_key_material_exposed' => is_string($encodedMetadata)
        && is_string($encodedPreflight)
        && (str_contains($encodedMetadata . $encodedPreflight, 'DEADBEEF') || str_contains($encodedMetadata . $encodedPreflight, 'CAFEFEED')),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'The latest xref-stream update inherits encryption from a previous trailer. WordPress import keeps only explicitly unencrypted XMP and file-ID review metadata until decryption support is available.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xref-trailer-encrypt-prev ' . htmlspecialchars(json_encode([
    'title' => $metadata['title'] ?? null,
    'document_fingerprint_source' => $metadata['document_fingerprint_source'] ?? null,
    'text_extraction' => $preflight['text_extraction_policy'],
    'metadata_source_policy' => $policy,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
