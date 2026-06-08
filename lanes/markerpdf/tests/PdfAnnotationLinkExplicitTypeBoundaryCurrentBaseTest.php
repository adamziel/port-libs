<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkExplicitTypeBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Typed docs Untyped docs Filespec decoy Typed highlight XObject decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 11 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Typed link review) /A << /S /URI /URI (https://example.com/typed-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Link /Rect [160 700 250 718] /Contents (Type omitted link review) /A << /S /URI /URI (https://example.com/type-omitted-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Filespec /Subtype /Link /Rect [260 700 365 718] /Contents (Filespec decoy link review) /A << /S /URI /URI (https://example.com/filespec-decoy-link) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /XObject /Subtype /Highlight /Rect [490 700 590 718] /QuadPoints [490 718 590 718 490 700 590 700] /Contents (XObject highlight decoy review) /T (Decoy QA) /C [1 0 0] >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [375 700 480 718] /QuadPoints [375 718 480 718 375 700 480 700] /Contents (Typed highlight review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
        . "%%EOF";
};

$annotationLinkExplicitTypeBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 590.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 590.0, 718.0],
                'spans' => [
                    ['text' => 'Typed docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Untyped docs', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Filespec decoy', 'bbox' => [260.0, 700.0, 365.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Typed highlight', 'bbox' => [375.0, 700.0, 480.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' XObject decoy', 'bbox' => [490.0, 700.0, 590.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects explicitly non annotation dictionaries before link and markup promotion' => static function (TestRunner $t) use (
        $annotationLinkExplicitTypeBoundaryPdf,
        $annotationLinkExplicitTypeBoundaryPages
    ): void {
        $pdf = $annotationLinkExplicitTypeBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotationPages));
        $t->same([7, 8, 11], array_column($annotationPages[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Link', 'Highlight'], array_column($annotationPages[0]['annotations'], 'subtype'));
        $t->same(['Typed link review', 'Type omitted link review', 'Typed highlight review'], array_column($annotationPages[0]['annotations'], 'contents'));
        $t->same(false, str_contains($encoded($annotationPages), 'filespec-decoy-link'));
        $t->same(false, str_contains($encoded($annotationPages), 'Filespec decoy link review'));
        $t->same(false, str_contains($encoded($annotationPages), 'XObject highlight decoy review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $linkPages = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($linkPages));
        $t->same([7, 8], array_column($linkPages[0]['links'], 'annotation_object'));
        $t->same(['https://example.com/typed-link', 'https://example.com/type-omitted-link'], array_column($linkPages[0]['links'], 'uri'));
        $t->same(false, str_contains($encoded($linkPages), 'filespec-decoy-link'));
        $t->same(false, str_contains($encoded($linkPages), 'Filespec decoy link review'));

        $markupExtractor = new PdfMarkupAnnotationExtractor();
        $markupPages = $markupExtractor->extractPageMarkups($pdf);
        $t->same(1, count($markupPages));
        $t->same([11], array_column($markupPages[0]['markups'], 'annotation_object'));
        $t->same(['Highlight'], array_column($markupPages[0]['markups'], 'subtype'));
        $t->same('Typed highlight review', $markupPages[0]['markups'][0]['contents']);
        $t->same(false, str_contains($encoded($markupPages), 'XObject highlight decoy review'));

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkExplicitTypeBoundaryPages(), $pdf);
        $reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/typed-link', $spans[0]['link_uri']);
        $t->same('https://example.com/type-omitted-link', $spans[1]['link_uri']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['review_annotations']));
        $t->same('Typed highlight review', $spans[3]['review_annotations'][0]['contents']);
        $t->true(!isset($spans[4]['review_annotations']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->contains('[Typed docs](https://example.com/typed-link)', $blocks[0]['text']);
        $t->contains('[Untyped docs](https://example.com/type-omitted-link)', $blocks[0]['text']);
        $t->contains('Filespec decoy', $blocks[0]['text']);
        $t->contains('XObject decoy', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'filespec-decoy-link'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Typed docs Untyped docs Filespec decoy Typed highlight XObject decoy', $plainText);
        foreach ([
            'typed-link',
            'type-omitted-link',
            'filespec-decoy-link',
            'Typed link review',
            'Type omitted link review',
            'Filespec decoy link review',
            'Typed highlight review',
            'XObject highlight decoy review',
        ] as $hiddenText) {
            $t->same(false, str_contains($plainText, $hiddenText));
        }
    },
];
