<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationFlagOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean flags Tailed hidden Tailed print Indirect hidden Valid hidden) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /F 4 /Contents (Clean flag review) /A << /S /URI /URI (https://example.com/clean-flags) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 270 718] /F 2 90 0 R /Contents (Tailed hidden flag review) /A << /S /URI /URI (https://example.com/tailed-hidden-flag) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [280 700 374 718] /F 4 90 0 R /Contents (Tailed print flag review) /A << /S /URI /URI (https://example.com/tailed-print-flag) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [384 700 496 718] /F 20 0 R /Contents (Indirect hidden flag review) /A << /S /URI /URI (https://example.com/indirect-hidden-flag) >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [506 700 600 718] /F 2 /Contents (Valid hidden flag review) /A << /S /URI /URI (https://example.com/valid-hidden-flag) >> >>\nendobj\n"
        . "20 0 obj\n2 90 0 R\nendobj\n"
        . "90 0 obj\n<< /S /JavaScript /JS (flagOperandTailReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

$linkAnnotationFlagOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 600.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 600.0, 718.0],
                'spans' => [
                    ['text' => 'Clean flags', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed hidden', 'bbox' => [168.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed print', 'bbox' => [280.0, 700.0, 374.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect hidden', 'bbox' => [384.0, 700.0, 496.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Valid hidden', 'bbox' => [506.0, 700.0, 600.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed Link annotation flag operands before WordPress visibility and flag review' => static function (
        TestRunner $t
    ) use ($linkAnnotationFlagOperandBoundaryPdf, $linkAnnotationFlagOperandBoundaryPages): void {
        $pdf = $linkAnnotationFlagOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9, 10, 11], array_column($annotationRows, 'annotation_object'));
        $t->same([4, 0, 0, 0, 2], array_column($annotationRows, 'annotation_flags'));
        $t->same(['print'], $annotationRows[0]['annotation_flag_names']);
        $t->same([], $annotationRows[1]['annotation_flag_names']);
        $t->same([], $annotationRows[2]['annotation_flag_names']);
        $t->same([], $annotationRows[3]['annotation_flag_names']);
        $t->same(['hidden'], $annotationRows[4]['annotation_flag_names']);
        $t->same(['visible', 'visible', 'visible', 'visible', 'hidden'], array_column($annotationRows, 'annotation_visibility'));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $linkRows = $links[0]['links'];
        $t->same([7, 8, 9, 10], array_column($linkRows, 'annotation_object'));
        $t->same([
            'https://example.com/clean-flags',
            'https://example.com/tailed-hidden-flag',
            'https://example.com/tailed-print-flag',
            'https://example.com/indirect-hidden-flag',
        ], array_column($linkRows, 'uri'));
        $t->same([4, 0, 0, 0], array_column($linkRows, 'annotation_flags'));
        $t->same(['visible', 'visible', 'visible', 'visible'], array_column($linkRows, 'annotation_visibility'));
        $t->same(false, str_contains($encoded($links), 'valid-hidden-flag'));
        $t->same(false, str_contains($encoded($links), 'flagOperandTailReview'));

        $pages = $extractor->applyLinksToPages($linkAnnotationFlagOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-flags', $spans[0]['link_uri']);
        $t->same(4, $spans[0]['link_annotation_flags']);
        $t->same(['print'], $spans[0]['link_annotation_flag_names']);
        $t->same('https://example.com/tailed-hidden-flag', $spans[1]['link_uri']);
        $t->same(0, $spans[1]['link_annotation_flags']);
        $t->same([], $spans[1]['link_annotation_flag_names']);
        $t->same('https://example.com/tailed-print-flag', $spans[2]['link_uri']);
        $t->same(0, $spans[2]['link_annotation_flags']);
        $t->same('https://example.com/indirect-hidden-flag', $spans[3]['link_uri']);
        $t->same(0, $spans[3]['link_annotation_flags']);
        $t->true(!isset($spans[4]['link_uri']));
        $t->true(!isset($spans[4]['link_annotation_flags']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Clean flags](https://example.com/clean-flags) [Tailed hidden](https://example.com/tailed-hidden-flag) '
                . '[Tailed print](https://example.com/tailed-print-flag) [Indirect hidden](https://example.com/indirect-hidden-flag) Valid hidden',
            $blocks[0]['text']
        );

        $encodedPages = $encoded([$pages, $links]);
        foreach (['valid-hidden-flag', 'Valid hidden flag review', 'flagOperandTailReview'] as $reviewOnlyText) {
            $t->same(false, str_contains($encodedPages, $reviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean flags Tailed hidden Tailed print Indirect hidden Valid hidden', $plainText);
        foreach ([
            'clean-flags',
            'tailed-hidden-flag',
            'tailed-print-flag',
            'indirect-hidden-flag',
            'valid-hidden-flag',
            'Clean flag review',
            'Tailed hidden flag review',
            'Tailed print flag review',
            'Indirect hidden flag review',
            'Valid hidden flag review',
            'flagOperandTailReview',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
