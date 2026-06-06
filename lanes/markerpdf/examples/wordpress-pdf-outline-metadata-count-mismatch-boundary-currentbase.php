<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline count mismatch chapter body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline count mismatch appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Count Mismatch Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Count Mismatch Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D /AppendixTarget /Next 13 0 R >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/count-mismatch-review) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$expectedTitles = ['Count Mismatch Chapter', 'Count Mismatch Appendix'];

if (($outline['outline_count'] ?? null) !== 1 || ($outline['item_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected declared root /Count mismatch with two imported outline rows.');
}
if (($outline['declared_count_mismatch_review_only'] ?? null) !== true
    || ($outline['declared_count_mismatch_payload_included'] ?? null) !== false
) {
    throw new RuntimeException('Expected count mismatch metadata to remain review-only and payload-free.');
}
if (($outline['declared_count_expected_visible_item_count'] ?? null) !== 1
    || ($outline['declared_count_actual_visible_item_count'] ?? null) !== 2
    || ($outline['declared_count_actual_item_count'] ?? null) !== 2
    || ($outline['declared_count_visible_item_count_delta'] ?? null) !== 1
) {
    throw new RuntimeException('Expected declared/actual outline count mismatch delta.');
}
if (($outline['titles'] ?? []) !== $expectedTitles) {
    throw new RuntimeException('Expected valid linked outline rows to remain imported.');
}
if (array_column($toc, 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected TOC rows to preserve valid linked outline titles.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected navigation review rows to preserve valid linked outline titles.');
}
if (array_column($navigation['outline_action_review_actions'] ?? [], 'action_type') !== ['GoTo', 'URI']) {
    throw new RuntimeException('Expected outline action chain to remain navigation-review metadata.');
}
if (!is_string($metadataEncoded) || str_contains($metadataEncoded, 'count-mismatch-review')) {
    throw new RuntimeException('Expected action payload to stay out of document metadata.');
}
if (!is_string($navigationEncoded) || !str_contains($navigationEncoded, 'count-mismatch-review')) {
    throw new RuntimeException('Expected navigation review to preserve action payload context.');
}
if (str_contains($plainText, 'Count Mismatch Chapter')
    || str_contains($plainText, 'Count Mismatch Appendix')
    || str_contains($plainText, 'count-mismatch-review')
) {
    throw new RuntimeException('Expected outline metadata and action payloads to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-count-mismatch-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-count-mismatch-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'catalog /Outlines root /Count mismatch is review metadata and does not cap valid linked outline rows',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_count' => $outline['outline_count'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'declared_count_expected_visible_item_count' => $outline['declared_count_expected_visible_item_count'] ?? null,
    'declared_count_actual_visible_item_count' => $outline['declared_count_actual_visible_item_count'] ?? null,
    'declared_count_actual_item_count' => $outline['declared_count_actual_item_count'] ?? null,
    'declared_count_visible_item_count_delta' => $outline['declared_count_visible_item_count_delta'] ?? null,
    'count_mismatch_review_only' => $outline['declared_count_mismatch_review_only'] ?? null,
    'count_mismatch_payload_included' => $outline['declared_count_mismatch_payload_included'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'action_review_types' => array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Count Mismatch Chapter')
        && !str_contains($plainText, 'Count Mismatch Appendix'),
    'metadata_excludes_action_payload' => is_string($metadataEncoded) && !str_contains($metadataEncoded, 'count-mismatch-review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline count mismatch review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
