<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkIndirectSubtypeTailBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Safe docs Tailed link Tailed highlight Clean highlight) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe docs subtype review) /A << /S /URI /URI (https://example.com/safe-subtype-tail-boundary) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype 20 0 R /Rect [160 700 250 718] /Contents (Tailed link subtype review) /A << /S /URI /URI (https://example.com/tailed-subtype-link-promote) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype 21 0 R /Rect [260 700 370 718] /QuadPoints [260 718 370 718 260 700 370 700] /Contents (Tailed highlight subtype review) /T (Subtype QA) /C [1 0.9 0] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [380 700 500 718] /QuadPoints [380 718 500 718 380 700 500 700] /Contents (Clean highlight subtype review) /T (Subtype QA) /C [0.1 0.8 0.3] >>\nendobj\n"
        . "20 0 obj\n/Link 30 0 R\nendobj\n"
        . "21 0 obj\n/Highlight 30 0 R\nendobj\n"
        . "30 0 obj\n<< /S /JavaScript /JS (tailedSubtypeReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

$linkIndirectSubtypeTailBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 500.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 500.0, 718.0],
                'spans' => [
                    ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed link', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed highlight', 'bbox' => [260.0, 700.0, 370.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Clean highlight', 'bbox' => [380.0, 700.0, 500.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed indirect annotation Subtype names before WordPress link and markup promotion' => static function (
        TestRunner $t
    ) use ($linkIndirectSubtypeTailBoundaryPdf, $linkIndirectSubtypeTailBoundaryPages): void {
        $pdf = $linkIndirectSubtypeTailBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Unknown', 'Unknown', 'Highlight'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same('Tailed link subtype review', $annotations[0]['annotations'][1]['contents']);
        $t->same('Tailed highlight subtype review', $annotations[0]['annotations'][2]['contents']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/safe-subtype-tail-boundary', $links[0]['links'][0]['uri']);
        $t->same(false, str_contains($encoded($links), 'tailed-subtype-link-promote'));
        $t->same(false, str_contains($encoded($links), 'tailedSubtypeReview'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same([10], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Clean highlight subtype review', $markups[0]['markups'][0]['contents']);
        $t->same(false, str_contains($encoded($markups), 'Tailed highlight subtype review'));

        $linkedPages = $linkExtractor->applyLinksToPages($linkIndirectSubtypeTailBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/safe-subtype-tail-boundary', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_annotation_object']));
        $t->true(!isset($spans[2]['review_annotations']));
        $t->same('Clean highlight subtype review', $spans[3]['review_annotations'][0]['contents']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Safe docs](https://example.com/safe-subtype-tail-boundary) Tailed link Tailed highlight Clean highlight', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Safe docs Tailed link Tailed highlight Clean highlight', $plainText);
        foreach ([
            'safe-subtype-tail-boundary',
            'tailed-subtype-link-promote',
            'tailedSubtypeReview',
            'Safe docs subtype review',
            'Tailed link subtype review',
            'Tailed highlight subtype review',
            'Clean highlight subtype review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
