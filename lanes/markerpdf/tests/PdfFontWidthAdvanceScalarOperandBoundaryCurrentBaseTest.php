<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceScalarOperandBoundaryCurrentBasePdf = static function (bool $indirectTail): string {
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
    $content = 'BT /Fcidscalar 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 120 720 Tm <00050006000700080009> Tj ET';
    $widthRange = $indirectTail ? '1 4 7 0 R 5 9 1000' : '1 4 1000Bad 5 9 1000';
    $helperObject = $indirectTail ? "7 0 obj\n1000 /Tail\nendobj\n" : '';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcidscalar 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ScalarOperandCIDWidth /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ScalarOperandCIDWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [{$widthRange}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . $helperObject
        . "%%EOF";
};

return [
    'rejects malformed CIDFont W range scalar tokens before current advance gaps on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceScalarOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceScalarOperandBoundaryCurrentBasePdf(false);
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Wide Block'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('Wide Block', $plainText);
        $t->same("Wide Block\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Block'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 108.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 108.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'WideBlock'));
        $t->true(!str_contains($plainText, '1000Bad'));
        $t->true(!str_contains($plainText, 'ScalarOperandCIDWidth'));
        $t->true(!str_contains($plainText, 'Fcidscalar'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects tailed indirect CIDFont W scalar helpers before current advance gaps on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceScalarOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceScalarOperandBoundaryCurrentBasePdf(true);
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Wide Block'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('Wide Block', $plainText);
        $t->same("Wide Block\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Block'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 108.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 108.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'WideBlock'));
        $t->true(!str_contains($plainText, 'Tail'));
        $t->true(!str_contains($plainText, 'ScalarOperandCIDWidth'));
        $t->true(!str_contains($plainText, 'Fcidscalar'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
