<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress selected duplicate outline intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress selected duplicate outline appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Overview) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Appendix Review) /Parent 5 0 R /Prev 6 0 R /Dest /LegacyTarget 12 0 R /Dest /AppendixTarget /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Closing Checklist) /Parent 5 0 R /Prev 7 0 R /Dest /ChapterStart >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (stale-wordpress-duplicate-dest.pdf) /D (stale-wordpress-duplicate-dest) /NewWindow true >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /FitH 640] (ChapterStart) [3 0 R /Fit]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$expectedTitles = ['Import Overview', 'Appendix Review', 'Closing Checklist'];

if (($outline['titles'] ?? []) !== $expectedTitles) {
    throw new RuntimeException('Expected selected duplicate outline titles in document metadata.');
}
if (($outline['resolved_destination_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected all selected duplicate outline destinations to resolve.');
}
if (array_column($items, 'destination') !== ['ChapterStart', 'AppendixTarget', 'ChapterStart']) {
    throw new RuntimeException('Expected selected duplicate /Dest to replace stale malformed entry.');
}
if (isset($items[1]['destination_operand_boundary_review'])) {
    throw new RuntimeException('Expected stale duplicate /Dest boundary review to be cleared by selected clean entry.');
}
if (($items[1]['duplicate_key_review']['keys'] ?? []) !== ['Dest']) {
    throw new RuntimeException('Expected duplicate /Dest key review to remain visible for WordPress import review.');
}
if (array_column($toc, 'title') !== $expectedTitles || array_column($navigation['outline'] ?? [], 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected selected duplicate outline entries in TOC/navigation review.');
}
if (array_column($toc, 'page') !== [0, 1, 0]) {
    throw new RuntimeException('Expected Appendix Review to resolve to the selected AppendixTarget destination page.');
}
if ($remoteActions !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected stale duplicate /Dest remote action decoy to stay unpromoted.');
}
if (!is_string($encoded) || str_contains($encoded, 'stale-wordpress-duplicate-dest.pdf')) {
    throw new RuntimeException('Expected stale duplicate destination action file to stay out of document metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'stale-wordpress-duplicate-dest.pdf')) {
    throw new RuntimeException('Expected stale duplicate destination action file to stay out of navigation metadata.');
}
foreach ($expectedTitles as $title) {
    if (str_contains($plainText, $title)) {
        throw new RuntimeException('Expected outline titles to stay out of visible WordPress text.');
    }
}

echo '<!-- markerpdf-outline-selected-duplicate-navigation-operand-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-selected-duplicate-navigation-operand-currentbase',
    'support_component' => 'native-pdf-outline-metadata-navigation-boundary',
    'native_boundary' => 'selected duplicate outline /Dest entry resolves cleanly after stale malformed /Dest operands',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'toc_pages' => array_column($toc, 'page'),
    'selected_appendix_destination' => $items[1]['destination'] ?? null,
    'duplicate_dest_review_preserved' => ($items[1]['duplicate_key_review']['keys'] ?? []) === ['Dest'],
    'stale_dest_boundary_cleared' => !isset($items[1]['destination_operand_boundary_review']),
    'remote_actions_excluded' => $remoteActions === [] && ($navigation['outline_action_review_actions'] ?? []) === [],
    'stale_action_excluded' => is_string($encoded)
        && is_string($navigationEncoded)
        && !str_contains($encoded, 'stale-wordpress-duplicate-dest.pdf')
        && !str_contains($navigationEncoded, 'stale-wordpress-duplicate-dest.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Overview')
        && !str_contains($plainText, 'Appendix Review')
        && !str_contains($plainText, 'Closing Checklist'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF selected duplicate outline review\"><ul>\n";
foreach ($toc as $entry) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($entry['page'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-view="' . htmlspecialchars((string) ($entry['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($entry['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
