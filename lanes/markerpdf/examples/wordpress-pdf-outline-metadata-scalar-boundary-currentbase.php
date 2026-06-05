<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline scalar boundary intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline scalar boundary appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title 16 0 R /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 1 /A 12 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title 17 0 R /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
    . "8 0 obj\n<< /Title (Stale Import Child Under Malformed Scalar) /Parent 6 0 R /Dest [4 0 R /XYZ 72 640 0] /A 13 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (malformed-wordpress-scalar-title.pdf) /D (malformed-title) /Next 14 0 R >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/stale-wordpress-outline-child) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('malformed wordpress title action'\\)) >>\nendobj\n"
    . "16 0 obj\n(Import Scalar Spoof) /A 12 0 R /Next 99 0 R\nendobj\n"
    . "17 0 obj\n(Import Scalar Appendix) % comment-only title tail is valid whitespace\nendobj\n"
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
$expectedTitles = ['Import Scalar Appendix'];

if ($outlineTitles !== $expectedTitles || $tocTitles !== $expectedTitles || $navigationTitles !== $expectedTitles) {
    throw new RuntimeException('Expected malformed indirect outline title scalar to be excluded before WordPress navigation import.');
}
if (($outline['item_count'] ?? null) !== 1 || ($outline['resolved_destination_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected only the valid indirect outline title scalar to be counted.');
}
if (($outline['items'][0]['previous_object'] ?? null) !== 6) {
    throw new RuntimeException('Expected valid sibling traversal to continue across the rejected malformed title object.');
}
if ($remoteActions !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected malformed title object action chains to remain excluded from review rows.');
}
if (!is_string($metadataEncoded)
    || str_contains($metadataEncoded, 'Import Scalar Spoof')
    || str_contains($metadataEncoded, 'Stale Import Child Under Malformed Scalar')
    || str_contains($metadataEncoded, 'malformed-wordpress-scalar-title.pdf')
    || str_contains($metadataEncoded, 'malformed wordpress title action')
) {
    throw new RuntimeException('Expected malformed scalar outline metadata to stay out of document metadata.');
}
if (!is_string($navigationEncoded)
    || str_contains($navigationEncoded, 'Import Scalar Spoof')
    || str_contains($navigationEncoded, 'stale-wordpress-outline-child')
) {
    throw new RuntimeException('Expected malformed scalar outline metadata to stay out of navigation review.');
}
if (str_contains($plainText, 'Import Scalar Spoof')
    || str_contains($plainText, 'Import Scalar Appendix')
    || str_contains($plainText, 'Stale Import Child Under Malformed Scalar')
    || str_contains($plainText, 'malformed wordpress title action')
) {
    throw new RuntimeException('Expected outline scalar metadata and actions to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-scalar-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-scalar-boundary-currentbase',
    'support_component' => 'native-pdf-outline-title-scalar-boundary-review',
    'native_boundary' => 'indirect outline title objects must contain one scalar string/name token; trailing action or sibling tokens reject the bookmark before metadata import',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'imported_item_count' => $outline['item_count'] ?? null,
    'outline_titles' => $outlineTitles,
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'outline_objects' => array_column($outline['items'] ?? [], 'outline_object'),
    'valid_item_previous_object' => $outline['items'][0]['previous_object'] ?? null,
    'malformed_scalar_rejected' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Import Scalar Spoof'),
    'malformed_child_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'Stale Import Child Under Malformed Scalar'),
    'malformed_action_excluded' => count($remoteActions) === 0
        && is_string($navigationEncoded)
        && !str_contains($navigationEncoded, 'malformed-wordpress-scalar-title.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Scalar Spoof')
        && !str_contains($plainText, 'Import Scalar Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline scalar-boundary review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-previous="' . htmlspecialchars((string) ($item['previous_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
