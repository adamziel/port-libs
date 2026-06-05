<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkIndirectNumericBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect docs Middle gap Indirect quad Wrong generation) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 0 R 51 0 R 52 0 R 53 0 R] /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [20 0 R 21 0 R 22 0 R 23 0 R] /Contents (Indirect numeric URI review) /C [90 0 R 91 0 R 92 0 R] /A << /S /URI /URI (https://example.com/indirect-numeric-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [30 0 R 31 0 R 32 0 R 33 0 R] /QuadPoints [40 0 R 41 0 R 42 0 R 43 0 R 44 0 R 45 0 R 46 0 R 47 0 R] /A << /S /URI /URI (https://example.com/indirect-quad-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [60 1 R 61 1 R 62 1 R 63 1 R] /Contents (Wrong generation numeric decoy) /A << /S /URI /URI (https://example.com/wrong-generation-link) >> >>\nendobj\n"
        . "20 0 obj\n72\nendobj\n"
        . "21 0 obj\n200\nendobj\n"
        . "22 0 obj\n150\nendobj\n"
        . "23 0 obj\n218\nendobj\n"
        . "30 0 obj\n160\nendobj\n"
        . "31 0 obj\n200\nendobj\n"
        . "32 0 obj\n292\nendobj\n"
        . "33 0 obj\n248\nendobj\n"
        . "40 0 obj\n210\nendobj\n"
        . "41 0 obj\n248\nendobj\n"
        . "42 0 obj\n292\nendobj\n"
        . "43 0 obj\n248\nendobj\n"
        . "44 0 obj\n210\nendobj\n"
        . "45 0 obj\n230\nendobj\n"
        . "46 0 obj\n292\nendobj\n"
        . "47 0 obj\n230\nendobj\n"
        . "50 0 obj\n50\nendobj\n"
        . "51 0 obj\n50\nendobj\n"
        . "52 0 obj\n300\nendobj\n"
        . "53 0 obj\n260\nendobj\n"
        . "60 0 obj\n72\nendobj\n"
        . "61 0 obj\n200\nendobj\n"
        . "62 0 obj\n150\nendobj\n"
        . "63 0 obj\n218\nendobj\n"
        . "90 0 obj\n0\nendobj\n"
        . "91 0 obj\n0.25\nendobj\n"
        . "92 0 obj\n0.75\nendobj\n"
        . "%%EOF";
};

$linkIndirectNumericBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 200.0, 292.0, 248.0],
            'lines' => [[
                'bbox' => [72.0, 200.0, 292.0, 248.0],
                'spans' => [
                    ['text' => 'Indirect docs', 'bbox' => [72.0, 200.0, 150.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Middle gap', 'bbox' => [160.0, 200.0, 204.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect quad', 'bbox' => [210.0, 230.0, 292.0, 248.0], 'font' => 'Helvetica'],
                    ['text' => ' Wrong generation', 'bbox' => [72.0, 160.0, 190.0, 178.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves indirect numeric Link annotation Rect QuadPoints and page boxes before WordPress span promotion' => static function (TestRunner $t) use (
        $linkIndirectNumericBoundaryPdf,
        $linkIndirectNumericBoundaryPages
    ): void {
        $pdf = $linkIndirectNumericBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([72.0, 200.0, 150.0, 218.0], $annotations[0]['annotations'][0]['rect']);
        $t->same([160.0, 200.0, 292.0, 248.0], $annotations[0]['annotations'][1]['rect']);
        $t->same(null, $annotations[0]['annotations'][2]['rect'], 'Wrong-generation numeric operands remain unresolved review metadata.');

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'), 'Wrong-generation numeric /Rect operands cannot define a clickable link boundary.');

        $rectLink = $links[0]['links'][0];
        $t->same([72.0, 200.0, 150.0, 218.0], $rectLink['rect']);
        $t->same([72.0, 200.0, 150.0, 218.0], $rectLink['visible_rect']);
        $t->same([50.0, 50.0, 300.0, 260.0], $rectLink['page_bbox']);
        $t->same('https://example.com/indirect-numeric-link', $rectLink['uri']);
        $t->same(['space' => 'DeviceRGB', 'components' => [0.0, 0.25, 0.75], 'hex' => '#0040bf'], $rectLink['border_color']);

        $quadLink = $links[0]['links'][1];
        $t->same([160.0, 200.0, 292.0, 248.0], $quadLink['rect']);
        $t->same([
            [210.0, 248.0, 292.0, 248.0, 210.0, 230.0, 292.0, 230.0],
        ], $quadLink['quad_points']);
        $t->same([[210.0, 230.0, 292.0, 248.0]], $quadLink['quad_rects']);
        $t->same('https://example.com/indirect-quad-link', $quadLink['uri']);

        $pages = $extractor->applyLinksToPages($linkIndirectNumericBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/indirect-numeric-link', $spans[0]['link_uri']);
        $t->same([72.0, 200.0, 150.0, 218.0], $spans[0]['link_page_rect']);
        $t->true(!isset($spans[1]['link_uri']), 'The gap span inside /Rect but outside /QuadPoints is not linked.');
        $t->same('https://example.com/indirect-quad-link', $spans[2]['link_uri']);
        $t->same(0, $spans[2]['link_quad_index']);
        $t->true(!isset($spans[3]['link_uri']), 'Wrong-generation numeric operands do not fall back to stale object-zero values.');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Indirect docs](https://example.com/indirect-numeric-link) Middle gap [Indirect quad](https://example.com/indirect-quad-link) Wrong generation', $blocks[0]['text']);

        $encodedPages = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedPages, 'wrong-generation-link'));
        $t->same(false, str_contains($encodedPages, 'Wrong generation numeric decoy'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Indirect docs Middle gap Indirect quad Wrong generation', $plainText);
        foreach ([
            'indirect-numeric-link',
            'indirect-quad-link',
            'wrong-generation-link',
            'Indirect numeric URI review',
            'Wrong generation numeric decoy',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
