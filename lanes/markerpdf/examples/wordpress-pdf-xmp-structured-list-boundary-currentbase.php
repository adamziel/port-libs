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
    . '<rdf:li xml:lang="x-default" rdf:parseType="Resource"><xmp:PrivateTitle>Structured Title Decoy</xmp:PrivateTitle><xmp:PrivateQualifier>title qualifier decoy</xmp:PrivateQualifier></rdf:li>'
    . '<rdf:li xml:lang="x-default">Current Structured List XMP Title</rdf:li>'
    . '</rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq>'
    . '<rdf:li rdf:parseType="Resource"><xmp:PrivateAuthor>Structured Author Decoy</xmp:PrivateAuthor><xmp:PrivateRole>author role decoy</xmp:PrivateRole></rdf:li>'
    . '<rdf:li>Structured List Author</rdf:li>'
    . '</rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt>'
    . '<rdf:li xml:lang="x-default" rdf:parseType="Resource"><xmp:PrivateDescription>Structured Description Decoy</xmp:PrivateDescription></rdf:li>'
    . '<rdf:li xml:lang="x-default">Structured list nodes without rdf:value stay review-only.</rdf:li>'
    . '</rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag>'
    . '<rdf:li rdf:parseType="Resource"><xmp:PrivateKeyword>structured-keyword-decoy</xmp:PrivateKeyword><xmp:PrivateTag>keyword tag decoy</xmp:PrivateTag></rdf:li>'
    . '<rdf:li>wordpress</rdf:li>'
    . '<rdf:li rdf:parseType="Resource"><rdf:value>xmp-structured-list</rdf:value><xmp:PrivateTag>safe keyword qualifier</xmp:PrivateTag></rdf:li>'
    . '</rdf:Bag></dc:subject>'
    . '<pdf:Producer>Structured List Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Structured List Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-07T10:12:29-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-07T14:12:29Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$decoyXmp = str_replace(
    ['Current Structured List XMP Title', 'Structured list nodes without rdf:value stay review-only.'],
    ['Trailing Structured List Decoy Title', 'Trailing structured-list packet stays outside metadata.'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP structured-list smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Structured List Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Structured List Info Title) /Author (Info Structured Author) /Keywords (info, fallback) /Producer (Info Structured Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Structured List XMP Title') {
    throw new RuntimeException('Expected scalar XMP title item to win after structured decoy list nodes.');
}
if (($metadata['authors'] ?? []) !== ['Structured List Author']) {
    throw new RuntimeException('Expected structured author list nodes without rdf:value to be skipped.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-structured-list']) {
    throw new RuntimeException('Expected only scalar and explicit rdf:value subject list items.');
}
if (!is_string($encoded) || str_contains($encoded, 'Structured Author Decoy') || str_contains($encoded, 'structured-keyword-decoy')) {
    throw new RuntimeException('Structured private XMP list nodes leaked into metadata output.');
}
if (str_contains($plainText, 'Current Structured List XMP Title') || str_contains($plainText, 'Structured Author Decoy')) {
    throw new RuntimeException('XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-structured-list-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-structured-list-boundary',
    'native_boundary' => 'XMP dc:title, dc:creator, and dc:subject list items skip structured RDF nodes without explicit rdf:value',
    'source' => $metadata['source'] ?? [],
    'title_from_scalar_xmp_item' => ($metadata['title'] ?? null) === 'Current Structured List XMP Title',
    'authors_from_scalar_items_only' => ($metadata['authors'] ?? []) === ['Structured List Author'],
    'keywords_from_scalar_and_rdf_value_items_only' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-structured-list'],
    'structured_nodes_without_rdf_value_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Structured Author Decoy')
        && !str_contains($encoded, 'structured-keyword-decoy'),
    'trailing_packet_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Structured List Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Structured List XMP Title')
        && !str_contains($plainText, 'Structured Author Decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-metadata-review ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? null,
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
]) . " -->\n";
