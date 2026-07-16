<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$xmpPacket = static function (string $title): string {
    return '<?xpacket begin=""?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>outline-role-operand-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$rootTitle = 'Hidden WordPress Root Outline Role Operand XMP';
$itemTitle = 'Hidden WordPress Item Outline Role Operand XMP';
$rootXmp = $xmpPacket($rootTitle);
$itemXmp = $xmpPacket($itemTitle);
$rootStream = gzcompress($rootXmp);
$itemStream = gzcompress($itemXmp);
if (!is_string($rootStream) || !is_string($itemStream)) {
    throw new RuntimeException('Unable to compress WordPress outline role-operand metadata payloads.');
}

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline role operand body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Role Operand Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata EmbeddedFile /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type 15 0 R /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
    . "15 0 obj\n/Metadata /EmbeddedFile\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$rootReview = $outline['metadata_stream_review'] ?? [];
$itemReview = $items[0]['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($rootReview['status'] ?? null) !== 'rejected_tailed_outline_root_metadata_stream_role_operand') {
    throw new RuntimeException('Expected tailed outline root metadata role operand to stay review-only.');
}
if (($itemReview['status'] ?? null) !== 'rejected_tailed_outline_item_metadata_stream_role_operand') {
    throw new RuntimeException('Expected tailed outline item metadata role operand to stay review-only.');
}
if (array_column($toc, 'title') !== ['WordPress Role Operand Boundary Chapter']) {
    throw new RuntimeException('Expected outline TOC row to remain importable.');
}
if ($plainText !== 'WordPress outline role operand body') {
    throw new RuntimeException('Expected visible page text to render without outline metadata payloads.');
}
foreach ([$rootXmp, $itemXmp, $rootTitle, $itemTitle, 'outline-role-operand-boundary'] as $payload) {
    if (!is_string($encodedMetadata) || str_contains($encodedMetadata, $payload)) {
        throw new RuntimeException('Expected tailed outline XMP payload to stay out of document metadata.');
    }
    if (!is_string($encodedNavigation) || str_contains($encodedNavigation, $payload)) {
        throw new RuntimeException('Expected tailed outline XMP payload to stay out of navigation review.');
    }
    if (str_contains($plainText, $payload)) {
        throw new RuntimeException('Expected tailed outline XMP payload to stay out of WordPress paragraph text.');
    }
}

echo '<!-- markerpdf-outline-metadata-role-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-role-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-role-operand-review',
    'native_boundary' => 'outline root and item /Metadata stream /Type and /Subtype roles must be single PDF name operands before bookmark-local XMP review',
    'outline_titles' => $outline['titles'] ?? [],
    'root_review_status' => $rootReview['status'] ?? null,
    'root_tailed_role_keys' => $rootReview['tailed_role_keys'] ?? [],
    'item_review_status' => $itemReview['status'] ?? null,
    'item_tailed_role_keys' => $itemReview['tailed_role_keys'] ?? [],
    'role_operand_boundary' => $itemReview['role_operand_boundary'] ?? null,
    'visible_text_excludes_outline_role_payloads' => !str_contains($plainText, $rootTitle)
        && !str_contains($plainText, $itemTitle),
    'navigation_excludes_outline_role_payloads' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, $rootTitle)
        && !str_contains($encodedNavigation, $itemTitle),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline role operand review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $row) {
    echo '<li data-marker-outline-object="' . (int) ($row['outline_object'] ?? 0)
        . '" data-marker-outline-role-status="' . htmlspecialchars((string) ($row['metadata_stream_review']['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
