<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root duplicate traversal intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root duplicate traversal appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 8 0 R /Last 8 0 R /Count 1 /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Overview) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Appendix Review) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /FitR 20 40 420 760] >>\nendobj\n"
    . "8 0 obj\n<< /Title (Stale WordPress Root First) /Parent 5 0 R /Dest [4 0 R /Fit] /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-wordpress-root-first) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['outline_root_traversal_duplicate_key_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['duplicate_outline_root_traversal_keys'] ?? []) !== ['First', 'Last', 'Count']) {
    throw new RuntimeException('Expected duplicate selected outline-root traversal keys to be review metadata.');
}
if (($outline['first_item_object'] ?? null) !== 6 || ($outline['last_item_object'] ?? null) !== 7 || ($outline['outline_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected selected outline-root traversal operands to own WordPress navigation.');
}
if (array_column($toc, 'title') !== ['Import Overview', 'Appendix Review']) {
    throw new RuntimeException('Expected TOC to use selected outline-root traversal operands.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedMetadata, 'Stale WordPress Root First')
    || str_contains($encodedNavigation, 'Stale WordPress Root First')
    || str_contains($encodedMetadata, 'stale-wordpress-root-first')
    || str_contains($encodedNavigation, 'stale-wordpress-root-first')
) {
    throw new RuntimeException('Expected stale root traversal operand metadata to stay excluded.');
}
if (str_contains($plainText, 'Import Overview')
    || str_contains($plainText, 'Appendix Review')
    || str_contains($plainText, 'Stale WordPress Root First')
    || str_contains($plainText, 'stale-wordpress-root-first')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-root-traversal-duplicate-keys-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-traversal-duplicate-keys-currentbase',
    'support_component' => 'native-pdf-outline-metadata-review',
    'native_boundary' => 'duplicate selected outline-root /First /Last /Count keys are review metadata while the last top-level operands own navigation',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'duplicate_root_traversal_key_count' => $outline['duplicate_outline_root_traversal_key_count'] ?? null,
    'duplicate_root_traversal_keys' => $outline['duplicate_outline_root_traversal_keys'] ?? [],
    'selected_first_item_object' => $outline['first_item_object'] ?? null,
    'selected_last_item_object' => $outline['last_item_object'] ?? null,
    'selected_outline_count' => $outline['outline_count'] ?? null,
    'selected_entry_policy' => $review['selected_entry_policy'] ?? null,
    'stale_root_operand_excluded_from_metadata' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale WordPress Root First'),
    'stale_root_operand_excluded_from_navigation' => is_string($encodedNavigation) && !str_contains($encodedNavigation, 'stale-wordpress-root-first'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Overview')
        && !str_contains($plainText, 'Stale WordPress Root First'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline root duplicate traversal review\"><ul>\n";
foreach ($toc as $row) {
    $pageNumber = is_int($row['page'] ?? null) ? $row['page'] + 1 : '';
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) $pageNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-view="' . htmlspecialchars((string) ($row['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
