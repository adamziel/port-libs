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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Embedded XML XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Embedded XML payload is not a document metadata stream</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-03T09:30:09Z</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-03T09:31:10Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress catalog metadata boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Catalog Metadata Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Info Boundary Title) /Author (Info Boundary Author) /Producer (Info Boundary Producer) /CreationDate (D:20260603093009Z) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];

if (($metadata['title'] ?? null) !== 'Info Boundary Title') {
    throw new RuntimeException('Expected trailer Info title to win when catalog Metadata points at an EmbeddedFile stream.');
}
if (($review['status'] ?? null) !== 'rejected_non_metadata_xml_stream') {
    throw new RuntimeException('Expected non-metadata catalog Metadata stream to be rejected as document XMP.');
}
if (!is_string($encoded) || str_contains($encoded, 'Hidden Embedded XML XMP Title')) {
    throw new RuntimeException('Rejected XMP text values leaked into metadata review output.');
}
if (str_contains($plainText, 'Hidden Embedded XML XMP Title')) {
    throw new RuntimeException('Rejected XMP stream leaked into visible WordPress text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-metadata-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-metadata-stream-boundary',
    'native_boundary' => 'Catalog /Metadata must target a /Type /Metadata /Subtype /XML stream before document XMP promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'info_fallback_title' => $metadata['title'] ?? null,
    'catalog_metadata_review_status' => $review['status'] ?? null,
    'catalog_metadata_object' => $review['object_number'] ?? null,
    'catalog_metadata_type' => $review['type'] ?? null,
    'catalog_metadata_subtype' => $review['subtype'] ?? null,
    'accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'payload_included' => $review['payload_included'] ?? null,
    'xmp_payload_values_redacted' => ($review['xmp_summary']['text_values_redacted'] ?? null) === true,
    'hidden_xmp_not_promoted' => !isset($metadata['xmp']['title']),
    'hidden_xmp_not_visible_text' => !str_contains($plainText, 'Hidden Embedded XML XMP Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-metadata-review ' . $htmlJson([
    'status' => $review['status'] ?? null,
    'type' => $review['type'] ?? null,
    'subtype' => $review['subtype'] ?? null,
    'bytes' => $review['bytes'] ?? null,
    'sha256' => $review['sha256'] ?? null,
    'xmp_summary' => $review['xmp_summary'] ?? [],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
