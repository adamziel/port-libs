<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (WordPress indirect count chapter body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress indirect count appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 18 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Media Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "8 0 obj\n<< /Title (Hidden Malformed Count Child) /Parent 6 0 R /Dest /HiddenChildTarget /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (hidden-indirect-count-child.pdf) /D (hidden-child-target) /NewWindow true >>\nendobj\n"
    . "18 0 obj\n1 99 0 R\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (HiddenChildTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['Import Chapter', 'Media Appendix']) {
    throw new RuntimeException('Expected malformed indirect item /Count to preserve sibling outline rows only.');
}
if (array_column($toc, 'title') !== ['Import Chapter', 'Media Appendix']) {
    throw new RuntimeException('Expected TOC rows to exclude hidden malformed count child.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['Import Chapter', 'Media Appendix']) {
    throw new RuntimeException('Expected navigation rows to exclude hidden malformed count child.');
}
if (($outline['max_depth'] ?? null) !== 1) {
    throw new RuntimeException('Expected malformed indirect count to block child traversal.');
}
if (($items[0]['outline_count'] ?? null) !== null || ($items[0]['structure_state'] ?? null) !== 'parent') {
    throw new RuntimeException('Expected tailed indirect count to be treated as absent parent metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'Hidden Malformed Count Child')) {
    throw new RuntimeException('Expected hidden child title to stay outside document metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'hidden-indirect-count-child.pdf')) {
    throw new RuntimeException('Expected hidden child remote action to stay outside navigation metadata.');
}
if (str_contains($plainText, 'Import Chapter')
    || str_contains($plainText, 'Media Appendix')
    || str_contains($plainText, 'Hidden Malformed Count Child')
    || str_contains($plainText, 'hidden-indirect-count-child.pdf')
) {
    throw new RuntimeException('Expected outline metadata and hidden actions to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-indirect-count-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-indirect-count-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'outline /Count indirect scalar objects must consume one top-level PDF token before child traversal is trusted',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'item_count' => $outline['item_count'] ?? null,
    'max_depth' => $outline['max_depth'] ?? null,
    'first_item_outline_count' => $items[0]['outline_count'] ?? null,
    'hidden_child_excluded' => is_string($encoded) && !str_contains($encoded, 'Hidden Malformed Count Child'),
    'hidden_remote_action_excluded' => is_string($navigationEncoded) && !str_contains($navigationEncoded, 'hidden-indirect-count-child.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Chapter')
        && !str_contains($plainText, 'Media Appendix')
        && !str_contains($plainText, 'Hidden Malformed Count Child'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($items as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
