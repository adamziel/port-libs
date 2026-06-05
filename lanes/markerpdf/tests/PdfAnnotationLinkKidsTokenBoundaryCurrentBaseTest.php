<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

$kidsTokenBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current docs) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second docs) Tj ET';
    $literalDecoyContent = 'BT /F1 12 Tf 72 720 Td (Literal decoy docs) Tj ET';
    $dictionaryDecoyContent = 'BT /F1 12 Tf 72 720 Td (Dictionary decoy docs) Tj ET';
    $nestedArrayDecoyContent = 'BT /F1 12 Tf 72 720 Td (Nested array decoy docs) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R (8 0 R) << /PrivatePage 9 0 R >> [10 0 R] 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R /Annots [7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 11 0 R /Annots [12 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Current docs review) /A << /S /URI /URI (https://example.com/current-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 13 0 R /Annots [14 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R /Annots [16 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 17 0 R /Annots [18 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Second docs review) /A << /S /URI /URI (https://example.com/second-docs) >> >>\nendobj\n"
        . "13 0 obj\n<< /Length " . strlen($literalDecoyContent) . " >>\nstream\n{$literalDecoyContent}\nendstream\nendobj\n"
        . "14 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /Contents (Literal decoy review) /A << /S /URI /URI (https://example.com/literal-decoy) >> >>\nendobj\n"
        . "15 0 obj\n<< /Length " . strlen($dictionaryDecoyContent) . " >>\nstream\n{$dictionaryDecoyContent}\nendstream\nendobj\n"
        . "16 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 220 718] /Contents (Dictionary decoy review) /A << /S /URI /URI (https://example.com/dictionary-decoy) >> >>\nendobj\n"
        . "17 0 obj\n<< /Length " . strlen($nestedArrayDecoyContent) . " >>\nstream\n{$nestedArrayDecoyContent}\nendstream\nendobj\n"
        . "18 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 235 718] /Contents (Nested array decoy review) /A << /S /URI /URI (https://example.com/nested-array-decoy) >> >>\nendobj\n"
        . "%%EOF";
};

$kidsTokenBoundaryPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 160.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 160.0, 718.0],
                    'spans' => [
                        ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 150.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 150.0, 718.0],
                    'spans' => [
                        ['text' => 'Second docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

return [
    'keeps page-tree Kids references token bounded before promoting Link annotations' => static function (TestRunner $t) use (
        $kidsTokenBoundaryPdf,
        $kidsTokenBoundaryPages
    ): void {
        $pdf = $kidsTokenBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(2, count($annotations), 'Only direct top-level Kids references are page leaves.');
        $t->same([3, 4], array_column($annotations, 'page_object'));
        $t->same([7], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([12], array_column($annotations[1]['annotations'], 'annotation_object'));
        $t->same(false, str_contains($encoded($annotations), 'literal-decoy'));
        $t->same(false, str_contains($encoded($annotations), 'dictionary-decoy'));
        $t->same(false, str_contains($encoded($annotations), 'nested-array-decoy'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(2, count($links));
        $t->same([3, 4], array_column($links, 'page_object'));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same([12], array_column($links[1]['links'], 'annotation_object'));
        $t->same('https://example.com/current-docs', $links[0]['links'][0]['uri']);
        $t->same('https://example.com/second-docs', $links[1]['links'][0]['uri']);
        $t->same(false, str_contains($encoded($links), 'literal-decoy'));
        $t->same(false, str_contains($encoded($links), 'dictionary-decoy'));
        $t->same(false, str_contains($encoded($links), 'nested-array-decoy'));

        $pages = $linkExtractor->applyLinksToPages($kidsTokenBoundaryPages(), $pdf);
        $t->same('https://example.com/current-docs', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same('https://example.com/second-docs', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            "[Current docs](https://example.com/current-docs)\n[Second docs](https://example.com/second-docs)",
            $blocks[0]['text']
        );
        $t->same(false, str_contains($blocks[0]['text'], 'decoy'));
    },
];
