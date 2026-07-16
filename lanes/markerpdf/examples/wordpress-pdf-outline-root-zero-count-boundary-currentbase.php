<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress root zero count outline intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress root zero count outline appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 0 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Stale WordPress Root Zero Chapter) /Parent 5 0 R /Dest /HiddenStart /Next 7 0 R /A 12 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Stale WordPress Root Zero Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /HiddenAppendix /A 13 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress root zero outline action leak'\\)) >>\nendobj\n"
    . "13 0 obj\n<< /S /GoToR /F (wordpress-root-zero-count-appendix.pdf) /D (hidden-appendix) /NewWindow true >>\nendobj\n"
    . "20 0 obj\n<< /Names [(HiddenAppendix) [4 0 R /Fit] (HiddenStart) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

if (($outline['outline_count'] ?? null) !== 0 || ($outline['item_count'] ?? null) !== 0 || ($outline['titles'] ?? null) !== []) {
    throw new RuntimeException('Expected root Count zero outline metadata without child rows.');
}
if ($toc !== [] || ($navigation['outline'] ?? null) !== [] || ($navigation['outline_action_review_actions'] ?? null) !== []) {
    throw new RuntimeException('Expected root Count zero outline rows and action reviews to stay suppressed.');
}
if (($lightweight['pdf_toc'] ?? null) !== []) {
    throw new RuntimeException('Expected lightweight pdf_toc to respect root Count zero.');
}
foreach ([$metadataEncoded, $navigationEncoded, $lightweightEncoded, $plainText] as $encoded) {
    if (!is_string($encoded)) {
        throw new RuntimeException('Expected encodable root zero-count review payload.');
    }
    if (str_contains($encoded, 'Stale WordPress Root Zero Chapter')
        || str_contains($encoded, 'Stale WordPress Root Zero Appendix')
        || str_contains($encoded, 'wordpress root zero outline action leak')
        || str_contains($encoded, 'wordpress-root-zero-count-appendix.pdf')
    ) {
        throw new RuntimeException('Expected stale root zero-count outline payload to stay out of WordPress output.');
    }
}

echo '<!-- markerpdf-outline-root-zero-count-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-zero-count-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-root-count-review',
    'native_boundary' => 'catalog /Outlines root Count zero suppresses contradictory child rows while root metadata stays review-only',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'first_item_object' => $outline['first_item_object'] ?? null,
    'last_item_object' => $outline['last_item_object'] ?? null,
    'declared_visible_count' => $outline['declared_visible_count'] ?? null,
    'metadata_item_count' => $outline['item_count'] ?? null,
    'toc_count' => count($toc),
    'navigation_count' => count($navigation['outline'] ?? []),
    'lightweight_toc_count' => count($lightweight['pdf_toc'] ?? []),
    'stale_outline_rows_suppressed' => is_string($metadataEncoded) && !str_contains($metadataEncoded, 'Stale WordPress Root Zero Chapter'),
    'stale_outline_actions_suppressed' => is_string($navigationEncoded) && !str_contains($navigationEncoded, 'wordpress-root-zero-count-appendix.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Stale WordPress Root Zero Chapter')
        && !str_contains($plainText, 'wordpress root zero outline action leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
