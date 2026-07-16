<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$overlapSpecificityPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Global banner Focused docs Sidebar note) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 690 365 730] /Contents (Broad banner review) /A << /S /URI /URI (https://example.com/broad-banner) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 260 718] /Contents (Focused docs review) /A << /S /URI /URI (https://example.com/focused-docs) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 690 360 730] /QuadPoints [270 718 360 718 270 700 360 700] /Contents (Sidebar quad review) /A << /S /URI /URI (https://example.com/sidebar-note) >> >>\nendobj\n"
        . "%%EOF";
};

$overlapSpecificityPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Global banner', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Focused docs', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Sidebar note', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'chooses the most specific overlapping Link annotation before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($overlapSpecificityPdf, $overlapSpecificityPages): void {
        $pdf = $overlapSpecificityPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 8, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/broad-banner', $links[0]['links'][0]['uri']);
        $t->same('https://example.com/focused-docs', $links[0]['links'][1]['uri']);
        $t->same('https://example.com/sidebar-note', $links[0]['links'][2]['uri']);
        $t->same([72.0, 690.0, 365.0, 730.0], $links[0]['links'][0]['rect']);
        $t->same([170.0, 700.0, 260.0, 718.0], $links[0]['links'][1]['rect']);
        $t->same([[270.0, 700.0, 360.0, 718.0]], $links[0]['links'][2]['quad_rects']);

        $pages = $extractor->applyLinksToPages($overlapSpecificityPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/broad-banner', $spans[0]['link_uri']);
        $t->same(7, $spans[0]['link_annotation_object']);
        $t->same([72.0, 690.0, 365.0, 730.0], $spans[0]['link_page_rect']);
        $t->same('https://example.com/focused-docs', $spans[1]['link_uri'], 'The narrower current link must not be swallowed by the broad first annotation.');
        $t->same(8, $spans[1]['link_annotation_object']);
        $t->same([170.0, 700.0, 260.0, 718.0], $spans[1]['link_rect']);
        $t->same('https://example.com/sidebar-note', $spans[2]['link_uri']);
        $t->same(9, $spans[2]['link_annotation_object']);
        $t->same(0, $spans[2]['link_quad_index']);
        $t->same([270.0, 700.0, 360.0, 718.0], $spans[2]['link_page_quad_rect']);
        $t->same('pdf_page_user_space', $spans[2]['link_rect_coordinate_space']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Global banner](https://example.com/broad-banner) [Focused docs](https://example.com/focused-docs) [Sidebar note](https://example.com/sidebar-note)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Global banner Focused docs Sidebar note', $plainText);
        foreach ([
            'broad-banner',
            'focused-docs',
            'sidebar-note',
            'Broad banner review',
            'Focused docs review',
            'Sidebar quad review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
