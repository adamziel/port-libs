<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkIndirectRectArrayBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect rect Tailed rect) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect 20 0 R /Contents (Indirect rect review) /A << /S /URI /URI (https://example.com/indirect-rect-array) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect 21 0 R /Contents (Tailed rect review) /A << /S /URI /URI (https://example.com/tailed-rect-array-decoy) >> >>\nendobj\n"
        . "20 0 obj\n[72 700 158 718]\nendobj\n"
        . "21 0 obj\n[168 700 260 718] 30 0 R\nendobj\n"
        . "30 0 obj\n<< /S /JavaScript /JS (tailedIndirectRectReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

$linkIndirectRectArrayBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 260.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 260.0, 718.0],
                'spans' => [
                    ['text' => 'Indirect rect', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed rect', 'bbox' => [168.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves whole-object indirect Link annotation Rect arrays before review and WordPress promotion' => static function (
        TestRunner $t
    ) use ($linkIndirectRectArrayBoundaryPdf, $linkIndirectRectArrayBoundaryPages): void {
        $pdf = $linkIndirectRectArrayBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $rows = $annotations[0]['annotations'];
        $t->same([7, 8], array_column($rows, 'annotation_object'));
        $t->same([72.0, 700.0, 158.0, 718.0], $rows[0]['rect']);
        $t->same(null, $rows[1]['rect'], 'Tailed indirect /Rect arrays stay review-only and cannot donate coordinates.');

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same([72.0, 700.0, 158.0, 718.0], $links[0]['links'][0]['rect']);
        $t->same('https://example.com/indirect-rect-array', $links[0]['links'][0]['uri']);

        $pages = $extractor->applyLinksToPages($linkIndirectRectArrayBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/indirect-rect-array', $spans[0]['link_uri']);
        $t->same([72.0, 700.0, 158.0, 718.0], $spans[0]['link_page_rect']);
        $t->true(!isset($spans[1]['link_uri']), 'Tailed indirect /Rect arrays must not promote a WordPress link.');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Indirect rect](https://example.com/indirect-rect-array) Tailed rect', $blocks[0]['text']);

        $encodedPages = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'tailed-rect-array-decoy',
            'tailedIndirectRectReview',
            'Tailed rect review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($encodedPages, $reviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Indirect rect Tailed rect', $plainText);
        foreach ([
            'indirect-rect-array',
            'tailed-rect-array-decoy',
            'tailedIndirectRectReview',
            'Indirect rect review',
            'Tailed rect review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
