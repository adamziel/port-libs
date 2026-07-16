<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Outline destination view boundary intro body) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (Outline destination view boundary target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 5 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Valid Direct FitH Bookmark) /Parent 5 0 R /Dest [4 0 R /FitH 680 999] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Invalid Action View Bookmark) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Launch 77] /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Invalid Indirect View Bookmark) /Parent 5 0 R /Prev 7 0 R /Dest [4 0 R 12 0 R 88] /Next 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Valid Named FitB Bookmark) /Parent 5 0 R /Prev 8 0 R /Dest /BoxTarget /Next 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Title (Valid Action XYZ Bookmark) /Parent 5 0 R /Prev 9 0 R /A 14 0 R >>\nendobj\n"
    . "12 0 obj\n/RichMedia\nendobj\n"
    . "14 0 obj\n<< /S /GoTo /D /ZoomTarget >>\nendobj\n"
    . "20 0 obj\n<< /Names [(BoxTarget) [3 0 R /FitB 111 222] (ZoomTarget) [4 0 R /XYZ 72 640 0 444] (InvalidNamed) [4 0 R /Movie 99]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$textExtractor = new PdfTextExtractor();
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES) ?: '';

if (($outline['resolved_destination_count'] ?? null) !== 3 || ($outline['unresolved_destination_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected invalid outline destination view names to stay unresolved in document metadata.');
}
if (array_column($toc, 'title') !== ['Valid Direct FitH Bookmark', 'Valid Named FitB Bookmark', 'Valid Action XYZ Bookmark']) {
    throw new RuntimeException('Expected WordPress TOC review to keep only valid outline destination view rows.');
}
if (array_column($toc, 'view_mode') !== ['FitH', 'FitB', 'XYZ']) {
    throw new RuntimeException('Expected valid outline destination view modes to remain normalized for review.');
}
if (($toc[0]['view_position'] ?? null) !== [680.0] || ($toc[2]['view_position'] ?? null) !== [72.0, 640.0, null]) {
    throw new RuntimeException('Expected surplus destination operands to stay out of WordPress outline review metadata.');
}
foreach (['Launch', 'RichMedia', 'Movie', '77', '88', '99', '444'] as $hidden) {
    if (str_contains($encodedMetadata, $hidden) || str_contains($encodedNavigation, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected invalid destination view operands to stay out of WordPress output.');
    }
}

echo '<!-- markerpdf-outline-metadata-destination-view-boundary-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-destination-view-boundary-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline destination arrays reject unknown view names and normalize valid Fit/FitB/XYZ operands before WordPress review',
    'metadata_item_count' => $outline['item_count'] ?? null,
    'metadata_resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'metadata_unresolved_destination_count' => $outline['unresolved_destination_count'] ?? null,
    'toc_titles' => array_column($toc, 'title'),
    'toc_view_modes' => array_column($toc, 'view_mode'),
    'toc_view_parameters' => array_column($toc, 'view_parameters'),
    'invalid_view_operands_excluded' => !str_contains($encodedMetadata, 'Launch')
        && !str_contains($encodedNavigation, 'RichMedia')
        && !str_contains($plainText, 'Movie'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($pages as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $row) {
    echo '<li data-marker-outline-view="' . htmlspecialchars((string) ($row['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) (($row['page'] ?? 0) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Outline bookmark: ' . htmlspecialchars($row['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
