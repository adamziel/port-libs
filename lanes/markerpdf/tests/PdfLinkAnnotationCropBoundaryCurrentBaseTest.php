<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkCropBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 60 210 Td (Visible docs Margin decoy Edge target Outside decoy Quad visible Quad outside) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 50 250 250] /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [40 200 134 218] /Contents (Partially cropped URI link review) /A << /S /URI /URI (https://example.com/visible-crop-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [150 200 230 218] /Dest [3 0 R /FitH 220] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 200 310 218] /Contents (Outside crop link review) /A << /S /URI /URI (https://example.com/outside-crop-link) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [60 230 300 248] /QuadPoints [60 248 130 248 60 238 130 238 260 240 300 240 260 230 300 230] /A << /S /URI /URI (https://example.com/visible-quad-link) >> >>\nendobj\n"
        . "%%EOF";
};

$linkCropBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [40.0, 200.0, 310.0, 248.0],
            'lines' => [[
                'bbox' => [40.0, 200.0, 310.0, 248.0],
                'spans' => [
                    ['text' => 'Visible docs', 'bbox' => [50.0, 200.0, 134.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Margin decoy', 'bbox' => [40.0, 200.0, 48.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Edge target', 'bbox' => [150.0, 200.0, 230.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Outside decoy', 'bbox' => [260.0, 200.0, 310.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Quad visible', 'bbox' => [60.0, 238.0, 130.0, 248.0], 'font' => 'Helvetica'],
                    ['text' => ' Quad outside', 'bbox' => [260.0, 230.0, 300.0, 240.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'clips Link annotation rectangles and QuadPoints to the visible page box before WordPress span promotion' => static function (TestRunner $t) use (
        $linkCropBoundaryPdf,
        $linkCropBoundaryPages
    ): void {
        $pdf = $linkCropBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same([7, 8, 10], array_column($links[0]['links'], 'annotation_object'), 'The fully out-of-crop link annotation is excluded.');

        $partial = $links[0]['links'][0];
        $t->same([40.0, 200.0, 134.0, 218.0], $partial['rect']);
        $t->same([50.0, 200.0, 134.0, 218.0], $partial['visible_rect']);
        $t->same(true, $partial['rect_clipped_to_page']);
        $t->same(false, $partial['rect_inside_page_bbox']);
        $t->same('https://example.com/visible-crop-link', $partial['uri']);
        $t->same('Partially cropped URI link review', $partial['contents']);

        $destination = $links[0]['links'][1];
        $t->same(8, $destination['annotation_object']);
        $t->same([150.0, 200.0, 230.0, 218.0], $destination['visible_rect']);
        $t->same(false, $destination['rect_clipped_to_page']);
        $t->same('local-destination', $destination['safety']);
        $t->same(0, $destination['destination_page']);
        $t->same('FitH', $destination['view_mode']);

        $quad = $links[0]['links'][2];
        $t->same(10, $quad['annotation_object']);
        $t->same([
            [60.0, 238.0, 130.0, 248.0],
            [260.0, 230.0, 300.0, 240.0],
        ], $quad['quad_rects']);
        $t->same([[60.0, 238.0, 130.0, 248.0]], $quad['visible_quad_rects']);
        $t->same(1, $quad['quad_rects_excluded_by_page_bbox']);
        $t->same('https://example.com/visible-quad-link', $quad['uri']);

        $pages = $extractor->applyLinksToPages($linkCropBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/visible-crop-link', $spans[0]['link_uri']);
        $t->same([50.0, 200.0, 134.0, 218.0], $spans[0]['link_visible_page_rect']);
        $t->same(true, $spans[0]['link_page_rect_clipped_to_page']);
        $t->true(!isset($spans[1]['link_uri']), 'A margin-only span inside the raw /Rect but outside CropBox is not linked.');
        $t->same(0, $spans[2]['link_destination_page']);
        $t->same('FitH', $spans[2]['link_view_mode']);
        $t->true(!isset($spans[3]['link_uri']), 'A fully out-of-crop link annotation is not promoted to a span.');
        $t->same('https://example.com/visible-quad-link', $spans[4]['link_uri']);
        $t->same(0, $spans[4]['link_quad_index']);
        $t->same([60.0, 238.0, 130.0, 248.0], $spans[4]['link_visible_page_quad_rect']);
        $t->true(!isset($spans[5]['link_uri']), 'A QuadPoints rectangle outside CropBox does not promote an outside span.');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Visible docs](https://example.com/visible-crop-link) Margin decoy Edge target Outside decoy [Quad visible](https://example.com/visible-quad-link) Quad outside', $blocks[0]['text']);

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, 'outside-crop-link'));
        $t->same(false, str_contains($encodedReview, 'Outside crop link review'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Visible docs Margin decoy Edge target Outside decoy Quad visible Quad outside', $plainText);
        foreach ([
            'visible-crop-link',
            'visible-quad-link',
            'outside-crop-link',
            'Partially cropped URI link review',
            'Outside crop link review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
