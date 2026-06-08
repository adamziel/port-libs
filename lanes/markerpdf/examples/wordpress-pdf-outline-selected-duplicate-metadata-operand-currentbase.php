<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress selected duplicate outline metadata operand body) Tj ET';
$staleRootPayload = '<outline-metadata role="stale-root">Stale WordPress root metadata operand payload</outline-metadata>';
$currentRootPayload = '<outline-metadata role="current-root">Current WordPress root metadata operand payload</outline-metadata>';
$staleItemPayload = '<outline-metadata role="stale-item">Stale WordPress item metadata operand payload</outline-metadata>';
$currentItemPayload = '<outline-metadata role="current-item">Current WordPress item metadata operand payload</outline-metadata>';

$staleRootStream = gzcompress($staleRootPayload);
$currentRootStream = gzcompress($currentRootPayload);
$staleItemStream = gzcompress($staleItemPayload);
$currentItemStream = gzcompress($currentItemPayload);
if (
    !is_string($staleRootStream)
    || !is_string($currentRootStream)
    || !is_string($staleItemStream)
    || !is_string($currentItemStream)
) {
    throw new RuntimeException('Unable to compress WordPress selected duplicate outline metadata streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R 88 0 R /Metadata 9 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (Selected Duplicate Operand Import) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 10 0 R 89 0 R /Metadata 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleRootStream) . " >>\nstream\n{$staleRootStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($currentRootStream) . " >>\nstream\n{$currentRootStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleItemStream) . " >>\nstream\n{$staleItemStream}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($currentItemStream) . " >>\nstream\n{$currentItemStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$rootReview = $outline['metadata_stream_review'] ?? [];
$item = $outline['items'][0] ?? [];
$itemReview = $item['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($rootReview['status'] ?? null) !== 'reviewed_outline_root_metadata_stream' || ($rootReview['object_number'] ?? null) !== 9) {
    throw new RuntimeException('Expected selected duplicate outline root metadata stream review.');
}
if (($itemReview['status'] ?? null) !== 'reviewed_outline_item_metadata_stream' || ($itemReview['object_number'] ?? null) !== 11) {
    throw new RuntimeException('Expected selected duplicate outline item metadata stream review.');
}
if (($rootReview['selected_entry_index'] ?? null) !== 1 || ($itemReview['selected_entry_index'] ?? null) !== 1) {
    throw new RuntimeException('Expected selected duplicate metadata entry indexes.');
}
if (array_column($toc, 'title') !== ['Selected Duplicate Operand Import']) {
    throw new RuntimeException('Expected selected outline TOC title.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['Selected Duplicate Operand Import']) {
    throw new RuntimeException('Expected selected outline navigation title.');
}
foreach ([$staleRootPayload, $currentRootPayload, $staleItemPayload, $currentItemPayload] as $payload) {
    if (!is_string($encodedMetadata) || str_contains($encodedMetadata, $payload)) {
        throw new RuntimeException('Expected outline metadata payloads to stay out of document metadata JSON.');
    }
    if (!is_string($encodedNavigation) || str_contains($encodedNavigation, $payload)) {
        throw new RuntimeException('Expected outline metadata payloads to stay out of navigation JSON.');
    }
    if (str_contains($plainText, $payload)) {
        throw new RuntimeException('Expected outline metadata payloads to stay out of visible WordPress text.');
    }
}
if (str_contains($plainText, 'Selected Duplicate Operand Import')) {
    throw new RuntimeException('Expected outline title to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-selected-duplicate-metadata-operand-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-selected-duplicate-metadata-operand-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'selected duplicate outline Metadata entries override stale malformed operands while payloads remain review-only',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'root_selected_entry_index' => $rootReview['selected_entry_index'] ?? null,
    'root_selected_object' => $rootReview['object_number'] ?? null,
    'item_selected_entry_index' => $itemReview['selected_entry_index'] ?? null,
    'item_selected_object' => $itemReview['object_number'] ?? null,
    'stale_malformed_operand_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'rejected_malformed_outline_root_metadata_operand')
        && !str_contains($encodedMetadata, 'rejected_malformed_outline_item_metadata_operand')
        && !str_contains($encodedMetadata, '88 0 R')
        && !str_contains($encodedMetadata, '89 0 R'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Selected Duplicate Operand Import')
        && !str_contains($plainText, 'Current WordPress item metadata operand payload')
        && !str_contains($plainText, 'Current WordPress root metadata operand payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $outlineItem) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($outlineItem['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-metadata-object="' . htmlspecialchars((string) ($outlineItem['metadata_stream_review']['object_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($outlineItem['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
