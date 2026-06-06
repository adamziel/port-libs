<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkIndirectArrayBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Fragment link Chain link Direct link Hidden link Literal decoy Nested decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 10 0 R 12 0 R (14 0 R) [15 0 R]] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n[7 0 R 8 0 R 9 0 R]\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 164 718] /Contents (Fragment link review) /A << /S /URI /URI (https://example.com/fragment-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 678 210 696] /Contents (Fragment note review) /T (Import QA) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /F 2 /Rect [352 700 438 718] /Contents (Hidden fragment review) /A << /S /URI /URI (https://example.com/hidden-fragment-link) >> >>\nendobj\n"
        . "10 0 obj\n11 0 R\nendobj\n"
        . "11 0 obj\n[16 0 R]\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 342 718] /Contents (Direct link review) /A << /S /URI /URI (https://example.com/direct-link) >> >>\nendobj\n"
        . "14 0 obj\n<< /Type /Annot /Subtype /Link /Rect [448 700 542 718] /Contents (Literal decoy review) /A << /S /URI /URI (https://example.com/literal-decoy-link) >> >>\nendobj\n"
        . "15 0 obj\n<< /Type /Annot /Subtype /Link /Rect [552 700 642 718] /Contents (Nested direct array decoy review) /A << /S /URI /URI (https://example.com/nested-direct-array-decoy) >> >>\nendobj\n"
        . "16 0 obj\n<< /Type /Annot /Subtype /Link /Rect [174 700 238 718] /Contents (Chain link review) /A << /S /URI /URI (https://example.com/chain-link) >> >>\nendobj\n"
        . "%%EOF";
};

$annotationLinkIndirectArrayBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 642.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 642.0, 718.0],
                'spans' => [
                    ['text' => 'Fragment link', 'bbox' => [72.0, 700.0, 164.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Chain link', 'bbox' => [174.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct link', 'bbox' => [248.0, 700.0, 342.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden link', 'bbox' => [352.0, 700.0, 438.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Literal decoy', 'bbox' => [448.0, 700.0, 542.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Nested decoy', 'bbox' => [552.0, 700.0, 642.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'flattens indirect Annots array fragments before annotation review and WordPress link promotion' => static function (TestRunner $t) use (
        $annotationLinkIndirectArrayBoundaryPdf,
        $annotationLinkIndirectArrayBoundaryPages
    ): void {
        $pdf = $annotationLinkIndirectArrayBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 16, 12], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Text', 'Link', 'Link', 'Link'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same('Fragment note review', $annotations[0]['annotations'][1]['contents']);
        $t->same('hidden', $annotations[0]['annotations'][2]['annotation_visibility']);
        $t->same(false, str_contains($encoded($annotations), 'literal-decoy-link'));
        $t->same(false, str_contains($encoded($annotations), 'nested-direct-array-decoy'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 16, 12], array_column($links[0]['links'], 'annotation_object'));
        $t->same([
            'https://example.com/fragment-link',
            'https://example.com/chain-link',
            'https://example.com/direct-link',
        ], array_column($links[0]['links'], 'uri'));
        $t->same(false, str_contains($encoded($links), 'hidden-fragment-link'));
        $t->same(false, str_contains($encoded($links), 'literal-decoy-link'));
        $t->same(false, str_contains($encoded($links), 'nested-direct-array-decoy'));

        $pages = $linkExtractor->applyLinksToPages($annotationLinkIndirectArrayBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/fragment-link', $spans[0]['link_uri']);
        $t->same('https://example.com/chain-link', $spans[1]['link_uri']);
        $t->same('https://example.com/direct-link', $spans[2]['link_uri']);
        $t->true(!isset($spans[3]['link_uri']));
        $t->true(!isset($spans[4]['link_uri']));
        $t->true(!isset($spans[5]['link_uri']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Fragment link](https://example.com/fragment-link) [Chain link](https://example.com/chain-link) [Direct link](https://example.com/direct-link) Hidden link Literal decoy Nested decoy',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Fragment link Chain link Direct link Hidden link Literal decoy Nested decoy', $plainText);
        foreach ([
            'Fragment link review',
            'Fragment note review',
            'Hidden fragment review',
            'Chain link review',
            'Direct link review',
            'Literal decoy review',
            'Nested direct array decoy review',
            'fragment-link',
            'chain-link',
            'direct-link',
            'hidden-fragment-link',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
