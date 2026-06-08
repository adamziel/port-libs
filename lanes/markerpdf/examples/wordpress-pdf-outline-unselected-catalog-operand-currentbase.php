<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress selected catalog operand intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress selected catalog operand appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 8 0 R 99 0 R /PageMode /UseOutlines /Outlines 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Selected Catalog Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Selected Catalog Appendix) /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Outlines /First 9 0 R /Last 9 0 R /Count 1 >>\nendobj\n"
    . "9 0 obj\n<< /Title (WordPress Unselected Catalog Operand Outline) /Parent 8 0 R /Dest [4 0 R /Fit] /A 99 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D [4 0 R /FitR 10 20 300 700] /Next 13 0 R >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/wordpress-selected-catalog-operand-review) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "99 0 obj\n<< /S /URI /URI (https://example.com/wordpress-unselected-catalog-outline-operand) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['outline_root_duplicate_key_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (array_key_exists('document_outline_boundary_review', $metadata)) {
    throw new RuntimeException('Expected unselected malformed catalog /Outlines operand not to suppress selected outline root.');
}
if (($outline['outline_root_object'] ?? null) !== 5 || ($review['selected_object_number'] ?? null) !== 5) {
    throw new RuntimeException('Expected selected duplicate catalog outline root object 5.');
}
if (array_column($toc, 'title') !== ['WordPress Selected Catalog Chapter', 'WordPress Selected Catalog Appendix']) {
    throw new RuntimeException('Expected selected catalog outline TOC titles.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['WordPress Selected Catalog Chapter', 'WordPress Selected Catalog Appendix']) {
    throw new RuntimeException('Expected selected catalog outline navigation titles.');
}
if (array_column($navigation['outline_action_review_actions'] ?? [], 'action_type') !== ['GoTo', 'URI']) {
    throw new RuntimeException('Expected selected catalog outline action chain review.');
}
foreach ([$encodedMetadata, $encodedNavigation] as $encoded) {
    if (!is_string($encoded)
        || str_contains($encoded, 'WordPress Unselected Catalog Operand Outline')
        || str_contains($encoded, 'wordpress-unselected-catalog-outline-operand')
    ) {
        throw new RuntimeException('Expected unselected catalog outline operand metadata to stay excluded.');
    }
}
if ($plainText !== "WordPress selected catalog operand intro body\nWordPress selected catalog operand appendix body"
    || str_contains($plainText, 'WordPress Selected Catalog Chapter')
    || str_contains($plainText, 'WordPress Unselected Catalog Operand Outline')
) {
    throw new RuntimeException('Expected only selected page content to become visible WordPress text.');
}

echo '<!-- markerpdf-outline-unselected-catalog-operand-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-unselected-catalog-operand-currentbase',
    'support_component' => 'native-pdf-catalog-outline-duplicate-root-boundary',
    'native_boundary' => 'unselected malformed duplicate catalog Outlines operands do not suppress the final selected outline root',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'duplicate_outline_root_objects' => $outline['duplicate_outline_root_objects'] ?? [],
    'selected_outline_root_object' => $outline['outline_root_object'] ?? null,
    'selected_entry_index' => $review['selected_entry_index'] ?? null,
    'unselected_operand_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'WordPress Unselected Catalog Operand Outline')
        && !str_contains($encodedMetadata, 'wordpress-unselected-catalog-outline-operand'),
    'selected_action_chain_reviewed' => array_column($navigation['outline_action_review_actions'] ?? [], 'action_type') === ['GoTo', 'URI'],
    'visible_text_excludes_outline_titles' => !str_contains($plainText, 'WordPress Selected Catalog Chapter')
        && !str_contains($plainText, 'WordPress Unselected Catalog Operand Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

foreach (explode("\n", $plainText) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $outlineItem) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($outlineItem['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($outlineItem['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
