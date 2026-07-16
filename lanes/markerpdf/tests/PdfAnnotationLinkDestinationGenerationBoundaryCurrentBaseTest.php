<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkDestinationGenerationBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current generation target Stale generation target) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Current destination body) Tj ET';
    $stalePageOneContent = 'BT /F1 12 Tf 72 720 Td (Stale page one body) Tj ET';
    $stalePageTwoContent = 'BT /F1 12 Tf 72 720 Td (Stale page two body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 1 R >>\nendobj\n"
        . "2 1 obj\n<< /Type /Pages /Kids [3 1 R 4 1 R] /Count 2 >>\nendobj\n"
        . "3 1 obj\n<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 1 obj\n<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 6 0 R >> >> /Contents 9 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 222 718] /Contents (Current destination generation review) /Dest [4 1 R /FitH 720] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [232 700 382 718] /Contents (Stale destination generation review) /Dest [4 0 R /FitH 111] >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [4 0 R 3 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($stalePageOneContent) . " >>\nstream\n{$stalePageOneContent}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($stalePageTwoContent) . " >>\nstream\n{$stalePageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$annotationLinkDestinationGenerationBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 382.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 382.0, 718.0],
                'spans' => [
                    ['text' => 'Current generation target', 'bbox' => [72.0, 700.0, 222.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale generation target', 'bbox' => [232.0, 700.0, 382.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses exact page generations for Link annotation local destinations before WordPress promotion' => static function (TestRunner $t) use (
        $annotationLinkDestinationGenerationBoundaryPdf,
        $annotationLinkDestinationGenerationBoundaryPages
    ): void {
        $pdf = $annotationLinkDestinationGenerationBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same(3, $annotations[0]['page_object']);
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([1], array_column($annotations[0]['annotations'][0]['actions'], 'destination_page'));
        $t->same(['FitH'], array_column($annotations[0]['annotations'][0]['actions'], 'view_mode'));
        $t->same([], $annotations[0]['annotations'][1]['actions'], 'A destination pointing at stale page generation 4 0 R is not a current-document page target.');

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('FitH', $links[0]['links'][0]['view_mode']);
        $t->same(['top' => 720.0], $links[0]['links'][0]['view_parameters']);
        $t->same(false, str_contains($encoded($links), 'Stale destination generation review'));
        $t->same(false, str_contains($encoded($links), 'FitH 111'));

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkDestinationGenerationBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('FitH', $spans[0]['link_view_mode']);
        $t->same(['top' => 720.0], $spans[0]['link_view_parameters']);
        $t->true(!isset($spans[0]['link_uri']));
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->true(!isset($spans[1]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('Current generation target Stale generation target', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current generation target Stale generation target', $plainText);
        $t->contains('Current destination body', $plainText);
        foreach ([
            'Current destination generation review',
            'Stale destination generation review',
            'Stale page one body',
            'Stale page two body',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
