<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root count cover body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root count appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count -2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Collapsed Import Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Collapsed Media Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "8 0 obj\n<< /Title (Stale Root Count Tail) /Parent 5 0 R /Prev 7 0 R /Dest /StaleTarget >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (StaleTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['outline_count'] ?? null) !== -2) {
    throw new RuntimeException('Expected raw collapsed root /Count to be preserved.');
}
if (($outline['declared_visible_count'] ?? null) !== 2 || ($outline['descendant_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected collapsed root count magnitude to remain reviewable.');
}
if (($outline['is_collapsed'] ?? null) !== true || ($outline['is_open'] ?? null) !== false) {
    throw new RuntimeException('Expected collapsed root open-state metadata.');
}
if (($outline['titles'] ?? []) !== ['Collapsed Import Chapter', 'Collapsed Media Appendix']) {
    throw new RuntimeException('Expected current outline titles only.');
}
if (array_column($toc, 'title') !== ['Collapsed Import Chapter', 'Collapsed Media Appendix']) {
    throw new RuntimeException('Expected TOC rows to remain importable for collapsed outline roots.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['Collapsed Import Chapter', 'Collapsed Media Appendix']) {
    throw new RuntimeException('Expected navigation review rows to remain importable for collapsed outline roots.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale Root Count Tail')) {
    throw new RuntimeException('Expected stale outline tail to stay outside document metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'Stale Root Count Tail')) {
    throw new RuntimeException('Expected stale outline tail to stay outside navigation metadata.');
}
if (str_contains($plainText, 'Collapsed Import Chapter')
    || str_contains($plainText, 'Collapsed Media Appendix')
    || str_contains($plainText, 'Stale Root Count Tail')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-root-count-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-count-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'catalog /Outlines root /Count sign is review metadata; absolute count is the visible descendant magnitude',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_count' => $outline['outline_count'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'descendant_count' => $outline['descendant_count'] ?? null,
    'root_is_collapsed' => $outline['is_collapsed'] ?? null,
    'root_is_open' => $outline['is_open'] ?? null,
    'root_structure_state' => $outline['structure_state'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'stale_outline_tail_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale Root Count Tail'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Collapsed Import Chapter')
        && !str_contains($plainText, 'Collapsed Media Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"Collapsed PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
