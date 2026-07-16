<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthDefaultOperandBoundaryCurrentBasePdf = static function (
    string $defaultWidthOperand,
    string $extraObjects = ''
): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "9 beginbfchar\n"
        . "<0001> <0057>\n"
        . "<0002> <0069>\n"
        . "<0003> <0064>\n"
        . "<0004> <0065>\n"
        . "<0005> <0042>\n"
        . "<0006> <006C>\n"
        . "<0007> <006F>\n"
        . "<0008> <0063>\n"
        . "<0009> <006B>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Fdw 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 96 720 Tm <00050006000700080009> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DefaultOperandCID /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DefaultOperandCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW {$defaultWidthOperand} >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$fontWidthDefaultOperandBoundaryCurrentBaseExtract = static function (string $pdf): array {
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
    'honors valid CIDFont DW default operands before current advance gaps on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthDefaultOperandBoundaryCurrentBasePdf,
        $fontWidthDefaultOperandBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthDefaultOperandBoundaryCurrentBaseExtract(
            $fontWidthDefaultOperandBoundaryCurrentBasePdf('250')
        );

        $t->same(['Wide Block'], $result['lines']);
        $t->same(['Wide', 'Block'], $result['runs']);
        $t->same('Wide Block', $result['plain']);
        $t->same("Wide Block\n", $result['naive']);
        $t->same(['Wide', 'Block'], $result['span_texts']);
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 39.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 39.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'WideBlock'));
        $t->true(!str_contains($result['plain'], 'DefaultOperandCID'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects tailed direct CIDFont DW operands before current advance gaps on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthDefaultOperandBoundaryCurrentBasePdf,
        $fontWidthDefaultOperandBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthDefaultOperandBoundaryCurrentBaseExtract(
            $fontWidthDefaultOperandBoundaryCurrentBasePdf('250 1000')
        );

        $t->same(['WideBlock'], $result['lines']);
        $t->same(['Wide', 'Block'], $result['runs']);
        $t->same('WideBlock', $result['plain']);
        $t->same("WideBlock\n", $result['naive']);
        $t->same(['Wide', 'Block'], $result['span_texts']);
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 108.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 108.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'Wide Block'));
        $t->true($result['span_bboxes'] !== [[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 39.0, 12.0]]);
        $t->true(!str_contains($result['plain'], 'DefaultOperandCID'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects tailed indirect CIDFont DW helpers before current advance gaps on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthDefaultOperandBoundaryCurrentBasePdf,
        $fontWidthDefaultOperandBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthDefaultOperandBoundaryCurrentBaseExtract(
            $fontWidthDefaultOperandBoundaryCurrentBasePdf(
                '7 0 R 1000',
                "7 0 obj\n250\nendobj\n"
            )
        );

        $t->same(['WideBlock'], $result['lines']);
        $t->same(['Wide', 'Block'], $result['runs']);
        $t->same('WideBlock', $result['plain']);
        $t->same("WideBlock\n", $result['naive']);
        $t->same(['Wide', 'Block'], $result['span_texts']);
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 108.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 108.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'Wide Block'));
        $t->true($result['span_bboxes'] !== [[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 39.0, 12.0]]);
        $t->true(!str_contains($result['plain'], 'DefaultOperandCID'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
];
