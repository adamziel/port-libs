<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$widgetParentGenerationBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Inherited docs Inherited jump Stale parent) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Exact widget destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 700 174 718] /P 3 0 R /F 4 /Parent 20 1 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [184 700 286 718] /P 3 0 R /F 4 /Parent 21 1 R >>\nendobj\n"
        . "15 0 obj\n<< /Fields [20 1 R 21 1 R] >>\nendobj\n"
        . "20 1 obj\n<< /FT /Btn /T (exact.parent.uri) /Kids [7 0 R] /A 30 1 R /AA << /U 31 1 R >> >>\nendobj\n"
        . "21 1 obj\n<< /FT /Btn /T (exact.parent.dest) /Kids [8 0 R] /Dest 44 1 R >>\nendobj\n"
        . "30 1 obj\n<< /S /URI /URI (https://example.com/current-parent-generation-link) >>\nendobj\n"
        . "31 1 obj\n<< /S /URI /URI (mailto:current-parent-generation@example.test) >>\nendobj\n"
        . "44 1 obj\n[4 0 R /FitH 720]\nendobj\n"
        . "20 0 obj\n<< /FT /Btn /T (stale.parent.uri) /Kids [7 0 R] /A 30 0 R /AA << /U 31 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /FT /Btn /T (stale.parent.dest) /Kids [8 0 R] /Dest 44 0 R >>\nendobj\n"
        . "30 0 obj\n<< /S /URI /URI (https://example.com/stale-parent-generation-link) >>\nendobj\n"
        . "31 0 obj\n<< /S /JavaScript /JS (staleFieldHover\\(\\)) >>\nendobj\n"
        . "44 0 obj\n[3 0 R /Fit]\nendobj\n"
        . "%%EOF";
};

$widgetParentGenerationBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 372.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 372.0, 718.0],
                'spans' => [
                    ['text' => 'Inherited docs', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Inherited jump', 'bbox' => [184.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale parent', 'bbox' => [296.0, 700.0, 372.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses exact widget Parent generations before inheriting link actions for WordPress spans' => static function (
        TestRunner $t
    ) use ($widgetParentGenerationBoundaryPdf, $widgetParentGenerationBoundaryPages): void {
        $pdf = $widgetParentGenerationBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));

        $uriWidget = $links[0]['links'][0];
        $t->same('Widget', $uriWidget['annotation_subtype']);
        $t->same('https://example.com/current-parent-generation-link', $uriWidget['uri']);
        $t->same(['A', 'AA'], $uriWidget['inherited_widget_link_keys']);
        $t->same(20, $uriWidget['widget_field_parent_object']);
        $t->same(1, $uriWidget['widget_field_parent_generation']);
        $t->same([20], $uriWidget['widget_field_chain']);
        $t->same([1], $uriWidget['widget_field_chain_generations']);
        $t->same(['A' => 20, 'AA' => 20], $uriWidget['widget_link_field_sources']);
        $t->same(['A' => 1, 'AA' => 1], $uriWidget['widget_link_field_source_generations']);
        $t->same(['U'], array_column($uriWidget['additional_actions'], 'event'));
        $t->same('mailto:current-parent-generation@example.test', $uriWidget['additional_actions'][0]['uri']);

        $destinationWidget = $links[0]['links'][1];
        $t->same('local-destination', $destinationWidget['safety']);
        $t->same(1, $destinationWidget['destination_page']);
        $t->same('FitH', $destinationWidget['view_mode']);
        $t->same(['top' => 720.0], $destinationWidget['view_parameters']);
        $t->same(['Dest'], $destinationWidget['inherited_widget_link_keys']);
        $t->same(21, $destinationWidget['widget_field_parent_object']);
        $t->same(1, $destinationWidget['widget_field_parent_generation']);
        $t->same(['Dest' => 21], $destinationWidget['widget_link_field_sources']);
        $t->same(['Dest' => 1], $destinationWidget['widget_link_field_source_generations']);

        foreach ([
            'stale-parent-generation-link',
            'staleFieldHover',
            'stale.parent',
        ] as $staleText) {
            $t->same(false, str_contains($encoded($links), $staleText));
        }

        $pages = $extractor->applyLinksToPages($widgetParentGenerationBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-parent-generation-link', $spans[0]['link_uri']);
        $t->same(1, $spans[0]['link_widget_field_parent_generation']);
        $t->same([1], $spans[0]['link_widget_field_chain_generations']);
        $t->same(['A' => 1, 'AA' => 1], $spans[0]['link_widget_field_source_generations']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same(1, $spans[1]['link_widget_field_parent_generation']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_destination_page']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Inherited docs](https://example.com/current-parent-generation-link) Inherited jump Stale parent', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Inherited docs Inherited jump Stale parent', $plainText);
        $t->contains('Exact widget destination body', $plainText);
        foreach ([
            'current-parent-generation-link',
            'stale-parent-generation-link',
            'staleFieldHover',
            'current-parent-generation@example.test',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
