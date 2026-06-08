<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkNextActionValueBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Scalar next Dict next Valid next) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Named target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Scalar next review) /A << /S /URI /URI (https://example.com/docs-scalar-next) /Next (named-target) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Dict next review) /A << /S /URI /URI (https://example.com/docs-dict-next) /Next << /D (named-target) >> >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 350 718] /Contents (Valid next review) /A << /S /URI /URI (https://example.com/docs-valid-next) /Next << /S /GoTo /D (named-target) >> >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(named-target) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
        . "%%EOF";
};

$linkNextActionValueBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 350.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 350.0, 718.0],
                'spans' => [
                    ['text' => 'Scalar next', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Dict next', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Valid next', 'bbox' => [260.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects scalar and destination-dictionary Link annotation Next values before destination review' => static function (
        TestRunner $t
    ) use ($linkNextActionValueBoundaryPdf, $linkNextActionValueBoundaryPages): void {
        $pdf = $linkNextActionValueBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9], array_column($annotationRows, 'annotation_object'));
        $t->same(['review-uri', 'malformed-action-dictionary'], array_column($annotationRows[0]['actions'], 'safety'));
        $t->same(['URI', 'unknown'], array_column($annotationRows[0]['actions'], 'action_type'));
        $t->same(['review-uri', 'malformed-action-dictionary'], array_column($annotationRows[1]['actions'], 'safety'));
        $t->same(['URI', 'unknown'], array_column($annotationRows[1]['actions'], 'action_type'));
        $t->same(['review-uri', 'local-destination'], array_column($annotationRows[2]['actions'], 'safety'));
        $t->same('named-target', $annotationRows[2]['actions'][1]['destination']);
        $t->same(1, $annotationRows[2]['actions'][1]['destination_page']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same([
            'https://example.com/docs-scalar-next',
            'https://example.com/docs-dict-next',
            'https://example.com/docs-valid-next',
        ], array_column($links[0]['links'], 'uri'));
        $t->same(['review-uri', 'malformed-action-dictionary'], array_column($links[0]['links'][0]['actions'], 'safety'));
        $t->same(['review-uri', 'malformed-action-dictionary'], array_column($links[0]['links'][1]['actions'], 'safety'));
        $t->same(['review-uri', 'local-destination'], array_column($links[0]['links'][2]['actions'], 'safety'));
        $t->same(false, isset($links[0]['links'][0]['actions'][1]['destination_page']));
        $t->same(false, isset($links[0]['links'][1]['actions'][1]['destination_page']));

        $linkedPages = $extractor->applyLinksToPages($linkNextActionValueBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/docs-scalar-next', $spans[0]['link_uri']);
        $t->same(['review-uri', 'malformed-action-dictionary'], array_column($spans[0]['link_actions_review'], 'safety'));
        $t->same('https://example.com/docs-dict-next', $spans[1]['link_uri']);
        $t->same(['review-uri', 'malformed-action-dictionary'], array_column($spans[1]['link_actions_review'], 'safety'));
        $t->same('https://example.com/docs-valid-next', $spans[2]['link_uri']);
        $t->same(['review-uri', 'local-destination'], array_column($spans[2]['link_actions_review'], 'safety'));
        $t->same(1, $spans[2]['link_actions_review'][1]['destination_page']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Scalar next](https://example.com/docs-scalar-next) [Dict next](https://example.com/docs-dict-next) [Valid next](https://example.com/docs-valid-next)', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'named-target'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Scalar next Dict next Valid next', $plainText);
        $t->contains('Named target body', $plainText);
        foreach ([
            'docs-scalar-next',
            'docs-dict-next',
            'docs-valid-next',
            'named-target',
            'Scalar next review',
            'Dict next review',
            'Valid next review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
