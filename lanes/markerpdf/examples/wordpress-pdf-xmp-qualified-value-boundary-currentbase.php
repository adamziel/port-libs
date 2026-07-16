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
    . '<dc:title><rdf:Alt>'
    . '<rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>Qualified XMP Import Title</rdf:value><xmp:Label>title qualifier noise</xmp:Label></rdf:li>'
    . '</rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq>'
    . '<rdf:li rdf:parseType="Resource"><rdf:value>Qualified Import Author</rdf:value><xmp:role>author qualifier noise</xmp:role></rdf:li>'
    . '</rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>Qualified XMP values stay clean for WordPress import metadata</rdf:value><pdf:Producer>description qualifier noise</pdf:Producer></rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li rdf:parseType="Resource"><rdf:value>wordpress</rdf:value><xmp:tag>keyword qualifier noise</xmp:tag></rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer rdf:parseType="Resource"><rdf:value>Qualified Import Producer</rdf:value><xmp:qualifier>producer qualifier noise</xmp:qualifier></pdf:Producer>'
    . '<xmp:CreatorTool rdf:parseType="Resource"><rdf:value>Qualified Import Tool</rdf:value><xmp:qualifier>tool qualifier noise</xmp:qualifier></xmp:CreatorTool>'
    . '<xmp:CreateDate rdf:parseType="Resource"><rdf:value>2026-06-04T21:18:45-04:00</rdf:value><xmp:precision>date qualifier noise</xmp:precision></xmp:CreateDate>'
    . '<xmp:MetadataDate rdf:parseType="Resource"><rdf:value>2026-06-05T01:19:45Z</rdf:value><xmp:precision>metadata date qualifier noise</xmp:precision></xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$decoyXmp = str_replace('Qualified XMP Import Title', 'Trailing Qualified Decoy Title', $xmp);
$metadataBytes = $xmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress qualified-value XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Qualified XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Qualified XMP Import Title') {
    throw new RuntimeException('Expected rdf:value title to be imported without qualifier text.');
}
if (($metadata['producer'] ?? null) !== 'Qualified Import Producer') {
    throw new RuntimeException('Expected top-level Producer property to win before nested qualifiers.');
}
if (($metadata['created_at_utc'] ?? null) !== '2026-06-05T01:18:45Z') {
    throw new RuntimeException('Expected qualified CreateDate rdf:value to normalize.');
}
if (!is_string($encoded) || str_contains($encoded, 'title qualifier noise') || str_contains($encoded, 'Trailing Qualified Decoy Title')) {
    throw new RuntimeException('Expected qualifier and trailing packet text to stay out of metadata output.');
}
if (str_contains($plainText, 'Qualified XMP Import Title') || str_contains($plainText, 'title qualifier noise')) {
    throw new RuntimeException('Expected qualified XMP text to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-qualified-value-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-qualified-value-boundary',
    'native_boundary' => 'XMP qualified property wrappers use rdf:value while qualifier text stays review-only and non-visible',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_rdf_value' => ($metadata['title'] ?? null) === 'Qualified XMP Import Title',
    'producer_from_top_level_property' => ($metadata['producer'] ?? null) === 'Qualified Import Producer',
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'qualifier_text_excluded' => is_string($encoded) && !str_contains($encoded, 'title qualifier noise'),
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Qualified Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Qualified XMP Import Title')
        && !str_contains($plainText, 'title qualifier noise'),
],) . " -->\n";

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
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
