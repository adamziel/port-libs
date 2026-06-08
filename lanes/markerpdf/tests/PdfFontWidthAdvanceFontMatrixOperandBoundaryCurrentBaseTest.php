<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceFontMatrixOperandBoundaryCurrentBasePdf = static function (
    string $matrixFirstOperand,
    string $extraObjects = ''
): string {
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
    $content = 'BT /Ft3bad 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 108 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3bad 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FontMatrixOperandBoundary /BaseFont /T3FontMatrixOperandBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [{$matrixFirstOperand} 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] /Encoding /WinAnsiEncoding "
        . "/CharProcs << >> /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

return [
    'rejects malformed Type3 FontMatrix numeric suffixes before width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceFontMatrixOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceFontMatrixOperandBoundaryCurrentBasePdf('0.002Bad');
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, '0.002Bad'));
        $t->true(!str_contains($plainText, 'T3FontMatrixOperandBoundary'));
        $t->true(!str_contains($plainText, 'Ft3bad'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects tailed indirect Type3 FontMatrix number helpers before width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceFontMatrixOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceFontMatrixOperandBoundaryCurrentBasePdf(
            '7 0 R',
            "7 0 obj\n0.002 /Tail\nendobj\n"
        );
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, 'Tail'));
        $t->true(!str_contains($plainText, 'T3FontMatrixOperandBoundary'));
        $t->true(!str_contains($plainText, 'Ft3bad'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
