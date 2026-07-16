<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress direct outline-root metadata fallback body) Tj ET';
$metadataPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Hidden Direct Root Metadata</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$metadataStream = gzcompress($metadataPayload);
if (!is_string($metadataStream)) {
    throw new RuntimeException('Unable to compress WordPress direct outline-root metadata stream.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Outlines << /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Direct Root Metadata Chapter) >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['WordPress Direct Root Metadata Chapter']) {
    throw new RuntimeException('Expected direct outline-root item to remain review metadata.');
}
if (($review['status'] ?? null) !== 'reviewed_outline_root_metadata_stream') {
    throw new RuntimeException('Expected direct outline-root Metadata stream to be reviewed.');
}
if (($review['object_number'] ?? null) !== 8 || ($review['accepted_as_document_xmp'] ?? null) !== false) {
    throw new RuntimeException('Expected outline-root Metadata stream to stay out of document XMP.');
}
if ($plainText !== 'WordPress direct outline-root metadata fallback body') {
    throw new RuntimeException('Expected fallback text to exclude direct outline-root Metadata payload.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedLightweight)
    || str_contains($encodedMetadata, $metadataPayload)
    || str_contains($encodedLightweight, $metadataPayload)
    || str_contains($plainText, 'WordPress Hidden Direct Root Metadata')
) {
    throw new RuntimeException('Expected direct outline-root Metadata payload to stay out of metadata and visible text.');
}

echo '<!-- markerpdf-outline-direct-root-metadata-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-direct-root-metadata-currentbase',
    'support_component' => 'native-pdf-direct-outline-root-metadata-boundary',
    'native_boundary' => 'direct catalog /Outlines root /Metadata streams stay review-only and are excluded from fallback WordPress text',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'metadata_stream_status' => $review['status'] ?? null,
    'metadata_stream_object' => $review['object_number'] ?? null,
    'metadata_stream_accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'fallback_text' => $plainText,
    'metadata_payload_excluded_from_document_metadata' => !str_contains($encodedMetadata, $metadataPayload),
    'metadata_payload_excluded_from_lightweight_metadata' => !str_contains($encodedLightweight, $metadataPayload),
    'metadata_payload_excluded_from_visible_text' => !str_contains($plainText, 'WordPress Hidden Direct Root Metadata'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
