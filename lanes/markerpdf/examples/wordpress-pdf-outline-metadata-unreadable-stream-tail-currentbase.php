<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress unreadable outline metadata stream tail body) Tj ET';
$rootPayload = '<?x:xmpmeta>Hidden WordPress unreadable outline root metadata payload</x:xmpmeta>';
$itemPayload = '<?x:xmpmeta>Hidden WordPress unreadable outline item metadata payload</x:xmpmeta>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (Unreadable WordPress Metadata Stream Tail) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootPayload) . " >>\nstream\n{$rootPayload}\nendstream /A 12 0 R\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemPayload) . " >>\nstream\n{$itemPayload}\nendstream /A 13 0 R\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden wordpress root metadata tail action'\\)) >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/hidden-wordpress-item-metadata-tail) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$rootReview = $outline['metadata_stream_review'] ?? [];
$itemReview = $outline['items'][0]['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($rootReview['status'] ?? null) !== 'rejected_malformed_outline_root_metadata_stream') {
    throw new RuntimeException('Expected unreadable outline root metadata stream tail to fail closed.');
}
if (($itemReview['status'] ?? null) !== 'rejected_malformed_outline_item_metadata_stream') {
    throw new RuntimeException('Expected unreadable outline item metadata stream tail to fail closed.');
}
if (($rootReview['stream_tail_operand_rejected'] ?? null) !== true
    || ($itemReview['stream_tail_operand_rejected'] ?? null) !== true
    || ($rootReview['native_metadata_decode'] ?? null) !== false
    || ($itemReview['native_metadata_decode'] ?? null) !== false
) {
    throw new RuntimeException('Expected tail operands to be review-only without native metadata decoding.');
}
if ($plainText !== 'WordPress unreadable outline metadata stream tail body') {
    throw new RuntimeException('Expected only page body text in WordPress paragraph output.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedMetadata, $rootPayload)
    || str_contains($encodedMetadata, $itemPayload)
    || str_contains($encodedNavigation, $rootPayload)
    || str_contains($encodedNavigation, $itemPayload)
    || str_contains($encodedNavigation, 'hidden-wordpress-item-metadata-tail')
    || str_contains($plainText, 'Hidden WordPress unreadable outline')
    || str_contains($plainText, 'hidden wordpress root metadata tail action')
) {
    throw new RuntimeException('Expected unreadable outline metadata stream payloads and hidden tail actions to stay out of import output.');
}

echo '<!-- markerpdf-outline-metadata-unreadable-stream-tail-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-unreadable-stream-tail-currentbase',
    'support_component' => 'native-pdf-outline-metadata-stream-tail-review',
    'native_boundary' => 'outline root and item /Metadata streams with unreadable Flate payloads and trailing operands are rejected as review-only metadata before WordPress import',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'root_metadata_stream_status' => $rootReview['status'] ?? null,
    'item_metadata_stream_status' => $itemReview['status'] ?? null,
    'root_stream_tail_operand_rejected' => $rootReview['stream_tail_operand_rejected'] ?? null,
    'item_stream_tail_operand_rejected' => $itemReview['stream_tail_operand_rejected'] ?? null,
    'root_native_metadata_decode' => $rootReview['native_metadata_decode'] ?? null,
    'item_native_metadata_decode' => $itemReview['native_metadata_decode'] ?? null,
    'metadata_payload_excluded_from_document_metadata' => !str_contains($encodedMetadata, $rootPayload)
        && !str_contains($encodedMetadata, $itemPayload),
    'metadata_payload_excluded_from_navigation_metadata' => !str_contains($encodedNavigation, $rootPayload)
        && !str_contains($encodedNavigation, $itemPayload),
    'metadata_payload_excluded_from_visible_text' => !str_contains($plainText, 'Hidden WordPress unreadable outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF unreadable outline metadata stream review\"><ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-outline-metadata-tail="' . htmlspecialchars((string) ($itemReview['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) (($item['page'] ?? 0) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
