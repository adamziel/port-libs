<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline last boundary intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline last boundary appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Last Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Last 8 0 R /Count -1 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Last Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 9 0 R /A 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Import Last Boundary Child) /Parent 6 0 R /Dest /ChildTarget /Next 10 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Stale Import Root After Last) /Parent 5 0 R /Dest /StaleRootTarget /A 13 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Title (Stale Import Child After Last) /Parent 6 0 R /Dest /StaleChildTarget /A 14 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (appendix-review.pdf) /D (appendix-remote) /NewWindow true >>\nendobj\n"
    . "13 0 obj\n<< /S /GoToR /F (stale-root-after-last.pdf) /D (stale-root) >>\nendobj\n"
    . "14 0 obj\n<< /S /GoToR /F (stale-child-after-last.pdf) /D (stale-child) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ChildTarget) [3 0 R /XYZ 72 680 0] (StaleChildTarget) [4 0 R /FitR 1 2 3 4] (StaleRootTarget) [4 0 R /FitB]] >>\nendobj\n"
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
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

$expectedTitles = [
    'Import Last Boundary Chapter',
    'Import Last Boundary Child',
    'Import Last Boundary Appendix',
];

if ($outlineTitles !== $expectedTitles || $tocTitles !== $expectedTitles || $navigationTitles !== $expectedTitles) {
    throw new RuntimeException('Expected outline metadata, TOC, and navigation review to stop at declared /Last items.');
}
if (($outline['item_count'] ?? null) !== 3 || ($outline['resolved_destination_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected only valid /Last-bounded outline items to resolve.');
}
if (array_column($remoteActions, 'title') !== ['Import Last Boundary Appendix']) {
    throw new RuntimeException('Expected stale remote outline actions after /Last to stay excluded.');
}
if (!is_string($metadataEncoded)
    || str_contains($metadataEncoded, 'Stale Import Root After Last')
    || str_contains($metadataEncoded, 'Stale Import Child After Last')
) {
    throw new RuntimeException('Expected stale outline rows after /Last to stay out of document metadata.');
}
if (!is_string($navigationEncoded)
    || str_contains($navigationEncoded, 'stale-root-after-last.pdf')
    || str_contains($navigationEncoded, 'stale-child-after-last.pdf')
) {
    throw new RuntimeException('Expected stale remote action rows after /Last to stay out of navigation review.');
}
if (str_contains($plainText, 'Import Last Boundary Chapter')
    || str_contains($plainText, 'Import Last Boundary Child')
    || str_contains($plainText, 'Import Last Boundary Appendix')
    || str_contains($plainText, 'Stale Import Root After Last')
    || str_contains($plainText, 'Stale Import Child After Last')
) {
    throw new RuntimeException('Expected outline titles to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-last-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-last-boundary-currentbase',
    'support_component' => 'native-pdf-outline-last-boundary-review',
    'native_boundary' => 'outline sibling traversal stops after the declared /Last item even when malformed /Next links point to same-parent decoys',
    'outline_titles' => $outlineTitles,
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'remote_action_titles' => array_column($remoteActions, 'title'),
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'outline_next_objects' => array_column($outline['items'] ?? [], 'next_object'),
    'last_item_object' => $outline['last_item_object'] ?? null,
    'stale_root_after_last_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Stale Import Root After Last'),
    'stale_child_after_last_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Stale Import Child After Last'),
    'stale_remote_actions_excluded' => is_string($navigationEncoded)
        && !str_contains($navigationEncoded, 'stale-root-after-last.pdf')
        && !str_contains($navigationEncoded, 'stale-child-after-last.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Last Boundary Chapter')
        && !str_contains($plainText, 'Import Last Boundary Child')
        && !str_contains($plainText, 'Import Last Boundary Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline last-boundary review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-next="' . htmlspecialchars((string) ($item['next_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
