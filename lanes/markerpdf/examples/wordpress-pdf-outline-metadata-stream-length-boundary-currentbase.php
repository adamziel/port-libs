<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmpPacket = static function (string $hiddenTitle): string {
    return '<?xpacket begin=""?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($hiddenTitle, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
};

$rootPayload = $xmpPacket('WordPress Hidden Root Length Metadata Payload');
$itemPayload = $xmpPacket('WordPress Hidden Item Length Metadata Payload');
$rootStream = gzcompress($rootPayload);
$itemStream = gzcompress($itemPayload);
if (!is_string($rootStream) || !is_string($itemStream)) {
    throw new RuntimeException('Unable to compress WordPress outline metadata length-boundary payloads.');
}

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata length boundary body) Tj ET';
$rootLength = strlen($rootStream);
$itemLength = strlen($itemStream);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /Metadata 20 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Length Boundary Import Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 21 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length {$rootLength} 25 0 R >>\nstream\n{$rootStream}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length 22 0 R >>\nstream\n{$itemStream}\nendstream\nendobj\n"
    . "22 0 obj\n{$itemLength} 25 0 R\nendobj\n"
    . "25 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress outline metadata length operand tail'\\)) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$rootReview = $outline['metadata_stream_review'] ?? [];
$itemReview = $items[0]['metadata_stream_review'] ?? [];
$navigationRootReview = $navigation['outline_root_review']['metadata_stream_review'] ?? [];
$navigationItemReview = $navigation['outline'][0]['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($rootReview['status'] ?? null) !== 'rejected_malformed_metadata_stream_length_operand'
    || ($itemReview['status'] ?? null) !== 'rejected_malformed_metadata_stream_length_operand'
    || ($rootReview['length_operand_boundary_rejected'] ?? null) !== true
    || ($itemReview['length_operand_boundary_rejected'] ?? null) !== true
) {
    throw new RuntimeException('Expected outline root and item metadata streams to reject malformed Length operands.');
}
if (($navigationRootReview['status'] ?? null) !== ($rootReview['status'] ?? null)
    || ($navigationItemReview['status'] ?? null) !== ($itemReview['status'] ?? null)
) {
    throw new RuntimeException('Expected navigation review metadata to preserve malformed Length operand reviews.');
}
if (array_column($toc, 'title') !== ['Length Boundary Import Chapter']
    || array_column($navigation['outline'] ?? [], 'title') !== ['Length Boundary Import Chapter']
) {
    throw new RuntimeException('Expected malformed metadata Length operands not to remove safe outline navigation.');
}
if ($plainText !== 'WordPress outline metadata length boundary body') {
    throw new RuntimeException('Expected only page body text in WordPress paragraph output.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedMetadata, $rootPayload)
    || str_contains($encodedMetadata, $itemPayload)
    || str_contains($encodedNavigation, $rootPayload)
    || str_contains($encodedNavigation, $itemPayload)
    || str_contains($encodedMetadata, 'wordpress outline metadata length operand tail')
    || str_contains($encodedNavigation, 'wordpress outline metadata length operand tail')
    || str_contains($plainText, 'WordPress Hidden')
) {
    throw new RuntimeException('Expected malformed outline metadata Length operands and helper tails to stay out of import output.');
}

echo '<!-- markerpdf-outline-metadata-stream-length-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-stream-length-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-stream-length-boundary',
    'native_boundary' => 'outline root/item /Metadata stream Length operands must resolve to one non-negative integer before XML summary or hash review',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'root_length_status' => $rootReview['status'] ?? null,
    'item_length_status' => $itemReview['status'] ?? null,
    'root_length_boundary_rejected' => $rootReview['length_operand_boundary_rejected'] ?? null,
    'item_length_boundary_rejected' => $itemReview['length_operand_boundary_rejected'] ?? null,
    'metadata_payloads_excluded_from_document_metadata' => !str_contains($encodedMetadata, $rootPayload)
        && !str_contains($encodedMetadata, $itemPayload),
    'metadata_payloads_excluded_from_navigation_metadata' => !str_contains($encodedNavigation, $rootPayload)
        && !str_contains($encodedNavigation, $itemPayload),
    'metadata_payloads_excluded_from_visible_text' => !str_contains($plainText, 'WordPress Hidden'),
    'executes_python_or_models' => false,
    'executes_ocr' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata length boundary\"><ul>\n";
foreach ($items as $item) {
    $review = $item['metadata_stream_review'] ?? [];
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-metadata-status="' . htmlspecialchars((string) ($review['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
