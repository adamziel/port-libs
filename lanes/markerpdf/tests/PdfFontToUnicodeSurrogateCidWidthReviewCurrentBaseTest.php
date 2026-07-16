<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontToUnicodeSurrogateCidWidthReviewCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DeclaredCIDRows-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0100> <0109> 700\n"
        . "<0200> <0207> 800\n"
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
        . "<0100> <D83DDE00>\n"
        . "<0101> <0049>\n"
        . "<0102> <006D>\n"
        . "<0103> <0070>\n"
        . "<0104> <006F>\n"
        . "<0105> <0072>\n"
        . "<0106> <0074>\n"
        . "<0107> <0057>\n"
        . "<0108> <0050>\n"
        . "<0109> <D83DDE03>\n"
        . "<0200> <0044>\n"
        . "<0201> <0061>\n"
        . "<0202> <0074>\n"
        . "<0203> <0061>\n"
        . "<0204> <0046>\n"
        . "<0205> <006C>\n"
        . "<0206> <006F>\n"
        . "<0207> <0077>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0100010101020103010401050106010701080109> Tj '
        . 'T* 1 0 0 1 72 704 Tm <0200020102020203> Tj '
        . '1 0 0 1 100 704 Tm <0204020502060207> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SurrogateDeclaredCIDSubset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SurrogateDeclaredCIDSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [700 709 1000 800 807 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'honors declared Type0 CID CMap row counts before surrogate ToUnicode width grouping on current base' => static function (TestRunner $t) use ($fontToUnicodeSurrogateCidWidthReviewCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontToUnicodeSurrogateCidWidthReviewCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(["\u{1F600}ImportWP\u{1F603}", 'DataFlow'], $extractor->extractTextLines($pdf));
        $t->same(["\u{1F600}ImportWP\u{1F603}", 'Data', 'Flow'], $extractor->extractTextRuns($pdf));
        $t->same("\u{1F600}ImportWP\u{1F603}\nDataFlow", $plainText);
        $t->same("\u{1F600}ImportWP\u{1F603}\nDataFlow\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, "\u{1F600}"));
        $t->true(str_contains($plainText, "\u{1F603}"));
        $t->true(!str_contains($plainText, 'Data Flow'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
