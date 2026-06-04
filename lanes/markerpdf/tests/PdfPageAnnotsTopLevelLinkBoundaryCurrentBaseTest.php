<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotsTopLevelLinkBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current docs Current highlight Stale private) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /PieceInfo << /WPImport << /Private << /Annots [8 0 R] /ReviewStage /stale-private-link >> >> >> /Annots [7 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Current docs link review) /A << /S /URI /URI (https://example.com/current-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 350 718] /Contents (Private nested stale link) /A << /S /URI /URI (https://example.com/private-stale-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Current highlight review) /T (Import QA) /C [1 0.9 0] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Text /Rect [360 700 420 718] /Contents (Current sticky note) >>\nendobj\n"
        . "%%EOF";
};

$pageAnnotsTopLevelLinkBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 350.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 350.0, 718.0],
                'spans' => [
                    ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Current highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale private', 'bbox' => [270.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses only top level page Annots before promoting links and markup review metadata' => static function (TestRunner $t) use (
        $pageAnnotsTopLevelLinkBoundaryPdf,
        $pageAnnotsTopLevelLinkBoundaryPages
    ): void {
        $pdf = $pageAnnotsTopLevelLinkBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight', 'Text'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->contains('Current sticky note', json_encode($annotations, JSON_UNESCAPED_SLASHES) ?: '');
        $t->same(false, str_contains(json_encode($annotations, JSON_UNESCAPED_SLASHES) ?: '', 'Private nested stale link'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same('https://example.com/current-docs', $links[0]['links'][0]['uri']);
        $t->same(false, str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'private-stale-link'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same(1, count($markups[0]['markups']));
        $t->same(9, $markups[0]['markups'][0]['annotation_object']);
        $t->same('Highlight', $markups[0]['markups'][0]['subtype']);

        $linkedPages = $linkExtractor->applyLinksToPages($pageAnnotsTopLevelLinkBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-docs', $spans[0]['link_uri']);
        $t->same('Current highlight review', $spans[1]['review_annotations'][0]['contents']);
        $t->same(false, isset($spans[2]['link_uri']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Current docs](https://example.com/current-docs) Current highlight Stale private', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current docs Current highlight Stale private', $plainText);
        $t->same(false, str_contains($plainText, 'private-stale-link'));
        $t->same(false, str_contains($plainText, 'Private nested stale link'));
        $t->same(false, str_contains($plainText, 'Current highlight review'));
    },
];
