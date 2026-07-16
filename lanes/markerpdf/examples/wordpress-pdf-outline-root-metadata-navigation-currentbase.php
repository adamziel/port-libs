<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root metadata navigation body) Tj ET';
$rootPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden WordPress Root Outline Metadata</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$tailPayload = '<x:xmpmeta>Trailing WordPress root metadata operand payload must stay hidden</x:xmpmeta>';
$rootStream = gzcompress($rootPayload);
$tailStream = gzcompress($tailPayload);
if (!is_string($rootStream) || !is_string($tailStream)) {
    throw new RuntimeException('Unable to compress WordPress outline root metadata streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R 10 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Root Metadata Review) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($tailStream) . " >>\nstream\n{$tailStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$metadataRootReview = $documentMetadata['document_outline']['metadata_stream_review'] ?? [];
$navigationRootReview = $navigation['outline_root_review']['metadata_stream_review'] ?? [];
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($metadataRootReview['status'] ?? null) !== 'rejected_malformed_outline_root_metadata_operand') {
    throw new RuntimeException('Expected document metadata to reject the malformed root outline Metadata operand.');
}
if (($navigationRootReview['status'] ?? null) !== 'rejected_malformed_outline_root_metadata_operand') {
    throw new RuntimeException('Expected navigation metadata to carry the root outline Metadata review.');
}
if (array_column($toc, 'title') !== ['WordPress Root Metadata Review']) {
    throw new RuntimeException('Expected TOC title to remain available.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['WordPress Root Metadata Review']) {
    throw new RuntimeException('Expected navigation outline title to remain available.');
}
if (!is_string($encodedNavigation)
    || str_contains($encodedNavigation, $rootPayload)
    || str_contains($encodedNavigation, $tailPayload)
    || str_contains($encodedNavigation, 'Hidden WordPress Root Outline Metadata')
) {
    throw new RuntimeException('Expected root outline Metadata payloads to stay out of navigation JSON.');
}
if (str_contains($plainText, 'WordPress Root Metadata Review')
    || str_contains($plainText, 'Hidden WordPress Root Outline Metadata')
    || str_contains($plainText, 'Trailing WordPress root metadata operand payload')
) {
    throw new RuntimeException('Expected outline metadata and payload text to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-root-metadata-navigation-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-metadata-navigation-currentbase',
    'support_component' => 'native-pdf-outline-root-metadata-navigation-review',
    'native_boundary' => 'catalog /Outlines /Metadata review is navigation metadata only and never visible paragraph text',
    'outline_root_object' => $navigation['outline_root_review']['outline_root_object'] ?? null,
    'root_metadata_status' => $navigationRootReview['status'] ?? null,
    'metadata_operand_count' => $navigationRootReview['metadata_operand_count'] ?? null,
    'trailing_reference_object_numbers' => $navigationRootReview['trailing_reference_object_numbers'] ?? [],
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'toc_titles' => array_column($toc, 'title'),
    'visible_text_excludes_outline_root_metadata' => !str_contains($plainText, 'WordPress Root Metadata Review')
        && !str_contains($plainText, 'Hidden WordPress Root Outline Metadata'),
    'payload_included' => $navigationRootReview['payload_included'] ?? null,
    'visible_text_source' => $navigationRootReview['visible_text_source'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline root metadata review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
