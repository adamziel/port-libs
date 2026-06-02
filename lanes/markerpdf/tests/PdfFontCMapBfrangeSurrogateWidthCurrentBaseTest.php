<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontCMapBfrangeSurrogateWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPBfrangeSurrogateCID-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "3 begincidrange\n"
        . "<0100> <0102> 900\n"
        . "<0300> <0300> 903\n"
        . "<0200> <0207> 1000\n"
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
        . "3 beginbfrange\n"
        . "<0100> <0102> <D83DDE000049006D>\n"
        . "<0300> <0300> [<D83DDE03>]\n"
        . "<0200> <0207> [<0044> <0061> <0074> <0061> <0046> <006C> <006F> <0077>]\n"
        . "endbfrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <010001010102> Tj '
        . '1 0 0 1 122 720 Tm <0300> Tj '
        . 'T* 1 0 0 1 72 704 Tm <0200020102020203> Tj '
        . '1 0 0 1 96 704 Tm <0204020502060207> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /BfrangeSurrogateCIDSubset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /BfrangeSurrogateCIDSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [900 903 1000 1000 1007 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'increments long ToUnicode bfrange surrogate targets before CID width grouping on current base' => static function (TestRunner $t) use ($fontCMapBfrangeSurrogateWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontCMapBfrangeSurrogateWidthCurrentBasePdf();
        $expectedSurrogateLine = "\u{1F600}Im\u{1F600}In\u{1F600}Io \u{1F603}";
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([$expectedSurrogateLine, 'Data Flow'], $extractor->extractTextLines($pdf));
        $t->same(["\u{1F600}Im\u{1F600}In\u{1F600}Io", "\u{1F603}", 'Data', 'Flow'], $extractor->extractTextRuns($pdf));
        $t->same($expectedSurrogateLine . "\nData Flow", $plainText);
        $t->same($expectedSurrogateLine . "\nData Flow\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, "\u{1F600}Im"));
        $t->true(str_contains($plainText, "\u{1F600}In"));
        $t->true(str_contains($plainText, "\u{1F603}"));
        $t->true(!str_contains($plainText, 'DataFlow'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
