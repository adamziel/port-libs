<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current generation page link Current generation markup Stale generation decoy) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale page body text) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 1 R] /Count 1 >>\nendobj\n"
    . "3 1 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /P 3 1 R /Rect [72 700 230 718] /Contents (Current generation page link review) /A << /S /URI /URI (https://example.com/current-page-generation-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /P 3 0 R /Rect [370 700 510 718] /Contents (Stale page generation link review) /A << /S /URI /URI (https://example.com/stale-page-generation-link) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 1 R /Rect [240 700 360 718] /QuadPoints [240 718 360 718 240 700 360 700] /Contents (Current generation markup review) /T (Generation QA) /C [0.2 0.7 0.4] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 0 R /Rect [370 700 510 718] /QuadPoints [370 718 510 718 370 700 510 700] /Contents (Stale page generation markup review) /T (Stale QA) /C [1 0 0] >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 40 40] /Resources << /Font << /F1 5 0 R >> >> /Contents 11 0 R /Annots [8 0 R 10 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 510.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'spans' => [
                ['text' => 'Current generation page link', 'bbox' => [72.0, 700.0, 230.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Current generation markup', 'bbox' => [240.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale generation decoy', 'bbox' => [370.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$encodedReview = json_encode([$annotations, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-page-annotation-link-generation-boundary',
    'native_boundary' => 'page tree /Kids references carry exact object generations into annotation /P membership before WordPress link and markup promotion',
    'page_object' => $links[0]['page_object'] ?? null,
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'markup_contents' => array_column($markups[0]['markups'] ?? [], 'contents'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'stale_page_generation_excluded' => !str_contains($encodedReview, 'stale-page-generation-link')
        && !str_contains($encodedReview, 'Stale page generation markup review'),
    'stale_span_promoted' => isset($reviewPages[0]['blocks'][0]['lines'][0]['spans'][2]['link_uri'])
        || isset($reviewPages[0]['blocks'][0]['lines'][0]['spans'][2]['review_annotations']),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-annotation-link-page-generation-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
