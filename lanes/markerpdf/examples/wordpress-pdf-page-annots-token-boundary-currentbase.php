<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current docs Current highlight Comment decoy Literal decoy Nested decoy Hex decoy Sticky note) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R % 8 0 R ] stale comment must not close or promote\n9 0 R (10 0 R) <313120302052> [12 0 R] 13 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Current docs link review) /A << /S /URI /URI (https://example.com/current-docs-token) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 350 718] /Contents (Comment decoy link) /A << /S /URI /URI (https://example.com/comment-decoy-link) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Current highlight token review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 450 718] /Contents (Literal decoy link) /A << /S /URI /URI (https://example.com/literal-decoy-link) >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [460 700 540 718] /Contents (Hex decoy link) /A << /S /URI /URI (https://example.com/hex-decoy-link) >> >>\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [550 700 620 718] /Contents (Nested decoy link) /A << /S /URI /URI (https://example.com/nested-decoy-link) >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /Annot /Subtype /Text /Rect [630 700 700 718] /Contents (Current sticky token note) /T (Import QA) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 700.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 700.0, 718.0],
            'spans' => [
                ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Current highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Comment decoy', 'bbox' => [270.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Literal decoy', 'bbox' => [360.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hex decoy', 'bbox' => [460.0, 700.0, 540.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Nested decoy', 'bbox' => [550.0, 700.0, 620.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Sticky note', 'bbox' => [630.0, 700.0, 700.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
$linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotationPages, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-page-annots-token-boundary',
    'annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_subtypes' => array_column($annotationPages[0]['annotations'] ?? [], 'subtype'),
    'page_link_count' => count($links[0]['links'] ?? []),
    'link_uri' => $links[0]['links'][0]['uri'] ?? null,
    'markup_annotation_object' => $markups[0]['markups'][0]['annotation_object'] ?? null,
    'markup_review' => $markups[0]['markups'][0]['contents'] ?? null,
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'comment_decoy_promoted' => str_contains($encodedReview, 'comment-decoy-link'),
    'literal_decoy_promoted' => str_contains($encodedReview, 'literal-decoy-link'),
    'hex_decoy_promoted' => str_contains($encodedReview, 'hex-decoy-link'),
    'nested_decoy_promoted' => str_contains($encodedReview, 'nested-decoy-link'),
    'visible_text_imported' => str_contains($visibleText, 'Current docs Current highlight'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Current highlight token review')
        || str_contains($visibleText, 'Current sticky token note'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-page-annots-token-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
