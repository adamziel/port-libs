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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Subtype Boundary XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>XMP Subtype Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Ambiguous metadata stream subtype operands must not promote document XMP.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-subtype-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Subtype Boundary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Subtype Boundary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-08T06:42:03-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-08T10:42:03Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP subtype boundary smoke fixture.');
}

$pdfFor = static function (
    string $metadataDictionary,
    string $bodyText,
    string $extraObjects = ''
) use ($compressedXmp): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Subtype Boundary Info Title) /Author (Info Subtype Boundary Author) /Producer (Info Subtype Boundary Producer) >>\nendobj\n"
        . $extraObjects
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();

$invalidPdf = $pdfFor('/Type /Metadata', 'XMP Missing Subtype Boundary Body');
$validPdf = $pdfFor(
    '/Type /Metadata /Subtype 7 0 R',
    'XMP Indirect XML Subtype Boundary Body',
    "7 0 obj\n/XML\nendobj\n"
);

$invalidMetadata = $metadataExtractor->extractDocumentMetadata($invalidPdf);
$invalidText = $textExtractor->extractPlainText($invalidPdf);
$validMetadata = $metadataExtractor->extractDocumentMetadata($validPdf);
$validText = $textExtractor->extractPlainText($validPdf);
$invalidEncoded = json_encode($invalidMetadata, JSON_UNESCAPED_SLASHES);
$invalidReview = $invalidMetadata['catalog']['metadata_stream_review'] ?? [];
$validEncoded = json_encode($validMetadata, JSON_UNESCAPED_SLASHES);

if (($invalidReview['status'] ?? null) !== 'rejected_missing_metadata_stream_subtype') {
    throw new RuntimeException('Expected missing XML subtype metadata stream to stay review-only.');
}
if (($invalidMetadata['title'] ?? null) !== 'Subtype Boundary Info Title') {
    throw new RuntimeException('Expected trailer Info title fallback after rejecting missing subtype XMP.');
}
if (!is_string($invalidEncoded) || str_contains($invalidEncoded, 'Hidden Subtype Boundary XMP Title')) {
    throw new RuntimeException('Expected rejected subtype-boundary XMP text values to stay redacted.');
}
if (str_contains($invalidText, 'Hidden Subtype Boundary XMP Title')) {
    throw new RuntimeException('Expected rejected subtype-boundary XMP payload to stay out of visible text.');
}
if (($validMetadata['title'] ?? null) !== 'Hidden Subtype Boundary XMP Title') {
    throw new RuntimeException('Expected single indirect XML subtype helper to preserve document XMP.');
}
if (isset($validMetadata['catalog']['metadata_stream_review'])) {
    throw new RuntimeException('Expected valid indirect XML subtype helper not to emit a rejection row.');
}
if (!is_string($validEncoded) || str_contains($validEncoded, 'rejected_missing_metadata_stream_subtype')) {
    throw new RuntimeException('Expected valid indirect XML subtype helper not to reuse rejection metadata.');
}
if (str_contains($validText, 'Hidden Subtype Boundary XMP Title')) {
    throw new RuntimeException('Expected accepted XMP metadata text to remain outside visible paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-subtype-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-subtype-boundary',
    'native_boundary' => 'Catalog /Metadata streams with /Type /Metadata require a single /Subtype /XML name before document XMP promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'rejected_metadata_source' => $invalidMetadata['source'] ?? [],
    'review_status' => $invalidReview['status'] ?? null,
    'info_fallback_title_selected' => ($invalidMetadata['title'] ?? null) === 'Subtype Boundary Info Title',
    'accepted_as_document_xmp' => $invalidReview['accepted_as_document_xmp'] ?? null,
    'payload_included' => $invalidReview['payload_included'] ?? null,
    'xmp_payload_redacted' => is_string($invalidEncoded) && !str_contains($invalidEncoded, 'Hidden Subtype Boundary XMP Title'),
    'visible_text_excludes_rejected_xmp' => !str_contains($invalidText, 'Hidden Subtype Boundary XMP Title'),
    'valid_indirect_subtype_source' => $validMetadata['source'] ?? [],
    'valid_indirect_subtype_promoted' => ($validMetadata['title'] ?? null) === 'Hidden Subtype Boundary XMP Title',
    'valid_indirect_subtype_has_no_rejection' => !isset($validMetadata['catalog']['metadata_stream_review']),
    'valid_visible_text_excludes_xmp' => !str_contains($validText, 'Hidden Subtype Boundary XMP Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($invalidMetadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-metadata-review ' . $htmlJson([
    'status' => $invalidReview['status'] ?? null,
    'type' => $invalidReview['type'] ?? null,
    'type_values' => $invalidReview['type_values'] ?? [],
    'subtype_entry_count' => $invalidReview['subtype_entry_count'] ?? null,
    'accepted_as_document_xmp' => $invalidReview['accepted_as_document_xmp'] ?? null,
    'payload_included' => $invalidReview['payload_included'] ?? null,
    'xmp_summary' => $invalidReview['xmp_summary'] ?? [],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($invalidText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
