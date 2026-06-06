<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata generation boundary body) Tj ET';
$currentPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Outline Metadata Generation Payload</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$stalePayload = str_replace('Current Outline Metadata Generation Payload', 'Stale Outline Metadata Generation Payload', $currentPayload);
$currentStream = gzcompress($currentPayload);
$staleStream = gzcompress($stalePayload);
if (!is_string($currentStream) || !is_string($staleStream)) {
    throw new RuntimeException('Unable to compress outline metadata generation smoke streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Exact Generation Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 1 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Stale Generation Metadata Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 640] /Metadata 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleStream) . " >>\nstream\n{$staleStream}\nendstream\nendobj\n"
    . "9 1 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($currentStream) . " >>\nstream\n{$currentStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outline = new PdfOutlineExtractor();
$toc = $outline->getPdfTocWithDestinationViews($pdf);
$navigation = $outline->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$items = $metadata['document_outline']['items'] ?? [];
$currentReview = $items[0]['metadata_stream_review'] ?? [];
$staleReview = $items[1]['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

$review = [
    'scenario' => 'wordpress-pdf-outline-metadata-stream-generation-currentbase',
    'support_component' => 'native-pdf-outline-item-metadata-generation-review',
    'native_boundary' => 'outline item /Metadata stream references preserve exact object generation and stale-generation payloads stay review-only/unresolved',
    'outline_titles' => array_column($items, 'title'),
    'toc_titles' => array_column($toc, 'title'),
    'exact_metadata_generation' => $currentReview['object_generation'] ?? null,
    'exact_metadata_status' => $currentReview['status'] ?? null,
    'stale_metadata_generation' => $staleReview['object_generation'] ?? null,
    'stale_metadata_status' => $staleReview['status'] ?? null,
    'current_payload_redacted' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Current Outline Metadata Generation Payload'),
    'stale_payload_redacted' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Outline Metadata Generation Payload'),
    'navigation_payload_redacted' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'Current Outline Metadata Generation Payload')
        && !str_contains($encodedNavigation, 'Stale Outline Metadata Generation Payload'),
    'visible_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'exact_metadata_generation',
    'stale_metadata_generation',
] as $requiredGeneration) {
    if (!is_int($review[$requiredGeneration])) {
        throw new RuntimeException('Missing outline metadata generation field: ' . $requiredGeneration);
    }
}
if (
    $review['exact_metadata_generation'] !== 1
    || $review['exact_metadata_status'] !== 'reviewed_outline_item_metadata_stream'
    || $review['stale_metadata_generation'] !== 0
    || $review['stale_metadata_status'] !== 'unresolved_metadata_reference'
    || !$review['current_payload_redacted']
    || !$review['stale_payload_redacted']
    || !$review['navigation_payload_redacted']
    || $plainText !== 'Outline metadata generation boundary body'
) {
    throw new RuntimeException('Outline metadata generation smoke failed.');
}

echo '<!-- markerpdf-outline-metadata-stream-generation-currentbase ' . $htmlJson($review) . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline generation review\"><ul>\n";
foreach ($items as $item) {
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
