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

$validPayload = $xmpPacket('WordPress Valid Outline Metadata Payload');
$filterPayload = $xmpPacket('WordPress Hidden Filter Operand Metadata Payload');
$decodeParmsPayload = $xmpPacket('WordPress Hidden DecodeParms Operand Metadata Payload');
$validStream = gzcompress($validPayload);
$filterStream = gzcompress($filterPayload);
$decodeParmsStream = gzcompress($decodeParmsPayload);
if (!is_string($validStream) || !is_string($filterStream) || !is_string($decodeParmsStream)) {
    throw new RuntimeException('Unable to compress WordPress outline metadata stream operand payloads.');
}

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata operand boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Valid Outline Metadata) /Parent 5 0 R /Next 7 0 R /Dest [3 0 R /FitH 720] /Metadata 20 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Filter Operand Boundary) /Parent 5 0 R /Prev 6 0 R /Next 8 0 R /Dest [3 0 R /FitH 700] /Metadata 21 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (DecodeParms Operand Boundary) /Parent 5 0 R /Prev 7 0 R /Dest [3 0 R /FitH 680] /Metadata 22 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($validStream) . " >>\nstream\n{$validStream}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /Metadata /Subtype /XML /Filter 23 0 R /Length " . strlen($filterStream) . " >>\nstream\n{$filterStream}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /DecodeParms 24 0 R /Length " . strlen($decodeParmsStream) . " >>\nstream\n{$decodeParmsStream}\nendstream\nendobj\n"
    . "23 0 obj\n/FlateDecode /Crypt 25 0 R\nendobj\n"
    . "24 0 obj\n<< /Predictor 1 /Columns 1 >> /Crypt 25 0 R\nendobj\n"
    . "25 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress outline metadata operand helper tail'\\)) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$validReview = $items[0]['metadata_stream_review'] ?? [];
$filterReview = $items[1]['metadata_stream_review'] ?? [];
$decodeParmsReview = $items[2]['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (array_column($items, 'title') !== ['Valid Outline Metadata', 'Filter Operand Boundary', 'DecodeParms Operand Boundary']) {
    throw new RuntimeException('Expected all outline metadata titles to remain navigable.');
}
if (($validReview['status'] ?? null) !== 'reviewed_outline_item_metadata_stream'
    || ($filterReview['status'] ?? null) !== 'rejected_malformed_metadata_stream_filter_operand'
    || ($decodeParmsReview['status'] ?? null) !== 'rejected_malformed_metadata_stream_decodeparms_operand'
) {
    throw new RuntimeException('Expected valid outline metadata review plus fail-closed malformed stream operand reviews.');
}
if (($filterReview['native_metadata_decode'] ?? null) !== false
    || ($decodeParmsReview['native_metadata_decode'] ?? null) !== false
    || array_key_exists('bytes', $filterReview)
    || array_key_exists('sha256', $filterReview)
    || array_key_exists('bytes', $decodeParmsReview)
    || array_key_exists('sha256', $decodeParmsReview)
) {
    throw new RuntimeException('Expected malformed outline metadata stream operands to fail before byte hashing.');
}
if ($plainText !== 'WordPress outline metadata operand boundary body') {
    throw new RuntimeException('Expected only page body text in WordPress paragraph output.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedMetadata, $validPayload)
    || str_contains($encodedMetadata, $filterPayload)
    || str_contains($encodedMetadata, $decodeParmsPayload)
    || str_contains($encodedNavigation, $validPayload)
    || str_contains($encodedNavigation, $filterPayload)
    || str_contains($encodedNavigation, $decodeParmsPayload)
    || str_contains($encodedMetadata, 'wordpress outline metadata operand helper tail')
    || str_contains($encodedNavigation, 'wordpress outline metadata operand helper tail')
    || str_contains($plainText, 'WordPress Hidden')
    || str_contains($plainText, 'WordPress Valid Outline Metadata Payload')
) {
    throw new RuntimeException('Expected outline metadata stream payloads and helper tails to stay out of import output.');
}

echo '<!-- markerpdf-outline-metadata-stream-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-stream-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-stream-operand-boundary',
    'native_boundary' => 'outline item /Metadata stream Filter and DecodeParms operands fail closed before XML summary or hash review',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'valid_metadata_stream_status' => $validReview['status'] ?? null,
    'filter_operand_status' => $filterReview['status'] ?? null,
    'decodeparms_operand_status' => $decodeParmsReview['status'] ?? null,
    'filter_operand_policy' => $filterReview['filter_operand_policy'] ?? null,
    'decodeparms_operand_policy' => $decodeParmsReview['decodeparms_operand_policy'] ?? null,
    'malformed_streams_decoded' => (($filterReview['native_metadata_decode'] ?? null) !== false)
        || (($decodeParmsReview['native_metadata_decode'] ?? null) !== false),
    'metadata_payloads_excluded_from_document_metadata' => !str_contains($encodedMetadata, $validPayload)
        && !str_contains($encodedMetadata, $filterPayload)
        && !str_contains($encodedMetadata, $decodeParmsPayload),
    'metadata_payloads_excluded_from_navigation_metadata' => !str_contains($encodedNavigation, $validPayload)
        && !str_contains($encodedNavigation, $filterPayload)
        && !str_contains($encodedNavigation, $decodeParmsPayload),
    'metadata_payloads_excluded_from_visible_text' => !str_contains($plainText, 'WordPress Hidden')
        && !str_contains($plainText, 'WordPress Valid Outline Metadata Payload'),
    'executes_python_or_models' => false,
    'executes_ocr' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata operand boundary\"><ul>\n";
foreach ($items as $item) {
    $review = $item['metadata_stream_review'] ?? [];
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-metadata-status="' . htmlspecialchars((string) ($review['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
