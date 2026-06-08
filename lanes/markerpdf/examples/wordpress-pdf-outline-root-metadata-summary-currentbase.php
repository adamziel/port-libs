<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf 72 720 Td (WordPress outline root metadata summary visible body) Tj ET';
$staleRootPayload = '<outline-metadata role="root-stale">Hidden WordPress outline root metadata summary payload</outline-metadata>';
$staleRootStream = gzcompress($staleRootPayload);
if (!is_string($staleRootStream)) {
    throw new RuntimeException('Unable to compress WordPress outline root metadata summary stream.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R /Metadata << /Type /Metadata /Subtype /XML >> >>\nendobj\n"
    . "6 0 obj\n<< /Title (Root Metadata Review) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleRootStream) . " >>\nstream\n{$staleRootStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$rootReview = $outline['metadata_stream_review'] ?? [];
$navigationRootReview = $navigation['outline_root_review']['metadata_stream_review'] ?? [];
$encoded = json_encode([$metadata, $navigation], JSON_UNESCAPED_SLASHES);

if (($outline['root_metadata_stream_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected outline root metadata summary count.');
}
if (($outline['root_metadata_stream_status'] ?? null) !== 'rejected_non_indirect_metadata_reference') {
    throw new RuntimeException('Expected selected direct outline root metadata operand to be rejected.');
}
if (($outline['root_metadata_stream_declared_entry_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected duplicate root metadata entry count.');
}
if (($outline['root_metadata_stream_selected_entry_index'] ?? null) !== 1) {
    throw new RuntimeException('Expected last root metadata entry to define the selected boundary.');
}
if (($outline['root_metadata_stream_operand_shape'] ?? null) !== 'dictionary') {
    throw new RuntimeException('Expected selected direct root metadata operand shape.');
}
if (array_key_exists('root_metadata_stream_object', $outline)) {
    throw new RuntimeException('Expected stale unselected root metadata object to stay out of summary provenance.');
}
if ($rootReview !== $navigationRootReview) {
    throw new RuntimeException('Expected navigation root review to preserve the same metadata boundary.');
}
if ($plainText !== 'WordPress outline root metadata summary visible body') {
    throw new RuntimeException('Expected visible WordPress paragraph text only.');
}
if (!is_string($encoded) || str_contains($encoded, $staleRootPayload) || str_contains($encoded, hash('sha256', $staleRootPayload))) {
    throw new RuntimeException('Expected stale root metadata payload and hash to stay out of import metadata.');
}
if (str_contains($plainText, 'Root Metadata Review') || str_contains($plainText, 'Hidden WordPress outline root metadata summary payload')) {
    throw new RuntimeException('Expected outline metadata and title to stay out of visible text.');
}

echo '<!-- markerpdf-outline-root-metadata-summary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-metadata-summary-currentbase',
    'support_component' => 'native-pdf-outline-root-metadata-summary-review',
    'native_boundary' => 'outline root /Metadata is summarized as payload-free review metadata and is not promoted to document XMP or visible WordPress paragraph text',
    'outline_titles' => $outline['titles'] ?? [],
    'root_metadata_stream_count' => $outline['root_metadata_stream_count'] ?? null,
    'root_metadata_stream_status' => $outline['root_metadata_stream_status'] ?? null,
    'root_metadata_stream_declared_entry_count' => $outline['root_metadata_stream_declared_entry_count'] ?? null,
    'root_metadata_stream_selected_entry_index' => $outline['root_metadata_stream_selected_entry_index'] ?? null,
    'root_metadata_stream_operand_shape' => $outline['root_metadata_stream_operand_shape'] ?? null,
    'stale_root_metadata_object_excluded' => !array_key_exists('root_metadata_stream_object', $outline),
    'navigation_root_review_preserved' => $rootReview === $navigationRootReview,
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Root Metadata Review')
        && !str_contains($plainText, 'Hidden WordPress outline root metadata summary payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline root metadata review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . (int) ($item['outline_object'] ?? 0) . '">'
        . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
