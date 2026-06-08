<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkPresentationOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean styled Tainted color Tainted border Tainted array Tainted scalars) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Clean styled review) /H /O /CA 0.5 /C [0.2 0.4 0.8] /BS << /W 2 /S /D /D [3 1] >> /A << /S /URI /URI (https://example.com/clean-styled) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 270 718] /Contents (Tainted color review) /C 60 0 R /BS << /W 1 /S /S >> /A << /S /URI /URI (https://example.com/tainted-color) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [280 700 380 718] /Contents (Tainted border review) /C [0.1 0.8 0.3] /BS 61 0 R /A << /S /URI /URI (https://example.com/tainted-border) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [390 700 488 718] /Contents (Tainted border array review) /C [] /Border 62 0 R /A << /S /URI /URI (https://example.com/tainted-border-array) >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [498 700 610 718] /Contents (Tainted scalar presentation review) /H /O 90 0 R /CA 0.25 90 0 R /A << /S /URI /URI (https://example.com/tainted-scalars) >> >>\nendobj\n"
        . "60 0 obj\n[1 0 0] 90 0 R\nendobj\n"
        . "61 0 obj\n<< /W 4 /S /D /D [9 9] >> 90 0 R\nendobj\n"
        . "62 0 obj\n[5 6 7 [1 1]] 90 0 R\nendobj\n"
        . "90 0 obj\n<< /S /JavaScript /JS (stalePresentationOperandReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

$linkPresentationOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 610.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 610.0, 718.0],
                'spans' => [
                    ['text' => 'Clean styled', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tainted color', 'bbox' => [170.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tainted border', 'bbox' => [280.0, 700.0, 380.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tainted array', 'bbox' => [390.0, 700.0, 488.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tainted scalars', 'bbox' => [498.0, 700.0, 610.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed Link annotation presentation operands without dropping safe WordPress links' => static function (
        TestRunner $t
    ) use ($linkPresentationOperandBoundaryPdf, $linkPresentationOperandBoundaryPages): void {
        $pdf = $linkPresentationOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9, 10, 11], array_column($annotationRows, 'annotation_object'));
        $t->same('#3366cc', $annotationRows[0]['border_color']['hex']);
        $t->same('dashed', $annotationRows[0]['border']['style']);
        $t->same(null, $annotationRows[1]['border_color'], 'Indirect tailed /C arrays are review-malformed and do not donate color.');
        $t->same('solid', $annotationRows[1]['border']['style'], 'A separate clean /BS still donates border style.');
        $t->same('#1acc4d', $annotationRows[2]['border_color']['hex']);
        $t->same(null, $annotationRows[2]['border'], 'Indirect tailed /BS dictionaries are review-malformed.');
        $t->same('transparent', $annotationRows[3]['border_color']['space']);
        $t->same(null, $annotationRows[3]['border'], 'Indirect tailed /Border arrays are review-malformed.');
        $t->same(null, $annotationRows[4]['opacity'], 'Direct tailed /CA values are review-malformed.');

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $linkRows = $links[0]['links'];
        $t->same([7, 8, 9, 10, 11], array_column($linkRows, 'annotation_object'));
        $t->same([
            'https://example.com/clean-styled',
            'https://example.com/tainted-color',
            'https://example.com/tainted-border',
            'https://example.com/tainted-border-array',
            'https://example.com/tainted-scalars',
        ], array_column($linkRows, 'uri'));

        $t->same('#3366cc', $linkRows[0]['border_color']['hex']);
        $t->same('dashed', $linkRows[0]['border']['style']);
        $t->same('outline', $linkRows[0]['highlight_mode_label']);
        $t->same(0.5, $linkRows[0]['opacity']);
        $t->true(!isset($linkRows[1]['border_color']));
        $t->same('solid', $linkRows[1]['border']['style']);
        $t->same('#1acc4d', $linkRows[2]['border_color']['hex']);
        $t->true(!isset($linkRows[2]['border']));
        $t->same('transparent', $linkRows[3]['border_color']['space']);
        $t->true(!isset($linkRows[3]['border']));
        $t->true(!isset($linkRows[4]['highlight_mode']));
        $t->true(!isset($linkRows[4]['opacity']));

        $pages = $linkExtractor->applyLinksToPages($linkPresentationOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-styled', $spans[0]['link_uri']);
        $t->same('https://example.com/tainted-color', $spans[1]['link_uri']);
        $t->true(!isset($spans[1]['link_annotation_border_color']));
        $t->same('solid', $spans[1]['link_annotation_border']['style']);
        $t->same('https://example.com/tainted-border', $spans[2]['link_uri']);
        $t->true(!isset($spans[2]['link_annotation_border']));
        $t->same('https://example.com/tainted-border-array', $spans[3]['link_uri']);
        $t->true(!isset($spans[3]['link_annotation_border']));
        $t->same('https://example.com/tainted-scalars', $spans[4]['link_uri']);
        $t->true(!isset($spans[4]['link_annotation_highlight_mode']));
        $t->true(!isset($spans[4]['link_annotation_opacity']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Clean styled](https://example.com/clean-styled) [Tainted color](https://example.com/tainted-color) '
                . '[Tainted border](https://example.com/tainted-border) [Tainted array](https://example.com/tainted-border-array) '
                . '[Tainted scalars](https://example.com/tainted-scalars)',
            $blocks[0]['text']
        );

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach (['stalePresentationOperandReview', '[1,0,0]', '[9,9]', '[5,6,7'] as $staleReviewText) {
            $t->same(false, str_contains($encodedReview, $staleReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean styled Tainted color Tainted border Tainted array Tainted scalars', $plainText);
        foreach ([
            'clean-styled',
            'tainted-color',
            'tainted-border',
            'tainted-border-array',
            'tainted-scalars',
            'Clean styled review',
            'Tainted color review',
            'Tainted border review',
            'Tainted border array review',
            'Tainted scalar presentation review',
            'stalePresentationOperandReview',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
