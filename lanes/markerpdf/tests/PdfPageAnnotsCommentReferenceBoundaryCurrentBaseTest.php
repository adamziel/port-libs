<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotsCommentReferenceBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Split docs Split highlight Split note Stale decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 % split link object reference\n0 R 9 % split markup object reference\n0 R 10 % split text object reference\n0 R % 11 0 R stale comment decoy must not promote\n 12 0R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Split link review) /A 20 % split action object reference\n0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 270 718] /QuadPoints [160 718 270 718 160 700 270 700] /Contents (Split highlight review) /T (Import QA) /C [0.4 0.8 1] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Text /Rect [280 700 350 718] /Contents (Split sticky review) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 450 718] /Contents (Stale comment decoy review) /A << /S /URI /URI (https://example.com/comment-decoy-annotation) >> >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 450 718] /Contents (Tight reference decoy review) /A << /S /URI /URI (https://example.com/tight-decoy-annotation) >> >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.com/comment-split-action) >>\nendobj\n"
        . "%%EOF";
};

$pageAnnotsCommentReferenceBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 450.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 450.0, 718.0],
                'spans' => [
                    ['text' => 'Split docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Split highlight', 'bbox' => [160.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Split note', 'bbox' => [280.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale decoy', 'bbox' => [360.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'treats PDF comments as whitespace inside page Annots and action references' => static function (TestRunner $t) use (
        $pageAnnotsCommentReferenceBoundaryPdf,
        $pageAnnotsCommentReferenceBoundaryPages
    ): void {
        $pdf = $pageAnnotsCommentReferenceBoundaryPdf();
        $encodedRows = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight', 'Text'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same('https://example.com/comment-split-action', $annotations[0]['annotations'][0]['actions'][0]['uri']);
        $t->same(20, $annotations[0]['annotations'][0]['actions'][0]['action_object']);
        $t->same(false, str_contains($encodedRows($annotations), 'comment-decoy-annotation'));
        $t->same(false, str_contains($encodedRows($annotations), 'tight-decoy-annotation'));
        $t->same(false, str_contains($encodedRows($annotations), 'Stale comment decoy review'));
        $t->same(false, str_contains($encodedRows($annotations), 'Tight reference decoy review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/comment-split-action', $links[0]['links'][0]['uri']);
        $t->same(20, $links[0]['links'][0]['action_object']);
        $t->same(false, str_contains($encodedRows($links), 'comment-decoy-annotation'));
        $t->same(false, str_contains($encodedRows($links), 'tight-decoy-annotation'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same([9], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Split highlight review', $markups[0]['markups'][0]['contents']);
        $t->same(false, str_contains($encodedRows($markups), 'Stale comment decoy review'));

        $linkedPages = $linkExtractor->applyLinksToPages($pageAnnotsCommentReferenceBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/comment-split-action', $spans[0]['link_uri']);
        $t->same('Split highlight review', $spans[1]['review_annotations'][0]['contents']);
        foreach ([2, 3] as $spanIndex) {
            $t->true(!isset($spans[$spanIndex]['link_uri']));
            $t->true(!isset($spans[$spanIndex]['review_annotations']));
        }
        $t->same(false, str_contains($encodedRows($reviewPages), 'comment-decoy-annotation'));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Split docs](https://example.com/comment-split-action) Split highlight Split note Stale decoy', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Split docs Split highlight Split note Stale decoy', $plainText);
        foreach ([
            'comment-split-action',
            'comment-decoy-annotation',
            'tight-decoy-annotation',
            'Split link review',
            'Split highlight review',
            'Split sticky review',
            'Stale comment decoy review',
            'Tight reference decoy review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
