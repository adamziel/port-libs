<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$malformedQuadPointsBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Broken quad Valid quad Rect decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 345 718] /QuadPoints [72 718 150 718 72 700 150 /BadCoordinate 218 718 300 718 218 700 300 700] /Contents (Malformed quad link review) /A << /S /URI /URI (https://example.com/valid-quad-only) >> >>\nendobj\n"
        . "%%EOF";
};

$malformedQuadPointsBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 345.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 345.0, 718.0],
                'spans' => [
                    ['text' => 'Broken quad', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Valid quad', 'bbox' => [218.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Rect decoy', 'bbox' => [310.0, 700.0, 345.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'skips malformed Link annotation QuadPoints groups without recombining later coordinates' => static function (
        TestRunner $t
    ) use ($malformedQuadPointsBoundaryPdf, $malformedQuadPointsBoundaryPages): void {
        $pdf = $malformedQuadPointsBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));

        $link = $links[0]['links'][0];
        $t->same(7, $link['annotation_object']);
        $t->same([72.0, 700.0, 345.0, 718.0], $link['rect']);
        $t->same([[218.0, 718.0, 300.0, 718.0, 218.0, 700.0, 300.0, 700.0]], $link['quad_points']);
        $t->same([[218.0, 700.0, 300.0, 718.0]], $link['quad_rects']);
        $t->same('https://example.com/valid-quad-only', $link['uri']);

        $pages = $extractor->applyLinksToPages($malformedQuadPointsBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_uri']), 'The malformed first quad group must not donate a shifted clickable rectangle.');
        $t->same('https://example.com/valid-quad-only', $spans[1]['link_uri']);
        $t->same(0, $spans[1]['link_quad_index']);
        $t->same([218.0, 700.0, 300.0, 718.0], $spans[1]['link_page_quad_rect']);
        $t->true(!isset($spans[2]['link_uri']), 'The broad /Rect remains review metadata and is not used while a valid QuadPoints group exists.');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Broken quad [Valid quad](https://example.com/valid-quad-only) Rect decoy', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Broken quad Valid quad Rect decoy', $plainText);
        foreach ([
            'valid-quad-only',
            'Malformed quad link review',
            'BadCoordinate',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
