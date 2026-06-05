<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkPresentationBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Styled docs Borderless docs Hidden review) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Contents (Styled link border review) /T (Import QA) /H /O /C 60 0 R /BS 61 0 R /A << /S /URI /URI (https://example.com/styled-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [166 700 262 718] /Contents <426f726465726c65737320726576696577> /T (Accessibility QA) /H /N /C [] /Border 62 0 R /A << /S /URI /URI (https://example.com/borderless-docs) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /F 2 /Contents (Hidden presentation must not promote) /H /P /C [1 0 0] /BS << /W 4 /S /U >> /A << /S /URI /URI (https://example.com/hidden-presentation) >> >>\nendobj\n"
        . "60 0 obj\n[0.2 0.4 0.8]\nendobj\n"
        . "61 0 obj\n<< /W 2 /S /D /D [3 1] >>\nendobj\n"
        . "62 0 obj\n[4 5 0 [2 2]]\nendobj\n"
        . "%%EOF";
};

$linkPresentationBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Styled docs', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Borderless docs', 'bbox' => [166.0, 700.0, 262.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden review', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'preserves Link annotation presentation metadata as review-only WordPress span context' => static function (TestRunner $t) use (
        $linkPresentationBoundaryPdf,
        $linkPresentationBoundaryPages
    ): void {
        $pdf = $linkPresentationBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(2, count($links[0]['links']), 'Hidden link presentation metadata is not promoted from page Annots.');

        $styled = $links[0]['links'][0];
        $t->same(7, $styled['annotation_object']);
        $t->same('Styled link border review', $styled['contents']);
        $t->same('Import QA', $styled['title']);
        $t->same('O', $styled['highlight_mode']);
        $t->same('outline', $styled['highlight_mode_label']);
        $t->same('DeviceRGB', $styled['border_color']['space']);
        $t->same([0.2, 0.4, 0.8], $styled['border_color']['components']);
        $t->same('#3366cc', $styled['border_color']['hex']);
        $t->same('BS', $styled['border']['source']);
        $t->same(2.0, $styled['border']['width']);
        $t->same('dashed', $styled['border']['style']);
        $t->same('D', $styled['border']['style_code']);
        $t->same([3.0, 1.0], $styled['border']['dash_pattern']);

        $borderless = $links[0]['links'][1];
        $t->same(8, $borderless['annotation_object']);
        $t->same('Borderless review', $borderless['contents']);
        $t->same('Accessibility QA', $borderless['title']);
        $t->same('N', $borderless['highlight_mode']);
        $t->same('none', $borderless['highlight_mode_label']);
        $t->same('transparent', $borderless['border_color']['space']);
        $t->same(null, $borderless['border_color']['hex']);
        $t->same('Border', $borderless['border']['source']);
        $t->same(0.0, $borderless['border']['width']);
        $t->same('none', $borderless['border']['style']);
        $t->same([2.0, 2.0], $borderless['border']['dash_pattern']);
        $t->same(4.0, $borderless['border']['horizontal_corner_radius']);
        $t->same(5.0, $borderless['border']['vertical_corner_radius']);

        $pages = $extractor->applyLinksToPages($linkPresentationBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/styled-docs', $spans[0]['link_uri']);
        $t->same('Styled link border review', $spans[0]['link_annotation_contents']);
        $t->same('outline', $spans[0]['link_annotation_highlight_mode_label']);
        $t->same('#3366cc', $spans[0]['link_annotation_border_color']['hex']);
        $t->same('dashed', $spans[0]['link_annotation_border']['style']);
        $t->same('https://example.com/borderless-docs', $spans[1]['link_uri']);
        $t->same('Borderless review', $spans[1]['link_annotation_contents']);
        $t->same('none', $spans[1]['link_annotation_highlight_mode_label']);
        $t->same('transparent', $spans[1]['link_annotation_border_color']['space']);
        $t->same('none', $spans[1]['link_annotation_border']['style']);
        $t->same(false, isset($spans[2]['link_uri']));
        $t->same(false, isset($spans[2]['link_annotation_contents']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Styled docs](https://example.com/styled-docs) [Borderless docs](https://example.com/borderless-docs) Hidden review', $blocks[0]['text']);

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, 'hidden-presentation'));
        $t->same(false, str_contains($encodedReview, 'Hidden presentation must not promote'));

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same('Styled link border review', $annotations[0]['annotations'][0]['contents']);
        $t->same('#3366cc', $annotations[0]['annotations'][0]['border_color']['hex']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Styled docs Borderless docs Hidden review', $plainText);
        foreach ([
            'Styled link border review',
            'Borderless review',
            'Hidden presentation must not promote',
            'styled-docs',
            'borderless-docs',
            'hidden-presentation',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
