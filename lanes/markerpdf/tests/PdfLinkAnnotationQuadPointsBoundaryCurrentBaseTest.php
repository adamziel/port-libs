<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkQuadPointsBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Left link middle Right link) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 680 318 718] /QuadPoints [72 718 150 718 72 700 150 700 230 698 318 698 230 680 318 680] /A << /S /URI /URI (https://example.com/quad-link) >> >>\nendobj\n"
        . "%%EOF";
};

$linkQuadPointsBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 680.0, 318.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 680.0, 318.0, 718.0],
                'spans' => [
                    ['text' => 'Left link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' middle', 'bbox' => [160.0, 690.0, 220.0, 708.0], 'font' => 'Helvetica'],
                    ['text' => ' Right link', 'bbox' => [230.0, 680.0, 318.0, 698.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

$rotatedLinkQuadPointsBoundaryPdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [10 20 210 320] /CropBox [20 40 180 240] /Rotate 90 /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /UserUnit 9 0 R /Annots [7 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [30 150 110 190] /QuadPoints [30 170 70 170 30 150 70 150 80 190 110 190 80 172 110 172] /A << /S /URI /URI (https://example.com/rotated-quad-link) >> >>\nendobj\n"
        . "9 0 obj\n2\nendobj\n"
        . "%%EOF";
};

$rotatedLinkQuadPointsBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 400.0, 320.0],
        'rotation' => 90,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [30.0, 20.0, 300.0, 180.0],
            'lines' => [[
                'bbox' => [30.0, 20.0, 300.0, 180.0],
                'spans' => [
                    ['text' => 'Rotated first', 'bbox' => [220.0, 20.0, 260.0, 100.0], 'font' => 'Helvetica'],
                    ['text' => ' raw decoy', 'bbox' => [30.0, 150.0, 70.0, 170.0], 'font' => 'Helvetica'],
                    ['text' => ' second', 'bbox' => [264.0, 120.0, 300.0, 180.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses Link annotation QuadPoints instead of the larger Rect before WordPress span promotion' => static function (TestRunner $t) use (
        $linkQuadPointsBoundaryPdf,
        $linkQuadPointsBoundaryPages
    ): void {
        $pdf = $linkQuadPointsBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));

        $link = $links[0]['links'][0];
        $t->same([72.0, 680.0, 318.0, 718.0], $link['rect']);
        $t->same([
            [72.0, 718.0, 150.0, 718.0, 72.0, 700.0, 150.0, 700.0],
            [230.0, 698.0, 318.0, 698.0, 230.0, 680.0, 318.0, 680.0],
        ], $link['quad_points']);
        $t->same([[72.0, 700.0, 150.0, 718.0], [230.0, 680.0, 318.0, 698.0]], $link['quad_rects']);
        $t->same([[72.0, 74.0, 150.0, 92.0], [230.0, 94.0, 318.0, 112.0]], $link['pdftext_quad_rects']);

        $pages = $extractor->applyLinksToPages($linkQuadPointsBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/quad-link', $spans[0]['link_uri']);
        $t->same(0, $spans[0]['link_quad_index']);
        $t->same([72.0, 700.0, 150.0, 718.0], $spans[0]['link_quad_rect']);
        $t->same('pdf_page_user_space', $spans[0]['link_rect_coordinate_space']);
        $t->same(false, isset($spans[1]['link_uri']), 'The middle span lies inside /Rect but outside both /QuadPoints rectangles.');
        $t->same(false, isset($spans[1]['link_quad_index']));
        $t->same('https://example.com/quad-link', $spans[2]['link_uri']);
        $t->same(1, $spans[2]['link_quad_index']);
        $t->same([230.0, 680.0, 318.0, 698.0], $spans[2]['link_page_quad_rect']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Left link](https://example.com/quad-link) middle [Right link](https://example.com/quad-link)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Left link middle Right link', $plainText);
        $t->same(false, str_contains($plainText, 'quad-link'));
    },
    'maps rotated Link annotation QuadPoints through page boxes before matching pdftext spans' => static function (TestRunner $t) use (
        $rotatedLinkQuadPointsBoundaryPdf,
        $rotatedLinkQuadPointsBoundaryPages
    ): void {
        $pdf = $rotatedLinkQuadPointsBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $link = $links[0]['links'][0];
        $t->same([30.0, 150.0, 110.0, 190.0], $link['rect']);
        $t->same([[30.0, 150.0, 70.0, 170.0], [80.0, 172.0, 110.0, 190.0]], $link['quad_rects']);
        $t->same([[220.0, 20.0, 260.0, 100.0], [264.0, 120.0, 300.0, 180.0]], $link['pdftext_quad_rects']);
        $t->same([20.0, 40.0, 180.0, 240.0], $link['page_bbox']);
        $t->same(90, $link['page_rotation']);
        $t->same(2.0, $link['page_user_unit']);

        $pages = $extractor->applyLinksToPages($rotatedLinkQuadPointsBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/rotated-quad-link', $spans[0]['link_uri']);
        $t->same(0, $spans[0]['link_quad_index']);
        $t->same('marker_pdftext_display', $spans[0]['link_rect_coordinate_space']);
        $t->same([30.0, 150.0, 70.0, 170.0], $spans[0]['link_page_quad_rect']);
        $t->same([220.0, 20.0, 260.0, 100.0], $spans[0]['link_pdftext_quad_rect']);
        $t->same(false, isset($spans[1]['link_uri']), 'Raw page-space quad coordinates are not used on marker/pdftext rotated pages.');
        $t->same('https://example.com/rotated-quad-link', $spans[2]['link_uri']);
        $t->same(1, $spans[2]['link_quad_index']);
        $t->same([264.0, 120.0, 300.0, 180.0], $spans[2]['link_quad_rect']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Rotated first](https://example.com/rotated-quad-link) raw decoy [second](https://example.com/rotated-quad-link)', $blocks[0]['text']);
    },
];
