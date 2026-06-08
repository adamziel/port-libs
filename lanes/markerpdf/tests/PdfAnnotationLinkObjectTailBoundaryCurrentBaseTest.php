<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkObjectTailBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean link Tailed link Tailed highlight Clean highlight) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean link review) /A << /S /URI /URI (https://example.com/clean-object-tail-boundary) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 238 718] /Contents (Tailed link review) /A << /S /URI /URI (https://example.com/tailed-link-promote) >> >> 11 0 R\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [248 700 362 718] /QuadPoints [248 718 362 718 248 700 362 700] /Contents (Tailed highlight review) /T (Tail QA) /C [1 0.8 0] >> 12 0 R\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [372 700 500 718] /QuadPoints [372 718 500 718 372 700 500 700] /Contents (Clean highlight review) /T (Import QA) /C [0.2 0.7 0.4] >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Text /Rect [160 676 238 694] /Contents (Tailed link extra note review) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Text /Rect [248 676 362 694] /Contents (Tailed highlight extra note review) >>\nendobj\n"
        . "%%EOF";
};

$annotationLinkObjectTailBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 500.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 500.0, 718.0],
                'spans' => [
                    ['text' => 'Clean link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed link', 'bbox' => [160.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed highlight', 'bbox' => [248.0, 700.0, 362.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Clean highlight', 'bbox' => [372.0, 700.0, 500.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed indirect annotation objects before link and markup promotion' => static function (
        TestRunner $t
    ) use ($annotationLinkObjectTailBoundaryPdf, $annotationLinkObjectTailBoundaryPages): void {
        $pdf = $annotationLinkObjectTailBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same(['Clean link review', 'Clean highlight review'], array_column($annotations[0]['annotations'], 'contents'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/clean-object-tail-boundary', $links[0]['links'][0]['uri']);

        $markupExtractor = new PdfMarkupAnnotationExtractor();
        $markups = $markupExtractor->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same([10], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Clean highlight review', $markups[0]['markups'][0]['contents']);

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkObjectTailBoundaryPages(), $pdf);
        $reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-object-tail-boundary', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[2]['review_annotations']));
        $t->same('Clean highlight review', $spans[3]['review_annotations'][0]['contents']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same(
            '[Clean link](https://example.com/clean-object-tail-boundary) Tailed link Tailed highlight Clean highlight',
            $blocks[0]['text']
        );

        $encodedReview = $encoded([$annotations, $links, $markups, $reviewPages]);
        foreach ([
            'tailed-link-promote',
            'Tailed link review',
            'Tailed highlight review',
            'Tailed link extra note review',
            'Tailed highlight extra note review',
        ] as $tailedReviewText) {
            $t->same(false, str_contains($encodedReview, $tailedReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean link Tailed link Tailed highlight Clean highlight', $plainText);
        foreach ([
            'clean-object-tail-boundary',
            'tailed-link-promote',
            'Clean link review',
            'Tailed link review',
            'Tailed highlight review',
            'Clean highlight review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
