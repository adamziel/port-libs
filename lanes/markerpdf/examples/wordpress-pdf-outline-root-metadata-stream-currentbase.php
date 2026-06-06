<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root metadata visible body) Tj ET';
$rootMetadataPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden WordPress Outline Root Metadata</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$rootMetadataStream = gzcompress($rootMetadataPayload);
if (!is_string($rootMetadataStream)) {
    throw new RuntimeException('Unable to compress WordPress outline root metadata stream.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Root Metadata Outline) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootMetadataStream) . " >>\nstream\n{$rootMetadataStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($review['status'] ?? null) !== 'reviewed_outline_root_metadata_stream') {
    throw new RuntimeException('Expected outline root metadata stream review summary.');
}
if (($review['payload_included'] ?? null) !== false || ($review['visible_text_source'] ?? null) !== false) {
    throw new RuntimeException('Expected outline root metadata stream to stay payload-free and non-visible.');
}
if (($review['accepted_as_document_xmp'] ?? null) !== false || array_key_exists('title', $metadata)) {
    throw new RuntimeException('Expected outline root metadata stream not to become document XMP metadata.');
}
if (($review['sha256'] ?? null) !== hash('sha256', $rootMetadataPayload)) {
    throw new RuntimeException('Expected outline root metadata stream hash.');
}
if ($plainText !== 'WordPress outline root metadata visible body') {
    throw new RuntimeException('Expected only page body text in WordPress paragraph output.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedMetadata, $rootMetadataPayload)
    || str_contains($encodedNavigation, $rootMetadataPayload)
    || str_contains($plainText, 'Hidden WordPress Outline Root Metadata')
) {
    throw new RuntimeException('Expected outline root metadata payload to stay out of review JSON and visible text.');
}

echo '<!-- markerpdf-outline-root-metadata-stream-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-metadata-stream-currentbase',
    'support_component' => 'native-pdf-outline-root-metadata-stream-review',
    'native_boundary' => 'outline root /Metadata streams are document-outline review metadata, not document XMP or WordPress paragraph text',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'metadata_stream_status' => $review['status'] ?? null,
    'metadata_stream_object' => $review['object_number'] ?? null,
    'metadata_stream_bytes' => $review['bytes'] ?? null,
    'metadata_stream_sha256' => $review['sha256'] ?? null,
    'metadata_payload_excluded_from_document_metadata' => !str_contains($encodedMetadata, $rootMetadataPayload),
    'metadata_payload_excluded_from_navigation_metadata' => !str_contains($encodedNavigation, $rootMetadataPayload),
    'metadata_payload_excluded_from_visible_text' => !str_contains($plainText, 'Hidden WordPress Outline Root Metadata'),
    'accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline root metadata review\"><ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-outline-root-metadata-stream="' . htmlspecialchars((string) ($review['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) (($item['page'] ?? 0) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
