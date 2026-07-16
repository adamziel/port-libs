<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline indirect title boundary intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline indirect title boundary appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title 7 0 R /Parent 5 0 R /Dest [3 0 R /Fit] /A 8 0 R /Next 10 0 R >>\nendobj\n"
    . "7 0 obj\n(Malformed WordPress Indirect Outline Title) /A 8 0 R\nendobj\n"
    . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('malformed wordpress indirect outline title action'\\)) >>\nendobj\n"
    . "10 0 obj\n<< /Title (WordPress Safe Outline Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /FitH 640] >>\nendobj\n"
    . "11 0 obj\n<< /Title (WordPress Info Title Current) /Author (MarkerPDF Lane) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "trailer\n<< /Info 11 0 R >>\n%%EOF";

$textExtractor = new PdfTextExtractor();
$outlineExtractor = new PdfOutlineExtractor();
$textMetadata = $textExtractor->extractOutlineMetadata($pdf);
$documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$textTocTitles = array_column($textMetadata['pdf_toc'], 'title');
$documentOutlineTitles = $documentMetadata['document_outline']['titles'] ?? [];
$navigationTitles = array_column($navigation['outline'] ?? [], 'title');
$encodedTextMetadata = json_encode($textMetadata, JSON_UNESCAPED_SLASHES);
$encodedDocumentMetadata = json_encode($documentMetadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$expectedTitles = ['WordPress Safe Outline Appendix'];

if ($textTocTitles !== $expectedTitles || $documentOutlineTitles !== $expectedTitles || $navigationTitles !== $expectedTitles) {
    throw new RuntimeException('Expected malformed indirect outline title to be rejected across metadata paths.');
}
if (($textMetadata['document_info']['title'] ?? null) !== 'WordPress Info Title Current') {
    throw new RuntimeException('Expected current document Info metadata to remain available.');
}
if (($documentMetadata['document_outline']['items'][0]['previous_object'] ?? null) !== 6) {
    throw new RuntimeException('Expected valid next sibling to remain importable after malformed indirect title boundary.');
}
if ($remoteActions !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected malformed indirect title action operands to remain excluded.');
}
if (!is_string($encodedTextMetadata)
    || !is_string($encodedDocumentMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedTextMetadata, 'Malformed WordPress Indirect Outline Title')
    || str_contains($encodedDocumentMetadata, 'Malformed WordPress Indirect Outline Title')
    || str_contains($encodedNavigation, 'Malformed WordPress Indirect Outline Title')
    || str_contains($encodedTextMetadata, 'malformed wordpress indirect outline title action')
    || str_contains($encodedDocumentMetadata, 'malformed wordpress indirect outline title action')
    || str_contains($encodedNavigation, 'malformed wordpress indirect outline title action')
) {
    throw new RuntimeException('Expected malformed indirect outline title/action payloads to stay out of metadata.');
}
if (str_contains($plainText, 'Malformed WordPress Indirect Outline Title')
    || str_contains($plainText, 'WordPress Safe Outline Appendix')
    || str_contains($plainText, 'malformed wordpress indirect outline title action')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-indirect-title-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-indirect-title-boundary-currentbase',
    'support_component' => 'native-pdf-outline-indirect-title-boundary-review',
    'native_boundary' => 'indirect outline titles must resolve to exactly one string/name token before lightweight TOC metadata imports them',
    'text_toc_titles' => $textTocTitles,
    'document_outline_titles' => $documentOutlineTitles,
    'navigation_titles' => $navigationTitles,
    'document_info_title_current' => $textMetadata['document_info']['title'] ?? null,
    'safe_outline_object' => $documentMetadata['document_outline']['items'][0]['outline_object'] ?? null,
    'safe_outline_previous_object' => $documentMetadata['document_outline']['items'][0]['previous_object'] ?? null,
    'malformed_indirect_title_excluded_from_lightweight_metadata' => is_string($encodedTextMetadata)
        && !str_contains($encodedTextMetadata, 'Malformed WordPress Indirect Outline Title'),
    'malformed_indirect_title_excluded_from_document_metadata' => is_string($encodedDocumentMetadata)
        && !str_contains($encodedDocumentMetadata, 'Malformed WordPress Indirect Outline Title'),
    'malformed_indirect_title_excluded_from_navigation_review' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'Malformed WordPress Indirect Outline Title'),
    'malformed_indirect_action_excluded' => count($remoteActions) === 0
        && ($navigation['outline_action_review_actions'] ?? []) === []
        && is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'malformed wordpress indirect outline title action'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Malformed WordPress Indirect Outline Title')
        && !str_contains($plainText, 'WordPress Safe Outline Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline indirect-title review\"><ul>\n";
foreach ($documentMetadata['document_outline']['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-previous="' . htmlspecialchars((string) ($item['previous_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
