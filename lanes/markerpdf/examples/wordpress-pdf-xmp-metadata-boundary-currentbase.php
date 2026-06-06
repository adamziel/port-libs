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

$nonStreamContent = 'BT /F1 12 Tf 72 720 Td (Non Stream Metadata Boundary Body) Tj ET';
$nonStreamPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($nonStreamContent) . " >>\nstream\n{$nonStreamContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Info Boundary Title) /Author (Info Boundary Author) /Producer (Info Boundary Producer) /CreationDate (D:20260603093009Z) >>\nendobj\n"
    . "7 0 obj\n<< /Type /Metadata /Subtype /XML /HiddenTitle (Hidden Non Stream Metadata Leak) /Length 321 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$duplicateContent = 'BT /F1 12 Tf 72 720 Td (Duplicate Metadata Boundary Body) Tj ET';
$duplicatePdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata null /Metadata 8 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Duplicate Metadata Info Title) /Author (Duplicate Metadata Info Author) /Producer (Duplicate Metadata Info Producer) /CreationDate (D:20260605233038Z) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$extraOperandContent = 'BT /F1 12 Tf 72 720 Td (Extra Operand Metadata Boundary Body) Tj ET';
$extraOperandPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 8 0 R 9 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($extraOperandContent) . " >>\nstream\n{$extraOperandContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Extra Operand Metadata Info Title) /Author (Extra Operand Metadata Info Author) /Producer (Extra Operand Metadata Info Producer) /CreationDate (D:20260606020610Z) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "9 0 obj\n<< /S /JavaScript /JS (app.alert\\('extra metadata operand action tail'\\)) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();

