<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverText = 'BT /F1 12 Tf 72 720 Td (Outline label metadata cover body) Tj ET';
$chapterText = 'BT /F1 12 Tf 72 720 Td (Outline label metadata chapter body) Tj ET';
$appendixText = 'BT /F1 12 Tf 72 720 Td (Outline label metadata appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageLabels 30 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 6 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 7 0 R /Last 9 0 R /Count 3 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Metadata Cover Bookmark) /Parent 5 0 R /Dest [3 0 R /Fit] /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Metadata Chapter Bookmark) /Parent 5 0 R /Prev 7 0 R /Dest /ChapterStart /Next 9 0 R /C [0 .2 .8] /F 2 >>\nendobj\n"
    . "9 0 obj\n<< /Title (Metadata Appendix Action) /Parent 5 0 R /Prev 8 0 R /A 21 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($chapterText) . " >>\nstream\n{$chapterText}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($appendixText) . " >>\nstream\n{$appendixText}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [6 0 R /XYZ 72 null 1.25] (ChapterStart) [4 0 R /FitH 640]] >>\nendobj\n"
    . "21 0 obj\n<< /S /GoTo /D /AppendixTarget /Next 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /S /URI /URI (https://example.com/outline-label-review) >>\nendobj\n"
    . "30 0 obj\n<< /Nums [0 << /P (Cover-) /S /r /St 3 >> 1 << /P (Chapter ) /S /D /St 7 >> 2 << /P (Appendix-) /S /A /St 1 >>] >>\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$pageLabels = $textExtractor->extractPageLabels($pdf);
$outlineLabels = array_column($metadata['document_outline']['items'] ?? [], 'page_label');

if ($pageLabels !== ['Cover-iii', 'Chapter 7', 'Appendix-A'] || $outlineLabels !== $pageLabels) {
    throw new RuntimeException('Expected document outline metadata page labels to align with import page labels.');
}

if (str_contains($plainText, 'Chapter 7') || str_contains($plainText, 'Metadata Chapter Bookmark') || str_contains($plainText, 'outline-label-review')) {
    throw new RuntimeException('Expected outline labels and action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-page-label-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-page-label-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-page-label-review',
    'native_boundary' => 'document outline metadata rows inherit resolved catalog PageLabels while labels/actions remain review-only',
    'document_outline_labels' => $outlineLabels,
    'navigation_outline_labels' => array_column($navigation['outline'] ?? [], 'page_label'),
    'page_labels' => $pageLabels,
    'outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'visible_text_excludes_outline_labels' => !str_contains($plainText, 'Chapter 7')
        && !str_contains($plainText, 'Appendix-A'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($metadata['document_outline']['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page-label="' . htmlspecialchars((string) ($item['page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
