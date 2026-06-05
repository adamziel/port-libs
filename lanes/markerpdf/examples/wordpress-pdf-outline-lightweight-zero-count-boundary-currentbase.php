<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (Lightweight zero count chapter body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (Lightweight zero count appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Zero Count Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 0 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Zero Count Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
    . "8 0 obj\n<< /Title (Hidden Zero Count Child) /Parent 6 0 R /Dest [4 0 R /FitR 1 2 3 4] /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (hidden-zero-count-child.pdf) /D (hidden-child-target) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$outlineExtractor = new PdfOutlineExtractor();
$lightweightMetadata = $textExtractor->extractOutlineMetadata($pdf);
$documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedLightweight = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);
$encodedDocument = json_encode($documentMetadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

$expectedTitles = ['Import Zero Count Chapter', 'Import Zero Count Appendix'];
if (array_column($lightweightMetadata['pdf_toc'], 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected lightweight pdf_toc to honor outline item /Count 0 child boundary.');
}
if (array_column($toc, 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected rich outline TOC to stay aligned with lightweight metadata.');
}
if (($documentMetadata['document_outline']['titles'] ?? []) !== $expectedTitles) {
    throw new RuntimeException('Expected document metadata outline titles to exclude hidden zero-count child.');
}
if (
    !is_string($encodedLightweight)
    || !is_string($encodedDocument)
    || !is_string($encodedNavigation)
    || str_contains($encodedLightweight, 'Hidden Zero Count Child')
    || str_contains($encodedDocument, 'Hidden Zero Count Child')
    || str_contains($encodedNavigation, 'hidden-zero-count-child.pdf')
) {
    throw new RuntimeException('Expected hidden zero-count child outline/action metadata to stay review-bounded.');
}
if (str_contains($plainText, 'Import Zero Count Chapter') || str_contains($plainText, 'Hidden Zero Count Child')) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-outline-lightweight-zero-count-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-lightweight-zero-count-boundary-currentbase',
    'support_component' => 'native-pdf-lightweight-outline-count-boundary',
    'native_boundary' => 'upstream-style lightweight pdf_toc extraction honors outline item /Count 0 before child traversal',
    'lightweight_toc_titles' => array_column($lightweightMetadata['pdf_toc'], 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'document_outline_titles' => $documentMetadata['document_outline']['titles'] ?? [],
    'hidden_child_unpromoted_from_lightweight_metadata' => is_string($encodedLightweight)
        && !str_contains($encodedLightweight, 'Hidden Zero Count Child'),
    'hidden_child_action_unpromoted_from_navigation' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'hidden-zero-count-child.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Zero Count Chapter')
        && !str_contains($plainText, 'Hidden Zero Count Child'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($lightweightMetadata['pdf_toc'] as $item) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) (($item['page'] ?? 0) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
