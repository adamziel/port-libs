<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (WordPress zero count outline chapter) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress zero count outline appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Zero Count Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 0 >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Zero Count Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "8 0 obj\n<< /Title (Hidden Zero Count Child) /Parent 6 0 R /Dest /HiddenChildTarget /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (hidden-zero-count-child.pdf) /D (hidden-child-target) /NewWindow true >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (HiddenChildTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

$expectedTitles = ['WordPress Zero Count Chapter', 'WordPress Zero Count Appendix'];
if (($outline['titles'] ?? []) !== $expectedTitles) {
    throw new RuntimeException('Expected zero-count child outline row to be excluded from document metadata.');
}
if (array_column($toc, 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected zero-count child outline row to be excluded from TOC metadata.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected zero-count child outline row to be excluded from navigation metadata.');
}
if ($remoteActions !== []) {
    throw new RuntimeException('Expected zero-count child remote action to remain untraversed.');
}
if (($outline['item_count'] ?? null) !== 2 || ($outline['resolved_destination_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected only the two declared top-level outline rows to be counted.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'Hidden Zero Count Child') || str_contains($encodedMetadata, 'hidden-zero-count-child.pdf')) {
    throw new RuntimeException('Expected hidden zero-count child metadata to stay out of document metadata.');
}
if (!is_string($encodedNavigation) || str_contains($encodedNavigation, 'Hidden Zero Count Child') || str_contains($encodedNavigation, 'hidden-zero-count-child.pdf')) {
    throw new RuntimeException('Expected hidden zero-count child metadata to stay out of navigation review.');
}
if (str_contains($plainText, 'WordPress Zero Count Chapter')
    || str_contains($plainText, 'WordPress Zero Count Appendix')
    || str_contains($plainText, 'Hidden Zero Count Child')
    || str_contains($plainText, 'hidden-zero-count-child.pdf')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-zero-count-child-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-zero-count-child-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-count-boundary-review',
    'native_boundary' => 'outline item /Count 0 blocks contradictory child traversal while preserving parent child-object references as review metadata',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'imported_item_count' => $outline['item_count'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'parent_first_child_object' => $outline['items'][0]['first_child_object'] ?? null,
    'parent_outline_count' => $outline['items'][0]['outline_count'] ?? null,
    'parent_descendant_count' => $outline['items'][0]['descendant_count'] ?? null,
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'remote_action_count' => count($remoteActions),
    'zero_count_child_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Hidden Zero Count Child'),
    'hidden_remote_action_excluded' => is_string($encodedNavigation) && !str_contains($encodedNavigation, 'hidden-zero-count-child.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Zero Count Chapter')
        && !str_contains($plainText, 'Hidden Zero Count Child'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-count="' . htmlspecialchars((string) ($item['outline_count'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
