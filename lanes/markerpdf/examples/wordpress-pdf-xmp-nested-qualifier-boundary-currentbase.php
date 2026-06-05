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
    . '<rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>Nested Qualifier XMP Import Title</rdf:value><xmp:labels><rdf:Bag><rdf:li>title nested qualifier label</rdf:li></rdf:Bag></xmp:labels></rdf:li>'
    . '</rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq>'
    . '<rdf:li rdf:parseType="Resource"><rdf:value>Nested Qualifier Import Author</rdf:value><xmp:roles><rdf:Bag><rdf:li>copy editor qualifier</rdf:li></rdf:Bag></xmp:roles></rdf:li>'
    . '<rdf:li>Data Liberation Team</rdf:li>'
    . '</rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>Nested qualifier RDF collections stay out of WordPress import metadata</rdf:value><pdf:Producer><rdf:Seq><rdf:li>description nested qualifier producer</rdf:li></rdf:Seq></pdf:Producer></rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li rdf:parseType="Resource"><rdf:value>nested-qualifier-xmp</rdf:value><xmp:tag><rdf:Seq><rdf:li>internal keyword qualifier</rdf:li></rdf:Seq></xmp:tag></rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer rdf:parseType="Resource"><rdf:value>Nested Qualifier Producer</rdf:value><xmp:qualifier><rdf:Bag><rdf:li>producer nested qualifier</rdf:li></rdf:Bag></xmp:qualifier></pdf:Producer>'
    . '<xmp:CreatorTool rdf:parseType="Resource"><rdf:value>Nested Qualifier Tool</rdf:value><xmp:qualifier><rdf:Bag><rdf:li>tool nested qualifier</rdf:li></rdf:Bag></xmp:qualifier></xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-04T22:55:34-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T02:55:34Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$decoyXmp = str_replace('Nested Qualifier XMP Import Title', 'Trailing Nested Qualifier Decoy Title', $xmp);
$metadataBytes = $xmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress nested-qualifier XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Nested Qualifier XMP Boundary Body) Tj ET';
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

if (($metadata['title'] ?? null) !== 'Nested Qualifier XMP Import Title') {
    throw new RuntimeException('Expected direct rdf:Alt item value to become the document title.');
}
if (($metadata['authors'] ?? []) !== ['Nested Qualifier Import Author', 'Data Liberation Team']) {
    throw new RuntimeException('Expected nested qualifier list items to stay out of author metadata.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'nested-qualifier-xmp']) {
    throw new RuntimeException('Expected nested qualifier list items to stay out of keyword metadata.');
}
if (($metadata['created_at_utc'] ?? null) !== '2026-06-05T02:55:34Z') {
    throw new RuntimeException('Expected nested-qualifier XMP date metadata to normalize.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'copy editor qualifier')
    || str_contains($encoded, 'internal keyword qualifier')
    || str_contains($encoded, 'Trailing Nested Qualifier Decoy Title')
) {
    throw new RuntimeException('Expected nested qualifier and trailing packet text to stay out of metadata output.');
}
if (
    str_contains($plainText, 'Nested Qualifier XMP Import Title')
    || str_contains($plainText, 'copy editor qualifier')
    || str_contains($plainText, 'internal keyword qualifier')
) {
    throw new RuntimeException('Expected nested qualifier XMP text to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-nested-qualifier-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-nested-qualifier-boundary',
    'native_boundary' => 'XMP list fields use direct RDF collection items while nested qualifier collections stay review-only and non-visible',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_direct_alt_item' => ($metadata['title'] ?? null) === 'Nested Qualifier XMP Import Title',
    'authors_exclude_nested_qualifier_lists' => ($metadata['authors'] ?? []) === ['Nested Qualifier Import Author', 'Data Liberation Team'],
    'keywords_exclude_nested_qualifier_lists' => ($metadata['keywords'] ?? []) === ['wordpress', 'nested-qualifier-xmp'],
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'nested_qualifier_text_excluded' => is_string($encoded)
        && !str_contains($encoded, 'copy editor qualifier')
        && !str_contains($encoded, 'internal keyword qualifier'),
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Nested Qualifier Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Nested Qualifier XMP Import Title')
        && !str_contains($plainText, 'copy editor qualifier')
        && !str_contains($plainText, 'internal keyword qualifier'),
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
