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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Raw Duplicate Type Hidden XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Raw Duplicate Type Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Ambiguous XMP stream role entries remain review-only.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-raw-duplicate-type-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Raw Duplicate Type Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Raw Duplicate Type Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-07T09:29:06-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-07T13:29:06Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP raw duplicate Type boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Raw Duplicate Type Boundary Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type (EmbeddedFile literal decoy) /Type /Metadata /Subtype (text/xml literal decoy) /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
    . "stream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Raw Duplicate Type Info Title) /Author (Info Raw Duplicate Author) /Producer (Info Raw Duplicate Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$summary = $review['xmp_summary'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress Raw Duplicate Type Info Title') {
    throw new RuntimeException('Expected Info title fallback when duplicate XMP stream role keys are rejected.');
}
if (($metadata['xmp'] ?? []) !== []) {
    throw new RuntimeException('Duplicate Type/Subtype XMP stream should not be promoted as document metadata.');
}
if (($review['status'] ?? null) !== 'rejected_duplicate_metadata_stream_type_keys') {
    throw new RuntimeException('Expected duplicate stream role keys to be recorded as fail-closed review metadata.');
}
if (($review['duplicate_keys'] ?? []) !== ['Type', 'Subtype']) {
    throw new RuntimeException('Expected both duplicate Type and Subtype keys in review metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'WordPress Raw Duplicate Type Hidden XMP Title')) {
    throw new RuntimeException('Rejected XMP payload text leaked into metadata output.');
}
if (str_contains($plainText, 'WordPress Raw Duplicate Type Hidden XMP Title')) {
    throw new RuntimeException('Rejected XMP payload leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-raw-duplicate-type-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-stream-role-boundary',
    'native_boundary' => 'Catalog /Metadata stream dictionaries with duplicate Type or Subtype keys are review-only even when one duplicate is a literal/non-name token',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'duplicate_stream_role_rejected' => ($review['status'] ?? null) === 'rejected_duplicate_metadata_stream_type_keys',
    'duplicate_keys' => $review['duplicate_keys'] ?? [],
    'info_fallback_preserved' => ($metadata['title'] ?? null) === 'WordPress Raw Duplicate Type Info Title',
    'xmp_payload_redacted' => is_string($encoded) && !str_contains($encoded, 'WordPress Raw Duplicate Type Hidden XMP Title'),
    'summary_redacts_text_values' => ($summary['text_values_redacted'] ?? null) === true,
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Raw Duplicate Type Hidden XMP Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-raw-duplicate-type-review ' . $htmlJson([
    'status' => $review['status'] ?? null,
    'duplicate_keys' => $review['duplicate_keys'] ?? [],
    'type_entry_count' => $review['type_entry_count'] ?? null,
    'subtype_entry_count' => $review['subtype_entry_count'] ?? null,
    'field_names' => $summary['field_names'] ?? [],
    'created_at_utc' => $summary['dates_utc']['created_at'] ?? null,
    'metadata_date_utc' => $summary['dates_utc']['metadata_date'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
