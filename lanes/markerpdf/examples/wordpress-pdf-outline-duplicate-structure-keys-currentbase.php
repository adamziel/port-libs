<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterText = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate structure key chapter body) Tj ET';
$appendixText = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate structure key appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress duplicate structure parent) /Parent 5 0 R /Dest [3 0 R /Fit] /Count 0 /First 10 0 R /Last 10 0 R /Count -1 /First 7 0 R /Last 7 0 R /Next 8 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress selected structure child) /Parent 6 0 R /Dest [4 0 R /FitH 640] >>\nendobj\n"
    . "8 0 obj\n<< /Title (WordPress duplicate style sibling) /Parent 5 0 R /Prev 99 0 R /Prev 6 0 R /Dest [4 0 R /Fit] /F 1 /C [1 0 0] /F 2 /C [0 .25 .5] >>\nendobj\n"
    . "10 0 obj\n<< /Title (Stale WordPress duplicate structure child) /Parent 6 0 R /Dest [4 0 R /FitR 1 2 3 4] /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-wordpress-duplicate-structure-child) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterText) . " >>\nstream\n{$chapterText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixText) . " >>\nstream\n{$appendixText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$parent = $items[0] ?? [];
$sibling = $items[2] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['duplicate_item_key_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected duplicate outline structure/style key review metadata.');
}
if (($parent['first_child_object'] ?? null) !== 7 || ($parent['outline_count'] ?? null) !== -1) {
    throw new RuntimeException('Expected last top-level Count/First/Last operands to select the real child.');
}
if (($sibling['text_color_hex'] ?? null) !== '#004080' || ($sibling['style_flags'] ?? null) !== 2) {
    throw new RuntimeException('Expected last top-level style/color operands to be selected.');
}
if (array_column($toc, 'title') !== [
    'WordPress duplicate structure parent',
    'WordPress selected structure child',
    'WordPress duplicate style sibling',
]) {
    throw new RuntimeException('Expected TOC navigation to use selected structure operands.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedMetadata, 'Stale WordPress duplicate structure child')
    || str_contains($encodedNavigation, 'Stale WordPress duplicate structure child')
    || str_contains($encodedMetadata, 'stale-wordpress-duplicate-structure-child')
    || str_contains($encodedNavigation, 'stale-wordpress-duplicate-structure-child')
) {
    throw new RuntimeException('Expected stale duplicate structure child metadata to stay excluded.');
}
if (str_contains($plainText, 'WordPress duplicate structure parent')
    || str_contains($plainText, 'WordPress selected structure child')
    || str_contains($plainText, 'WordPress duplicate style sibling')
    || str_contains($plainText, 'Stale WordPress duplicate structure child')
    || str_contains($plainText, 'stale-wordpress-duplicate-structure-child')
) {
    throw new RuntimeException('Expected outline structure metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-duplicate-structure-keys-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-duplicate-structure-keys-currentbase',
    'support_component' => 'native-pdf-outline-metadata-review',
    'native_boundary' => 'duplicate outline Count/First/Last/Prev/F/C keys are review metadata while last top-level operands drive navigation',
    'duplicate_item_key_count' => $outline['duplicate_item_key_count'] ?? null,
    'duplicate_item_keys' => $outline['duplicate_item_keys'] ?? [],
    'selected_parent_child_object' => $parent['first_child_object'] ?? null,
    'selected_parent_count' => $parent['outline_count'] ?? null,
    'selected_style_flags' => $sibling['style_flags'] ?? null,
    'selected_text_color_hex' => $sibling['text_color_hex'] ?? null,
    'stale_child_excluded' => is_string($encodedMetadata)
        && is_string($encodedNavigation)
        && !str_contains($encodedMetadata, 'Stale WordPress duplicate structure child')
        && !str_contains($encodedNavigation, 'Stale WordPress duplicate structure child'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress duplicate structure parent')
        && !str_contains($plainText, 'Stale WordPress duplicate structure child'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline structure review\"><ul>\n";
foreach ($toc as $row) {
    echo '<li data-marker-outline-destination="' . htmlspecialchars((string) ($row['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-view="' . htmlspecialchars((string) ($row['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
