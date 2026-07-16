<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata summary visible body) Tj ET';
$catalogXmp = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Catalog Metadata Summary</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$itemXmp = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden WordPress Bookmark Metadata</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$malformedItemXmp = str_replace('Hidden WordPress Bookmark Metadata', 'Hidden Malformed WordPress Bookmark Metadata', $itemXmp);
$trailingItemXmp = str_replace('Hidden WordPress Bookmark Metadata', 'Hidden Trailing WordPress Bookmark Metadata', $itemXmp);

$catalogStream = gzcompress($catalogXmp);
$itemStream = gzcompress($itemXmp);
$malformedItemStream = gzcompress($malformedItemXmp);
$trailingItemStream = gzcompress($trailingItemXmp);
if (
    !is_string($catalogStream)
    || !is_string($itemStream)
    || !is_string($malformedItemStream)
    || !is_string($trailingItemStream)
) {
    throw new RuntimeException('Unable to compress WordPress outline metadata summary streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 20 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Metadata Runbook) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /Metadata 8 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Malformed Metadata Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 680] /A 12 0 R /Metadata 9 0 R 10 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($malformedItemStream) . " >>\nstream\n{$malformedItemStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($trailingItemStream) . " >>\nstream\n{$trailingItemStream}\nendstream\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/wordpress-bookmark-metadata-review) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($catalogStream) . " >>\nstream\n{$catalogStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress Catalog Metadata Summary') {
    throw new RuntimeException('Expected catalog XMP to remain the document title.');
}
if (($outline['item_metadata_stream_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected outline item metadata stream summary count.');
}
if (($outline['item_metadata_stream_statuses'] ?? []) !== [
    'reviewed_outline_item_metadata_stream',
    'rejected_malformed_outline_item_metadata_operand',
]) {
    throw new RuntimeException('Expected reviewed and malformed bookmark metadata statuses.');
}
if (($outline['item_metadata_stream_objects'] ?? []) !== [8, 9]) {
    throw new RuntimeException('Expected item metadata object provenance.');
}
if (($outline['item_metadata_stream_trailing_reference_objects'] ?? []) !== [10]) {
    throw new RuntimeException('Expected malformed item metadata trailing object provenance.');
}
if (($navigation['outline_action_review_actions'][0]['outline_metadata_stream_review']['status'] ?? null) !== 'rejected_malformed_outline_item_metadata_operand') {
    throw new RuntimeException('Expected action review rows to carry malformed bookmark metadata review.');
}

foreach ([
    $itemXmp,
    $malformedItemXmp,
    $trailingItemXmp,
    'Hidden WordPress Bookmark Metadata',
    'Hidden Malformed WordPress Bookmark Metadata',
    'Hidden Trailing WordPress Bookmark Metadata',
] as $payload) {
    if (!is_string($metadataEncoded) || str_contains($metadataEncoded, $payload)) {
        throw new RuntimeException('Expected bookmark-local metadata payload to stay out of document metadata roots.');
    }
    if (!is_string($navigationEncoded) || str_contains($navigationEncoded, $payload)) {
        throw new RuntimeException('Expected bookmark-local metadata payload to stay out of navigation review rows.');
    }
    if (str_contains($plainText, $payload)) {
        throw new RuntimeException('Expected bookmark-local metadata payload to stay out of visible WordPress text.');
    }
}
if (str_contains($plainText, 'Metadata Runbook') || str_contains($plainText, 'Malformed Metadata Appendix')) {
    throw new RuntimeException('Expected outline titles to stay out of visible paragraph text.');
}

echo '<!-- markerpdf-outline-metadata-item-summary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-item-summary-currentbase',
    'support_component' => 'native-pdf-outline-item-metadata-summary-review',
    'native_boundary' => 'outline item /Metadata streams are bookmark-local review metadata summarized at the outline level, not document XMP roots or visible text',
    'document_title' => $metadata['title'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'item_metadata_stream_count' => $outline['item_metadata_stream_count'] ?? null,
    'item_metadata_stream_statuses' => $outline['item_metadata_stream_statuses'] ?? [],
    'item_metadata_stream_objects' => $outline['item_metadata_stream_objects'] ?? [],
    'item_metadata_stream_trailing_reference_objects' => $outline['item_metadata_stream_trailing_reference_objects'] ?? [],
    'navigation_action_metadata_status' => $navigation['outline_action_review_actions'][0]['outline_metadata_stream_review']['status'] ?? null,
    'bookmark_xmp_not_document_metadata' => is_string($metadataEncoded) && !str_contains($metadataEncoded, 'Hidden WordPress Bookmark Metadata'),
    'bookmark_xmp_not_navigation_payload' => is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Hidden WordPress Bookmark Metadata'),
    'visible_text_excludes_bookmark_metadata' => !str_contains($plainText, 'Hidden WordPress Bookmark Metadata')
        && !str_contains($plainText, 'Metadata Runbook'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    $review = is_array($item['metadata_stream_review'] ?? null) ? $item['metadata_stream_review'] : [];
    echo '<li data-marker-outline-object="' . (int) ($item['outline_object'] ?? 0)
        . '" data-marker-metadata-status="' . htmlspecialchars((string) ($review['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
