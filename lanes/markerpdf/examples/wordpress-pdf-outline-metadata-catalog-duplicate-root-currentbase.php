<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverContent = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate outline root cover) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate outline root appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Out#6Cines 8 0 R /PageMode /UseOutlines /Outlines 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Checklist) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Media Review Appendix) /Parent 5 0 R /Prev 6 0 R /A 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Outlines /First 9 0 R /Last 9 0 R /Count 1 >>\nendobj\n"
    . "9 0 obj\n<< /Title (Stale Duplicate Root Import) /Parent 8 0 R /Dest [4 0 R /Fit] /A 12 0 R >>\nendobj\n"
    . "11 0 obj\n<< /S /GoTo /D [4 0 R /FitR 10 20 300 700] >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-wordpress-duplicate-root) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['outline_root_duplicate_key_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['Import Checklist', 'Media Review Appendix']) {
    throw new RuntimeException('Expected selected duplicate catalog Outlines root titles.');
}
if (($outline['duplicate_outline_root_objects'] ?? []) !== [8, 5]) {
    throw new RuntimeException('Expected duplicate catalog Outlines root candidates to be review metadata.');
}
if (($outline['duplicate_outline_root_selected_object'] ?? null) !== 5) {
    throw new RuntimeException('Expected final top-level catalog Outlines root to be selected.');
}
if (($review['source'] ?? null) !== 'catalog_outline_root_duplicate_key') {
    throw new RuntimeException('Expected duplicate catalog Outlines root review metadata.');
}
if (array_column($toc, 'title') !== ['Import Checklist', 'Media Review Appendix']) {
    throw new RuntimeException('Expected selected TOC titles from duplicate catalog Outlines roots.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['Import Checklist', 'Media Review Appendix']) {
    throw new RuntimeException('Expected selected navigation titles from duplicate catalog Outlines roots.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'Stale Duplicate Root Import')) {
    throw new RuntimeException('Expected unselected duplicate root title to stay out of document metadata.');
}
if (!is_string($encodedNavigation) || str_contains($encodedNavigation, 'stale-wordpress-duplicate-root')) {
    throw new RuntimeException('Expected unselected duplicate root action target to stay out of navigation review.');
}
if (str_contains($plainText, 'Import Checklist')
    || str_contains($plainText, 'Media Review Appendix')
    || str_contains($plainText, 'Stale Duplicate Root Import')
    || str_contains($plainText, 'stale-wordpress-duplicate-root')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-outline-metadata-catalog-duplicate-root-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-catalog-duplicate-root-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'duplicate top-level catalog /Outlines operands are review-only provenance while the selected root owns WordPress navigation metadata',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'duplicate_root_entry_count' => $outline['duplicate_outline_root_entry_count'] ?? null,
    'duplicate_root_objects' => $outline['duplicate_outline_root_objects'] ?? [],
    'selected_root_object' => $outline['duplicate_outline_root_selected_object'] ?? null,
    'selected_entry_index' => $outline['duplicate_outline_root_selected_entry_index'] ?? null,
    'duplicate_root_review_only' => $outline['duplicate_outline_root_review_only'] ?? null,
    'duplicate_root_payload_included' => $outline['duplicate_outline_root_payload_included'] ?? null,
    'stale_root_excluded_from_metadata' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Duplicate Root Import'),
    'stale_root_excluded_from_navigation' => is_string($encodedNavigation) && !str_contains($encodedNavigation, 'stale-wordpress-duplicate-root'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Checklist')
        && !str_contains($plainText, 'Media Review Appendix')
        && !str_contains($plainText, 'Stale Duplicate Root Import'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline duplicate root review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . (int) ($item['outline_object'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