$metadata = $metadataExtractor->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$nonStreamMetadata = $metadataExtractor->extractDocumentMetadata($nonStreamPdf);
$nonStreamText = $textExtractor->extractPlainText($nonStreamPdf);
$duplicateMetadata = $metadataExtractor->extractDocumentMetadata($duplicatePdf);
$duplicateText = $textExtractor->extractPlainText($duplicatePdf);
$extraOperandMetadata = $metadataExtractor->extractDocumentMetadata($extraOperandPdf);
$extraOperandText = $textExtractor->extractPlainText($extraOperandPdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$nonStreamEncoded = json_encode($nonStreamMetadata, JSON_UNESCAPED_SLASHES);
$nonStreamReview = $nonStreamMetadata['catalog']['metadata_stream_review'] ?? [];
$duplicateEncoded = json_encode($duplicateMetadata, JSON_UNESCAPED_SLASHES);
$duplicateReview = $duplicateMetadata['catalog']['metadata_stream_review'] ?? [];
$extraOperandEncoded = json_encode($extraOperandMetadata, JSON_UNESCAPED_SLASHES);
$extraOperandReview = $extraOperandMetadata['catalog']['metadata_stream_review'] ?? [];

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
if (($nonStreamReview['status'] ?? null) !== 'rejected_non_stream_metadata_object') {
    throw new RuntimeException('Expected indirect non-stream catalog Metadata object to be rejected distinctly.');
}
if (!is_string($nonStreamEncoded) || str_contains($nonStreamEncoded, 'Hidden Non Stream Metadata Leak')) {
    throw new RuntimeException('Non-stream catalog Metadata dictionary text leaked into metadata review output.');
}
if (str_contains($nonStreamText, 'Hidden Non Stream Metadata Leak')) {
    throw new RuntimeException('Non-stream catalog Metadata dictionary leaked into visible WordPress text.');
}
if (($duplicateMetadata['title'] ?? null) !== 'Duplicate Metadata Info Title') {
    throw new RuntimeException('Expected duplicate catalog Metadata keys to fall back to trailer Info metadata.');
}
if (($duplicateReview['status'] ?? null) !== 'rejected_duplicate_metadata_entries') {
    throw new RuntimeException('Expected duplicate catalog Metadata review status.');
}
if (!is_string($duplicateEncoded) || str_contains($duplicateEncoded, 'Hidden Embedded XML XMP Title')) {
    throw new RuntimeException('Duplicate catalog Metadata XMP values leaked into metadata review output.');
}
if (str_contains($duplicateText, 'Hidden Embedded XML XMP Title')) {
    throw new RuntimeException('Duplicate catalog Metadata XMP leaked into visible WordPress text.');
}
if (($extraOperandMetadata['title'] ?? null) !== 'Extra Operand Metadata Info Title') {
    throw new RuntimeException('Expected malformed catalog Metadata operands to fall back to trailer Info metadata.');
}
if (($extraOperandReview['status'] ?? null) !== 'rejected_malformed_metadata_operand') {
    throw new RuntimeException('Expected extra catalog Metadata operand review status.');
}
if (!is_string($extraOperandEncoded) || str_contains($extraOperandEncoded, 'Hidden Embedded XML XMP Title')) {
    throw new RuntimeException('Extra-operand catalog Metadata XMP values leaked into metadata review output.');
}
if (is_string($extraOperandEncoded) && str_contains($extraOperandEncoded, 'extra metadata operand action tail')) {
    throw new RuntimeException('Extra-operand catalog Metadata action tail leaked into metadata review output.');
}
if (str_contains($extraOperandText, 'Hidden Embedded XML XMP Title')) {
    throw new RuntimeException('Extra-operand catalog Metadata XMP leaked into visible WordPress text.');
}
if (str_contains($extraOperandText, 'extra metadata operand action tail')) {
    throw new RuntimeException('Extra-operand catalog Metadata action tail leaked into visible WordPress text.');
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
    'non_stream_metadata_status' => $nonStreamReview['status'] ?? null,
    'non_stream_metadata_type' => $nonStreamReview['type'] ?? null,
    'non_stream_metadata_subtype' => $nonStreamReview['subtype'] ?? null,
    'non_stream_metadata_declared_length' => $nonStreamReview['declared_length'] ?? null,
    'non_stream_metadata_values_redacted' => is_string($nonStreamEncoded)
        && !str_contains($nonStreamEncoded, 'Hidden Non Stream Metadata Leak'),
    'non_stream_metadata_not_visible_text' => !str_contains($nonStreamText, 'Hidden Non Stream Metadata Leak'),
    'duplicate_metadata_status' => $duplicateReview['status'] ?? null,
    'duplicate_metadata_entry_count' => $duplicateReview['metadata_entry_count'] ?? null,
    'duplicate_metadata_candidate_objects' => $duplicateReview['candidate_object_numbers'] ?? [],
    'duplicate_metadata_info_fallback_title' => $duplicateMetadata['title'] ?? null,
    'duplicate_metadata_values_redacted' => is_string($duplicateEncoded)
        && !str_contains($duplicateEncoded, 'Hidden Embedded XML XMP Title'),
    'duplicate_metadata_not_visible_text' => !str_contains($duplicateText, 'Hidden Embedded XML XMP Title'),
    'extra_operand_metadata_status' => $extraOperandReview['status'] ?? null,
    'extra_operand_metadata_operand_count' => $extraOperandReview['metadata_operand_count'] ?? null,
    'extra_operand_metadata_trailing_reference_objects' => $extraOperandReview['trailing_reference_object_numbers'] ?? [],
    'extra_operand_metadata_info_fallback_title' => $extraOperandMetadata['title'] ?? null,
    'extra_operand_metadata_values_redacted' => is_string($extraOperandEncoded)
        && !str_contains($extraOperandEncoded, 'Hidden Embedded XML XMP Title'),
    'extra_operand_metadata_action_redacted' => is_string($extraOperandEncoded)
        && !str_contains($extraOperandEncoded, 'extra metadata operand action tail'),
    'extra_operand_metadata_not_visible_text' => !str_contains($extraOperandText, 'Hidden Embedded XML XMP Title')
        && !str_contains($extraOperandText, 'extra metadata operand action tail'),
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

echo '<!-- markerpdf:catalog-non-stream-metadata-review ' . $htmlJson([
    'status' => $nonStreamReview['status'] ?? null,
    'type' => $nonStreamReview['type'] ?? null,
    'subtype' => $nonStreamReview['subtype'] ?? null,
    'declared_length' => $nonStreamReview['declared_length'] ?? null,
    'accepted_as_document_xmp' => $nonStreamReview['accepted_as_document_xmp'] ?? null,
    'payload_included' => $nonStreamReview['payload_included'] ?? null,
]) . " -->\n\n";

echo '<!-- markerpdf:catalog-duplicate-metadata-review ' . $htmlJson([
    'status' => $duplicateReview['status'] ?? null,
    'metadata_entry_count' => $duplicateReview['metadata_entry_count'] ?? null,
    'candidate_object_numbers' => $duplicateReview['candidate_object_numbers'] ?? [],
    'accepted_as_document_xmp' => $duplicateReview['accepted_as_document_xmp'] ?? null,
    'payload_included' => $duplicateReview['payload_included'] ?? null,
]) . " -->\n\n";

echo '<!-- markerpdf:catalog-extra-operand-metadata-review ' . $htmlJson([
    'status' => $extraOperandReview['status'] ?? null,
    'metadata_entry_count' => $extraOperandReview['metadata_entry_count'] ?? null,
    'metadata_operand_count' => $extraOperandReview['metadata_operand_count'] ?? null,
    'object_number' => $extraOperandReview['object_number'] ?? null,
    'trailing_reference_object_numbers' => $extraOperandReview['trailing_reference_object_numbers'] ?? [],
    'accepted_as_document_xmp' => $extraOperandReview['accepted_as_document_xmp'] ?? null,
    'payload_included' => $extraOperandReview['payload_included'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
