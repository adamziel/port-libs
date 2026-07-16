<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseHorizontalPdf = static function (): string {
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
    $content = 'BT /Fcidw 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 120 720 Tm <00050006000700080009> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcidw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OverflowCidWidthRange /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OverflowCidWidthRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 70000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseVerticalPdf = static function (): string {
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
    $content = 'BT /Fcidw2 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 72 696 Tm <00050006000700080009000A> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcidw2 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OverflowVerticalCidWidthRange /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /OverflowVerticalCidWidthRange-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OverflowVerticalCidWidthRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 70000 -250 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects overflowing CIDFont W range upper bounds before horizontal advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseHorizontalPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseHorizontalPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Wide Block'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('Wide Block', $plainText);
        $t->same("Wide Block\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Block'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 78.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 78.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'WideBlock'));
        $t->true(!str_contains($plainText, 'OverflowCidWidthRange'));
        $t->true(!str_contains($plainText, 'Fcidw'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects overflowing CIDFont W2 range upper bounds before vertical advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseVerticalPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseVerticalPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Vert Import'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import'], $extractor->extractTextRuns($pdf));
        $t->same('Vert Import', $plainText);
        $t->same("Vert Import\n", $extractor->naiveGetText($pdf));
        $t->same(['Vert', 'Import'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 72.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 72.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'VertImport'));
        $t->true(!str_contains($plainText, 'OverflowVerticalCidWidthRange'));
        $t->true(!str_contains($plainText, 'Fcidw2'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
