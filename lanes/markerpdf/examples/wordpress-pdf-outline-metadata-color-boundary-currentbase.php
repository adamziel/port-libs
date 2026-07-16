<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline color boundary intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline color boundary appendix) Tj ET';
$reviewContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline color boundary review) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 10 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Color Review) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /C [0 .25 .5] /F 2 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Malformed Extra Color) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 8 0 R /C [0 .25 .5 (hidden color operand)] /F 1 >>\nendobj\n"
    . "8 0 obj\n<< /Title (Indirect Extra Color) /Parent 5 0 R /Prev 7 0 R /Dest /ReviewTarget /C 21 0 R /F 3 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ReviewTarget) [10 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "21 0 obj\n[0 .5 1 99]\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($reviewContent) . " >>\nstream\n{$reviewContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$navigationItems = $navigation['outline'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES) ?: '';

if (($outline['item_count'] ?? null) !== 3 || ($outline['resolved_destination_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected all outline rows and destinations to remain reviewable.');
}
if (($items[0]['text_color_hex'] ?? null) !== '#004080' || ($navigationItems[0]['text_color_hex'] ?? null) !== '#004080') {
    throw new RuntimeException('Expected valid three-component outline color to be preserved.');
}
foreach ([1, 2] as $index) {
    if (array_key_exists('text_color_hex', $items[$index] ?? []) || array_key_exists('text_color_hex', $navigationItems[$index] ?? [])) {
        throw new RuntimeException('Malformed outline /C arrays must not be promoted to valid WordPress color metadata.');
    }
}
foreach (['hidden color operand', '[0,0.5,1]', 'Import Color Review', 'Malformed Extra Color', 'Indirect Extra Color'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Outline color review metadata leaked into visible WordPress text.');
    }
}
if (str_contains($encodedMetadata, 'hidden color operand') || str_contains($encodedNavigation, 'hidden color operand')) {
    throw new RuntimeException('Malformed color extra operands must stay out of metadata payloads.');
}

echo '<!-- markerpdf-outline-metadata-color-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-color-boundary-currentbase',
    'support_component' => 'native-pdf-outline-color-review-boundary',
    'native_boundary' => 'outline item /C color metadata is accepted only as exactly three RGB operands; malformed arrays remain review-neutral',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'valid_outline_color_hex' => $items[0]['text_color_hex'] ?? null,
    'malformed_direct_color_excluded' => !array_key_exists('text_color_hex', $items[1] ?? []),
    'malformed_indirect_color_excluded' => !array_key_exists('text_color_hex', $items[2] ?? []),
    'navigation_malformed_colors_excluded' => !array_key_exists('text_color_hex', $navigationItems[1] ?? [])
        && !array_key_exists('text_color_hex', $navigationItems[2] ?? []),
    'visible_text_excludes_outline_titles' => !str_contains($plainText, 'Import Color Review')
        && !str_contains($plainText, 'Malformed Extra Color')
        && !str_contains($plainText, 'Indirect Extra Color'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline color review\"><ul>\n";
foreach ($items as $item) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-color="' . htmlspecialchars((string) ($item['text_color_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
