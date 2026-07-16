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
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Omitted Type XMP WordPress Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Native PHP XMP Importer</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Subtype XML catalog metadata remains document XMP when Type is omitted.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-omitted-type</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Omitted Type Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Omitted Type Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-07T08:02:02Z</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-07T08:03:02Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress omitted Type XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Visible WordPress body for omitted Type XMP) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Info Omitted Type Fallback) /Author (Info Fallback Author) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Omitted Type XMP WordPress Title') {
    throw new RuntimeException('Expected omitted Type catalog XMP title to become document metadata.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP packet-boundary metadata to be recorded.');
}
if (isset($metadata['catalog']['metadata_stream_review'])) {
    throw new RuntimeException('Expected omitted Type catalog XMP stream to avoid review-only rejection.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'catalog_metadata_stream_boundary')) {
    throw new RuntimeException('Expected omitted Type catalog XMP stream not to emit a catalog rejection row.');
}
if (str_contains($plainText, 'Omitted Type XMP WordPress Title')) {
    throw new RuntimeException('Expected XMP metadata text to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-omitted-type-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-metadata-boundary',
    'native_boundary' => 'Catalog /Metadata streams with omitted optional /Type and /Subtype /XML are accepted as document XMP',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'] ?? [],
    'title_from_xmp' => true,
    'info_title_preserved' => ($metadata['info']['Title'] ?? null) === 'Info Omitted Type Fallback',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'catalog_rejection_absent' => !isset($metadata['catalog']['metadata_stream_review']),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Omitted Type XMP WordPress Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'description' => $metadata['description'] ?? null,
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
