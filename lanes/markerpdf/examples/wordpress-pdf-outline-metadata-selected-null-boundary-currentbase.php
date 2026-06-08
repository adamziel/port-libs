<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress selected null outline metadata body) Tj ET';
$rootPayload = '<outline-metadata role="root-stale">Stale WordPress root metadata stream ignored by selected null</outline-metadata>';
$itemPayload = '<outline-metadata role="item-stale">Stale WordPress item metadata stream ignored by selected null</outline-metadata>';

$rootStream = gzcompress($rootPayload);
$itemStream = gzcompress($itemPayload);
if (!is_string($rootStream) || !is_string($itemStream)) {
    throw new RuntimeException('Unable to compress selected-null outline metadata smoke streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R /Metadata null >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Selected Null Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /Metadata null >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$rootReview = $outline['metadata_stream_review'] ?? [];
$itemReview = ($outline['items'] ?? [])[0]['metadata_stream_review'] ?? [];
$navigationReview = ($navigation['outline'] ?? [])[0]['metadata_stream_review'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($rootReview['status'] ?? null) !== 'selected_null_outline_root_metadata_reference') {
    throw new RuntimeException('Expected outline root selected-null Metadata review.');
}
if (($itemReview['status'] ?? null) !== 'selected_null_outline_item_metadata_reference') {
    throw new RuntimeException('Expected outline item selected-null Metadata review.');
}
if (($navigationReview['status'] ?? null) !== 'selected_null_outline_item_metadata_reference') {
    throw new RuntimeException('Expected navigation selected-null Metadata review.');
}
if (($rootReview['selected_null_entry'] ?? null) !== true || ($itemReview['selected_null_entry'] ?? null) !== true) {
    throw new RuntimeException('Expected selected-null entries to be explicit review metadata.');
}
if (array_key_exists('sha256', $rootReview) || array_key_exists('sha256', $itemReview) || array_key_exists('object_number', $itemReview)) {
    throw new RuntimeException('Expected stale outline Metadata streams to be unselected.');
}
foreach ([$rootPayload, $itemPayload] as $payload) {
    if ((is_string($metadataEncoded) && (str_contains($metadataEncoded, $payload) || str_contains($metadataEncoded, hash('sha256', $payload))))
        || (is_string($navigationEncoded) && (str_contains($navigationEncoded, $payload) || str_contains($navigationEncoded, hash('sha256', $payload))))
        || str_contains($plainText, $payload)
    ) {
        throw new RuntimeException('Expected unselected outline Metadata payloads to stay out of review and visible text.');
    }
}
if (str_contains($plainText, 'WordPress Selected Null Metadata Chapter')) {
    throw new RuntimeException('Expected outline titles to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-outline-metadata-selected-null-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-selected-null-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-review',
    'native_boundary' => 'duplicate outline /Metadata keys whose selected value is null suppress stale stream hashes while preserving review provenance',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'root_metadata_status' => $rootReview['status'] ?? null,
    'item_metadata_status' => $itemReview['status'] ?? null,
    'selected_null_entry' => ($rootReview['selected_null_entry'] ?? null) === true
        && ($itemReview['selected_null_entry'] ?? null) === true,
    'stale_stream_hashes_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, hash('sha256', $rootPayload))
        && !str_contains($metadataEncoded, hash('sha256', $itemPayload)),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Selected Null Metadata Chapter')
        && !str_contains($plainText, 'Stale WordPress root metadata stream')
        && !str_contains($plainText, 'Stale WordPress item metadata stream'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF selected-null outline metadata review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $row) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($row['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-metadata-status="' . htmlspecialchars((string) ($row['metadata_stream_review']['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
