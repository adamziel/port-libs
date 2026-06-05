<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

$annotationLinkPageGenerationBoundaryPdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation page link Current generation markup Stale generation decoy) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale page body text) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 1 R] /Count 1 >>\nendobj\n"
        . "3 1 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /P 3 1 R /Rect [72 700 230 718] /Contents (Current generation page link review) /A << /S /URI /URI (https://example.com/current-page-generation-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /P 3 0 R /Rect [370 700 510 718] /Contents (Stale page generation link review) /A << /S /URI /URI (https://example.com/stale-page-generation-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 1 R /Rect [240 700 360 718] /QuadPoints [240 718 360 718 240 700 360 700] /Contents (Current generation markup review) /T (Generation QA) /C [0.2 0.7 0.4] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 0 R /Rect [370 700 510 718] /QuadPoints [370 718 510 718 370 700 510 700] /Contents (Stale page generation markup review) /T (Stale QA) /C [1 0 0] >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 40 40] /Resources << /Font << /F1 5 0 R >> >> /Contents 11 0 R /Annots [8 0 R 10 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$annotationLinkPageGenerationBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 510.0, 718.0],
                'spans' => [
                    ['text' => 'Current generation page link', 'bbox' => [72.0, 700.0, 230.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Current generation markup', 'bbox' => [240.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale generation decoy', 'bbox' => [370.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'honors exact page-object generations for annotation P link boundaries' => static function (TestRunner $t) use (
        $annotationLinkPageGenerationBoundaryPdf,
        $annotationLinkPageGenerationBoundaryPages
    ): void {
        $pdf = $annotationLinkPageGenerationBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same(3, $annotations[0]['page_object']);
        $t->same([7, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Current generation page link review', 'Current generation markup review'], array_column($annotations[0]['annotations'], 'contents'));
        $t->same(false, str_contains($encoded($annotations), 'stale-page-generation-link'));
        $t->same(false, str_contains($encoded($annotations), 'Stale page generation markup review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(3, $links[0]['page_object']);
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/current-page-generation-link', $links[0]['links'][0]['uri']);
        $t->same([0.0, 0.0, 612.0, 792.0], $links[0]['links'][0]['page_bbox']);
        $t->same(false, str_contains($encoded($links), 'stale-page-generation-link'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same(3, $markups[0]['page_object']);
        $t->same([9], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Current generation markup review', $markups[0]['markups'][0]['contents']);
        $t->same(false, str_contains($encoded($markups), 'Stale page generation markup review'));

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkPageGenerationBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/current-page-generation-link', $spans[0]['link_uri']);
        $t->same('Current generation markup review', $spans[1]['review_annotations'][0]['contents']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['review_annotations']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Current generation page link](https://example.com/current-page-generation-link) Current generation markup Stale generation decoy', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'stale-page-generation-link'));
    },
];
