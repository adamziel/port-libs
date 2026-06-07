<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkScalarDestinationPageBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Valid index Out direct Out action URI docs) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Appendix destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Valid scalar destination review) /Dest 1 >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 245 718] /Contents (Out of range direct destination review) /Dest 9 >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [255 700 340 718] /Contents (Out of range action destination review) /A << /S /GoTo /D 12 >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [350 700 430 718] /Contents (URI docs review) /A << /S /URI /URI (https://example.com/scalar-page-boundary) >> >>\nendobj\n"
        . "%%EOF";
};

$linkScalarDestinationPageBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 430.0, 718.0],
                'spans' => [
                    ['text' => 'Valid index', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Out direct', 'bbox' => [160.0, 700.0, 245.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Out action', 'bbox' => [255.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' URI docs', 'bbox' => [350.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'bounds scalar Link annotation destination page indexes before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($linkScalarDestinationPageBoundaryPdf, $linkScalarDestinationPageBoundaryPages): void {
        $pdf = $linkScalarDestinationPageBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['local-destination'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same(1, $annotations[0]['annotations'][0]['actions'][0]['destination_page']);
        $t->same([], $annotations[0]['annotations'][1]['actions'], 'A direct scalar /Dest outside the current page count is malformed.');
        $t->same(
            ['unsupported-action-review'],
            array_column($annotations[0]['annotations'][2]['actions'], 'safety'),
            'A scalar GoTo /D outside the current page count remains review-only and is not a local destination.'
        );
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][3]['actions'], 'safety'));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same(1, $links[0]['links'][0]['destination_page']);
        $t->same('local-destination', $links[0]['links'][0]['safety']);
        $t->same('https://example.com/scalar-page-boundary', $links[0]['links'][1]['uri']);
        $t->same(false, str_contains($encoded($links), 'Out of range direct destination review'));
        $t->same(false, str_contains($encoded($links), 'Out of range action destination review'));

        $linkedPages = $extractor->applyLinksToPages($linkScalarDestinationPageBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('local-destination', $spans[0]['link_safety']);
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_destination_page']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->same('https://example.com/scalar-page-boundary', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('Valid index Out direct Out action [URI docs](https://example.com/scalar-page-boundary)', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Valid index Out direct Out action URI docs', $plainText);
        $t->contains('Appendix destination body', $plainText);
        foreach ([
            'Valid scalar destination review',
            'Out of range direct destination review',
            'Out of range action destination review',
            'URI docs review',
            'scalar-page-boundary',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
