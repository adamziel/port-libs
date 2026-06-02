<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontCidType3ToUnicodeSpacingWidthCurrentBasePdf = static function (): string {
    $type0EncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /Type0CidSpaceCurrent-H def\n"
        . "1 begincodespacerange\n"
        . "<F000> <F0FF>\n"
        . "endcodespacerange\n"
        . "4 begincidchar\n"
        . "<F020> 32\n"
        . "<F041> 65\n"
        . "<F042> 66\n"
        . "<F043> 67\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $type0ToUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<F000> <F0FF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<F020> <2060>\n"
        . "<F041> <0041>\n"
        . "<F042> <0042>\n"
        . "<F043> <0043>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $type3EncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /Type3CidSpaceCurrent-H def\n"
        . "1 begincodespacerange\n"
        . "<E000> <E0FF>\n"
        . "endcodespacerange\n"
        . "4 begincidchar\n"
        . "<E020> 32\n"
        . "<E044> 68\n"
        . "<E045> 69\n"
        . "<E046> 70\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $type3ToUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<E000> <E0FF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<E020> <2060>\n"
        . "<E044> <0044>\n"
        . "<E045> <0045>\n"
        . "<E046> <0046>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $type3Widths = array_fill(0, 39, 500.0);
    $type3WidthArray = implode(' ', array_map(
        static fn (float $width): string => rtrim(rtrim(sprintf('%.1F', $width), '0'), '.'),
        $type3Widths
    ));

    $content = 'BT /Fcid 12 Tf 18 Tw 1 0 0 1 72 720 Tm <F041F020F042> Tj '
        . '1 0 0 1 104 720 Tm <F043> Tj '
        . 'T* /Ft3 12 Tf 18 Tw 1 0 0 1 72 704 Tm <E044E020E045> Tj '
        . '1 0 0 1 104 704 Tm <E046> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R /Ft3 10 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /Type0CidSpaceCurrent /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 5 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($type0EncodingCMap) . " >>\nstream\n{$type0EncodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /Type0CidSpaceCurrent /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 32 500 65 67 500] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($type0ToUnicode) . " >>\nstream\n{$type0ToUnicode}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CidSpaceCurrent /BaseFont /T3CidSpaceCurrent /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 32 /LastChar 70 /Widths 13 0 R /Encoding 11 0 R /CharProcs << >> /ToUnicode 12 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($type3EncodingCMap) . " >>\nstream\n{$type3EncodingCMap}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($type3ToUnicode) . " >>\nstream\n{$type3ToUnicode}\nendstream\nendobj\n"
        . "13 0 obj\n[{$type3WidthArray}]\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'uses current CID and Type3 ToUnicode source CIDs for word spacing and width grouping' => static function (TestRunner $t) use ($fontCidType3ToUnicodeSpacingWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontCidType3ToUnicodeSpacingWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expectedLines = ["A\u{2060}BC", "D\u{2060}EF"];

        $t->same($expectedLines, $extractor->extractTextLines($pdf));
        $t->same($expectedLines, explode("\n", $plainText));
        $t->same("A\u{2060}BC\nD\u{2060}EF\n", $extractor->naiveGetText($pdf));
        $t->same(["A\u{2060}B", 'C', "D\u{2060}E", 'F'], $extractor->extractTextRuns($pdf));
        $t->true(str_contains($plainText, "\u{2060}"));
        $t->true(!str_contains($plainText, 'B C'));
        $t->true(!str_contains($plainText, 'E F'));
        $t->true(!str_contains($plainText, 'F020'));
        $t->true(!str_contains($plainText, 'E020'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
