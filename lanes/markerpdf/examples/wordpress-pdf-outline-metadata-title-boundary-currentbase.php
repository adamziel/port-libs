<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline title boundary intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline title boundary appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 4 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Title Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Parent 5 0 R /Prev 6 0 R /Next 10 0 R /First 8 0 R /Last 9 0 R /Count 2 /A 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Stale Import Child Under Untitled Outline) /Parent 7 0 R /Dest /StaleChild /Next 9 0 R /A 13 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Stale Import Remote Child Under Untitled Outline) /Parent 7 0 R /Prev 8 0 R /A 14 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Title (Import Title Boundary Appendix) /Parent 5 0 R /Prev 7 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('untitled wordpress outline parent action'\\)) >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/stale-wordpress-untitled-outline-child) >>\nendobj\n"
    . "14 0 obj\n<< /S /GoToR /F (stale-wordpress-untitled-outline-child.pdf) /D (stale-child) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (StaleChild) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
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
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$expectedTitles = ['Import Title Boundary Chapter', 'Import Title Boundary Appendix'];

if ($outlineTitles !== $expectedTitles || $tocTitles !== $expectedTitles || $navigationTitles !== $expectedTitles) {
    throw new RuntimeException('Expected untitled outline item descendants to stay out of document navigation review.');
}
if (($outline['item_count'] ?? null) !== 2 || ($outline['resolved_destination_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected only titled outline items to be counted.');
}
if (($outline['items'][1]['previous_object'] ?? null) !== 7) {
    throw new RuntimeException('Expected valid sibling traversal to continue across the untitled outline item.');
}
if ($remoteActions !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected untitled outline item child actions to remain excluded.');
}
if (!is_string($metadataEncoded)
    || str_contains($metadataEncoded, 'Stale Import Child Under Untitled Outline')
    || str_contains($metadataEncoded, 'stale-wordpress-untitled-outline-child')
    || str_contains($metadataEncoded, 'untitled wordpress outline parent action')
) {
    throw new RuntimeException('Expected stale untitled outline metadata to stay out of document metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'stale-wordpress-untitled-outline-child')) {
    throw new RuntimeException('Expected stale untitled outline child actions to stay out of navigation review.');
}
if (str_contains($plainText, 'Import Title Boundary Chapter')
    || str_contains($plainText, 'Import Title Boundary Appendix')
    || str_contains($plainText, 'Stale Import Child Under Untitled Outline')
    || str_contains($plainText, 'untitled wordpress outline parent action')
) {
    throw new RuntimeException('Expected outline titles and actions to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-title-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-title-boundary-currentbase',
    'support_component' => 'native-pdf-outline-title-boundary-review',
    'native_boundary' => 'untitled outline item dictionaries are traversal boundaries for child/action metadata while valid next siblings remain importable',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'imported_item_count' => $outline['item_count'] ?? null,
    'outline_titles' => $outlineTitles,
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'second_item_previous_object' => $outline['items'][1]['previous_object'] ?? null,
    'untitled_child_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Stale Import Child Under Untitled Outline'),
    'untitled_parent_action_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'untitled wordpress outline parent action'),
    'stale_remote_actions_excluded' => count($remoteActions) === 0
        && is_string($navigationEncoded)
        && !str_contains($navigationEncoded, 'stale-wordpress-untitled-outline-child'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Title Boundary Chapter')
        && !str_contains($plainText, 'Stale Import Child Under Untitled Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline title-boundary review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-previous="' . htmlspecialchars((string) ($item['previous_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
