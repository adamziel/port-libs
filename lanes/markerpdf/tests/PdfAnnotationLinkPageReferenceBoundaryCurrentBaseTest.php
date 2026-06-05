<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkPageReferenceBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Page one link Page one highlight Cross page decoy) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Page two link Page two highlight) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 11 0 R /Annots [8 0 R 10 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /P 3 0 R /Rect [72 700 162 718] /Contents (Page one link review) /A << /S /URI /URI (https://example.com/page-one-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /P 4 0 R /Rect [72 700 162 718] /Contents (Page two link review) /A << /S /URI /URI (https://example.com/page-two-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 0 R /Rect [174 700 292 718] /QuadPoints [174 718 292 718 174 700 292 700] /Contents (Page one highlight review) /T (Import QA) /C [1 0.9 0] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /P 4 0 R /Rect [174 700 292 718] /QuadPoints [174 718 292 718 174 700 292 700] /Contents (Page two highlight review) /T (Import QA) /C [0.4 0.8 1] >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$annotationLinkPageReferenceBoundaryPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 400.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 400.0, 718.0],
                    'spans' => [
                        ['text' => 'Page one link', 'bbox' => [72.0, 700.0, 162.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Page one highlight', 'bbox' => [174.0, 700.0, 292.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Cross page decoy', 'bbox' => [304.0, 700.0, 400.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 292.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 292.0, 718.0],
                    'spans' => [
                        ['text' => 'Page two link', 'bbox' => [72.0, 700.0, 162.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Page two highlight', 'bbox' => [174.0, 700.0, 292.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

return [
    'keeps link and markup annotations on the page named by their annotation P reference' => static function (TestRunner $t) use (
        $annotationLinkPageReferenceBoundaryPdf,
        $annotationLinkPageReferenceBoundaryPages
    ): void {
        $pdf = $annotationLinkPageReferenceBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(2, count($annotationPages));
        $t->same([7, 9], array_column($annotationPages[0]['annotations'], 'annotation_object'));
        $t->same([8, 10], array_column($annotationPages[1]['annotations'], 'annotation_object'));
        $t->same(['Page one link review', 'Page one highlight review'], array_column($annotationPages[0]['annotations'], 'contents'));
        $t->same(['Page two link review', 'Page two highlight review'], array_column($annotationPages[1]['annotations'], 'contents'));
        $t->same(false, str_contains($encoded([$annotationPages[0]]), 'page-two-link'));
        $t->same(false, str_contains($encoded([$annotationPages[0]]), 'Page two highlight review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $linkPages = $linkExtractor->extractPageLinks($pdf);
        $t->same(2, count($linkPages));
        $t->same(0, $linkPages[0]['pnum']);
        $t->same(1, $linkPages[1]['pnum']);
        $t->same([7], array_column($linkPages[0]['links'], 'annotation_object'));
        $t->same([8], array_column($linkPages[1]['links'], 'annotation_object'));
        $t->same('https://example.com/page-one-link', $linkPages[0]['links'][0]['uri']);
        $t->same('https://example.com/page-two-link', $linkPages[1]['links'][0]['uri']);
        $t->same(false, str_contains($encoded([$linkPages[0]]), 'page-two-link'));

        $markupPages = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(2, count($markupPages));
        $t->same([9], array_column($markupPages[0]['markups'], 'annotation_object'));
        $t->same([10], array_column($markupPages[1]['markups'], 'annotation_object'));
        $t->same('Page one highlight review', $markupPages[0]['markups'][0]['contents']);
        $t->same('Page two highlight review', $markupPages[1]['markups'][0]['contents']);
        $t->same(false, str_contains($encoded([$markupPages[0]]), 'Page two highlight review'));

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkPageReferenceBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $pageOneSpans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $pageTwoSpans = $reviewPages[1]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/page-one-link', $pageOneSpans[0]['link_uri']);
        $t->same('Page one highlight review', $pageOneSpans[1]['review_annotations'][0]['contents']);
        $t->true(!isset($pageOneSpans[2]['link_uri']));
        $t->true(!isset($pageOneSpans[2]['review_annotations']));
        $t->same(false, str_contains($encoded([$reviewPages[0]]), 'page-two-link'));
        $t->same(false, str_contains($encoded([$reviewPages[0]]), 'Page two highlight review'));
        $t->same('https://example.com/page-two-link', $pageTwoSpans[0]['link_uri']);
        $t->same('Page two highlight review', $pageTwoSpans[1]['review_annotations'][0]['contents']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same(
            "[Page one link](https://example.com/page-one-link) Page one highlight Cross page decoy\n"
                . '[Page two link](https://example.com/page-two-link) Page two highlight',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Page one link Page one highlight Cross page decoy', $plainText);
        $t->contains('Page two link Page two highlight', $plainText);
        foreach ([
            'page-one-link',
            'page-two-link',
            'Page one link review',
            'Page two link review',
            'Page one highlight review',
            'Page two highlight review',
        ] as $hiddenText) {
            $t->same(false, str_contains($plainText, $hiddenText));
        }
    },
];
