<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Safe docs Tailed link Tailed highlight Clean highlight) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe docs subtype review) /A << /S /URI /URI (https://example.com/safe-subtype-tail-boundary) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype 20 0 R /Rect [160 700 250 718] /Contents (Tailed link subtype review) /A << /S /URI /URI (https://example.com/tailed-subtype-link-promote) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype 21 0 R /Rect [260 700 370 718] /QuadPoints [260 718 370 718 260 700 370 700] /Contents (Tailed highlight subtype review) /T (Subtype QA) /C [1 0.9 0] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [380 700 500 718] /QuadPoints [380 718 500 718 380 700 500 700] /Contents (Clean highlight subtype review) /T (Subtype QA) /C [0.1 0.8 0.3] >>\nendobj\n"
    . "20 0 obj\n/Link 30 0 R\nendobj\n"
    . "21 0 obj\n/Highlight 30 0 R\nendobj\n"
    . "30 0 obj\n<< /S /JavaScript /JS (tailedSubtypeReview\\(\\)) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 500.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 500.0, 718.0],
            'spans' => [
                ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed link', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed highlight', 'bbox' => [260.0, 700.0, 370.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Clean highlight', 'bbox' => [380.0, 700.0, 500.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$markupExtractor = new PdfMarkupAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$markups = $markupExtractor->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';
$encodedPromoted = json_encode([$links, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';
$spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$summary = [
    'support_component' => 'native-pdf-link-annotation-indirect-subtype-tail-boundary',
    'native_boundary' => 'indirect annotation Subtype name objects with trailing top-level operands fail closed before WordPress link and markup span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_subtypes' => array_column($annotations[0]['annotations'] ?? [], 'subtype'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'markup_objects' => array_column($markups[0]['markups'] ?? [], 'annotation_object'),
    'tailed_subtype_link_promoted' => isset($spans[1]['link_uri']) || str_contains($encodedPromoted, 'tailed-subtype-link-promote'),
    'tailed_subtype_markup_attached' => isset($spans[2]['review_annotations']) || str_contains(json_encode($markups, JSON_UNESCAPED_SLASHES) ?: '', 'Tailed highlight subtype review'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'visible_text_imported' => str_contains($plainText, 'Safe docs Tailed link Tailed highlight Clean highlight'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Tailed link subtype review')
        || str_contains($plainText, 'Tailed highlight subtype review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['annotation_subtypes'] ?? []) !== ['Link', 'Unknown', 'Unknown', 'Highlight']) {
    throw new RuntimeException('Expected tailed indirect Subtype name operands to be marked Unknown in annotation review.');
}
if (($summary['promoted_link_objects'] ?? []) !== [7] || ($summary['markup_objects'] ?? []) !== [10]) {
    throw new RuntimeException('Expected only clean direct Link and Highlight annotations to promote.');
}
if (($summary['tailed_subtype_link_promoted'] ?? true) || ($summary['tailed_subtype_markup_attached'] ?? true)) {
    throw new RuntimeException('Tailed indirect Subtype annotations must not attach to WordPress spans.');
}
if (($summary['annotation_payload_text_visible'] ?? true) || !($summary['visible_text_imported'] ?? false)) {
    throw new RuntimeException('Annotation review payload text boundary failed.');
}

echo '<!-- markerpdf-pdf-link-annotation-indirect-subtype-tail-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
