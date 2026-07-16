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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">ParseType Collection WordPress Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator rdf:parseType="Collection">'
    . '<rdf:Description><rdf:value>ParseType Import Author</rdf:value><xmp:role>internal author qualifier</xmp:role></rdf:Description>'
    . '<rdf:Description><rdf:value>Data Liberation Team</rdf:value><xmp:role>internal reviewer qualifier</xmp:role></rdf:Description>'
    . '</dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">RDF parseType Collection lists remain structured before WordPress import.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject rdf:parseType="Collection">'
    . '<rdf:Description><rdf:value>wordpress</rdf:value><xmp:tag>internal keyword qualifier</xmp:tag></rdf:Description>'
    . '<rdf:Description><rdf:value>xmp-parse-type-collection</rdf:value><xmp:tag>internal keyword qualifier</xmp:tag></rdf:Description>'
    . '</dc:subject>'
    . '<pdf:Producer>ParseType Collection Producer</pdf:Producer>'
    . '<xmp:CreatorTool>ParseType Collection Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-06T14:30:31-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-06T18:30:31Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '<rdf:Description rdf:about="#parseTypeCollectionDecoy">'
    . '<dc:creator rdf:parseType="Collection"><rdf:Description><rdf:value>External Collection Decoy</rdf:value></rdf:Description></dc:creator>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['ParseType Collection WordPress Title', 'wordpress'],
    ['Trailing ParseType Collection Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress parseType Collection XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (ParseType Collection XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (ParseType Collection Info Fallback Title) /Author (Info ParseType Author) /Keywords (info, fallback) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['title'] ?? null) !== 'ParseType Collection WordPress Title') {
    throw new RuntimeException('Expected parseType Collection XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['ParseType Import Author', 'Data Liberation Team']) {
    throw new RuntimeException('Expected parseType Collection creator nodes to remain separate authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-parse-type-collection']) {
    throw new RuntimeException('Expected parseType Collection subject nodes to remain separate keywords.');
}
if (in_array('Info ParseType Author', $metadata['authors'] ?? [], true)) {
    throw new RuntimeException('Expected XMP parseType Collection authors to take precedence over Info fallback authors.');
}
if (
    str_contains($encoded, 'internal author qualifier')
    || str_contains($encoded, 'internal keyword qualifier')
    || str_contains($encoded, 'External Collection Decoy')
    || str_contains($encoded, 'Trailing ParseType Collection Decoy Title')
) {
    throw new RuntimeException('Expected parseType Collection qualifiers and decoys to stay out of metadata output.');
}
if (
    str_contains($plainText, 'ParseType Collection WordPress Title')
    || str_contains($plainText, 'ParseType Import Author')
    || str_contains($plainText, 'Trailing ParseType Collection Decoy Title')
) {
    throw new RuntimeException('Expected parseType Collection XMP metadata to stay out of visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-parse-type-collection-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-rdf-parse-type-collection-boundary',
    'native_boundary' => 'XMP RDF parseType Collection property node elements are ordered before WordPress document metadata promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'] ?? [],
    'title_from_xmp' => ($metadata['title'] ?? null) === 'ParseType Collection WordPress Title',
    'authors_from_parse_type_collection' => ($metadata['authors'] ?? []) === ['ParseType Import Author', 'Data Liberation Team'],
    'keywords_from_parse_type_collection' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-parse-type-collection'],
    'info_author_not_promoted' => !in_array('Info ParseType Author', $metadata['authors'] ?? [], true),
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'qualifier_text_excluded' => !str_contains($encoded, 'internal author qualifier')
        && !str_contains($encoded, 'internal keyword qualifier'),
    'unreferenced_collection_excluded' => !str_contains($encoded, 'External Collection Decoy'),
    'trailing_decoy_excluded' => !str_contains($encoded, 'Trailing ParseType Collection Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'ParseType Collection WordPress Title')
        && !str_contains($plainText, 'ParseType Import Author')
        && !str_contains($plainText, 'Trailing ParseType Collection Decoy Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-parse-type-collection-review ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'description' => $metadata['description'] ?? null,
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
