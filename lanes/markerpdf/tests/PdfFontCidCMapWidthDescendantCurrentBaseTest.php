<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontCidCMapWidthDescendantCurrentBasePdf = static function (): string {
    $baseEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0001> <000A> 40\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $derivedEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincidrange\n"
        . "<0014> <001B> 60\n"
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

    $content = 'BT /Fv 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 72 672 Tm <00050006000700080009000A> Tj '
        . '1 0 0 1 96 720 Tm <0014001500160017> Tj '
        . '1 0 0 1 96 708 Tm <00180019001A001B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DescendantIndirectW2Subset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /WPDescendantDerived-V /UseCMap /WPDescendantBase-V /Length " . strlen($derivedEncodingCMap) . " >>\nstream\n{$derivedEncodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DescendantIndirectW2Subset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 9 0 R] /W2 [40 49 8 0 R 500 880 60 67 10 0 R 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /CMapName /WPDescendantBase-V /WMode 1 /Length " . strlen($baseEncodingCMap) . " >>\nstream\n{$baseEncodingCMap}\nendstream\nendobj\n"
        . "8 0 obj\n-1000\nendobj\n"
        . "9 0 obj\n-1000\nendobj\n"
        . "10 0 obj\n-250\nendobj\n%%EOF";
};

return [
    'resolves indirect descendant CIDFont W2 operands after Type0 CMap CIDs on current base' => static function (TestRunner $t) use ($fontCidCMapWidthDescendantCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontCidCMapWidthDescendantCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['VertImport', 'DataFlow'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import', 'Data', 'Flow'], $extractor->extractTextRuns($pdf));
        $t->same("VertImport\nDataFlow", $plainText);
        $t->same("VertImport\nDataFlow\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, "Vert\nImport"));
        $t->true(!str_contains($plainText, 'Data Flow'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
