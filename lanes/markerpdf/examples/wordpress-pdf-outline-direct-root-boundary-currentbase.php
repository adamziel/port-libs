<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverContent = 'BT /F1 12 Tf 72 720 Td (WordPress direct outline root cover body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress direct outline root appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines << /Type /Outlines /First 6 0 R /Count 1 >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Direct Root Chapter) /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Stale Direct Root Parent Review) /Parent 99 0 R /Prev 6 0 R /Dest [4 0 R /Fit] /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (stale-wordpress-direct-root-outline.pdf) /D (stale-direct-root-target) /NewWindow true >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$textExtractor = new PdfTextExtractor();
$lightweightMetadata = $textExtractor->extractOutlineMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$outlineTitles = $outline['titles'] ?? [];
$tocTitles = array_column($toc, 'title');
$lightweightTitles = array_column($lightweightMetadata['pdf_toc'] ?? [], 'title');
$navigationTitles = array_column($navigation['outline'] ?? [], 'title');
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$expectedTitles = ['Import Direct Root Chapter'];

if ($outlineTitles !== $expectedTitles || $tocTitles !== $expectedTitles || $lightweightTitles !== $expectedTitles || $navigationTitles !== $expectedTitles) {
    throw new RuntimeException('Expected direct catalog outline root to expose only the valid root-level bookmark.');
}
if (($outline['item_count'] ?? null) !== 1 || ($outline['resolved_destination_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected explicit stale /Parent sibling to stay outside WordPress outline metadata.');
}
if ($remoteActions !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected stale direct-root remote action to remain excluded from review rows.');
}
if (!is_string($metadataEncoded)
    || str_contains($metadataEncoded, 'Stale Direct Root Parent Review')
    || str_contains($metadataEncoded, 'stale-wordpress-direct-root-outline.pdf')
) {
    throw new RuntimeException('Expected stale explicit-parent outline sibling to stay out of document metadata.');
}
if (!is_string($navigationEncoded)
    || str_contains($navigationEncoded, 'Stale Direct Root Parent Review')
    || str_contains($navigationEncoded, 'stale-wordpress-direct-root-outline.pdf')
) {
    throw new RuntimeException('Expected stale explicit-parent outline sibling to stay out of navigation metadata.');
}
if (!is_string($lightweightEncoded)
    || str_contains($lightweightEncoded, 'Stale Direct Root Parent Review')
    || str_contains($lightweightEncoded, 'stale-wordpress-direct-root-outline.pdf')
) {
    throw new RuntimeException('Expected stale explicit-parent outline sibling to stay out of lightweight outline metadata.');
}
if (str_contains($plainText, 'Import Direct Root Chapter')
    || str_contains($plainText, 'Stale Direct Root Parent Review')
    || str_contains($plainText, 'stale-wordpress-direct-root-outline.pdf')
) {
    throw new RuntimeException('Expected direct-root outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-direct-root-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-direct-root-boundary-currentbase',
    'support_component' => 'native-pdf-direct-outline-root-parent-boundary-review',
    'native_boundary' => 'direct catalog /Outlines roots allow root-level missing Parent items but reject explicit stale parent references before WordPress navigation import',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'imported_item_count' => $outline['item_count'] ?? null,
    'outline_titles' => $outlineTitles,
    'toc_titles' => $tocTitles,
    'lightweight_toc_titles' => $lightweightTitles,
    'navigation_titles' => $navigationTitles,
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'valid_item_next_object' => $outline['items'][0]['next_object'] ?? null,
    'stale_explicit_parent_rejected' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Stale Direct Root Parent Review'),
    'stale_remote_action_excluded' => count($remoteActions) === 0
        && is_string($navigationEncoded)
        && !str_contains($navigationEncoded, 'stale-wordpress-direct-root-outline.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Direct Root Chapter')
        && !str_contains($plainText, 'Stale Direct Root Parent Review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF direct outline root review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-next="' . htmlspecialchars((string) ($item['next_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
