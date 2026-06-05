<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotsTokenBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current docs Current highlight Comment decoy Literal decoy Nested decoy Hex decoy Sticky note) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R % 8 0 R ] stale comment must not close or promote\n9 0 R (10 0 R) <313120302052> [12 0 R] 13 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Current docs link review) /A << /S /URI /URI (https://example.com/current-docs-token) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 350 718] /Contents (Comment decoy link) /A << /S /URI /URI (https://example.com/comment-decoy-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Current highlight token review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 450 718] /Contents (Literal decoy link) /A << /S /URI /URI (https://example.com/literal-decoy-link) >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [460 700 540 718] /Contents (Hex decoy link) /A << /S /URI /URI (https://example.com/hex-decoy-link) >> >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [550 700 620 718] /Contents (Nested decoy link) /A << /S /URI /URI (https://example.com/nested-decoy-link) >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /Annot /Subtype /Text /Rect [630 700 700 718] /Contents (Current sticky token note) /T (Import QA) >>\nendobj\n"
        . "%%EOF";
};

$pageAnnotsTokenBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 700.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 700.0, 718.0],
                'spans' => [
                    ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Current highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Comment decoy', 'bbox' => [270.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Literal decoy', 'bbox' => [360.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hex decoy', 'bbox' => [460.0, 700.0, 540.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Nested decoy', 'bbox' => [550.0, 700.0, 620.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Sticky note', 'bbox' => [630.0, 700.0, 700.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'tokenizes page Annots arrays before promoting link and markup annotations' => static function (TestRunner $t) use (
        $pageAnnotsTokenBoundaryPdf,
        $pageAnnotsTokenBoundaryPages
    ): void {
        $pdf = $pageAnnotsTokenBoundaryPdf();
        $encodedRows = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 9, 13], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight', 'Text'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->contains('Current sticky token note', $encodedRows($annotations));
        $t->same(false, str_contains($encodedRows($annotations), 'Comment decoy link'));
        $t->same(false, str_contains($encodedRows($annotations), 'Literal decoy link'));
        $t->same(false, str_contains($encodedRows($annotations), 'Hex decoy link'));
        $t->same(false, str_contains($encodedRows($annotations), 'Nested decoy link'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same('https://example.com/current-docs-token', $links[0]['links'][0]['uri']);
        $t->same(false, str_contains($encodedRows($links), 'comment-decoy-link'));
        $t->same(false, str_contains($encodedRows($links), 'literal-decoy-link'));
        $t->same(false, str_contains($encodedRows($links), 'hex-decoy-link'));
        $t->same(false, str_contains($encodedRows($links), 'nested-decoy-link'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same(1, count($markups[0]['markups']));
        $t->same(9, $markups[0]['markups'][0]['annotation_object']);
        $t->same('Current highlight token review', $markups[0]['markups'][0]['contents']);

        $linkedPages = $linkExtractor->applyLinksToPages($pageAnnotsTokenBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-docs-token', $spans[0]['link_uri']);
        $t->same('Current highlight token review', $spans[1]['review_annotations'][0]['contents']);
        foreach ([2, 3, 4, 5] as $spanIndex) {
            $t->true(!isset($spans[$spanIndex]['link_uri']));
            $t->true(!isset($spans[$spanIndex]['review_annotations']));
        }

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Current docs](https://example.com/current-docs-token) Current highlight Comment decoy Literal decoy Hex decoy Nested decoy Sticky note', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current docs Current highlight Comment decoy Literal decoy Nested decoy Hex decoy Sticky note', $plainText);
        foreach ([
            'current-docs-token',
            'comment-decoy-link',
            'literal-decoy-link',
            'hex-decoy-link',
            'nested-decoy-link',
            'Current highlight token review',
            'Current sticky token note',
        ] as $hiddenText) {
            $t->same(false, str_contains($plainText, $hiddenText));
        }
    },
];
