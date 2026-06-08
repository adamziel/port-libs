<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Hidden Outline Root Metadata Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>WordPress Outline Root Metadata Editor</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>outline-root-duplicate-type-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>WordPress Outline Root Metadata Boundary Producer</pdf:Producer>'
    . '<xmp:CreateDate>2026-06-08T21:25:54Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress WordPress outline root metadata duplicate Type XMP.');
}

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root metadata duplicate Type body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Root Duplicate Type Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
    . "8 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
    . "stream\n{$compressedXmp}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['metadata_stream_review'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($review['source'] ?? null) !== 'outline_root_metadata_stream') {
    throw new RuntimeException('Expected outline root metadata stream review.');
}
if (($review['outline_metadata_scope'] ?? null) !== 'root'
    || ($review['document_xmp_promotion_boundary'] ?? null) !== 'outline_root_review_only'
) {
    throw new RuntimeException('Expected root-local outline metadata stream scope.');
}
if (($review['status'] ?? null) !== 'rejected_duplicate_metadata_stream_type_keys') {
    throw new RuntimeException('Expected duplicate Type/Subtype outline root metadata stream to be rejected precisely.');
}
if (($review['duplicate_keys'] ?? null) !== ['Type', 'Subtype']) {
    throw new RuntimeException('Expected duplicate Type and Subtype keys to be recorded.');
}
if (($review['accepted_as_document_xmp'] ?? null) !== false || ($review['payload_included'] ?? null) !== false) {
    throw new RuntimeException('Expected outline root metadata stream payload to remain review-only.');
}
if (($review['sha256'] ?? null) !== hash('sha256', $xmp)) {
    throw new RuntimeException('Expected duplicate Type outline root metadata XMP hash.');
}
if (array_column($toc, 'title') !== ['WordPress Root Duplicate Type Metadata Chapter']) {
    throw new RuntimeException('Expected outline TOC row to remain importable.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['WordPress Root Duplicate Type Metadata Chapter']) {
    throw new RuntimeException('Expected navigation row to remain importable.');
}
if (!is_string($metadataEncoded)
    || str_contains($metadataEncoded, 'WordPress Hidden Outline Root Metadata Title')
    || str_contains($metadataEncoded, 'outline-root-duplicate-type-boundary')
    || !is_string($navigationEncoded)
    || str_contains($navigationEncoded, 'WordPress Hidden Outline Root Metadata Title')
) {
    throw new RuntimeException('Expected duplicate Type outline root metadata XMP values to stay redacted.');
}
if (str_contains($plainText, 'WordPress Root Duplicate Type Metadata Chapter')
    || str_contains($plainText, 'WordPress Hidden Outline Root Metadata Title')
    || str_contains($plainText, 'outline-root-duplicate-type-boundary')
) {
    throw new RuntimeException('Expected outline root metadata payload to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-root-metadata-stream-duplicate-type-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-metadata-stream-duplicate-type-currentbase',
    'support_component' => 'native-pdf-outline-root-metadata-stream-review',
    'native_boundary' => 'outline root Metadata streams with duplicate Type/Subtype keys are root-local review metadata and never document XMP',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'metadata_source' => $review['source'] ?? null,
    'metadata_scope' => $review['outline_metadata_scope'] ?? null,
    'metadata_status' => $review['status'] ?? null,
    'metadata_duplicate_keys' => $review['duplicate_keys'] ?? [],
    'metadata_type_values' => $review['type_values'] ?? [],
    'metadata_subtype_values' => $review['subtype_values'] ?? [],
    'metadata_payload_included' => $review['payload_included'] ?? null,
    'metadata_accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'metadata_sha256' => $review['sha256'] ?? null,
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'visible_text_excludes_outline_root_metadata_payload' => !str_contains($plainText, 'WordPress Hidden Outline Root Metadata Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline root metadata stream duplicate Type review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $row) {
    echo '<li data-marker-outline-object="' . (int) ($row['outline_object'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($row['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
