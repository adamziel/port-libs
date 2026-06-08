<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkDuplicateAnnotsBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Repeated link Repeated highlight Sticky note) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 7 0 R 8 0 R 8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n[7 0 R 8 0 R 9 0 R]\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Contents (Repeated link review) /A << /S /URI /URI (https://example.com/repeated-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [182 700 328 718] /QuadPoints [182 718 328 718 182 700 328 700] /Contents (Repeated highlight review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [340 700 420 718] /Contents (Repeated sticky note review) /T (Import QA) >>\nendobj\n"
        . "%%EOF";
};

$annotationLinkDuplicateAnnotsBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 420.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 420.0, 718.0],
                'spans' => [
                    ['text' => 'Repeated link', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Repeated highlight', 'bbox' => [182.0, 700.0, 328.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Sticky note', 'bbox' => [340.0, 700.0, 420.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'deduplicates repeated page Annots references before link and markup review' => static function (
        TestRunner $t
    ) use ($annotationLinkDuplicateAnnotsBoundaryPdf, $annotationLinkDuplicateAnnotsBoundaryPages): void {
        $pdf = $annotationLinkDuplicateAnnotsBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight', 'Text'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same(1, substr_count($encoded($annotations), 'Repeated link review'));
        $t->same(1, substr_count($encoded($annotations), 'Repeated highlight review'));
        $t->same(1, substr_count($encoded($annotations), 'Repeated sticky note review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/repeated-link', $links[0]['links'][0]['uri']);
        $t->same(1, count($links[0]['links'][0]['actions']));

        $markupExtractor = new PdfMarkupAnnotationExtractor();
        $markups = $markupExtractor->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same([8], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Repeated highlight review', $markups[0]['markups'][0]['contents']);
        $t->same(1, substr_count($encoded($markups), 'Repeated highlight review'));

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkDuplicateAnnotsBoundaryPages(), $pdf);
        $reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(1, count($linkedPages[0]['links']));
        $t->same('https://example.com/repeated-link', $spans[0]['link_uri']);
        $t->same('Repeated highlight review', $spans[1]['review_annotations'][0]['contents']);
        $t->same(1, count($spans[1]['review_annotations']));
        $t->true(!isset($spans[2]['link_uri']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Repeated link](https://example.com/repeated-link) Repeated highlight Sticky note', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Repeated link Repeated highlight Sticky note', $plainText);
        foreach ([
            'repeated-link',
            'Repeated link review',
            'Repeated highlight review',
            'Repeated sticky note review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
