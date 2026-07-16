<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthCMapFallbackFlagsCurrentBasePdf = static function (): string {
    $cmap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "18 beginbfchar\n"
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
        . "<0014> <0044>\n"
        . "<0015> <0061>\n"
        . "<0016> <0074>\n"
        . "<0017> <0061>\n"
        . "<0018> <0046>\n"
        . "<0019> <006C>\n"
        . "<001A> <006F>\n"
        . "<001B> <0077>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 72 672 Tm <00050006000700080009000A> Tj '
        . '1 0 0 1 96 720 Tm <0014001500160017> Tj 1 0 0 1 96 708 Tm <00180019001A001B> Tj ET';
    $cidSet = "\x7f\xe0\x0f\xf0";
    $compressedCidSet = gzcompress($cidSet);
    if (!is_string($compressedCidSet)) {
        throw new RuntimeException('Unable to compress focused vertical CIDSet fixture.');
    }
    $flags = (1 << 1) | (1 << 5) | (1 << 6);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectVerticalSubset /Encoding 8 0 R /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectVerticalSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /DW2 [880 -1000] /W2 [20 23 -250 500 880] /FontDescriptor 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /IndirectVerticalSerifItalic /Flags {$flags} /CIDSet 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n"
        . "8 0 obj\n/UniJIS-UCS2-V\nendobj\n%%EOF";
};

return [
    'resolves indirect predefined vertical CMap names before width grouping and font flags' => static function (TestRunner $t) use ($fontWidthCMapFallbackFlagsCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthCMapFallbackFlagsCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

        $t->same(['VertImport', 'DataFlow'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import', 'Data', 'Flow'], $extractor->extractTextRuns($pdf));
        $t->same("VertImport\nDataFlow", $plainText);
        $t->same("VertImport\nDataFlow\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, "Vert\nImport"));
        $t->true(!str_contains($plainText, 'Data Flow'));
        $t->true(!str_contains($plainText, "\0"));
        $t->same('IndirectVerticalSerifItalic_serif_non_symbolic_italic', $firstSpan['font'] ?? null);
        $t->same((1 << 1) | (1 << 5) | (1 << 6), $firstSpan['font_flags'] ?? null);
    },
];
