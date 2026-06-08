<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata key operand body) Tj ET';
$rootPayload = '<outline-metadata>Root key-like operand payload must stay review only</outline-metadata>';
$itemPayload = '<outline-metadata>Item key-like operand payload must stay review only</outline-metadata>';
$rootStream = gzcompress($rootPayload);
$itemStream = gzcompress($itemPayload);
if (!is_string($rootStream) || !is_string($itemStream)) {
    throw new RuntimeException('Unable to compress outline metadata key-operand payloads.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R /Private /A 20 0 R /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Key Operand Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /Private /AA 21 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Clean Key Operand Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
    . "20 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden root key operand action'\\)) >>\nendobj\n"
    . "21 0 obj\n<< /S /URI /URI (https://example.com/hidden-item-key-operand) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$outlineRows = $navigation['outline'] ?? [];

$rootReview = $metadata['document_outline']['metadata_stream_review'] ?? [];
$itemReview = $metadata['document_outline']['items'][0]['metadata_stream_review'] ?? [];
if (($rootReview['status'] ?? null) !== 'rejected_malformed_outline_root_metadata_operand') {
    throw new RuntimeException('Expected root outline Metadata key-like operand to be rejected.');
}
if (($itemReview['status'] ?? null) !== 'rejected_malformed_outline_item_metadata_operand') {
    throw new RuntimeException('Expected item outline Metadata key-like operand to be rejected.');
}
if (($outlineRows[0]['metadata_stream_review']['status'] ?? null) !== 'rejected_malformed_outline_item_metadata_operand') {
    throw new RuntimeException('Expected navigation review to mirror rejected item metadata.');
}
foreach ([$rootPayload, $itemPayload, 'hidden root key operand action', 'hidden-item-key-operand'] as $hidden) {
    if (
        !is_string($encodedMetadata)
        || !is_string($encodedNavigation)
        || str_contains($encodedMetadata, $hidden)
        || str_contains($encodedNavigation, $hidden)
        || str_contains($plainText, $hidden)
    ) {
        throw new RuntimeException('Expected key-operand outline metadata/action payload to stay out of WordPress import surfaces.');
    }
}
if ($plainText !== 'Outline metadata key operand body') {
    throw new RuntimeException('Expected only page content text in WordPress paragraph output.');
}

echo '<!-- markerpdf-outline-metadata-key-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-key-operand-currentbase',
    'support_component' => 'native-pdf-outline-metadata-review',
    'native_boundary' => 'outline /Metadata indirect references followed by malformed key-like operands are rejected before WordPress navigation metadata review',
    'outline_titles' => array_column($toc, 'title'),
    'root_metadata_status' => $rootReview['status'] ?? null,
    'item_metadata_status' => $itemReview['status'] ?? null,
    'root_trailing_operand_names' => $rootReview['trailing_operand_names'] ?? [],
    'item_trailing_operand_names' => $itemReview['trailing_operand_names'] ?? [],
    'payloads_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, $rootPayload)
        && !str_contains($encodedMetadata, $itemPayload),
    'action_payloads_excluded' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'hidden root key operand action')
        && !str_contains($encodedNavigation, 'hidden-item-key-operand'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($metadata['document_outline']['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
