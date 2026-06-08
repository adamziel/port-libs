<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf = static function (string $content): string {
    $widths = implode(' ', array_fill(0, 37, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fstate 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextStateOperandCount /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 68 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract = static function (string $pdf): array {
    $extractor = new PdfTextExtractor();
    $pages = $extractor->extractStyledTextPages($pdf);
    $styledLines = [];
    foreach (($pages[0]['blocks'] ?? []) as $block) {
        foreach (($block['lines'] ?? []) as $line) {
            $styledLines[] = $line;
        }
    }
    $line = $styledLines[0] ?? [];
    $spans = $line['spans'] ?? [];

    return [
        'lines' => $extractor->extractTextLines($pdf),
        'runs' => $extractor->extractTextRuns($pdf),
        'plain' => $extractor->extractPlainText($pdf),
        'naive' => $extractor->naiveGetText($pdf),
        'span_texts' => array_column($spans, 'text'),
        'span_bboxes' => array_column($spans, 'bbox'),
        'line_bbox' => $line['bbox'] ?? null,
        'line_count' => count($styledLines),
    ];
};

return [
    'rejects extra Tw operands before current font-width advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf,
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract(
            $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf(
                'BT /Fstate 12 Tf 100 30 Tw 1 0 0 1 72 720 Tm (A ) Tj (B) Tj ET'
            )
        );

        $t->same(['A B'], $result['lines']);
        $t->same(['A ', 'B'], $result['runs']);
        $t->same('A B', $result['plain']);
        $t->same("A B\n", $result['naive']);
        $t->same(['A ', 'B'], $result['span_texts']);
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 36.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 36.0, 12.0], $result['line_bbox']);
        $t->true($result['span_bboxes'] !== [[0.0, 0.0, 54.0, 12.0], [54.0, 0.0, 66.0, 12.0]]);
        $t->true(!str_contains($result['plain'], 'TextStateOperandCount'));
        $t->true(!str_contains($result['plain'], 'Fstate'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects extra Tc operands before current styled advance bboxes on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf,
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract(
            $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf(
                'BT /Fstate 12 Tf 100 6 Tc 1 0 0 1 72 720 Tm (AB) Tj (CD) Tj ET'
            )
        );

        $t->same(['ABCD'], $result['lines']);
        $t->same(['AB', 'CD'], $result['runs']);
        $t->same('ABCD', $result['plain']);
        $t->same("ABCD\n", $result['naive']);
        $t->same(['AB', 'CD'], $result['span_texts']);
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 48.0, 12.0], $result['line_bbox']);
        $t->true($result['span_bboxes'] !== [[0.0, 0.0, 30.0, 12.0], [30.0, 0.0, 54.0, 12.0]]);
        $t->true(!str_contains($result['plain'], 'TextStateOperandCount'));
        $t->true(!str_contains($result['plain'], 'Fstate'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects extra Ts operands before current styled span rise bboxes on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf,
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract(
            $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf(
                'BT /Fstate 12 Tf 100 6 Ts 1 0 0 1 72 720 Tm (AB) Tj (CD) Tj ET'
            )
        );

        $t->same(['ABCD'], $result['lines']);
        $t->same(['AB', 'CD'], $result['runs']);
        $t->same('ABCD', $result['plain']);
        $t->same(['AB', 'CD'], $result['span_texts']);
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 48.0, 12.0], $result['line_bbox']);
        $t->true($result['span_bboxes'] !== [[0.0, 6.0, 24.0, 18.0], [24.0, 6.0, 48.0, 18.0]]);
        $t->true(!str_contains($result['plain'], 'TextStateOperandCount'));
        $t->true(!str_contains($result['plain'], 'Fstate'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects extra quote operator leading operands before current quote advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf,
        $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBaseExtract(
            $fontWidthAdvanceTextStateOperandCountBoundaryCurrentBasePdf(
                'BT /Fstate 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj 999 24 6 (Tail) " ET'
            )
        );

        $t->same(['Lead'], $result['lines']);
        $t->same(['Lead'], $result['runs']);
        $t->same('Lead', $result['plain']);
        $t->same("Lead\n", $result['naive']);
        $t->same(['Lead'], $result['span_texts']);
        $t->same([[0.0, 0.0, 48.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 48.0, 12.0], $result['line_bbox']);
        $t->same(1, $result['line_count']);
        $t->true(!str_contains($result['plain'], 'Tail'));
        $t->true(!str_contains($result['plain'], 'TextStateOperandCount'));
        $t->true(!str_contains($result['plain'], 'Fstate'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
];
