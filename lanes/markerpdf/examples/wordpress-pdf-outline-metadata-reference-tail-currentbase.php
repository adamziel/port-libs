<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata tailed reference body) Tj ET';
$rootPayload = '<x:xmpmeta>WordPress root metadata tail payload must stay review only</x:xmpmeta>';
$itemPayload = '<x:xmpmeta>WordPress item metadata tail payload must stay review only</x:xmpmeta>';
$tailPayload = '<x:xmpmeta>WordPress trailing metadata operand payload must stay hidden</x:xmpmeta>';
$rootStream = gzcompress($rootPayload);
$itemStream = gzcompress($itemPayload);
$tailStream = gzcompress($tailPayload);
if (!is_string($rootStream) || !is_string($itemStream) || !is_string($tailStream)) {
    throw new RuntimeException('Unable to compress WordPress outline metadata tailed-reference streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R 10 0 R /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Metadata Review) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R 10 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Clean Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($tailStream) . " >>\nstream\n{$tailStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$rootReview = $outline['metadata_stream_review'] ?? [];
$itemReview = $outline['items'][0]['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($rootReview['status'] ?? null) !== 'rejected_malformed_outline_root_metadata_operand') {
    throw new RuntimeException('Expected tailed outline root Metadata reference to be rejected.');
}
if (($itemReview['status'] ?? null) !== 'rejected_malformed_outline_item_metadata_operand') {
    throw new RuntimeException('Expected tailed outline item Metadata reference to be rejected.');
}
if (array_column($toc, 'title') !== ['Import Metadata Review', 'Clean Appendix']) {
    throw new RuntimeException('Expected TOC titles to survive tailed metadata operand rejection.');
}
foreach ([$rootPayload, $itemPayload, $tailPayload] as $payload) {
    if (!is_string($encodedMetadata) || str_contains($encodedMetadata, $payload)) {
        throw new RuntimeException('Expected metadata stream payloads to stay out of document metadata JSON.');
    }
    if (!is_string($encodedNavigation) || str_contains($encodedNavigation, $payload)) {
        throw new RuntimeException('Expected metadata stream payloads to stay out of navigation JSON.');
    }
    if (str_contains($plainText, $payload)) {
        throw new RuntimeException('Expected metadata stream payloads to stay out of visible WordPress text.');
    }
}
if (str_contains($plainText, 'Import Metadata Review') || str_contains($plainText, 'Clean Appendix')) {
    throw new RuntimeException('Expected outline titles to stay review-only.');
}

echo '<!-- markerpdf-outline-metadata-reference-tail-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-reference-tail-currentbase',
    'support_component' => 'native-pdf-outline-metadata-reference-tail-boundary',
    'native_boundary' => 'outline root and item /Metadata references with trailing top-level operands are rejected as review-only metadata before WordPress navigation output',
    'outline_titles' => $outline['titles'] ?? [],
    'root_metadata_status' => $rootReview['status'] ?? null,
    'item_metadata_status' => $itemReview['status'] ?? null,
    'root_trailing_references' => $rootReview['trailing_reference_object_numbers'] ?? [],
    'item_trailing_references' => $itemReview['trailing_reference_object_numbers'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Metadata Review')
        && !str_contains($plainText, 'Clean Appendix'),
    'metadata_payloads_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, $rootPayload)
        && !str_contains($encodedMetadata, $itemPayload)
        && !str_contains($encodedMetadata, $tailPayload),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-metadata-status="' . htmlspecialchars((string) ($item['metadata_stream_review']['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
