<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

$markupPdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R << /Type /Annot /Subtype /Text /Rect [72 650 180 668] /Contents (sticky note only) >> 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots 10 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [260 718 72 700] /QuadPoints [72 718 160 718 72 700 160 700 170 718 250 718 170 700 250 700] /Contents (Needs plugin review) /T (Editor) /Subj (Highlight) /M (D:20260602033700Z) /NM <686967682D31> /C [1 0.92 0] /CA 0.45 /F 4 /Border [0 0 2 [3 1]] /BS 12 0 R /Popup 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [72 600 220 620] /QuadPoints [72 620 220 620 72 600 220 600] /Contents (unreferenced highlight) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /StrikeOut /Rect [72 676 148 696] /QuadPoints [72 696 148 696 72 676 148 676] /Contents <FEFF005200650064006100630074> /T (Reviewer Two) /C [1 0 0] /Popup << /Type /Annot /Subtype /Popup /Rect [180 672 300 720] /Open false /Parent 9 0 R /Contents (direct popup review) >> >>\nendobj\n"
        . "10 0 obj\n[ << /Type /Annot /Subtype /Underline /Rect [72 640 220 658] /QuadPoints [72 658 220 658 72 640 220 640] /Contents (import later) /T (QA) /Subj (Underline) /Border [0 0 0] >> << /Type /Annot /Subtype /Highlight /Rect [72 612 220 628] /Contents (missing quads) >> ]\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [260 700 420 760] /Open true /Parent 7 0 R /Contents (expanded reviewer popup) /M (D:20260602033800Z) >>\nendobj\n"
        . "12 0 obj\n<< /Type /BorderStyle /S /D /W 1.5 /D [4 2] >>\nendobj\n"
        . "%%EOF";
};

$suppliedPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 676.0, 250.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 250.0, 718.0],
                    'spans' => [
                        ['text' => 'Needs plugin', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' review', 'bbox' => [170.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' outside', 'bbox' => [260.0, 700.0, 310.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ], [
                    'bbox' => [72.0, 676.0, 148.0, 696.0],
                    'spans' => [
                        ['text' => 'Redact', 'bbox' => [72.0, 676.0, 148.0, 696.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 640.0, 220.0, 658.0],
                'lines' => [[
                    'bbox' => [72.0, 640.0, 220.0, 658.0],
                    'spans' => [
                        ['text' => 'Import later', 'bbox' => [72.0, 640.0, 220.0, 658.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

return [
    'extracts text-markup annotation QuadPoints and review metadata' => static function (TestRunner $t) use ($markupPdf): void {
        $pages = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($markupPdf());

        $t->same(2, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(3, $pages[0]['page_object']);
        $t->same(2, count($pages[0]['markups']));
        $t->same(1, count($pages[1]['markups']));

        $highlight = $pages[0]['markups'][0];
        $t->same('Highlight', $highlight['subtype']);
        $t->same([72.0, 700.0, 260.0, 718.0], $highlight['rect']);
        $t->same([[72.0, 718.0, 160.0, 718.0, 72.0, 700.0, 160.0, 700.0], [170.0, 718.0, 250.0, 718.0, 170.0, 700.0, 250.0, 700.0]], $highlight['quad_points']);
        $t->same([[72.0, 700.0, 160.0, 718.0], [170.0, 700.0, 250.0, 718.0]], $highlight['quad_rects']);
        $t->same('Needs plugin review', $highlight['contents']);
        $t->same('Editor', $highlight['author']);
        $t->same('Highlight', $highlight['subject']);
        $t->same('D:20260602033700Z', $highlight['modified_at']);
        $t->same('high-1', $highlight['name']);
        $t->same([1.0, 0.92, 0.0], $highlight['color']);
        $t->same(0.45, $highlight['opacity']);
        $t->same(4, $highlight['flags']);
        $t->same(7, $highlight['annotation_object']);

        $strikeOut = $pages[0]['markups'][1];
        $t->same('StrikeOut', $strikeOut['subtype']);
        $t->same('Redact', $strikeOut['contents']);
        $t->same('Reviewer Two', $strikeOut['author']);

        $underline = $pages[1]['markups'][0];
        $t->same('Underline', $underline['subtype']);
        $t->same('QA', $underline['author']);
        $t->same(null, $underline['annotation_object'], 'direct annotations from an indirect array keep a null object id.');
    },
    'extracts annotation border styles and popup review metadata' => static function (TestRunner $t) use ($markupPdf): void {
        $pages = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($markupPdf());
        $highlight = $pages[0]['markups'][0];

        $t->same([
            'horizontal_corner_radius' => 0.0,
            'vertical_corner_radius' => 0.0,
            'width' => 2.0,
            'dash_pattern' => [3.0, 1.0],
            'source' => 'Border',
        ], $highlight['border']);
        $t->same([
            'width' => 1.5,
            'style' => 'dashed',
            'style_name' => 'D',
            'dash_pattern' => [4.0, 2.0],
            'source' => 'BS',
        ], $highlight['border_style']);
        $t->same([
            'annotation_object' => 11,
            'rect' => [260.0, 700.0, 420.0, 760.0],
            'open' => true,
            'contents' => 'expanded reviewer popup',
            'modified_at' => 'D:20260602033800Z',
            'parent_object' => 7,
        ], $highlight['popup']);

        $strikeOut = $pages[0]['markups'][1];
        $t->same(false, $strikeOut['popup']['open']);
        $t->same('direct popup review', $strikeOut['popup']['contents']);
        $t->same(9, $strikeOut['popup']['parent_object']);

        $underline = $pages[1]['markups'][0];
        $t->same([
            'width' => 0.0,
            'style' => 'solid',
            'style_name' => 'S',
            'dash_pattern' => [],
            'source' => 'Border',
        ], $underline['border_style']);
        $t->same(null, $underline['popup']);
    },
    'applies text-markup annotations to overlapping supplied pdftext spans' => static function (TestRunner $t) use ($markupPdf, $suppliedPages): void {
        $pages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($suppliedPages(), $markupPdf());

        $t->same(2, count($pages[0]['markup_annotations']));
        $t->same('Highlight', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['subtype']);
        $t->same('Needs plugin review', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['contents']);
        $t->same('dashed', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['border_style']['style']);
        $t->same('expanded reviewer popup', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['popup']['contents']);
        $t->same([72.0, 700.0, 160.0, 718.0], $pages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['quad_rect']);
        $t->same(0, $pages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['quad_index']);

        $t->same('Highlight', $pages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations'][0]['subtype']);
        $t->same([170.0, 700.0, 250.0, 718.0], $pages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations'][0]['quad_rect']);
        $t->same(1, $pages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations'][0]['quad_index']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][2]['review_annotations']));

        $t->same('StrikeOut', $pages[0]['blocks'][0]['lines'][1]['spans'][0]['review_annotations'][0]['subtype']);
        $t->same('Redact', $pages[0]['blocks'][0]['lines'][1]['spans'][0]['review_annotations'][0]['contents']);

        $t->same(1, count($pages[1]['markup_annotations']), 'direct underline with QuadPoints is kept while missing-QuadPoints highlight is excluded.');
        $t->same('Underline', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['subtype']);
        $t->same('import later', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0]['contents']);
    },
    'keeps pages without text-markup annotations unchanged' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [<< /Type /Annot /Subtype /Text /Rect [72 700 160 718] /Contents (sticky note) >>] >>\nendobj\n%%EOF";
        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'lines' => [[
                    'spans' => [['text' => 'Plain review text', 'bbox' => [72.0, 700.0, 160.0, 718.0]]],
                ]],
            ]],
        ]];

        $marked = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($pages, $pdf);

        $t->same([], (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf));
        $t->same($pages, $marked);
    },
];
