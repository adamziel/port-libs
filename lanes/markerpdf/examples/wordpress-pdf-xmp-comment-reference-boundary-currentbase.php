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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Comment Split XMP Smoke Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Comment Reference Editor</rdf:li><rdf:li>WordPress Import Review</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Catalog Metadata reference operands are split by PDF comments</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-comment-reference-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Comment Reference Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Comment Reference Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-07T10:06:11Z</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-07T10:06:11Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress comment-reference XMP smoke packet.');
}

$buildPdf = static function (string $catalogMetadataValue, string $bodyText, string $extraObjects = '') use ($compressedXmp): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata {$catalogMetadataValue} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . $extraObjects
        . "6 0 obj\n<< /Title (Comment Reference Info Title) /Author (Info Comment Author) /Producer (Info Comment Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$validPdf = $buildPdf(
    "5 % object number/generation split by PDF comment\n 0 % generation/R split by PDF comment\n R",
    'Comment Reference XMP Body'
);
$malformedPdf = $buildPdf(
    "5 % object number/generation split by PDF comment\n 0 R 7 0 R",
    'Comment Reference Trailing Operand Body',
    "7 0 obj\n<< /S /JavaScript /JS (app.alert\\('comment reference metadata tail'\\)) >>\nendobj\n"
);

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();

$validMetadata = $metadataExtractor->extractDocumentMetadata($validPdf);
$validText = $textExtractor->extractPlainText($validPdf);
$malformedMetadata = $metadataExtractor->extractDocumentMetadata($malformedPdf);
$malformedText = $textExtractor->extractPlainText($malformedPdf);
$malformedReview = $malformedMetadata['catalog']['metadata_stream_review'] ?? [];
$malformedEncoded = json_encode($malformedMetadata, JSON_UNESCAPED_SLASHES);

if (($validMetadata['title'] ?? null) !== 'Comment Split XMP Smoke Title') {
    throw new RuntimeException('Expected comment-split catalog Metadata reference to promote document XMP.');
}
if (($validMetadata['info']['Title'] ?? null) !== 'Comment Reference Info Title') {
    throw new RuntimeException('Expected trailer Info metadata to remain available after XMP promotion.');
}
if (($validMetadata['catalog']['metadata_stream_review'] ?? null) !== null) {
    throw new RuntimeException('Expected valid comment-split catalog Metadata reference to avoid boundary review.');
}
if (str_contains($validText, 'Comment Split XMP Smoke Title')) {
    throw new RuntimeException('Promoted XMP title leaked into visible WordPress text.');
}
if (($malformedReview['status'] ?? null) !== 'rejected_malformed_metadata_operand') {
    throw new RuntimeException('Expected trailing catalog Metadata operand to stay review-only.');
}
if (($malformedReview['object_number'] ?? null) !== 5 || ($malformedReview['trailing_reference_object_numbers'] ?? []) !== [7]) {
    throw new RuntimeException('Expected malformed Metadata operand review to preserve reference ownership.');
}
if (!is_string($malformedEncoded) || str_contains($malformedEncoded, 'comment reference metadata tail')) {
    throw new RuntimeException('Trailing catalog Metadata action operand leaked into metadata output.');
}
if (str_contains($malformedText, 'comment reference metadata tail')) {
    throw new RuntimeException('Trailing catalog Metadata action operand leaked into visible WordPress text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-comment-reference-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-metadata-comment-reference-boundary',
    'native_boundary' => 'PDF comments are whitespace inside catalog /Metadata indirect references before document XMP promotion',
    'promotes_comment_split_xmp' => ($validMetadata['title'] ?? null) === 'Comment Split XMP Smoke Title',
    'info_fallback_preserved' => ($validMetadata['info']['Title'] ?? null) === 'Comment Reference Info Title',
    'xmp_not_visible_text' => !str_contains($validText, 'Comment Split XMP Smoke Title'),
    'trailing_operand_rejected' => ($malformedReview['status'] ?? null) === 'rejected_malformed_metadata_operand',
    'trailing_operand_object_number' => $malformedReview['object_number'] ?? null,
    'trailing_reference_object_numbers' => $malformedReview['trailing_reference_object_numbers'] ?? [],
    'trailing_action_redacted' => is_string($malformedEncoded)
        && !str_contains($malformedEncoded, 'comment reference metadata tail')
        && !str_contains($malformedText, 'comment reference metadata tail'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($validMetadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-metadata-comment-reference-review ' . $htmlJson([
    'valid_source' => $validMetadata['source'] ?? [],
    'malformed_status' => $malformedReview['status'] ?? null,
    'metadata_operand_count' => $malformedReview['metadata_operand_count'] ?? null,
    'object_number' => $malformedReview['object_number'] ?? null,
    'trailing_reference_object_numbers' => $malformedReview['trailing_reference_object_numbers'] ?? [],
    'accepted_as_document_xmp' => $malformedReview['accepted_as_document_xmp'] ?? null,
    'payload_included' => $malformedReview['payload_included'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($validText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
