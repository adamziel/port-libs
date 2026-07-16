<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current XRef XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Current xref stream metadata review</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2024-06-02T08:30:00-04:00</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress xref-stream XMP fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Current xref metadata body) Tj ET';
$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream");
$addObject(6, 0, '<< /Title (Current Info Title) /Author (Current XRef Author) /Producer (Current XRef Producer) /ModDate (D:20240602112233Z) >>');
$addObject(8, 0, '<< /Title (Stale Trailer Title) /Author (Stale Trailer Author) /Producer (Stale Trailer Producer) >>');

$currentPermanent = 'Current Permanent';
$currentChanging = 'Current Changing';
$pdf .= "trailer\n<< /Root 1 0 R /Info 8 0 R /ID [(Stale\\040Permanent) <" . strtoupper(bin2hex('Stale Changing')) . ">] >>\n";

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
    $rows .= pack('N', $objectNumber === 9 ? $xrefOffset : ($offsets[$objectNumber] ?? 0));
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress xref-stream metadata fixture.');
}

$pdf .= "9 0 obj\n"
    . '<< /Type /XRef /Size 10 /Root 1 0 R /Info 6 0 R /ID [(Current\040Permanent) <' . strtoupper(bin2hex($currentChanging)) . '>] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-stream-metadata-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Current PDF xref-stream trailer dictionary supplies /Root, /Info, and /ID before WordPress metadata review',
    'source' => $metadata['source'],
    'current_xref_stream_info_selected' => ($metadata['producer'] ?? null) === 'Current XRef Producer',
    'current_xref_stream_id_selected' => ($metadata['trailer_ids']['permanent']['hex'] ?? null) === bin2hex($currentPermanent),
    'stale_textual_trailer_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Trailer Title'),
    'metadata_not_visible_text' => !str_contains($plainText, 'Current XRef XMP Title') && !str_contains($plainText, 'Stale Trailer Title'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . htmlspecialchars(json_encode([
    'authors' => $metadata['authors'] ?? [],
    'description' => $metadata['description'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at' => $metadata['created_at'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'modified_at' => $metadata['modified_at'] ?? null,
    'modified_at_utc' => $metadata['modified_at_utc'] ?? null,
    'document_fingerprint' => $metadata['document_fingerprint'] ?? null,
    'document_fingerprint_source' => $metadata['document_fingerprint_source'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
