<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkHighlightModePageKeyBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Push link Tailed push Real page decoy) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Target page link) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R /Annots [7 0 R 8 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 6 0 R /Annots [10 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Valid push highlight review) /H /P /A << /S /URI /URI (https://example.com/push-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Tailed push highlight review) /H /P 4 0 R /A << /S /URI /URI (https://example.com/tailed-push) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /P 4 0 R /Rect [72 700 178 718] /Contents (Real page reference review) /H /O /A << /S /URI /URI (https://example.com/real-page-reference) >> >>\nendobj\n"
        . "%%EOF";
};

$annotationLinkHighlightModePageKeyBoundaryPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 360.0, 718.0],
                    'spans' => [
                        ['text' => 'Push link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Tailed push', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' Real page decoy', 'bbox' => [260.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 178.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 178.0, 718.0],
                    'spans' => [
                        ['text' => 'Target page link', 'bbox' => [72.0, 700.0, 178.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

return [
    'does not treat Link highlight-mode P operands as annotation page references' => static function (
        TestRunner $t
    ) use ($annotationLinkHighlightModePageKeyBoundaryPdf, $annotationLinkHighlightModePageKeyBoundaryPages): void {
        $pdf = $annotationLinkHighlightModePageKeyBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(2, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([10], array_column($annotations[1]['annotations'], 'annotation_object'));
        $t->same(['Valid push highlight review', 'Tailed push highlight review'], array_column($annotations[0]['annotations'], 'contents'));
        $t->same('Real page reference review', $annotations[1]['annotations'][0]['contents']);
        $t->same(false, str_contains($encoded([$annotations[0]]), 'real-page-reference'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(2, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(1, $links[1]['pnum']);
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same([10], array_column($links[1]['links'], 'annotation_object'));
        $t->same('push', $links[0]['links'][0]['highlight_mode_label']);
        $t->true(!isset($links[0]['links'][1]['highlight_mode']));
        $t->same('https://example.com/tailed-push', $links[0]['links'][1]['uri']);
        $t->same('https://example.com/real-page-reference', $links[1]['links'][0]['uri']);
        $t->same(false, str_contains($encoded([$links[0]]), 'real-page-reference'));

        $pages = $linkExtractor->applyLinksToPages($annotationLinkHighlightModePageKeyBoundaryPages(), $pdf);
        $pageOneSpans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $pageTwoSpans = $pages[1]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/push-link', $pageOneSpans[0]['link_uri']);
        $t->same('push', $pageOneSpans[0]['link_annotation_highlight_mode_label']);
        $t->same('https://example.com/tailed-push', $pageOneSpans[1]['link_uri']);
        $t->true(!isset($pageOneSpans[1]['link_annotation_highlight_mode']));
        $t->true(!isset($pageOneSpans[2]['link_uri']));
        $t->same('https://example.com/real-page-reference', $pageTwoSpans[0]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            "[Push link](https://example.com/push-link) [Tailed push](https://example.com/tailed-push) Real page decoy\n"
                . '[Target page link](https://example.com/real-page-reference)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Push link Tailed push Real page decoy', $plainText);
        $t->contains('Target page link', $plainText);
        foreach ([
            'Valid push highlight review',
            'Tailed push highlight review',
            'Real page reference review',
            'push-link',
            'tailed-push',
            'real-page-reference',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
