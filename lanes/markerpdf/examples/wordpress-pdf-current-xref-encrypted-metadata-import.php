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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current XRef Encrypted XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Current encrypted xref stream metadata review</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2024-06-02T08:30:00-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2024-06-02T12:45:00Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
$compressedProfile = gzcompress('Current encrypted OutputIntent profile bytes');
if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress current xref encrypted metadata fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Encrypted xref stream visible leak) Tj ET';
$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream");
$addObject(6, 0, '<< /Title (Current Encrypted Info Title) /Author (Current Encrypted Author) /Producer (Current Encrypted Producer) /CreationDate (D:20240602112233Z) >>');
$addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream");
$addObject(8, 0, '<< /Title (Stale Trailer Info Title) /Author (Stale Trailer Author) >>');
$addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted XRef sRGB) /Info (Encrypted XRef PDF/A) /DestOutputProfile 7 0 R >>');
$addObject(10, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata false >>');

$currentChanging = 'XRef Encrypted Changing';
$pdf .= "trailer\n<< /Root 1 0 R /Info 8 0 R /ID [(Stale\\040Permanent) <" . strtoupper(bin2hex('Stale Changing')) . ">] >>\n";

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 12; $objectNumber++) {
    $rows .= pack('N', $objectNumber === 11 ? $xrefOffset : ($offsets[$objectNumber] ?? 0));
}
$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress current xref encrypted metadata rows.');
}

$pdf .= "11 0 obj\n"
    . '<< /Type /XRef /Size 12 /Root 1 0 R /Info 6 0 R /Encrypt 10 0 R /ID [(XRef\040Encrypted\040Permanent) <' . strtoupper(bin2hex($currentChanging)) . '>] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$policy = $metadata['encryption']['metadata_source_policy'] ?? [];

echo '<!-- markerpdf-current-xref-encrypted-metadata-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_decryption' => false,
    'native_boundary' => 'latest xref-stream trailer /Encrypt gates XMP dates, Info, OutputIntent, trailer IDs, and visible text',
    'source' => $metadata['source'],
    'encryption_source' => $metadata['encryption']['source'] ?? null,
    'preserved_sources' => $policy['preserved_sources'] ?? [],
    'suppressed_sources' => $policy['suppressed_sources'] ?? [],
    'xmp_date_utc_normalized' => ($metadata['created_at_utc'] ?? null) === '2024-06-02T12:30:00Z',
    'metadata_date_utc_normalized' => ($metadata['metadata_date_utc'] ?? null) === '2024-06-02T12:45:00Z',
    'current_xref_stream_id_selected' => ($metadata['trailer_ids']['permanent']['hex'] ?? null) === bin2hex('XRef Encrypted Permanent'),
    'encrypted_text_blocked' => $plainText === '',
    'raw_key_material_exposed' => is_string($encodedMetadata)
        && (str_contains($encodedMetadata, 'DEADBEEF') || str_contains($encodedMetadata, 'CAFEFEED')),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Current Encrypted PDF Metadata Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'The latest xref-stream trailer marks this PDF encrypted. WordPress import keeps only explicitly unencrypted XMP dates and fingerprints while suppressing encrypted Info, OutputIntent, and visible text bytes.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:current-xref-encrypted-metadata ' . htmlspecialchars(json_encode([
    'title' => $metadata['title'] ?? null,
    'description' => $metadata['description'] ?? null,
    'created_at' => $metadata['created_at'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date' => $metadata['metadata_date'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'document_fingerprint_source' => $metadata['document_fingerprint_source'] ?? null,
    'text_extraction' => 'blocked_without_decryption',
    'metadata_source_policy' => $policy,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
