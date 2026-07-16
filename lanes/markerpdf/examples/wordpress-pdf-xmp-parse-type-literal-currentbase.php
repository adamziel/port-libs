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
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
    . ' xmlns:html="http://www.w3.org/1999/xhtml">'
    . '<dc:title><rdf:Alt>'
    . '<rdf:li xml:lang="fr-FR" rdf:parseType="Literal"><html:span>Titre literal ignore</html:span></rdf:li>'
    . '<rdf:li xml:lang="x-default" rdf:parseType="Literal"><html:span>WordPress Literal XMP Title</html:span></rdf:li>'
    . '</rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq>'
    . '<rdf:li rdf:parseType="Literal"><html:span>Literal Import Author One</html:span></rdf:li>'
    . '<rdf:li rdf:parseType="Literal"><html:span>Literal Import Author Two</html:span></rdf:li>'
    . '</rdf:Seq></dc:creator>'
    . '<dc:description rdf:parseType="Literal"><html:p>Literal XML metadata becomes WordPress-safe plain text.</html:p></dc:description>'
    . '<dc:subject><rdf:Bag>'
    . '<rdf:li rdf:parseType="Literal"><html:span>wordpress</html:span></rdf:li>'
    . '<rdf:li rdf:parseType="Literal"><html:span>xmp-literal</html:span></rdf:li>'
    . '</rdf:Bag></dc:subject>'
    . '<pdf:Producer rdf:parseType="Literal"><html:span>Literal Import Producer</html:span></pdf:Producer>'
    . '<xmp:CreatorTool rdf:parseType="Literal"><html:span>Literal Import Tool</html:span></xmp:CreatorTool>'
    . '<xmp:CreateDate rdf:parseType="Literal"><html:time>2026-06-08T07:41:29-04:00</html:time></xmp:CreateDate>'
    . '<xmp:MetadataDate rdf:parseType="Literal"><html:time>2026-06-08T11:42:29Z</html:time></xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$decoyXmp = str_replace('WordPress Literal XMP Title', 'Trailing Literal XMP Decoy Title', $xmp);
$metadataBytes = $xmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress WordPress XMP parseType Literal fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (WordPress Literal XMP Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Info Literal XMP Title) /Author (Info Literal Author) /Producer (Info Literal Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$rejectedPdf = str_replace('/Type /Metadata /Subtype /XML', '/Type /EmbeddedFile /Subtype /text#2Fxml', $pdf);

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();
$metadata = $metadataExtractor->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$rejectedMetadata = $metadataExtractor->extractDocumentMetadata($rejectedPdf);
$rejectedPlainText = $textExtractor->extractPlainText($rejectedPdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$rejectedEncoded = json_encode($rejectedMetadata, JSON_UNESCAPED_SLASHES);
$review = $rejectedMetadata['catalog']['metadata_stream_review'] ?? [];
$summary = $review['xmp_summary'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress Literal XMP Title') {
    throw new RuntimeException('Expected parseType Literal title to become document metadata.');
}
if (($metadata['description'] ?? null) !== 'Literal XML metadata becomes WordPress-safe plain text.') {
    throw new RuntimeException('Expected parseType Literal description to become plain document metadata.');
}
if (($metadata['authors'] ?? null) !== ['Literal Import Author One', 'Literal Import Author Two']) {
    throw new RuntimeException('Expected parseType Literal creator list to become ordered authors.');
}
if (!is_string($encoded) || str_contains($encoded, '<html:span>') || str_contains($encoded, 'Trailing Literal XMP Decoy Title')) {
    throw new RuntimeException('XMP literal XML tags or trailing packet values leaked into metadata output.');
}
if (str_contains($plainText, 'WordPress Literal XMP Title') || str_contains($plainText, 'Trailing Literal XMP Decoy Title')) {
    throw new RuntimeException('XMP literal metadata leaked into visible WordPress text.');
}
if (($review['status'] ?? null) !== 'rejected_non_metadata_xml_stream') {
    throw new RuntimeException('Expected EmbeddedFile XML to stay review-only.');
}
if (($summary['text_values_redacted'] ?? null) !== true) {
    throw new RuntimeException('Expected rejected parseType Literal XMP text values to be redacted.');
}
if (!is_string($rejectedEncoded) || str_contains($rejectedEncoded, 'WordPress Literal XMP Title')) {
    throw new RuntimeException('Rejected literal XMP title leaked into review output.');
}
if (str_contains($rejectedPlainText, 'WordPress Literal XMP Title')) {
    throw new RuntimeException('Rejected literal XMP title leaked into visible text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-parse-type-literal-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-rdf-parse-type-literal',
    'native_boundary' => 'RDF parseType Literal XML children become scalar metadata text only on document XMP streams',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? null,
    'xmp_literal_xml_tags_redacted' => is_string($encoded) && !str_contains($encoded, '<html:span>'),
    'trailing_packet_redacted' => is_string($encoded) && !str_contains($encoded, 'Trailing Literal XMP Decoy Title'),
    'xmp_not_visible_text' => !str_contains($plainText, 'WordPress Literal XMP Title'),
    'rejected_xml_status' => $review['status'] ?? null,
    'rejected_xml_field_names' => $summary['field_names'] ?? [],
    'rejected_xml_values_redacted' => ($summary['text_values_redacted'] ?? null) === true,
    'rejected_xml_not_visible_text' => !str_contains($rejectedPlainText, 'WordPress Literal XMP Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
