<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$currentPageText = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate outline nav-key current page) Tj ET';
$actionPageText = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate outline nav-key action page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Stale WordPress Bookmark) /Parent 5 0 R /Dest /StaleWordPressDest /Title (Current WordPress Bookmark) /Dest /CurrentWordPressDest /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Action Bookmark) /Parent 5 0 R /Prev 6 0 R /A 12 0 R /A 13 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-wordpress-outline-action) >>\nendobj\n"
    . "13 0 obj\n<< /S /GoTo /D /ActionWordPressDest /Next 14 0 R >>\nendobj\n"
    . "14 0 obj\n<< /S /URI /URI (https://example.com/current-wordpress-outline-followup) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(ActionWordPressDest) [4 0 R /Fit] (CurrentWordPressDest) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($actionPageText) . " >>\nstream\n{$actionPageText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$tocTitles = array_column($toc, 'title');
$expectedTitles = ['Current WordPress Bookmark', 'WordPress Action Bookmark'];

if ($tocTitles !== $expectedTitles || array_column($items, 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected duplicate outline title operands to select the last top-level bookmark title.');
}
if (($outline['duplicate_item_key_count'] ?? null) !== 2 || ($outline['duplicate_item_keys'] ?? []) !== ['Title', 'Dest', 'A']) {
    throw new RuntimeException('Expected duplicate outline navigation keys to be surfaced as review metadata.');
}
if (($items[0]['destination'] ?? null) !== 'CurrentWordPressDest' || ($items[0]['page'] ?? null) !== 0) {
    throw new RuntimeException('Expected duplicate /Dest operands to select the current WordPress target.');
}
if (($items[1]['action_object'] ?? null) !== 13 || ($items[1]['destination'] ?? null) !== 'ActionWordPressDest') {
    throw new RuntimeException('Expected duplicate /A operands to select the current action dictionary.');
}
if (!is_string($metadataEncoded)
    || str_contains($metadataEncoded, 'Stale WordPress Bookmark')
    || str_contains($metadataEncoded, 'StaleWordPressDest')
    || str_contains($metadataEncoded, 'stale-wordpress-outline-action')
) {
    throw new RuntimeException('Expected unselected duplicate outline operands to stay out of document metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'stale-wordpress-outline-action')) {
    throw new RuntimeException('Expected unselected duplicate outline actions to stay out of navigation review.');
}
if (str_contains($plainText, 'Current WordPress Bookmark')
    || str_contains($plainText, 'Stale WordPress Bookmark')
    || str_contains($plainText, 'stale-wordpress-outline-action')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-duplicate-nav-keys-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-duplicate-nav-keys-currentbase',
    'support_component' => 'native-pdf-outline-metadata-review',
    'native_boundary' => 'duplicate outline /Title /Dest /A entries select the last top-level operand while recording payload-free review provenance',
    'duplicate_item_key_count' => $outline['duplicate_item_key_count'] ?? null,
    'duplicate_item_keys' => $outline['duplicate_item_keys'] ?? [],
    'duplicate_item_key_review_only' => $outline['duplicate_item_key_review_only'] ?? null,
    'duplicate_item_key_payload_included' => $outline['duplicate_item_key_payload_included'] ?? null,
    'selected_titles' => $tocTitles,
    'selected_destinations' => array_column($items, 'destination'),
    'selected_action_object' => $items[1]['action_object'] ?? null,
    'stale_duplicate_title_excluded' => is_string($metadataEncoded) && !str_contains($metadataEncoded, 'Stale WordPress Bookmark'),
    'stale_duplicate_dest_excluded' => is_string($metadataEncoded) && !str_contains($metadataEncoded, 'StaleWordPressDest'),
    'stale_duplicate_action_excluded' => is_string($metadataEncoded) && !str_contains($metadataEncoded, 'stale-wordpress-outline-action'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Current WordPress Bookmark')
        && !str_contains($plainText, 'Stale WordPress Bookmark'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline duplicate-key review\"><ul>\n";
foreach ($items as $item) {
    $review = $item['duplicate_key_review'] ?? [];
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-duplicate-keys="' . htmlspecialchars(implode(',', is_array($review['keys'] ?? null) ? $review['keys'] : []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination="' . htmlspecialchars((string) ($item['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
