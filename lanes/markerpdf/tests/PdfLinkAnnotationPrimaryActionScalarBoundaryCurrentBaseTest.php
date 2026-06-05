<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkPrimaryActionScalarBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Direct docs Scalar action Dict action Direct dest) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Named destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Direct docs review) /A << /S /URI /URI (https://example.com/direct-docs-scalar-boundary) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 255 718] /Contents (Scalar action review) /A (named-target) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [265 700 360 718] /Contents (Dictionary without S review) /A << /D (named-target) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 455 718] /Contents (Direct destination review) /Dest (named-target) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(named-target) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
        . "%%EOF";
};

$linkPrimaryActionScalarBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 455.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 455.0, 718.0],
                'spans' => [
                    ['text' => 'Direct docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Scalar action', 'bbox' => [160.0, 700.0, 255.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Dict action', 'bbox' => [265.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct dest', 'bbox' => [370.0, 700.0, 455.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects scalar and actionless-dictionary Link annotation A values before WordPress span promotion' => static function (TestRunner $t) use (
        $linkPrimaryActionScalarBoundaryPdf,
        $linkPrimaryActionScalarBoundaryPages
    ): void {
        $pdf = $linkPrimaryActionScalarBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same([], $annotations[0]['annotations'][1]['actions'], 'A scalar /A value is not an action dictionary.');
        $t->same([], $annotations[0]['annotations'][2]['actions'], 'A dictionary without /S is not an action dictionary.');
        $t->same(['local-destination'], array_column($annotations[0]['annotations'][3]['actions'], 'safety'));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/direct-docs-scalar-boundary', $links[0]['links'][0]['uri']);
        $t->same('review-uri', $links[0]['links'][0]['safety']);
        $t->same('local-destination', $links[0]['links'][1]['safety']);
        $t->same('named-target', $links[0]['links'][1]['destination']);
        $t->same(1, $links[0]['links'][1]['destination_page']);
        $t->same('XYZ', $links[0]['links'][1]['view_mode']);
        $t->same(['left' => 36.0, 'top' => 700.0, 'zoom' => null], $links[0]['links'][1]['view_parameters']);

        $linkedPages = $extractor->applyLinksToPages($linkPrimaryActionScalarBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/direct-docs-scalar-boundary', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_destination_page']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->same(1, $spans[3]['link_destination_page']);
        $t->same('named-target', $spans[3]['link_destination']);
        $t->true(!isset($spans[3]['link_uri']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Direct docs](https://example.com/direct-docs-scalar-boundary) Scalar action Dict action Direct dest', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Direct docs Scalar action Dict action Direct dest', $plainText);
        $t->contains('Named destination body', $plainText);
        foreach ([
            'direct-docs-scalar-boundary',
            'named-target',
            'Scalar action review',
            'Dictionary without S review',
            'Direct destination review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
