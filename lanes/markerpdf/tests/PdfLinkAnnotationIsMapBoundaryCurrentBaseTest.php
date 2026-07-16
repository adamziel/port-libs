<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationIsMapBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Static docs Coordinate map Chained map) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Static docs URI review) /A << /S /URI /URI (https://example.com/static-docs) /IsMap false >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 260 718] /Contents (Coordinate map URI review) /A << /S /URI /URI (https://maps.example.com/lookup) /IsMap true >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Chained map review) /A << /S /JavaScript /JS (openMapReview\\(\\)) /Next 10 0 R >> >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://maps.example.com/chained) /IsMap true >>\nendobj\n"
        . "%%EOF";
};

$linkAnnotationIsMapBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Static docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Coordinate map', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Chained map', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps coordinate dependent IsMap URI Link annotations review-only before WordPress span promotion' => static function (TestRunner $t) use (
        $linkAnnotationIsMapBoundaryPdf,
        $linkAnnotationIsMapBoundaryPages
    ): void {
        $pdf = $linkAnnotationIsMapBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same(false, $annotations[0]['annotations'][0]['actions'][0]['uri_is_map']);
        $t->same(['coordinate-dependent-uri-review'], array_column($annotations[0]['annotations'][1]['actions'], 'safety'));
        $t->same(true, $annotations[0]['annotations'][1]['actions'][0]['uri_is_map']);
        $t->same(true, $annotations[0]['annotations'][1]['actions'][0]['requires_activation_coordinates']);
        $t->same('https://maps.example.com/lookup', $annotations[0]['annotations'][1]['actions'][0]['uri']);
        $t->same(['blocked-javascript', 'coordinate-dependent-uri-review'], array_column($annotations[0]['annotations'][2]['actions'], 'safety'));
        $t->same(true, $annotations[0]['annotations'][2]['actions'][1]['chained']);
        $t->same(true, $annotations[0]['annotations'][2]['actions'][1]['uri_is_map']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only static URI annotations are promoted as WordPress links.');
        $t->same('https://example.com/static-docs', $links[0]['links'][0]['uri']);
        $t->same(false, $links[0]['links'][0]['uri_is_map']);
        $t->same(false, str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'maps.example.com'));

        $pages = $extractor->applyLinksToPages($linkAnnotationIsMapBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/static-docs', $spans[0]['link_uri']);
        $t->same(false, $spans[0]['link_actions_review'][0]['uri_is_map']);
        foreach ([1, 2] as $spanIndex) {
            $t->true(!isset($spans[$spanIndex]['link_uri']));
            $t->true(!isset($spans[$spanIndex]['link_actions_review']));
        }

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Static docs](https://example.com/static-docs) Coordinate map Chained map', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'maps.example.com'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Static docs Coordinate map Chained map', $plainText);
        foreach ([
            'static-docs',
            'maps.example.com',
            'Static docs URI review',
            'Coordinate map URI review',
            'Chained map review',
            'openMapReview',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
