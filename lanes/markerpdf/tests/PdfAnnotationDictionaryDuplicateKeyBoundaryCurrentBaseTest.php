<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationDictionaryDuplicateKeyBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current docs Current highlight Stale duplicate) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [270 700 390 718] /F 2 /Contents (Stale duplicate Link review) /C [1 0 0] /A << /S /URI /URI (https://stale.example.com/first-link) >> /Subtype /Link /Rect [72 700 150 718] /F 4 /Contents (Current duplicate Link review) /C [0 0.25 1] /A << /S /URI /URI (https://example.com/current-duplicate-annotation-key-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [270 700 390 718] /QuadPoints [270 718 390 718 270 700 390 700] /Contents (Stale duplicate markup review) /T (Stale QA) /C [1 0 0] /CA 0.1 /F 2 /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Current duplicate Highlight review) /T (Current QA) /Subj (Accepted highlight) /C [0.1 0.8 0.3] /CA 0.55 /F 4 /Border [0 0 1 [2 1]] >>\nendobj\n"
        . "%%EOF";
};

$annotationDictionaryDuplicateKeyBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 390.0, 718.0],
                'spans' => [
                    ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Current highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale duplicate', 'bbox' => [270.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses last top-level annotation dictionary keys before WordPress link and markup promotion' => static function (
        TestRunner $t
    ) use ($annotationDictionaryDuplicateKeyBoundaryPdf, $annotationDictionaryDuplicateKeyBoundaryPages): void {
        $pdf = $annotationDictionaryDuplicateKeyBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same([72.0, 700.0, 150.0, 718.0], $annotations[0]['annotations'][0]['rect']);
        $t->same([160.0, 700.0, 260.0, 718.0], $annotations[0]['annotations'][1]['rect']);
        $t->same('Current duplicate Link review', $annotations[0]['annotations'][0]['contents']);
        $t->same('Current duplicate Highlight review', $annotations[0]['annotations'][1]['contents']);
        $t->same('visible', $annotations[0]['annotations'][0]['annotation_visibility']);
        $t->same(['space' => 'DeviceRGB', 'components' => [0.0, 0.25, 1.0], 'hex' => '#0040ff'], $annotations[0]['annotations'][0]['border_color']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Link', $links[0]['links'][0]['annotation_subtype']);
        $t->same([72.0, 700.0, 150.0, 718.0], $links[0]['links'][0]['rect']);
        $t->same('https://example.com/current-duplicate-annotation-key-link', $links[0]['links'][0]['uri']);
        $t->same('Current duplicate Link review', $links[0]['links'][0]['contents']);
        $t->same('visible', $links[0]['links'][0]['annotation_visibility']);

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same([8], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Highlight', $markups[0]['markups'][0]['subtype']);
        $t->same([160.0, 700.0, 260.0, 718.0], $markups[0]['markups'][0]['rect']);
        $t->same([[160.0, 700.0, 260.0, 718.0]], $markups[0]['markups'][0]['quad_rects']);
        $t->same('Current duplicate Highlight review', $markups[0]['markups'][0]['contents']);
        $t->same('Current QA', $markups[0]['markups'][0]['author']);
        $t->same('Accepted highlight', $markups[0]['markups'][0]['subject']);
        $t->same(['space' => 'DeviceRGB', 'components' => [0.1, 0.8, 0.3], 'hex' => '#1acc4d'], $annotations[0]['annotations'][1]['border_color']);
        $t->same([0.1, 0.8, 0.3], $markups[0]['markups'][0]['color']);
        $t->same(0.55, $markups[0]['markups'][0]['opacity']);

        $linkedPages = $linkExtractor->applyLinksToPages($annotationDictionaryDuplicateKeyBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-duplicate-annotation-key-link', $spans[0]['link_uri']);
        $t->same('Current duplicate Highlight review', $spans[1]['review_annotations'][0]['contents']);
        $t->same(false, isset($spans[2]['link_uri']));
        $t->same(false, isset($spans[2]['review_annotations']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Current docs](https://example.com/current-duplicate-annotation-key-link) Current highlight Stale duplicate', $blocks[0]['text']);

        $encodedReview = $encoded([$annotations, $links, $markups, $reviewPages]);
        foreach ([
            'stale.example.com',
            'Stale duplicate Link review',
            'Stale duplicate markup review',
            'Stale QA',
        ] as $staleReviewText) {
            $t->same(false, str_contains($encodedReview, $staleReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current docs Current highlight Stale duplicate', $plainText);
        foreach ([
            'current-duplicate-annotation-key-link',
            'Current duplicate Link review',
            'Current duplicate Highlight review',
            'Stale duplicate Link review',
            'Stale duplicate markup review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
