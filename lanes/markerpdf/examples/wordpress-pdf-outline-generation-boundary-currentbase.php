<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline generation boundary intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline generation boundary appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Generation Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 1 R /Last 8 1 R /Count -1 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Generation Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 9 1 R /A 12 1 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Stale Import Generation Child) /Parent 6 0 R /Dest /StaleChildTarget /A 13 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Stale Import Generation Sibling) /Parent 5 0 R /Dest /StaleSiblingTarget /A 14 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (stale-import-action.pdf) /D (stale-action-target) >>\nendobj\n"
    . "13 0 obj\n<< /S /GoToR /F (stale-import-child.pdf) /D (stale-child-target) >>\nendobj\n"
    . "14 0 obj\n<< /S /GoToR /F (stale-import-sibling.pdf) /D (stale-sibling-target) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (StaleChildTarget) [4 0 R /FitR 1 2 3 4] (StaleSiblingTarget) [4 1 R /FitB]] >>\nendobj\n"
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
$encoded = json_encode([$metadata, $navigation, $remoteActions], JSON_UNESCAPED_SLASHES);

$expectedTitles = ['Import Generation Boundary Chapter', 'Import Generation Boundary Appendix'];
if ($outlineTitles !== $expectedTitles || $tocTitles !== $expectedTitles || $navigationTitles !== $expectedTitles) {
    throw new RuntimeException('Expected generation-exact outline titles across metadata, TOC, and navigation review.');
}
if (($outline['item_count'] ?? null) !== 2 || array_column($outline['items'] ?? [], 'level') !== [1, 1]) {
    throw new RuntimeException('Expected mismatched-generation child and sibling outline references to be excluded.');
}
if (($navigation['outline_action_review_actions'] ?? []) !== [] || $remoteActions !== []) {
    throw new RuntimeException('Expected mismatched-generation outline action reference to stay out of review rows.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Stale Import Generation Child')
    || str_contains($encoded, 'Stale Import Generation Sibling')
    || str_contains($encoded, 'stale-import-action.pdf')
) {
    throw new RuntimeException('Expected stale generation outline/action metadata to be excluded.');
}
if (str_contains($plainText, 'Import Generation Boundary Chapter')
    || str_contains($plainText, 'Import Generation Boundary Appendix')
    || str_contains($plainText, 'Stale Import Generation Child')
    || str_contains($plainText, 'Stale Import Generation Sibling')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-generation-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-generation-boundary-currentbase',
    'support_component' => 'native-pdf-outline-generation-boundary-review',
    'native_boundary' => 'outline /First, /Next, /A, and destination page references resolve only when object generations match',
    'outline_titles' => $outlineTitles,
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'outline_levels' => array_column($outline['items'] ?? [], 'level'),
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'stale_child_excluded' => !str_contains($encoded, 'Stale Import Generation Child'),
    'stale_sibling_excluded' => !str_contains($encoded, 'Stale Import Generation Sibling'),
    'stale_action_excluded' => !str_contains($encoded, 'stale-import-action.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Generation Boundary Chapter')
        && !str_contains($plainText, 'Stale Import Generation Child'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline generation-boundary review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
