<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceDw2OperandBoundaryCurrentBasePdf = static function (string $dw2Operand): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/WMode 1 def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 begincidrange\n"
        . "<0001> <0004> 40\n"
        . "<0005> <000A> 50\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "10 beginbfchar\n"
        . "<0001> <0056>\n"
        . "<0002> <0065>\n"
        . "<0003> <0072>\n"
        . "<0004> <0074>\n"
        . "<0005> <0049>\n"
        . "<0006> <006D>\n"
        . "<0007> <0070>\n"
        . "<0008> <006F>\n"
        . "<0009> <0072>\n"
        . "<000A> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fdw2 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 72 696 Tm <00050006000700080009000A> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdw2 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DW2OperandBoundaryCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /DW2OperandBoundaryCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DW2OperandBoundaryCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 {$dw2Operand} >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$fontWidthAdvanceDw2OperandBoundaryCurrentBaseExtract = static function (string $pdf): array {
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
    'honors valid CIDFont DW2 default vertical metrics before styled span grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceDw2OperandBoundaryCurrentBasePdf,
        $fontWidthAdvanceDw2OperandBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceDw2OperandBoundaryCurrentBaseExtract(
            $fontWidthAdvanceDw2OperandBoundaryCurrentBasePdf('[880 -250]')
        );

        $t->same(['Vert Import'], $result['lines']);
        $t->same(['Vert', 'Import'], $result['runs']);
        $t->same('Vert Import', $result['plain']);
        $t->same("Vert Import\n", $result['naive']);
        $t->same(['Vert', 'Import'], $result['span_texts']);
        $t->same([[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 18.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 24.0, 18.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'DW2OperandBoundaryCID'));
        $t->true(!str_contains($result['plain'], 'Fdw2'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects tailed CIDFont DW2 default vertical metric arrays before styled span grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceDw2OperandBoundaryCurrentBasePdf,
        $fontWidthAdvanceDw2OperandBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceDw2OperandBoundaryCurrentBaseExtract(
            $fontWidthAdvanceDw2OperandBoundaryCurrentBasePdf('[880 -250 /Tail]')
        );

        $t->same(['Vert Import'], $result['lines']);
        $t->same(['Vert', 'Import'], $result['runs']);
        $t->same('Vert Import', $result['plain']);
        $t->same("Vert Import\n", $result['naive']);
        $t->same(['Vert', 'Import'], $result['span_texts']);
        $t->same([[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 72.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 24.0, 72.0], $result['line_bbox']);
        $t->true($result['span_bboxes'] !== [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 18.0]]);
        $t->true(!str_contains($result['plain'], 'Tail'));
        $t->true(!str_contains($result['plain'], 'DW2OperandBoundaryCID'));
        $t->true(!str_contains($result['plain'], 'Fdw2'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
    'rejects extra numeric CIDFont DW2 default vertical metrics before styled span grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthAdvanceDw2OperandBoundaryCurrentBasePdf,
        $fontWidthAdvanceDw2OperandBoundaryCurrentBaseExtract
    ): void {
        $result = $fontWidthAdvanceDw2OperandBoundaryCurrentBaseExtract(
            $fontWidthAdvanceDw2OperandBoundaryCurrentBasePdf('[880 -250 1000]')
        );

        $t->same(['Vert Import'], $result['lines']);
        $t->same(['Vert', 'Import'], $result['runs']);
        $t->same('Vert Import', $result['plain']);
        $t->same("Vert Import\n", $result['naive']);
        $t->same(['Vert', 'Import'], $result['span_texts']);
        $t->same([[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 72.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 24.0, 72.0], $result['line_bbox']);
        $t->true($result['span_bboxes'] !== [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 18.0]]);
        $t->true(!str_contains($result['plain'], 'DW2OperandBoundaryCID'));
        $t->true(!str_contains($result['plain'], 'Fdw2'));
        $t->true(!str_contains($result['plain'], "\0"));
    },
];
