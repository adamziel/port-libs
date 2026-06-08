<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Repeated link Repeated highlight Sticky note) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 7 0 R 8 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n[7 0 R 8 0 R 9 0 R]\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Contents (Repeated link review) /A << /S /URI /URI (https://example.com/repeated-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [182 700 328 718] /QuadPoints [182 718 328 718 182 700 328 700] /Contents (Repeated highlight review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [340 700 420 718] /Contents (Repeated sticky note review) /T (Import QA) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 420.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 420.0, 718.0],
            'spans' => [
                ['text' => 'Repeated link', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Repeated highlight', 'bbox' => [182.0, 700.0, 328.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Sticky note', 'bbox' => [340.0, 700.0, 420.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkPages = $linkExtractor->extractPageLinks($pdf);
$markupExtractor = new PdfMarkupAnnotationExtractor();
$markupPages = $markupExtractor->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$summary = [
    'support_component' => 'native-pdf-annotation-link-duplicate-annots-boundary',
    'native_boundary' => 'page /Annots duplicate indirect references are deduplicated after nested array expansion before WordPress link and markup promotion',
    'annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'link_annotation_objects' => array_column($linkPages[0]['links'] ?? [], 'annotation_object'),
    'markup_annotation_objects' => array_column($markupPages[0]['markups'] ?? [], 'annotation_object'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'deduplicated_annotation_count' => count($annotationPages[0]['annotations'] ?? []),
    'deduplicated_link_count' => count($linkPages[0]['links'] ?? []),
    'deduplicated_markup_count' => count($markupPages[0]['markups'] ?? []),
    'span_review_annotation_count' => count($reviewPages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations'] ?? []),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'Repeated link review')
        && !str_contains($visibleText, 'Repeated highlight review')
        && !str_contains($visibleText, 'Repeated sticky note review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['annotation_objects'] ?? []) !== [7, 8, 9]) {
    throw new RuntimeException('Expected repeated /Annots references to produce one review row per annotation object.');
}
if (($summary['link_annotation_objects'] ?? []) !== [7]) {
    throw new RuntimeException('Expected repeated Link annotation references to produce one promoted link row.');
}
if (($summary['markup_annotation_objects'] ?? []) !== [8]) {
    throw new RuntimeException('Expected repeated text-markup references to produce one markup row.');
}
if (($summary['span_review_annotation_count'] ?? 0) !== 1) {
    throw new RuntimeException('Expected the WordPress span to carry one markup review annotation.');
}
if (($summary['wordpress_text'] ?? '') !== '[Repeated link](https://example.com/repeated-link) Repeated highlight Sticky note') {
    throw new RuntimeException('Unexpected WordPress paragraph output for duplicate annotation references.');
}
if (($summary['annotation_payload_text_excluded_from_visible_text'] ?? false) !== true) {
    throw new RuntimeException('Annotation review payload leaked into visible PDF text.');
}

$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}';
echo '<!-- markerpdf-pdf-annotation-link-duplicate-annots-currentbase ' . htmlspecialchars($summaryJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
