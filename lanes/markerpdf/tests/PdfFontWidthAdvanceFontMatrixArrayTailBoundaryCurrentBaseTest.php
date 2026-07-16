<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBasePdf = static function (string $fontMatrixOperand, string $extraObjects = ''): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<41> <0041>\n"
        . "<42> <0042>\n"
        . "<43> <0043>\n"
        . "<44> <0044>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Ft3tail 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 108 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3tail 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FontMatrixArrayTail /BaseFont /T3FontMatrixArrayTail "
        . "/FontBBox [0 0 1000 700] /FontMatrix {$fontMatrixOperand} "
        . "/FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] /Encoding /WinAnsiEncoding "
        . "/CharProcs << >> /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseExtract = static function (string $pdf): array {
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
    'rejects tailed indirect Type3 FontMatrix array helpers before width advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBasePdf,
        $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseExtract(
            $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBasePdf(
                '7 0 R',
                "7 0 obj\n[0.002 0 0 0.001 0 0] /Tail\nendobj\n"
            )
        );

        $t->same(['AB CD'], $result['lines']);
        $t->same(['AB', 'CD'], $result['runs']);
        $t->same('AB CD', $result['plain']);
        $t->same("AB CD\n", $result['naive']);
        $t->same(['AB', 'CD'], $result['span_texts']);
        $t->same([[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 60.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'ABCD'));
        $t->true(!str_contains($result['plain'], 'Tail'));
        $t->true(!str_contains($result['plain'], 'T3FontMatrixArrayTail'));
        $t->true(!str_contains($result['plain'], 'Ft3tail'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'still honors valid indirect Type3 FontMatrix array helpers for current advance grouping' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBasePdf,
        $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseExtract(
            $fontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBasePdf(
                '7 0 R',
                "7 0 obj\n[0.002 0 0 0.001 0 0]\nendobj\n"
            )
        );

        $t->same(['ABCD'], $result['lines']);
        $t->same(['AB', 'CD'], $result['runs']);
        $t->same('ABCD', $result['plain']);
        $t->same("ABCD\n", $result['naive']);
        $t->same(['AB', 'CD'], $result['span_texts']);
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 96.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 96.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'AB CD'));
        $t->true(!str_contains($result['plain'], 'Tail'));
        $t->true(!str_contains($result['plain'], 'T3FontMatrixArrayTail'));
        $t->true(!str_contains($result['plain'], 'Ft3tail'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
];
