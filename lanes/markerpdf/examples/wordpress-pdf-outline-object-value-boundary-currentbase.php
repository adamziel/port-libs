<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline object value boundary chapter body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline object value boundary appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Object Value Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Stale WordPress Object Value Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] /A 12 0 R >> /Next 99 0 R\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-wordpress-object-value-outline-action) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

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
$navigationTitles = array_column($navigation['outline'] ?? [], 'title');
$lightweightTitles = array_column($lightweightMetadata['pdf_toc'] ?? [], 'title');
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);
$expectedTitles = ['WordPress Object Value Boundary Chapter'];

if ($outlineTitles !== $expectedTitles || $tocTitles !== $expectedTitles || $navigationTitles !== $expectedTitles || $lightweightTitles !== $expectedTitles) {
    throw new RuntimeException('Expected malformed outline item object with an extra top-level operand to be rejected across metadata paths.');
}
if (($outline['item_count'] ?? null) !== 1 || ($outline['resolved_destination_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected only the valid outline item to be imported.');
}
if (($outline['declared_count_expected_visible_item_count'] ?? null) !== 2 || ($outline['declared_count_actual_item_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected declared outline Count mismatch to remain visible for review.');
}
if ($remoteActions !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected stale outline item action to stay excluded from navigation review.');
}
if (!is_string($metadataEncoded)
    || !is_string($navigationEncoded)
    || !is_string($lightweightEncoded)
    || str_contains($metadataEncoded, 'Stale WordPress Object Value Appendix')
    || str_contains($navigationEncoded, 'Stale WordPress Object Value Appendix')
    || str_contains($lightweightEncoded, 'Stale WordPress Object Value Appendix')
    || str_contains($metadataEncoded, 'stale-wordpress-object-value-outline-action')
    || str_contains($navigationEncoded, 'stale-wordpress-object-value-outline-action')
    || str_contains($lightweightEncoded, 'stale-wordpress-object-value-outline-action')
) {
    throw new RuntimeException('Expected stale outline title/action payloads to stay out of metadata.');
}
if (str_contains($plainText, 'WordPress Object Value Boundary Chapter')
    || str_contains($plainText, 'Stale WordPress Object Value Appendix')
    || str_contains($plainText, 'stale-wordpress-object-value-outline-action')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-object-value-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-object-value-boundary-currentbase',
    'support_component' => 'native-pdf-outline-object-value-boundary-review',
    'native_boundary' => 'outline root and item objects must contain one top-level dictionary value before metadata or lightweight TOC traversal',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'imported_item_count' => $outline['item_count'] ?? null,
    'declared_count_expected_visible_item_count' => $outline['declared_count_expected_visible_item_count'] ?? null,
    'declared_count_actual_item_count' => $outline['declared_count_actual_item_count'] ?? null,
    'outline_titles' => $outlineTitles,
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'lightweight_toc_titles' => $lightweightTitles,
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'remote_action_count' => count($remoteActions),
    'stale_outline_object_value_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Stale WordPress Object Value Appendix')
        && is_string($lightweightEncoded)
        && !str_contains($lightweightEncoded, 'Stale WordPress Object Value Appendix'),
    'stale_action_payload_excluded' => is_string($navigationEncoded)
        && !str_contains($navigationEncoded, 'stale-wordpress-object-value-outline-action'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Object Value Boundary Chapter')
        && !str_contains($plainText, 'Stale WordPress Object Value Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline object-value boundary review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
