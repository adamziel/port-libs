<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata stream visible body) Tj ET';
$metadataPayload = 'BT /F1 12 Tf 72 720 Td (WordPress outline item metadata stream must stay review only) Tj ET';
$metadataStream = gzcompress($metadataPayload);
if (!is_string($metadataStream)) {
    throw new RuntimeException('Unable to compress WordPress outline item metadata stream.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 7 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Outline Metadata Stream Boundary) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 8 0 R /A 9 0 R /C [0 .2 .4] /F 1 >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] /Next 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/wordpress-outline-metadata-stream-review) >>\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$directRows = $outlineExtractor->getOutlineStructureDestinationPageContext($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$review = $item['metadata_stream_review'] ?? [];
$navigationOutline = $navigation['outline'][0] ?? [];
$navigationReview = $navigationOutline['metadata_stream_review'] ?? [];
$directReview = $directRows[0]['metadata_stream_review'] ?? [];
$actions = $navigation['outline_action_review_actions'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['WordPress Outline Metadata Stream Boundary']) {
    throw new RuntimeException('Expected outline item title to remain review metadata.');
}
if (($review['status'] ?? null) !== 'reviewed_outline_item_metadata_stream') {
    throw new RuntimeException('Expected outline item metadata stream review summary.');
}
if (($review['payload_included'] ?? null) !== false || ($review['visible_text_source'] ?? null) !== false) {
    throw new RuntimeException('Expected outline item metadata stream to stay payload-free and non-visible.');
}
if (($review['bytes'] ?? null) !== strlen($metadataPayload) || ($review['sha256'] ?? null) !== hash('sha256', $metadataPayload)) {
    throw new RuntimeException('Expected outline item metadata stream hash and decoded byte count.');
}
if (($navigationReview['status'] ?? null) !== 'reviewed_outline_item_metadata_stream'
    || ($navigationReview['sha256'] ?? null) !== hash('sha256', $metadataPayload)
    || ($directReview['sha256'] ?? null) !== hash('sha256', $metadataPayload)
) {
    throw new RuntimeException('Expected navigation review rows to carry outline metadata stream summaries.');
}
foreach ($actions as $action) {
    if (($action['outline_metadata_stream_review']['sha256'] ?? null) !== hash('sha256', $metadataPayload)) {
        throw new RuntimeException('Expected outline action review rows to carry prefixed metadata stream summaries.');
    }
}
if ($plainText !== 'WordPress outline metadata stream visible body') {
    throw new RuntimeException('Expected page text extraction to preserve only visible body text.');
}
if (!is_string($encoded) || str_contains($encoded, $metadataPayload) || str_contains($plainText, 'metadata stream must stay review only')) {
    throw new RuntimeException('Expected outline item metadata stream payload to stay out of metadata and WordPress text.');
}
if (!is_string($encodedNavigation) || str_contains($encodedNavigation, $metadataPayload)) {
    throw new RuntimeException('Expected outline metadata stream payload to stay out of navigation review JSON.');
}

echo '<!-- markerpdf-outline-metadata-stream-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-stream-boundary-currentbase',
    'support_component' => 'native-pdf-outline-item-metadata-stream-review',
    'native_boundary' => 'outline item /Metadata streams are review-only bookmark metadata on document, navigation, and action-review rows',
    'outline_titles' => $outline['titles'] ?? [],
    'outline_object' => $item['outline_object'] ?? null,
    'metadata_stream_status' => $review['status'] ?? null,
    'metadata_stream_object' => $review['object_number'] ?? null,
    'metadata_stream_bytes' => $review['bytes'] ?? null,
    'metadata_stream_sha256' => $review['sha256'] ?? null,
    'navigation_metadata_stream_status' => $navigationReview['status'] ?? null,
    'navigation_metadata_stream_sha256' => $navigationReview['sha256'] ?? null,
    'direct_row_metadata_stream_sha256' => $directReview['sha256'] ?? null,
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_metadata_stream_hashes' => array_values(array_filter(array_map(
        static fn (array $action): ?string => is_string($action['outline_metadata_stream_review']['sha256'] ?? null)
            ? $action['outline_metadata_stream_review']['sha256']
            : null,
        $actions
    ))),
    'metadata_payload_excluded_from_document_metadata' => is_string($encoded) && !str_contains($encoded, $metadataPayload),
    'metadata_payload_excluded_from_navigation_metadata' => is_string($encodedNavigation) && !str_contains($encodedNavigation, $metadataPayload),
    'metadata_payload_excluded_from_visible_text' => !str_contains($plainText, 'metadata stream must stay review only'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
echo '<li data-marker-outline-metadata-stream="' . htmlspecialchars((string) ($navigationReview['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-metadata-object="' . htmlspecialchars((string) ($navigationReview['object_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">' . htmlspecialchars((string) ($navigationOutline['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
