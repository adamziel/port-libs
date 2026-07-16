<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata stream type body) Tj ET';
$embeddedPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Rejected Embedded Outline Metadata</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$malformedPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Rejected Malformed Outline Metadata</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$embeddedStream = gzcompress($embeddedPayload);
$malformedStream = gzcompress($malformedPayload);
if (!is_string($embeddedStream) || !is_string($malformedStream)) {
    throw new RuntimeException('Unable to compress WordPress outline metadata stream type payloads.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Non Metadata Stream Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Malformed Metadata Stream Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /Fit] /Metadata 10 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /Length " . strlen($embeddedStream) . " >>\nstream\n{$embeddedStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($malformedStream) . " >>\nstream\n{$malformedStream}\nendstream /A 12 0 R\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress malformed outline metadata tail action'\\)) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$items = $metadata['document_outline']['items'] ?? [];
$embeddedReview = $items[0]['metadata_stream_review'] ?? [];
$malformedReview = $items[1]['metadata_stream_review'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($embeddedReview['status'] ?? null) !== 'rejected_non_metadata_outline_item_stream') {
    throw new RuntimeException('Expected non-metadata outline /Metadata stream to be rejected as review-only.');
}
if (($malformedReview['status'] ?? null) !== 'rejected_malformed_outline_item_metadata_stream') {
    throw new RuntimeException('Expected malformed outline /Metadata stream object to be rejected as review-only.');
}
if (array_column($toc, 'title') !== ['WordPress Non Metadata Stream Chapter', 'WordPress Malformed Metadata Stream Appendix']) {
    throw new RuntimeException('Expected outline TOC rows to remain importable.');
}
if ($plainText !== 'WordPress outline metadata stream type body') {
    throw new RuntimeException('Expected visible page text to render without outline metadata payloads.');
}
if (!is_string($metadataEncoded)
    || !is_string($navigationEncoded)
    || str_contains($metadataEncoded, $embeddedPayload)
    || str_contains($metadataEncoded, $malformedPayload)
    || str_contains($navigationEncoded, 'WordPress Rejected Embedded Outline Metadata')
    || str_contains($plainText, 'WordPress Rejected Malformed Outline Metadata')
    || str_contains($metadataEncoded, 'wordpress malformed outline metadata tail action')
) {
    throw new RuntimeException('Expected rejected outline metadata stream payloads and tail actions to stay out of WordPress import output.');
}

echo '<!-- markerpdf-outline-metadata-stream-type-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-stream-type-boundary-currentbase',
    'support_component' => 'native-pdf-outline-item-metadata-stream-boundary',
    'native_boundary' => 'outline item /Metadata accepts only single-token metadata XML stream objects; non-metadata and malformed streams are review-only rejected rows',
    'outline_titles' => array_column($items, 'title'),
    'non_metadata_status' => $embeddedReview['status'] ?? null,
    'non_metadata_type' => $embeddedReview['type'] ?? null,
    'non_metadata_subtype' => $embeddedReview['subtype'] ?? null,
    'malformed_status' => $malformedReview['status'] ?? null,
    'malformed_type' => $malformedReview['type'] ?? null,
    'metadata_payload_included' => [$embeddedReview['payload_included'] ?? null, $malformedReview['payload_included'] ?? null],
    'visible_text_excludes_rejected_metadata_payloads' => !str_contains($plainText, 'WordPress Rejected Embedded Outline Metadata')
        && !str_contains($plainText, 'WordPress Rejected Malformed Outline Metadata'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata stream review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $row) {
    echo '<li data-marker-outline-object="' . (int) ($row['outline_object'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($row['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
