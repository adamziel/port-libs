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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Wrapped Hidden XMP Smoke Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Wrapped metadata references are not document XMP streams.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-indirect-object-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Wrapped Metadata Smoke Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Wrapped Metadata Smoke Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-08T13:10:58Z</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-08T13:10:58Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$content = 'BT /F1 12 Tf 72 720 Td (Wrapped Metadata Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n[6 0 R 7 0 R]\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
    . "7 0 obj\n<< /S /JavaScript /JS (app.alert\\('wrapped metadata action tail'\\)) >>\nendobj\n"
    . "8 0 obj\n<< /Title (Wrapped Metadata Info Title) /Author (Info Wrapped Metadata Author) /Producer (Info Wrapped Metadata Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 8 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}';
$review = $metadata['catalog']['metadata_stream_review'] ?? [];

if (($metadata['title'] ?? null) !== 'Wrapped Metadata Info Title') {
    throw new RuntimeException('Expected trailer Info title when catalog Metadata points at an array wrapper.');
}
if (($review['status'] ?? null) !== 'rejected_non_stream_metadata_object') {
    throw new RuntimeException('Expected catalog Metadata array wrapper to be rejected as a non-stream object.');
}
if (($review['object_value_type'] ?? null) !== 'array') {
    throw new RuntimeException('Expected array wrapper value metadata.');
}
if (($review['referenced_object_numbers'] ?? null) !== [6, 7]) {
    throw new RuntimeException('Expected wrapped reference object numbers to remain review metadata.');
}
if (str_contains($encoded, 'Wrapped Hidden XMP Smoke Title') || str_contains($encoded, 'wrapped metadata action tail')) {
    throw new RuntimeException('Wrapped XMP/action payload leaked into document metadata.');
}
if (str_contains($plainText, 'Wrapped Hidden XMP Smoke Title') || str_contains($plainText, 'wrapped metadata action tail')) {
    throw new RuntimeException('Wrapped XMP/action payload leaked into visible WordPress text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-indirect-object-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-metadata-stream-boundary',
    'native_boundary' => 'Catalog /Metadata wrapper objects are not chased into document XMP streams',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'info_fallback_title' => $metadata['title'] ?? null,
    'review_status' => $review['status'] ?? null,
    'object_number' => $review['object_number'] ?? null,
    'object_value_type' => $review['object_value_type'] ?? null,
    'referenced_object_numbers' => $review['referenced_object_numbers'] ?? [],
    'accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'payload_included' => $review['payload_included'] ?? null,
    'hidden_xmp_not_promoted' => !isset($metadata['xmp']['title']),
    'hidden_xmp_not_visible_text' => !str_contains($plainText, 'Wrapped Hidden XMP Smoke Title'),
    'action_tail_not_promoted' => !str_contains($encoded, 'wrapped metadata action tail'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-metadata-review ' . $htmlJson([
    'status' => $review['status'] ?? null,
    'object_value_type' => $review['object_value_type'] ?? null,
    'object_value_preview' => $review['object_value_preview'] ?? null,
    'referenced_object_numbers' => $review['referenced_object_numbers'] ?? [],
    'accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'payload_included' => $review['payload_included'] ?? null,
]) . " -->\n";
