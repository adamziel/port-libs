<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="markerpdf"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Direct Array Hidden XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Direct catalog Metadata arrays stay review-only.</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-08T17:22:05Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta><?xpacket end="w"?>';

$content = 'BT /F1 12 Tf 72 720 Td (XMP Direct Array Boundary Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata [5 0 R 7 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Action /S /JavaScript /JS (WordPressDirectArrayHiddenAction) >>\nendobj\n"
    . "8 0 obj\n<< /Title (WordPress Direct Array Info Title) /Author (Info Direct Array Author) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 8 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress Direct Array Info Title') {
    throw new RuntimeException('Expected Info title fallback when Catalog /Metadata is a direct array.');
}
if (($metadata['xmp'] ?? []) !== []) {
    throw new RuntimeException('Direct catalog Metadata arrays must not promote referenced XMP streams.');
}
if (($review['status'] ?? null) !== 'rejected_non_indirect_metadata_reference') {
    throw new RuntimeException('Expected direct array Metadata value to be recorded as rejected review metadata.');
}
if (($review['metadata_value_type'] ?? null) !== 'array') {
    throw new RuntimeException('Expected direct array Metadata value type in review metadata.');
}
if (($review['referenced_object_numbers'] ?? []) !== [5, 7]) {
    throw new RuntimeException('Expected direct array referenced object numbers to be reported.');
}
if (!is_string($encoded) || str_contains($encoded, 'WordPress Direct Array Hidden XMP Title')) {
    throw new RuntimeException('Rejected XMP payload text leaked into metadata output.');
}
if (str_contains($plainText, 'WordPress Direct Array Hidden XMP Title')) {
    throw new RuntimeException('Rejected XMP payload leaked into visible WordPress paragraph text.');
}
if (str_contains($plainText, 'WordPressDirectArrayHiddenAction')) {
    throw new RuntimeException('Rejected action payload leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-direct-array-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-xmp-metadata-boundary',
    'native_boundary' => 'Catalog /Metadata direct arrays are review-only and expose referenced object numbers without promoting referenced XMP payloads',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'status' => $review['status'] ?? null,
    'metadata_value_type' => $review['metadata_value_type'] ?? null,
    'referenced_object_numbers' => $review['referenced_object_numbers'] ?? [],
    'info_fallback_preserved' => ($metadata['title'] ?? null) === 'WordPress Direct Array Info Title',
    'xmp_payload_redacted' => is_string($encoded) && !str_contains($encoded, 'WordPress Direct Array Hidden XMP Title'),
    'action_payload_redacted' => is_string($encoded) && !str_contains($encoded, 'WordPressDirectArrayHiddenAction'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Direct Array Hidden XMP Title'),
    'visible_text_excludes_action' => !str_contains($plainText, 'WordPressDirectArrayHiddenAction'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-direct-array-review ' . $htmlJson([
    'status' => $review['status'] ?? null,
    'metadata_value_preview' => $review['metadata_value_preview'] ?? null,
    'metadata_array_entry_count' => $review['metadata_array_entry_count'] ?? null,
    'referenced_object_numbers' => $review['referenced_object_numbers'] ?? [],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
