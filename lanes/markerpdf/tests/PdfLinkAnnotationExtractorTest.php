<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

$linkPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Plugin docs) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second page support) Tj ET';
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R << /Type /Annot /Subtype /Text /Rect [72 700 160 718] /Contents (review note) >> 10 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots 11 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /A << /S /URI /URI (https://example.com/plugin\\)docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /A << /S /URI /URI (https://example.com/unreferenced) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /Dest [4 0 R /Fit] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [200 700 260 718] /A << /S /URI /URI (javascript:alert\\(1\\)) >> >>\nendobj\n"
        . "11 0 obj\n[ << /Type /Annot /Subtype /Link /Rect [72 680 220 698] /A 12 0 R >> 9 0 R ]\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI <6d61696c746f3a696d706f7274406578616d706c652e636f6d> >>\nendobj\n"
        . "%%EOF";
};

$suppliedPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 260.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 260.0, 718.0],
                    'spans' => [
                        ['text' => 'Plugin docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' outside', 'bbox' => [170.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 680.0, 250.0, 698.0],
                'lines' => [[
                    'bbox' => [72.0, 680.0, 250.0, 698.0],
                    'spans' => [
                        ['text' => 'Second page support', 'bbox' => [72.0, 680.0, 220.0, 698.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

return [
    'extracts page link URI annotations at the native PDF page boundary' => static function (TestRunner $t) use ($linkPdf): void {
        $links = (new PdfLinkAnnotationExtractor())->extractPageLinks($linkPdf());

        $t->same(2, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same('https://example.com/plugin)docs', $links[0]['links'][0]['uri']);
        $t->same([72.0, 700.0, 160.0, 718.0], $links[0]['links'][0]['rect']);
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same(1, $links[1]['pnum']);
        $t->same('mailto:import@example.com', $links[1]['links'][0]['uri']);
        $t->same(1, count($links[0]['links']), 'non-link, unsafe, and unreferenced annotations are excluded.');
        $t->same(1, count($links[1]['links']), 'GoTo destination annotations are excluded from URI links.');
    },
    'applies URI annotations only to overlapping supplied pdftext spans' => static function (TestRunner $t) use ($linkPdf, $suppliedPages): void {
        $pages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($suppliedPages(), $linkPdf());

        $t->same('https://example.com/plugin)docs', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same([72.0, 700.0, 160.0, 718.0], $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_rect']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']));
        $t->same('mailto:import@example.com', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same(1, count($pages[0]['links']));
        $t->same(1, count($pages[1]['links']));
    },
    'renders linked spans as Markdown links before WordPress block conversion' => static function (TestRunner $t) use ($linkPdf, $suppliedPages): void {
        $linked = (new PdfLinkAnnotationExtractor())->applyLinksToPages($suppliedPages(), $linkPdf());
        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($linked));

        $t->same('[Plugin docs](https://example.com/plugin\\)docs) outside [Second page support](mailto:import@example.com)', $blocks[0]['text']);

        $html = "<!-- wp:paragraph -->\n<p>" . preg_replace('/\[([^]]+)\]\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', '<a href="$2">$1</a>', $blocks[0]['text']) . "</p>\n<!-- /wp:paragraph -->\n";
        $t->contains('<a href="https://example.com/plugin\\)docs">Plugin docs</a>', $html);
    },
    'keeps pages without link annotations unchanged' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n%%EOF";
        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'lines' => [[
                    'spans' => [['text' => 'Plain import text', 'bbox' => [10.0, 20.0, 100.0, 32.0]]],
                ]],
            ]],
        ]];

        $linked = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $pdf);

        $t->same($pages, $linked);
    },
];
