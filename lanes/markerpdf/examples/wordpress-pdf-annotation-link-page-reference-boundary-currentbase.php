<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Page one link Page one highlight Cross page decoy) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Page two link Page two highlight) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 11 0 R /Annots [8 0 R 10 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /P 3 0 R /Rect [72 700 162 718] /Contents (Page one link review) /A << /S /URI /URI (https://example.com/page-one-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /P 4 0 R /Rect [72 700 162 718] /Contents (Page two link review) /A << /S /URI /URI (https://example.com/page-two-link) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 0 R /Rect [174 700 292 718] /QuadPoints [174 718 292 718 174 700 292 700] /Contents (Page one highlight review) /T (Import QA) /C [1 0.9 0] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /P 4 0 R /Rect [174 700 292 718] /QuadPoints [174 718 292 718 174 700 292 700] /Contents (Page two highlight review) /T (Import QA) /C [0.4 0.8 1] >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "%%EOF";

$pages = [
    [
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 400.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 400.0, 718.0],
                'spans' => [
                    ['text' => 'Page one link', 'bbox' => [72.0, 700.0, 162.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Page one highlight', 'bbox' => [174.0, 700.0, 292.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Cross page decoy', 'bbox' => [304.0, 700.0, 400.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
    [
        'pnum' => 1,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 292.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 292.0, 718.0],
                'spans' => [
                    ['text' => 'Page two link', 'bbox' => [72.0, 700.0, 162.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Page two highlight', 'bbox' => [174.0, 700.0, 292.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkPages = $linkExtractor->extractPageLinks($pdf);
$markupPages = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$pageOneReview = json_encode([$annotationPages[0] ?? [], $linkPages[0] ?? [], $markupPages[0] ?? [], $reviewPages[0] ?? []], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object') !== [7, 9]) {
    throw new RuntimeException('Expected page-one annotation review to exclude page-two /P references.');
}
if (array_column($linkPages[0]['links'] ?? [], 'annotation_object') !== [7]) {
    throw new RuntimeException('Expected page-one WordPress links to exclude page-two /P references.');
}
if (array_column($markupPages[0]['markups'] ?? [], 'annotation_object') !== [9]) {
    throw new RuntimeException('Expected page-one markup review to exclude page-two /P references.');
}
if (str_contains($pageOneReview, 'page-two-link') || str_contains($pageOneReview, 'Page two highlight review')) {
    throw new RuntimeException('Cross-page annotation review leaked into page one.');
}
if (str_contains($visibleText, 'Page one link review') || str_contains($visibleText, 'Page two link review')) {
    throw new RuntimeException('Annotation payload text leaked into visible PDF text.');
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-annotation-link-page-reference-boundary',
    'native_boundary' => 'page /Annots entries are accepted only when their optional annotation /P page reference is absent or names the same page object before WordPress link and markup promotion',
    'page_one_annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'page_two_annotation_objects' => array_column($annotationPages[1]['annotations'] ?? [], 'annotation_object'),
    'page_one_link_uris' => array_column($linkPages[0]['links'] ?? [], 'uri'),
    'page_two_link_uris' => array_column($linkPages[1]['links'] ?? [], 'uri'),
    'page_one_markup_contents' => array_column($markupPages[0]['markups'] ?? [], 'contents'),
    'page_two_markup_contents' => array_column($markupPages[1]['markups'] ?? [], 'contents'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'cross_page_uri_excluded_from_page_one' => !str_contains($pageOneReview, 'page-two-link'),
    'cross_page_markup_excluded_from_page_one' => !str_contains($pageOneReview, 'Page two highlight review'),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'Page one link review')
        && !str_contains($visibleText, 'Page two link review')
        && !str_contains($visibleText, 'Page one highlight review')
        && !str_contains($visibleText, 'Page two highlight review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-link-page-reference-boundary-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
