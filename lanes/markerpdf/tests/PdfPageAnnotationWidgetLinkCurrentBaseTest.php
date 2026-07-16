<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

$indirectWidgetLinkPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Widget docs Named target Hidden widget) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Widget destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /Names << /Dests 16 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 40 0 R /P 3 0 R /F 41 0 R /Parent 20 0 R /A 30 0 R /AA << /U 31 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 42 0 R /P 3 0 R /F 43 0 R /Parent 21 0 R /Dest 44 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 45 0 R /P 3 0 R /F 46 0 R /Parent 22 0 R /A << /S /URI /URI (https://example.com/hidden-widget) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 47 0 R /P 3 0 R /F 48 0 R /Parent 23 0 R /A << /S /URI /URI (https://example.com/no-view-widget) >> >>\nendobj\n"
        . "15 0 obj\n<< /Fields [20 0 R 21 0 R 22 0 R 23 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Names [(widget-target) 17 0 R] >>\nendobj\n"
        . "17 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
        . "20 0 obj\n<< /FT /Btn /T (widget.docs) /Kids [7 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /FT /Btn /T (widget.target) /Kids [8 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /FT /Btn /T (widget.hidden) /Kids [9 0 R] >>\nendobj\n"
        . "23 0 obj\n<< /FT /Btn /T (widget.no_view) /Kids [10 0 R] >>\nendobj\n"
        . "30 0 obj\n<< /S /URI /URI (https://example.com/indirect-widget) /Next << /S /GoTo /D (widget-target) >> >>\nendobj\n"
        . "31 0 obj\n<< /S /URI /URI (mailto:widget-review@example.com) >>\nendobj\n"
        . "40 0 obj\n[72 700 158 718]\nendobj\n"
        . "41 0 obj\n4\nendobj\n"
        . "42 0 obj\n[166 700 248 718]\nendobj\n"
        . "43 0 obj\n4\nendobj\n"
        . "44 0 obj\n[4 0 R /FitH 720]\nendobj\n"
        . "45 0 obj\n[256 700 340 718]\nendobj\n"
        . "46 0 obj\n2\nendobj\n"
        . "47 0 obj\n[72 650 140 668]\nendobj\n"
        . "48 0 obj\n32\nendobj\n"
        . "%%EOF";
};

$indirectWidgetLinkPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 340.0, 718.0],
                'spans' => [
                    ['text' => 'Widget docs', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Named target', 'bbox' => [166.0, 700.0, 248.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden widget', 'bbox' => [256.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves indirect widget link rectangles and flags at the current page annotation boundary' => static function (TestRunner $t) use ($indirectWidgetLinkPdf, $indirectWidgetLinkPages): void {
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($indirectWidgetLinkPdf());

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(2, count($links[0]['links']), 'Indirect hidden and no-view widget flags prevent link promotion.');

        $uriWidget = $links[0]['links'][0];
        $t->same('Widget', $uriWidget['annotation_subtype']);
        $t->true($uriWidget['widget_annotation']);
        $t->same(7, $uriWidget['annotation_object']);
        $t->same([72.0, 700.0, 158.0, 718.0], $uriWidget['rect']);
        $t->same('https://example.com/indirect-widget', $uriWidget['uri']);
        $t->same('review-uri', $uriWidget['safety']);
        $t->same(false, $uriWidget['executes_on_import']);
        $t->same(['review-uri', 'local-destination'], array_column($uriWidget['actions'], 'safety'));
        $t->same('widget-target', $uriWidget['actions'][1]['destination']);
        $t->same(1, $uriWidget['actions'][1]['destination_page']);
        $t->same(['U'], array_column($uriWidget['additional_actions'], 'event'));
        $t->same('mailto:widget-review@example.com', $uriWidget['additional_actions'][0]['uri']);

        $destinationWidget = $links[0]['links'][1];
        $t->same('Widget', $destinationWidget['annotation_subtype']);
        $t->same(8, $destinationWidget['annotation_object']);
        $t->same([166.0, 700.0, 248.0, 718.0], $destinationWidget['rect']);
        $t->same('GoTo', $destinationWidget['action_type']);
        $t->same('local-destination', $destinationWidget['safety']);
        $t->same(1, $destinationWidget['destination_page']);
        $t->same('FitH', $destinationWidget['view_mode']);
        $t->same(['top' => 720.0], $destinationWidget['view_parameters']);

        $pages = $extractor->applyLinksToPages($indirectWidgetLinkPages(), $indirectWidgetLinkPdf());
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/indirect-widget', $spans[0]['link_uri']);
        $t->same([72.0, 700.0, 158.0, 718.0], $spans[0]['link_rect']);
        $t->same('Widget', $spans[0]['link_annotation_subtype']);
        $t->true($spans[0]['link_widget_annotation']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same('FitH', $spans[1]['link_view_mode']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_destination_page']));

        $encoded = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'hidden-widget'));
        $t->true(!str_contains($encoded, 'no-view-widget'));

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($pages));
        $t->same('[Widget docs](https://example.com/indirect-widget) Named target Hidden widget', $blocks[0]['text']);
    },
];
