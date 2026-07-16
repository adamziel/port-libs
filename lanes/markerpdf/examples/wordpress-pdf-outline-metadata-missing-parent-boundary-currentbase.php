<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline missing parent boundary intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline missing parent boundary appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Missing Parent Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Count -1 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Missing Parent Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "8 0 obj\n<< /Title (Import Missing Parent Child) /Parent 6 0 R /Dest /ChildTarget /Next 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Import Orphan Outline After Child) /Dest /OrphanTarget /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (orphan-outline-import.pdf) /D (orphan-target) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ChildTarget) [3 0 R /XYZ 72 680 0] (OrphanTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
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
$outlineTitles = $outline['titles'] ?? [];
$tocTitles = array_column($toc, 'title');
$navigationTitles = array_column($navigation['outline'] ?? [], 'title');
$encodedReview = json_encode([$metadata, $navigation, $remoteActions], JSON_UNESCAPED_SLASHES);
$expectedTitles = ['Import Missing Parent Chapter', 'Import Missing Parent Child', 'Import Missing Parent Appendix'];

if ($outlineTitles !== $expectedTitles) {
    throw new RuntimeException('Expected missing-parent-bounded document outline titles.');
}
if ($tocTitles !== $expectedTitles || $navigationTitles !== $expectedTitles) {
    throw new RuntimeException('Expected TOC and navigation review rows to match missing-parent-bounded outline metadata.');
}
if (($outline['item_count'] ?? null) !== 3 || array_column($outline['items'] ?? [], 'level') !== [1, 2, 1]) {
    throw new RuntimeException('Expected orphan child /Next row to be excluded from outline traversal.');
}
if ($remoteActions !== [] || !is_string($encodedReview) || str_contains($encodedReview, 'orphan-outline-import.pdf')) {
    throw new RuntimeException('Expected orphan outline remote action to stay out of review rows.');
}
if (str_contains($plainText, 'Import Missing Parent Chapter')
    || str_contains($plainText, 'Import Missing Parent Child')
    || str_contains($plainText, 'Import Missing Parent Appendix')
    || str_contains($plainText, 'Import Orphan Outline After Child')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-missing-parent-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-missing-parent-boundary-currentbase',
    'support_component' => 'native-pdf-outline-missing-parent-boundary-review',
    'native_boundary' => 'outline child /Next traversal stops when the next item omits required /Parent',
    'outline_titles' => $outlineTitles,
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'outline_levels' => array_column($outline['items'] ?? [], 'level'),
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'outline_parent_objects' => array_column($outline['items'] ?? [], 'parent_object'),
    'orphan_outline_excluded' => !is_string($encodedReview) || !str_contains($encodedReview, 'Import Orphan Outline After Child'),
    'orphan_action_excluded' => !is_string($encodedReview) || !str_contains($encodedReview, 'orphan-outline-import.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Missing Parent Chapter')
        && !str_contains($plainText, 'Import Missing Parent Child')
        && !str_contains($plainText, 'Import Missing Parent Appendix')
        && !str_contains($plainText, 'Import Orphan Outline After Child'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline missing-parent-boundary review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-parent="' . htmlspecialchars((string) ($item['parent_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
