<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotsDuplicateKeyLinkBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current docs Current highlight Stale duplicate) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [8 0 R] /PieceInfo << /WPImport << /Private << /Annots [11 0 R] /ReviewStage /private-decoy >> >> >> /Contents 4 0 R /Annots [7 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Current duplicate Annots link review) /A << /S /URI /URI (https://example.com/current-duplicate-annots-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 390 718] /Contents (Stale first Annots link review) /A << /S /URI /URI (https://example.com/stale-first-annots-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Current duplicate Annots highlight review) /T (Duplicate Annots QA) /C [0.1 0.8 0.3] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Text /Rect [400 700 470 718] /Contents (Current duplicate Annots sticky review) >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 390 718] /Contents (Private nested duplicate Annots link review) /A << /S /URI /URI (https://example.com/private-nested-annots-link) >> >>\nendobj\n"
        . "%%EOF";
};

$pageAnnotsDuplicateKeyLinkBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 390.0, 718.0],
                'spans' => [
                    ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Current highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale duplicate', 'bbox' => [270.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses the last top level page Annots entry before WordPress link promotion' => static function (TestRunner $t) use (
        $pageAnnotsDuplicateKeyLinkBoundaryPdf,
        $pageAnnotsDuplicateKeyLinkBoundaryPages
    ): void {
        $pdf = $pageAnnotsDuplicateKeyLinkBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight', 'Text'], array_column($annotations[0]['annotations'], 'subtype'));
        $annotationJson = json_encode($annotations, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($annotationJson, 'stale-first-annots-link'));
        $t->same(false, str_contains($annotationJson, 'private-nested-annots-link'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same('https://example.com/current-duplicate-annots-link', $links[0]['links'][0]['uri']);
        $linkJson = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($linkJson, 'stale-first-annots-link'));
        $t->same(false, str_contains($linkJson, 'private-nested-annots-link'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same(1, count($markups[0]['markups']));
        $t->same(9, $markups[0]['markups'][0]['annotation_object']);
        $t->same('Current duplicate Annots highlight review', $markups[0]['markups'][0]['contents']);

        $linkedPages = $linkExtractor->applyLinksToPages($pageAnnotsDuplicateKeyLinkBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-duplicate-annots-link', $spans[0]['link_uri']);
        $t->same('Current duplicate Annots highlight review', $spans[1]['review_annotations'][0]['contents']);
        $t->same(false, isset($spans[2]['link_uri']));
        $t->same(false, isset($spans[2]['review_annotations']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Current docs](https://example.com/current-duplicate-annots-link) Current highlight Stale duplicate', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current docs Current highlight Stale duplicate', $plainText);
        foreach ([
            'current-duplicate-annots-link',
            'stale-first-annots-link',
            'private-nested-annots-link',
            'Current duplicate Annots link review',
            'Current duplicate Annots highlight review',
            'Stale first Annots link review',
            'Private nested duplicate Annots link review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
