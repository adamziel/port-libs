<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean link Tailed link Tailed highlight Clean highlight) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean link review) /A << /S /URI /URI (https://example.com/clean-object-tail-boundary) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 238 718] /Contents (Tailed link review) /A << /S /URI /URI (https://example.com/tailed-link-promote) >> >> 11 0 R\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [248 700 362 718] /QuadPoints [248 718 362 718 248 700 362 700] /Contents (Tailed highlight review) /T (Tail QA) /C [1 0.8 0] >> 12 0 R\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [372 700 500 718] /QuadPoints [372 718 500 718 372 700 500 700] /Contents (Clean highlight review) /T (Import QA) /C [0.2 0.7 0.4] >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Text /Rect [160 676 238 694] /Contents (Tailed link extra note review) >>\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Text /Rect [248 676 362 694] /Contents (Tailed highlight extra note review) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 500.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 500.0, 718.0],
            'spans' => [
                ['text' => 'Clean link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed link', 'bbox' => [160.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed highlight', 'bbox' => [248.0, 700.0, 362.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Clean highlight', 'bbox' => [372.0, 700.0, 500.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$markupExtractor = new PdfMarkupAnnotationExtractor();
$linkPages = $linkExtractor->extractPageLinks($pdf);
$markupPages = $markupExtractor->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotationPages, $linkPages, $markupPages, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-annotation-link-object-tail-boundary',
    'native_boundary' => 'indirect page annotation objects must contain one top-level annotation dictionary before WordPress link and markup promotion',
    'annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_subtypes' => array_column($annotationPages[0]['annotations'] ?? [], 'subtype'),
    'promoted_link_objects' => array_column($linkPages[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_uris' => array_column($linkPages[0]['links'] ?? [], 'uri'),
    'markup_annotation_objects' => array_column($markupPages[0]['markups'] ?? [], 'annotation_object'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'tailed_link_object_excluded' => !str_contains($encodedReview, 'tailed-link-promote')
        && !str_contains($encodedReview, 'Tailed link review'),
    'tailed_markup_object_excluded' => !str_contains($encodedReview, 'Tailed highlight review'),
    'tailed_object_tail_operands_excluded' => !str_contains($encodedReview, 'Tailed link extra note review')
        && !str_contains($encodedReview, 'Tailed highlight extra note review'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Clean link review')
        || str_contains($plainText, 'Tailed link review')
        || str_contains($plainText, 'Clean highlight review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['annotation_objects'] ?? []) !== [7, 10]) {
    throw new RuntimeException('Expected only single-value annotation objects to remain in page annotation review metadata.');
}
if (($summary['promoted_link_objects'] ?? []) !== [7]) {
    throw new RuntimeException('Expected only the clean link annotation object to promote into WordPress spans.');
}
if (($summary['markup_annotation_objects'] ?? []) !== [10]) {
    throw new RuntimeException('Expected only the clean markup annotation object to remain in review metadata.');
}
if (($summary['tailed_link_object_excluded'] ?? false) !== true || ($summary['tailed_markup_object_excluded'] ?? false) !== true) {
    throw new RuntimeException('Tailed indirect annotation objects must stay out of WordPress annotation imports.');
}
if (($summary['tailed_object_tail_operands_excluded'] ?? false) !== true || ($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation payload and object-tail text must not be imported as visible WordPress text.');
}

echo '<!-- markerpdf:pdf-annotation-link-object-tail-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
