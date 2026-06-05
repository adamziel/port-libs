<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$quadPointsStaleRectBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 210 Td (Quad rescue Rect decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 50 250 250] /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 200 310 218] /QuadPoints [72 218 150 218 72 200 150 200] /Contents (Stale rect quad review) /A << /S /URI /URI (https://example.com/quad-rescue) >> >>\nendobj\n"
        . "%%EOF";
};

$quadPointsStaleRectBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 200.0, 310.0, 218.0],
            'lines' => [[
                'bbox' => [72.0, 200.0, 310.0, 218.0],
                'spans' => [
                    ['text' => 'Quad rescue', 'bbox' => [72.0, 200.0, 150.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Rect decoy', 'bbox' => [260.0, 200.0, 310.0, 218.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses visible Link QuadPoints when the annotation Rect is stale or off-page' => static function (
        TestRunner $t
    ) use ($quadPointsStaleRectBoundaryPdf, $quadPointsStaleRectBoundaryPages): void {
        $pdf = $quadPointsStaleRectBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));

        $link = $links[0]['links'][0];
        $t->same(7, $link['annotation_object']);
        $t->same([260.0, 200.0, 310.0, 218.0], $link['rect']);
        $t->same(true, $link['rect_clipped_to_page']);
        $t->same(false, $link['rect_inside_page_bbox']);
        $t->same([72.0, 200.0, 150.0, 218.0], $link['visible_quad_rects'][0]);
        $t->same(0, $link['quad_rects_excluded_by_page_bbox']);
        $t->same('https://example.com/quad-rescue', $link['uri']);
        $t->same('Stale rect quad review', $link['contents']);

        $pages = $extractor->applyLinksToPages($quadPointsStaleRectBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/quad-rescue', $spans[0]['link_uri']);
        $t->same(0, $spans[0]['link_quad_index']);
        $t->same([72.0, 200.0, 150.0, 218.0], $spans[0]['link_visible_page_quad_rect']);
        $t->true(!isset($spans[1]['link_uri']), 'The stale off-page /Rect must not donate a link to the rect-only span.');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Quad rescue](https://example.com/quad-rescue) Rect decoy', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Quad rescue Rect decoy', $plainText);
        foreach ([
            'quad-rescue',
            'Stale rect quad review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
