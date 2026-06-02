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

$destinationBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Review jump and docs link) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R << /Type /Annot /Subtype /Text /Rect [250 700 320 718] /Contents (sticky only) >>] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [9 0 R] /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 155 718] /Dest [4 0 R /FitH 720] /AA << /E << /S /URI /URI (https://example.com/hover) >> /D 12 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 238 718] /A << /S /URI /URI (https://example.com/docs) /Next << /S /GoTo /D (named-review) >> >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 180 718] /A << /S /GoTo /D (named-review) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 640 180 658] /Dest [3 0 R /Fit] /AA << /E << /S /JavaScript /JS (staleHover\\(\\)) >> >> >>\nendobj\n"
        . "11 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (linkDownReview\\(\\)) >>\nendobj\n"
        . "13 0 obj\n<< /Names [(named-review) 11 0 R] >>\nendobj\n"
        . "%%EOF";
};

$destinationBoundaryPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 238.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 238.0, 718.0],
                    'spans' => [
                        ['text' => 'Review jump', 'bbox' => [72.0, 700.0, 155.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' docs link', 'bbox' => [160.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 180.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 180.0, 718.0],
                    'spans' => [
                        ['text' => 'Destination page', 'bbox' => [72.0, 700.0, 180.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

$widgetLinkPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Download docs Section jump Hidden widget) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Widget destination target) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /Names << /Dests 16 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 700 160 718] /P 3 0 R /F 4 /Parent 20 0 R /A 30 0 R /AA << /U 31 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [170 700 260 718] /P 3 0 R /F 4 /Parent 21 0 R /Dest [4 0 R /FitH 720] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [270 700 340 718] /P 3 0 R /F 36 /Parent 22 0 R /A << /S /URI /URI (https://example.com/hidden-widget) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 650 160 668] /Parent 22 0 R /A << /S /URI /URI (https://example.com/detached-widget) >> >>\nendobj\n"
        . "15 0 obj\n<< /Fields [20 0 R 21 0 R 22 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Names [(target-section) 17 0 R] >>\nendobj\n"
        . "17 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
        . "20 0 obj\n<< /FT /Btn /T (download.docs) /Ff 65536 /Kids [7 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /FT /Btn /T (section.jump) /Ff 65536 /Kids [8 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /FT /Btn /T (hidden.widget) /Ff 65536 /Kids [9 0 R 10 0 R] >>\nendobj\n"
        . "30 0 obj\n<< /S /URI /URI (https://example.com/widget-docs) /Next << /S /GoTo /D (target-section) >> >>\nendobj\n"
        . "31 0 obj\n<< /S /URI /URI (mailto:review@example.com) >>\nendobj\n"
        . "%%EOF";
};

$widgetLinkPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 340.0, 718.0],
                'spans' => [
                    ['text' => 'Download docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Section jump', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden widget', 'bbox' => [270.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
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
        $t->same(2, count($links[1]['links']), 'GoTo destination annotations are retained as review-only local link metadata.');
        $t->same('local-destination', $links[1]['links'][1]['safety']);
        $t->same(1, $links[1]['links'][1]['destination_page']);
    },
    'applies URI annotations only to overlapping supplied pdftext spans' => static function (TestRunner $t) use ($linkPdf, $suppliedPages): void {
        $pages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($suppliedPages(), $linkPdf());

        $t->same('https://example.com/plugin)docs', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same([72.0, 700.0, 160.0, 718.0], $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_rect']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']));
        $t->same('mailto:import@example.com', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same(1, count($pages[0]['links']));
        $t->same(2, count($pages[1]['links']));
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
    'reviews link destinations and annotation additional actions without executing them' => static function (TestRunner $t) use ($destinationBoundaryPdf, $destinationBoundaryPages): void {
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($destinationBoundaryPdf());

        $t->same(2, count($links));
        $t->same(2, count($links[0]['links']));
        $t->same(1, count($links[1]['links']));

        $local = $links[0]['links'][0];
        $t->same('GoTo', $local['action_type']);
        $t->same('local-destination', $local['safety']);
        $t->same(1, $local['destination_page']);
        $t->same('FitH', $local['view_mode']);
        $t->same(['top' => 720.0], $local['view_parameters']);
        $t->same(null, $local['uri']);
        $t->same(false, $local['executes_on_import']);
        $t->same(['E', 'D'], array_column($local['additional_actions'], 'event'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($local['additional_actions'], 'safety'));

        $uri = $links[0]['links'][1];
        $t->same('https://example.com/docs', $uri['uri']);
        $t->same(['review-uri', 'local-destination'], array_column($uri['actions'], 'safety'));
        $t->same(true, $uri['actions'][1]['chained']);
        $t->same('named-review', $uri['actions'][1]['destination']);
        $t->same('XYZ', $uri['actions'][1]['view_mode']);
        $t->same(['left' => 36.0, 'top' => 700.0, 'zoom' => null], $uri['actions'][1]['view_parameters']);

        $named = $links[1]['links'][0];
        $t->same('named-review', $named['destination']);
        $t->same(1, $named['destination_page']);
        $t->same('XYZ', $named['view_mode']);

        $pages = $extractor->applyLinksToPages($destinationBoundaryPages(), $destinationBoundaryPdf());
        $t->same(1, $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_destination_page']);
        $t->same('FitH', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_view_mode']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']));
        $t->same('https://example.com/docs', $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']);
        $t->same('named-review', $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_actions_review'][1]['destination']);
        $t->same('named-review', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['link_destination']);
        $t->same(3, array_sum(array_map(static fn (array $page): int => count($page['links'] ?? []), $pages)), 'unreferenced stale destination annotation is excluded.');
    },
    'promotes current page widget URI and destination annotations as non executing links' => static function (TestRunner $t) use ($widgetLinkPdf, $widgetLinkPages): void {
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($widgetLinkPdf());

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(2, count($links[0]['links']), 'hidden current widgets and detached field-only widgets are not promoted.');

        $uriWidget = $links[0]['links'][0];
        $t->same('Widget', $uriWidget['annotation_subtype']);
        $t->true($uriWidget['widget_annotation']);
        $t->same(7, $uriWidget['annotation_object']);
        $t->same('https://example.com/widget-docs', $uriWidget['uri']);
        $t->same('review-uri', $uriWidget['safety']);
        $t->same(false, $uriWidget['executes_on_import']);
        $t->same(['review-uri', 'local-destination'], array_column($uriWidget['actions'], 'safety'));
        $t->same('target-section', $uriWidget['actions'][1]['destination']);
        $t->same(1, $uriWidget['actions'][1]['destination_page']);
        $t->same(['U'], array_column($uriWidget['additional_actions'], 'event'));
        $t->same(['review-uri'], array_column($uriWidget['additional_actions'], 'safety'));
        $t->same('mailto:review@example.com', $uriWidget['additional_actions'][0]['uri']);

        $destinationWidget = $links[0]['links'][1];
        $t->same('Widget', $destinationWidget['annotation_subtype']);
        $t->true($destinationWidget['widget_annotation']);
        $t->same('GoTo', $destinationWidget['action_type']);
        $t->same('local-destination', $destinationWidget['safety']);
        $t->same(1, $destinationWidget['destination_page']);
        $t->same('FitH', $destinationWidget['view_mode']);
        $t->same(['top' => 720.0], $destinationWidget['view_parameters']);

        $pages = $extractor->applyLinksToPages($widgetLinkPages(), $widgetLinkPdf());
        $t->same('https://example.com/widget-docs', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same('Widget', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_annotation_subtype']);
        $t->true($pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_widget_annotation']);
        $t->same('FitH', $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_view_mode']);
        $t->same(1, $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_destination_page']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']));
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][2]['link_uri']));
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][2]['link_destination_page']));

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($pages));
        $t->same('[Download docs](https://example.com/widget-docs) Section jump Hidden widget', $blocks[0]['text']);
    },
];
