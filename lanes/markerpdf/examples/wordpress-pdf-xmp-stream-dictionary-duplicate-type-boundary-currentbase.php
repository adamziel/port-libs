<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Duplicate Type Hidden XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>WordPress Duplicate Type Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Duplicate metadata stream role keys stay review-only.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-duplicate-type-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>WordPress Duplicate Type Producer</pdf:Producer>'
    . '<xmp:CreatorTool>WordPress Duplicate Type Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-06T06:34:03-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-06T10:34:03Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress duplicate-Type XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Duplicate Type XMP WordPress Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length "
    . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Duplicate Type Info Title) /Author (Info Duplicate Type Author) /Producer (Info Duplicate Type Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$summary = is_array($review) && is_array($review['xmp_summary'] ?? null) ? $review['xmp_summary'] : [];

if (($metadata['title'] ?? null) !== 'Duplicate Type Info Title') {
    throw new RuntimeException('Expected Info title fallback when metadata stream role keys are duplicated.');
}
if (($review['status'] ?? null) !== 'rejected_duplicate_metadata_stream_type_keys') {
    throw new RuntimeException('Expected duplicate metadata stream Type/Subtype keys to be rejected.');
}
if (($metadata['xmp'] ?? []) !== []) {
    throw new RuntimeException('Expected duplicate Type/Subtype stream XMP to remain review-only.');
}
if (str_contains($encoded, 'WordPress Duplicate Type Hidden XMP Title')
    || str_contains($encoded, 'Duplicate metadata stream role keys stay review-only.')
) {
    throw new RuntimeException('Expected hidden XMP text values to stay redacted from metadata JSON.');
}
if (str_contains($plainText, 'WordPress Duplicate Type Hidden XMP Title')
    || str_contains($plainText, 'xmp-duplicate-type-boundary')
) {
    throw new RuntimeException('Expected XMP text values to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-stream-dictionary-duplicate-type-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-stream-dictionary-duplicate-type-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-stream-dictionary-boundary',
    'native_boundary' => 'Catalog Metadata streams with duplicate Type or Subtype keys are review-only even if the last values are Metadata and XML',
    'source' => $metadata['source'] ?? [],
    'title' => $metadata['title'] ?? null,
    'xmp_promoted' => ($metadata['xmp'] ?? []) !== [],
    'review_status' => $review['status'] ?? null,
    'duplicate_keys' => $review['duplicate_keys'] ?? [],
    'type_values' => $review['type_values'] ?? [],
    'subtype_values' => $review['subtype_values'] ?? [],
    'xmp_summary_fields' => $summary['field_names'] ?? [],
    'hidden_xmp_redacted' => !str_contains($encoded, 'WordPress Duplicate Type Hidden XMP Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Duplicate Type Hidden XMP Title')
        && !str_contains($plainText, 'xmp-duplicate-type-boundary'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
