<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageWidgetFieldActionLinkPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Inherited widget Local widget Unsafe parent) Tj ET';
    $targetText = 'BT /F1 12 Tf 72 720 Td (Inherited widget target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /Names << /Dests 16 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 700 185 718] /P 3 0 R /F 4 /Parent 20 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [194 700 286 718] /P 3 0 R /F 4 /Parent 21 0 R /A << /S /URI /URI (https://example.com/local-widget-action) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [294 700 390 718] /P 3 0 R /F 4 /Parent 22 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
        . "15 0 obj\n<< /Fields [20 0 R 21 0 R 22 0 R 23 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Names [(inherited-target) 17 0 R] >>\nendobj\n"
        . "17 0 obj\n[4 0 R /FitH 720]\nendobj\n"
        . "20 0 obj\n<< /FT /Btn /T (inherited.widget) /Kids [7 0 R] /A 30 0 R /AA << /U 31 0 R /D << /S /GoTo /D (inherited-target) >> >> >>\nendobj\n"
        . "21 0 obj\n<< /FT /Btn /T (local.widget) /Kids [8 0 R] /A << /S /URI /URI (https://example.com/stale-parent-action) >> >>\nendobj\n"
        . "22 0 obj\n<< /FT /Btn /T (unsafe.parent) /Kids [9 0 R] /A << /S /URI /URI (javascript:parentFieldReview\\(\\)) >> >>\nendobj\n"
        . "23 0 obj\n<< /FT /Btn /T (detached.inherited.widget) /Kids [24 0 R] /A << /S /URI /URI (https://example.com/detached-field-widget) >> >>\nendobj\n"
        . "24 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 640 180 658] /Parent 23 0 R >>\nendobj\n"
        . "30 0 obj\n<< /S /URI /URI (https://example.com/inherited-widget-action) /Next << /S /GoTo /D (inherited-target) >> >>\nendobj\n"
        . "31 0 obj\n<< /S /URI /URI (mailto:field-review@example.com) >>\nendobj\n"
        . "%%EOF";
};

$pageWidgetFieldActionLinkPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 390.0, 718.0],
                'spans' => [
                    ['text' => 'Inherited widget', 'bbox' => [72.0, 700.0, 185.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Local widget', 'bbox' => [194.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Unsafe parent', 'bbox' => [294.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'inherits page widget link actions from terminal field dictionaries without promoting detached fields' => static function (TestRunner $t) use (
        $pageWidgetFieldActionLinkPdf,
        $pageWidgetFieldActionLinkPages
    ): void {
        $pdf = $pageWidgetFieldActionLinkPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(2, count($links[0]['links']), 'Unsafe inherited JavaScript and detached field widgets are not promoted.');

        $inherited = $links[0]['links'][0];
        $t->same(7, $inherited['annotation_object']);
        $t->same('Widget', $inherited['annotation_subtype']);
        $t->true($inherited['widget_annotation']);
        $t->same('https://example.com/inherited-widget-action', $inherited['uri']);
        $t->same('review-uri', $inherited['safety']);
        $t->same([72.0, 700.0, 185.0, 718.0], $inherited['rect']);
        $t->same(['A', 'AA'], $inherited['inherited_widget_link_keys']);
        $t->same(20, $inherited['widget_field_parent_object']);
        $t->same([20], $inherited['widget_field_chain']);
        $t->same('field_parent', $inherited['widget_link_action_source']);
        $t->same(['review-uri', 'local-destination'], array_column($inherited['actions'], 'safety'));
        $t->same('inherited-target', $inherited['actions'][1]['destination']);
        $t->same(1, $inherited['actions'][1]['destination_page']);
        $t->same(['U', 'D'], array_column($inherited['additional_actions'], 'event'));
        $t->same(['review-uri', 'local-destination'], array_column($inherited['additional_actions'], 'safety'));
        $t->same('mailto:field-review@example.com', $inherited['additional_actions'][0]['uri']);
        $t->same(1, $inherited['additional_actions'][1]['destination_page']);
        $t->same(false, $inherited['executes_on_import']);

        $local = $links[0]['links'][1];
        $t->same(8, $local['annotation_object']);
        $t->same('https://example.com/local-widget-action', $local['uri']);
        $t->same(false, isset($local['inherited_widget_link_keys']));
        $t->same(false, str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'stale-parent-action'));
        $t->same(false, str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'parentFieldReview'));
        $t->same(false, str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'detached-field-widget'));

        $pages = $extractor->applyLinksToPages($pageWidgetFieldActionLinkPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/inherited-widget-action', $spans[0]['link_uri']);
        $t->same([72.0, 700.0, 185.0, 718.0], $spans[0]['link_rect']);
        $t->same(['A', 'AA'], $spans[0]['link_inherited_widget_keys']);
        $t->same(20, $spans[0]['link_widget_field_parent_object']);
        $t->same([20], $spans[0]['link_widget_field_chain']);
        $t->same('field_parent', $spans[0]['link_widget_action_source']);
        $t->same('https://example.com/local-widget-action', $spans[1]['link_uri']);
        $t->true(!isset($spans[1]['link_inherited_widget_keys']));
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_destination_page']));

        $encodedPages = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedPages, 'parentFieldReview'));
        $t->same(false, str_contains($encodedPages, 'detached-field-widget'));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Inherited widget](https://example.com/inherited-widget-action) [Local widget](https://example.com/local-widget-action) Unsafe parent', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Inherited widget Local widget Unsafe parent', $plainText);
        $t->contains('Inherited widget target page', $plainText);
        $t->same(false, str_contains($plainText, 'inherited-widget-action'));
        $t->same(false, str_contains($plainText, 'local-widget-action'));
        $t->same(false, str_contains($plainText, 'detached-field-widget'));
    },
];
