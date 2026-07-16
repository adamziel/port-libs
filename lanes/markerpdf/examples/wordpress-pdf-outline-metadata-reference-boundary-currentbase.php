<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata reference body) Tj ET';
$inlinePayload = 'Inline WordPress outline metadata payload must stay hidden';
$nonStreamPayload = 'Resolved WordPress non-stream outline metadata payload must stay hidden';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Direct Metadata Operand) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /Metadata << /Type /Metadata /Subtype /XML /Private ({$inlinePayload}) >> >>\nendobj\n"
    . "7 0 obj\n<< /Title (Unresolved Metadata Operand) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 680] /Next 8 0 R /Metadata 99 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Non Stream Metadata Operand) /Parent 5 0 R /Prev 7 0 R /Dest [3 0 R /FitH 640] /Metadata 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /Type /Metadata /Subtype /XML /Private ({$nonStreamPayload}) /Length 56 >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$statuses = array_map(
    static fn (array $item): ?string => $item['metadata_stream_review']['status'] ?? null,
    $items
);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if ($statuses !== [
    'rejected_non_indirect_metadata_reference',
    'unresolved_metadata_reference',
    'rejected_non_stream_outline_item_metadata',
]) {
    throw new RuntimeException('Expected fail-closed outline metadata reference review statuses.');
}
if (($outline['resolved_destination_count'] ?? null) !== 3 || array_column($toc, 'title') !== array_column($navigation['outline'] ?? [], 'title')) {
    throw new RuntimeException('Expected outline navigation metadata to remain intact.');
}
foreach ([$inlinePayload, $nonStreamPayload] as $payload) {
    if (!is_string($encodedMetadata) || str_contains($encodedMetadata, $payload)) {
        throw new RuntimeException('Expected outline metadata payload to stay out of document metadata.');
    }
    if (!is_string($encodedNavigation) || str_contains($encodedNavigation, $payload)) {
        throw new RuntimeException('Expected outline metadata payload to stay out of navigation review metadata.');
    }
    if (str_contains($plainText, $payload)) {
        throw new RuntimeException('Expected outline metadata payload to stay out of WordPress paragraph text.');
    }
}

echo '<!-- markerpdf-outline-metadata-reference-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-reference-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-reference-review',
    'native_boundary' => 'outline item /Metadata must be an indirect metadata stream; malformed/direct/unresolved/non-stream operands are review-only and never become document XMP or WordPress text',
    'outline_titles' => $outline['titles'] ?? [],
    'metadata_review_statuses' => $statuses,
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'visible_text_excludes_outline_metadata_payloads' => !str_contains($plainText, $inlinePayload)
        && !str_contains($plainText, $nonStreamPayload),
    'navigation_excludes_outline_metadata_payloads' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, $inlinePayload)
        && !str_contains($encodedNavigation, $nonStreamPayload),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata review\"><ul>\n";
foreach ($items as $item) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-metadata-status="' . htmlspecialchars((string) ($item['metadata_stream_review']['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
