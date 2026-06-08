<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotsTailedOperandLinkBoundaryPdf = static function (): string {
    $directTailContent = 'BT /F1 12 Tf 72 720 Td (Direct tail link Direct tail highlight) Tj ET';
    $indirectTailContent = 'BT /F1 12 Tf 72 720 Td (Indirect tail link Indirect tail highlight) Tj ET';
    $validContent = 'BT /F1 12 Tf 72 720 Td (Valid link Valid highlight) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 15 0 R >> >> /Annots [7 0 R 8 0 R] 9 0 R /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 15 0 R >> >> /Annots 20 0 R /Contents 16 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 15 0 R >> >> /Annots [13 0 R 14 0 R] /Contents 26 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($directTailContent) . " >>\nstream\n{$directTailContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 172 718] /Contents (Direct tailed Annots link review) /A << /S /URI /URI (https://example.com/direct-tailed-annots-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [184 700 340 718] /QuadPoints [184 718 340 718 184 700 340 700] /Contents (Direct tailed Annots highlight review) /T (Import QA) /C [1 0.8 0] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [350 700 430 718] /Contents (Direct tailed extra sticky review) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 184 718] /Contents (Indirect tailed Annots link review) /A << /S /URI /URI (https://example.com/indirect-tailed-annots-link) >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [196 700 368 718] /QuadPoints [196 718 368 718 196 700 368 700] /Contents (Indirect tailed Annots highlight review) /T (Import QA) /C [0.4 0.8 1] >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Text /Rect [378 700 460 718] /Contents (Indirect tailed extra sticky review) >>\nendobj\n"
        . "13 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Valid link review) /A << /S /URI /URI (https://example.com/current-valid-link) >> >>\nendobj\n"
        . "14 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Valid highlight review) /T (Import QA) /C [0.1 0.8 0.3] >>\nendobj\n"
        . "15 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "16 0 obj\n<< /Length " . strlen($indirectTailContent) . " >>\nstream\n{$indirectTailContent}\nendstream\nendobj\n"
        . "20 0 obj\n[10 0 R 11 0 R] 12 0 R\nendobj\n"
        . "26 0 obj\n<< /Length " . strlen($validContent) . " >>\nstream\n{$validContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageAnnotsTailedOperandLinkBoundaryPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 340.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 340.0, 718.0],
                    'spans' => [
                        ['text' => 'Direct tail link', 'bbox' => [72.0, 700.0, 172.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Direct tail highlight', 'bbox' => [184.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 368.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 368.0, 718.0],
                    'spans' => [
                        ['text' => 'Indirect tail link', 'bbox' => [72.0, 700.0, 184.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Indirect tail highlight', 'bbox' => [196.0, 700.0, 368.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 2,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 260.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 260.0, 718.0],
                    'spans' => [
                        ['text' => 'Valid link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Valid highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

return [
    'rejects tailed page Annots operands before annotation review and WordPress link promotion' => static function (
        TestRunner $t
    ) use ($pageAnnotsTailedOperandLinkBoundaryPdf, $pageAnnotsTailedOperandLinkBoundaryPages): void {
        $pdf = $pageAnnotsTailedOperandLinkBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations), 'Only the valid page /Annots value is reviewable.');
        $t->same(2, $annotations[0]['pnum']);
        $t->same([13, 14], array_column($annotations[0]['annotations'], 'annotation_object'));

        $links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(2, $links[0]['pnum']);
        $t->same([13], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/current-valid-link', $links[0]['links'][0]['uri']);

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same(2, $markups[0]['pnum']);
        $t->same([14], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Valid highlight review', $markups[0]['markups'][0]['contents']);

        $linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pageAnnotsTailedOperandLinkBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $directTailSpans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $indirectTailSpans = $reviewPages[1]['blocks'][0]['lines'][0]['spans'];
        $validSpans = $reviewPages[2]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($directTailSpans[0]['link_uri']));
        $t->true(!isset($directTailSpans[1]['review_annotations']));
        $t->true(!isset($indirectTailSpans[0]['link_uri']));
        $t->true(!isset($indirectTailSpans[1]['review_annotations']));
        $t->same('https://example.com/current-valid-link', $validSpans[0]['link_uri']);
        $t->same('Valid highlight review', $validSpans[1]['review_annotations'][0]['contents']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same(
            "Direct tail link Direct tail highlight Indirect tail link Indirect tail highlight\n"
                . '[Valid link](https://example.com/current-valid-link) Valid highlight',
            $blocks[0]['text']
        );

        $encodedReview = $encoded([$annotations, $links, $markups, $reviewPages]);
        foreach ([
            'direct-tailed-annots-link',
            'indirect-tailed-annots-link',
            'Direct tailed Annots link review',
            'Direct tailed Annots highlight review',
            'Direct tailed extra sticky review',
            'Indirect tailed Annots link review',
            'Indirect tailed Annots highlight review',
            'Indirect tailed extra sticky review',
        ] as $tailedReviewText) {
            $t->same(false, str_contains($encodedReview, $tailedReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Direct tail link Direct tail highlight', $plainText);
        $t->contains('Indirect tail link Indirect tail highlight', $plainText);
        $t->contains('Valid link Valid highlight', $plainText);
        foreach ([
            'direct-tailed-annots-link',
            'indirect-tailed-annots-link',
            'current-valid-link',
            'Direct tailed Annots link review',
            'Indirect tailed Annots link review',
            'Valid link review',
            'Valid highlight review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
