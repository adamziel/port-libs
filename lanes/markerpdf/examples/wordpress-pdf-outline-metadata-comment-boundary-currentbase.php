<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata comment boundary intro) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata comment boundary appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First % 99 0 R stale first item\n 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title % /Title (Stale Import Comment Title)\n 30 0 R /Parent 5 0 R /Dest [ % 99 0 R ] stale destination\n 3 0 R /XYZ null null null ] /Next % 99 0 R stale sibling\n 7 0 R /C [ % 1 0 0 ] stale red color\n 0 .25 .5 ] /F % 0 stale style\n 2 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Comment Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest 21 0 R /Next % /Next 99 0 R\n 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Stale Import After Comment Next) /Parent 5 0 R /Prev 7 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /FitH 640] (StaleComment) [99 0 R /Fit]] >>\nendobj\n"
    . "21 0 obj\n% [99 0 R /Fit] stale import indirect destination array\n[4 0 R /FitH 640]\nendobj\n"
    . "30 0 obj\n% /Title (Fake import indirect title)\n(Import Comment Boundary Chapter)\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['Import Comment Boundary Chapter', 'Import Comment Boundary Appendix']) {
    throw new RuntimeException('Expected comment-aware document outline metadata titles.');
}
if (array_column($toc, 'title') !== ['Import Comment Boundary Chapter', 'Import Comment Boundary Appendix']) {
    throw new RuntimeException('Expected comment-aware TOC titles.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['Import Comment Boundary Chapter', 'Import Comment Boundary Appendix']) {
    throw new RuntimeException('Expected comment-aware navigation review titles.');
}
if (($outline['items'][0]['text_color_hex'] ?? null) !== '#004080') {
    throw new RuntimeException('Expected comment-aware outline color metadata.');
}
if (($outline['items'][0]['style_flags'] ?? null) !== 2 || ($outline['items'][0]['is_bold'] ?? null) !== true) {
    throw new RuntimeException('Expected comment-aware outline style metadata.');
}
if (($outline['items'][1]['view_mode'] ?? null) !== 'FitH' || ($outline['items'][1]['page_object'] ?? null) !== 4) {
    throw new RuntimeException('Expected comment-aware indirect outline destination array metadata.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Stale Import Comment Title')
    || str_contains($encoded, 'Fake import indirect title')
    || str_contains($encoded, 'Stale Import After Comment Next')
) {
    throw new RuntimeException('Expected comment-only outline operands to stay out of document metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'Stale Import After Comment Next')) {
    throw new RuntimeException('Expected comment-only outline operands to stay out of navigation metadata.');
}
if (str_contains($plainText, 'Import Comment Boundary Chapter')
    || str_contains($plainText, 'Import Comment Boundary Appendix')
    || str_contains($plainText, 'Stale Import Comment Title')
    || str_contains($plainText, 'Stale Import After Comment Next')
) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-outline-metadata-comment-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-comment-boundary-currentbase',
    'support_component' => 'native-pdf-document-outline-comment-aware-token-reader',
    'native_boundary' => 'PDF comments before outline metadata operands are whitespace, not alternate titles, destinations, siblings, style flags, or color values',
    'outline_titles' => $outline['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'first_item_object' => $outline['first_item_object'] ?? null,
    'last_item_object' => $outline['last_item_object'] ?? null,
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'comment_title_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale Import Comment Title'),
    'comment_destination_excluded' => is_string($encoded) && !str_contains($encoded, '99 0 R'),
    'comment_next_excluded_from_traversal' => is_string($encoded) && !str_contains($encoded, 'Stale Import After Comment Next'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Comment Boundary Chapter')
        && !str_contains($plainText, 'Import Comment Boundary Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata comment review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-color="' . htmlspecialchars((string) ($item['text_color_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
