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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Null Extra Operand Hidden XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Hidden XMP after null must not define WordPress metadata</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:MetadataDate>2026-06-06T13:16:35Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$content = 'BT /F1 12 Tf 72 720 Td (Null Extra Operand Metadata Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata null 5 0 R 7 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Null Extra Operand Info Title) /Author (Null Extra Operand Author) /Producer (Null Extra Operand Producer) >>\nendobj\n"
    . "7 0 obj\n<< /S /JavaScript /JS (app.alert\\('null metadata operand action tail'\\)) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];

if (($metadata['source'] ?? null) !== ['info', 'catalog']) {
    throw new RuntimeException('Expected malformed null catalog Metadata operands to emit catalog review metadata.');
}
if (($metadata['title'] ?? null) !== 'Null Extra Operand Info Title') {
    throw new RuntimeException('Expected trailer Info title fallback when malformed null Metadata operands are rejected.');
}
if (($metadata['xmp'] ?? null) !== []) {
    throw new RuntimeException('Expected XMP not to be promoted from a trailing operand after catalog Metadata null.');
}
if (($review['status'] ?? null) !== 'rejected_malformed_metadata_operand') {
    throw new RuntimeException('Expected null Metadata trailing operands to fail closed.');
}
if (($review['trailing_reference_object_numbers'] ?? null) !== [5, 7]) {
    throw new RuntimeException('Expected trailing metadata/action references to be summarized for review.');
}
if (($review['accepted_as_document_xmp'] ?? null) !== false || ($review['payload_included'] ?? null) !== false) {
    throw new RuntimeException('Expected malformed catalog Metadata operands to stay review-only.');
}
if ($plainText !== 'Null Extra Operand Metadata Boundary Body') {
    throw new RuntimeException('Expected page text to remain the only visible import text.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'Null Extra Operand Hidden XMP Title')
    || str_contains($encoded, 'null metadata operand action tail')
) {
    throw new RuntimeException('Hidden XMP and action-tail text must not be promoted into WordPress metadata.');
}
if (str_contains($plainText, 'Null Extra Operand Hidden XMP Title') || str_contains($plainText, 'null metadata operand action tail')) {
    throw new RuntimeException('Hidden XMP and action-tail text must not become visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-metadata-null-extra-operand-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-metadata-null-extra-operand-boundary',
    'native_boundary' => 'Catalog /Metadata null followed by extra top-level operands is malformed and rejected before document XMP promotion',
    'source' => $metadata['source'],
    'info_fallback_title' => $metadata['title'] ?? null,
    'metadata_null_with_trailing_operands_rejected' => true,
    'review_status' => $review['status'] ?? null,
    'metadata_operand_count' => $review['metadata_operand_count'] ?? null,
    'trailing_reference_object_numbers' => $review['trailing_reference_object_numbers'] ?? null,
    'xmp_not_promoted' => $metadata['xmp'] === [],
    'payload_included' => $review['payload_included'] ?? null,
    'accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'visible_text_is_page_content_only' => $plainText === 'Null Extra Operand Metadata Boundary Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
