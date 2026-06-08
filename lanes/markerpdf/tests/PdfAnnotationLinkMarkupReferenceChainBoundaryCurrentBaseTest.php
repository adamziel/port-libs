<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkMarkupReferenceChainBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Chained link Chained highlight Cyclic decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n10 0 R\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 164 718] /Contents (Chained link review) /A << /S /URI /URI (https://example.com/chained-annots-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [174 700 314 718] /QuadPoints [174 718 314 718 174 700 314 700] /Contents (Chained highlight review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
        . "10 0 obj\n[7 0 R 8 0 R 12 0 R]\nendobj\n"
        . "12 0 obj\n13 0 R\nendobj\n"
        . "13 0 obj\n12 0 R\nendobj\n"
        . "%%EOF";
};

$annotationLinkMarkupReferenceChainBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 420.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 420.0, 718.0],
                'spans' => [
                    ['text' => 'Chained link', 'bbox' => [72.0, 700.0, 164.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Chained highlight', 'bbox' => [174.0, 700.0, 314.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Cyclic decoy', 'bbox' => [324.0, 700.0, 420.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves chained Annots references for link and markup review without following cycles' => static function (
        TestRunner $t
    ) use ($annotationLinkMarkupReferenceChainBoundaryPdf, $annotationLinkMarkupReferenceChainBoundaryPages): void {
        $pdf = $annotationLinkMarkupReferenceChainBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same(['Chained link review', 'Chained highlight review'], array_column($annotations[0]['annotations'], 'contents'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/chained-annots-link', $links[0]['links'][0]['uri']);
        $t->same('review-uri', $links[0]['links'][0]['safety']);

        $markupExtractor = new PdfMarkupAnnotationExtractor();
        $markups = $markupExtractor->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same([8], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Highlight', $markups[0]['markups'][0]['subtype']);
        $t->same('Chained highlight review', $markups[0]['markups'][0]['contents']);
        $t->same([[174.0, 700.0, 314.0, 718.0]], $markups[0]['markups'][0]['quad_rects']);

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkMarkupReferenceChainBoundaryPages(), $pdf);
        $reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/chained-annots-link', $spans[0]['link_uri']);
        $t->same('Chained highlight review', $spans[1]['review_annotations'][0]['contents']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['review_annotations']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Chained link](https://example.com/chained-annots-link) Chained highlight Cyclic decoy', $blocks[0]['text']);

        $reviewJson = $encoded([$annotations, $links, $markups, $reviewPages]);
        $t->same(false, str_contains($reviewJson, '12 0 R'));
        $t->same(false, str_contains($reviewJson, '13 0 R'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Chained link Chained highlight Cyclic decoy', $plainText);
        foreach ([
            'chained-annots-link',
            'Chained link review',
            'Chained highlight review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
