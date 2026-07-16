<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$metadataBytes = '<!DOCTYPE x:xmpmeta ['
    . '<!ENTITY entityTitle "Expanded WordPress Entity XMP Title">'
    . '<!ENTITY entityDescription "Expanded entity description must not become WordPress metadata">'
    . '<!ENTITY entityDate "2026-06-04T23:27:55-04:00">'
    . ']>'
    . '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">&entityTitle;</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">&entityDescription;</rdf:li></rdf:Alt></dc:description>'
    . '<pdf:Producer>WordPress Entity Boundary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>WordPress Entity Boundary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>&entityDate;</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T03:27:55Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP entity boundary smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Entity Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Entity Boundary Info Title) /Author (Info Entity Author) /Producer (Info Entity Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$summary = is_array($review['xmp_summary'] ?? null) ? $review['xmp_summary'] : [];

if (($metadata['title'] ?? null) !== 'Entity Boundary Info Title') {
    throw new RuntimeException('Expected unsafe XMP entity metadata to fail closed to the Info title.');
}
if (($review['status'] ?? null) !== 'rejected_unsafe_document_xmp_stream') {
    throw new RuntimeException('Expected unsafe document XMP stream review metadata.');
}
if (str_contains($encoded, 'Expanded WordPress Entity XMP Title') || str_contains($encoded, 'Expanded entity description')) {
    throw new RuntimeException('Expected expanded entity values to stay out of WordPress metadata.');
}
if (str_contains($plainText, 'Expanded WordPress Entity XMP Title') || str_contains($plainText, 'Expanded entity description')) {
    throw new RuntimeException('Expected XMP entity values to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-entity-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-entity-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-entity-boundary',
    'native_boundary' => 'catalog XMP streams with DTD/entity declarations fail closed before WordPress metadata promotion',
    'source' => $metadata['source'] ?? [],
    'title' => $metadata['title'] ?? null,
    'xmp_promoted' => ($metadata['xmp'] ?? []) !== [],
    'metadata_review_status' => $review['status'] ?? null,
    'xmp_summary_status' => $summary['status'] ?? null,
    'unsafe_markup' => $summary['unsafe_markup'] ?? [],
    'entity_title_excluded' => !str_contains($encoded, 'Expanded WordPress Entity XMP Title'),
    'entity_description_excluded' => !str_contains($encoded, 'Expanded entity description'),
    'visible_text_excludes_xmp_entities' => !str_contains($plainText, 'Expanded WordPress Entity XMP Title')
        && !str_contains($plainText, 'Expanded entity description'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
