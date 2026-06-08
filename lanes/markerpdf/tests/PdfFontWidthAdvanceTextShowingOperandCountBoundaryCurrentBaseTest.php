<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthTextShowingOperandCountBoundaryCurrentBasePdf = static function (string $malformedOperator): string {
    $content = 'BT /Fshow 12 Tf '
        . '1 0 0 1 72 720 Tm (Lead) Tj '
        . $malformedOperator . ' '
        . '72 0 Td (Safe) Tj ET';
    $widths = implode(' ', array_fill(0, 90, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fshow 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextShowingOperandCount /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 121 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthTextShowingOperandCountBoundaryCurrentBaseExtract = static function (string $pdf): array {
    $extractor = new PdfTextExtractor();
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    return [
        'lines' => $extractor->extractTextLines($pdf),
        'runs' => $extractor->extractTextRuns($pdf),
        'plain' => $extractor->extractPlainText($pdf),
        'naive' => $extractor->naiveGetText($pdf),
        'span_texts' => array_column($spans, 'text'),
        'span_bboxes' => array_column($spans, 'bbox'),
        'line_bbox' => $line['bbox'] ?? null,
    ];
};

return [
    'rejects extra Tj operands before current font-width advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthTextShowingOperandCountBoundaryCurrentBasePdf,
        $fontWidthTextShowingOperandCountBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthTextShowingOperandCountBoundaryCurrentBaseExtract(
            $fontWidthTextShowingOperandCountBoundaryCurrentBasePdf('100 (Decoy) Tj')
        );

        $t->same(['Lead Safe'], $result['lines']);
        $t->same(['Lead', 'Safe'], $result['runs']);
        $t->same('Lead Safe', $result['plain']);
        $t->same("Lead Safe\n", $result['naive']);
        $t->same(['Lead', 'Safe'], $result['span_texts']);
        $t->same([[0.0, 0.0, 48.0, 12.0], [72.0, 0.0, 120.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 120.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'Decoy'));
        $t->true(!str_contains($result['plain'], 'LeadDecoySafe'));
        $t->true(!str_contains($result['plain'], 'TextShowingOperandCount'));
        $t->true(!str_contains($result['plain'], 'Fshow'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects extra TJ array operands before current font-width advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthTextShowingOperandCountBoundaryCurrentBasePdf,
        $fontWidthTextShowingOperandCountBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthTextShowingOperandCountBoundaryCurrentBaseExtract(
            $fontWidthTextShowingOperandCountBoundaryCurrentBasePdf('[(Bad)] [(Array)] TJ')
        );

        $t->same(['Lead Safe'], $result['lines']);
        $t->same(['Lead', 'Safe'], $result['runs']);
        $t->same('Lead Safe', $result['plain']);
        $t->same("Lead Safe\n", $result['naive']);
        $t->same(['Lead', 'Safe'], $result['span_texts']);
        $t->same([[0.0, 0.0, 48.0, 12.0], [72.0, 0.0, 120.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 120.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'Bad'));
        $t->true(!str_contains($result['plain'], 'Array'));
        $t->true(!str_contains($result['plain'], 'TextShowingOperandCount'));
        $t->true(!str_contains($result['plain'], 'Fshow'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
];
