<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkIndirectSubtypeBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect subtype link Nonlink decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype 20 0 R /Rect [72 700 204 718] /Contents (Indirect annotation subtype review) /H 22 0 R /BS << /W 2 /S 23 0 R /D [3 1] >> /A << /S /URI /URI (https://example.com/indirect-annotation-subtype) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype 21 0 R /Rect [214 700 330 718] /Contents (Indirect text subtype decoy) /H 22 0 R /A << /S /URI /URI (https://example.com/indirect-text-decoy) >> >>\nendobj\n"
        . "20 0 obj\n/Link\nendobj\n"
        . "21 0 obj\n/Text\nendobj\n"
        . "22 0 obj\n/P\nendobj\n"
        . "23 0 obj\n/D\nendobj\n"
        . "%%EOF";
};

$linkIndirectSubtypeBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 330.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 330.0, 718.0],
                'spans' => [
                    ['text' => 'Indirect subtype link', 'bbox' => [72.0, 700.0, 204.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Nonlink decoy', 'bbox' => [214.0, 700.0, 330.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves indirect Link annotation subtype names before WordPress span promotion' => static function (TestRunner $t) use (
        $linkIndirectSubtypeBoundaryPdf,
        $linkIndirectSubtypeBoundaryPages
    ): void {
        $pdf = $linkIndirectSubtypeBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Text'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same('Indirect annotation subtype review', $annotations[0]['annotations'][0]['contents']);
        $t->same('Indirect text subtype decoy', $annotations[0]['annotations'][1]['contents']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the indirect /Link subtype is promoted.');
        $t->same('Link', $links[0]['links'][0]['annotation_subtype']);
        $t->same('https://example.com/indirect-annotation-subtype', $links[0]['links'][0]['uri']);
        $t->same('P', $links[0]['links'][0]['highlight_mode']);
        $t->same('push', $links[0]['links'][0]['highlight_mode_label']);
        $t->same([
            'source' => 'BS',
            'width' => 2.0,
            'style' => 'dashed',
            'style_code' => 'D',
            'dash_pattern' => [3.0, 1.0],
            'horizontal_corner_radius' => null,
            'vertical_corner_radius' => null,
        ], $links[0]['links'][0]['border']);
        $t->same(false, str_contains($encoded($links), 'indirect-text-decoy'));
        $t->same(false, str_contains($encoded($links), 'Indirect text subtype decoy'));

        $pages = $extractor->applyLinksToPages($linkIndirectSubtypeBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/indirect-annotation-subtype', $spans[0]['link_uri']);
        $t->same('Link', $spans[0]['link_annotation_subtype']);
        $t->same('P', $spans[0]['link_annotation_highlight_mode']);
        $t->same('push', $spans[0]['link_annotation_highlight_mode_label']);
        $t->same('dashed', $spans[0]['link_annotation_border']['style']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_annotation_object']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Indirect subtype link](https://example.com/indirect-annotation-subtype) Nonlink decoy', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Indirect subtype link Nonlink decoy', $plainText);
        foreach ([
            'indirect-annotation-subtype',
            'indirect-text-decoy',
            'Indirect annotation subtype review',
            'Indirect text subtype decoy',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
