<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action destination operand chapter body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action destination operand appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Malformed Action D Chapter) /Parent 5 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Action D Clean Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] 99 0 R >>\nendobj\n"
    . "99 0 obj\n<< /S /URI /URI (https://example.com/wordpress-tailed-action-destination-decoy) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$malformed = $items[0] ?? [];
$review = $malformed['action_destination_operand_boundary_review'] ?? [];
$actionRows = $navigation['outline_action_review_actions'] ?? [];
$tocTitles = array_column($toc, 'title');
$navigationTitles = array_column($navigation['outline'] ?? [], 'title');
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);
$decoyUri = 'https://example.com/wordpress-tailed-action-destination-decoy';

if (($outline['resolved_destination_count'] ?? null) !== 1 || ($outline['unresolved_destination_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected malformed action /D operands to remain unresolved while the clean outline item resolves.');
}
if (($malformed['destination_resolved'] ?? null) !== false || ($malformed['destination'] ?? null) !== null) {
    throw new RuntimeException('Expected tailed action /D operand to stay out of WordPress outline destinations.');
}
if (($review['status'] ?? null) !== 'rejected_malformed_outline_action_d_operand'
    || ($review['trailing_reference_object_numbers'] ?? []) !== [99]
) {
    throw new RuntimeException('Expected review metadata for malformed GoTo action /D operands.');
}
if ($tocTitles !== ['WordPress Action D Clean Appendix']
    || $navigationTitles !== ['WordPress Action D Clean Appendix']
    || array_column($lightweight['pdf_toc'] ?? [], 'title') !== ['WordPress Action D Clean Appendix']
) {
    throw new RuntimeException('Expected only the clean outline destination in WordPress TOC/navigation imports.');
}
if (array_column($actionRows, 'safety') !== ['unsupported-action-review']
    || array_column($actionRows, 'action_object') !== [12]
) {
    throw new RuntimeException('Expected malformed local GoTo action to remain a non-executing review row.');
}
foreach ([$encodedMetadata, $encodedNavigation, $encodedLightweight] as $encoded) {
    if (!is_string($encoded) || str_contains($encoded, $decoyUri)) {
        throw new RuntimeException('Expected tailed URI action payload to stay out of imported metadata.');
    }
}
if (str_contains($plainText, 'WordPress Malformed Action D Chapter')
    || str_contains($plainText, 'WordPress Action D Clean Appendix')
    || str_contains($plainText, $decoyUri)
) {
    throw new RuntimeException('Expected outline metadata and tailed URI payload to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-action-destination-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-action-destination-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-action-destination-boundary',
    'native_boundary' => 'GoTo action /D must be a single top-level value before TOC/navigation promotion',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => $tocTitles,
    'navigation_titles' => $navigationTitles,
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'unresolved_destination_count' => $outline['unresolved_destination_count'] ?? null,
    'review_status' => $review['status'] ?? null,
    'trailing_reference_object_numbers' => $review['trailing_reference_object_numbers'] ?? [],
    'malformed_action_destination_unresolved' => ($malformed['destination_resolved'] ?? null) === false
        && ($malformed['destination'] ?? null) === null,
    'review_action_safety' => array_column($actionRows, 'safety'),
    'decoy_uri_excluded' => is_string($encodedMetadata)
        && is_string($encodedNavigation)
        && !str_contains($encodedMetadata, $decoyUri)
        && !str_contains($encodedNavigation, $decoyUri),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Malformed Action D Chapter')
        && !str_contains($plainText, 'WordPress Action D Clean Appendix')
        && !str_contains($plainText, $decoyUri),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline action destination boundary review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . (int) ($item['page'] ?? 0)
        . '" data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
