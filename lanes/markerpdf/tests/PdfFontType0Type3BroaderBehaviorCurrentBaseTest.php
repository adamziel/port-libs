<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontType0DirectDescendantDictionaryCurrentBasePdf = static function (): string {
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

    $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 118 720 Tm <00050006000700080009> Tj ET';
    $flags = (1 << 1) | (1 << 5);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DirectDescendantDictionary /Encoding /Identity-H "
        . "/DescendantFonts 5 0 R /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DirectDescendantDictionary "
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 "
        . "/FontDescriptor << /Type /FontDescriptor /FontName /DirectDescendantSerif /Flags {$flags} >> >>\nendobj\n%%EOF";
};

$fontType3CMapCharProcWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /Type3CharProcWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "17 begincidchar\n"
        . "<01> 87\n"
        . "<02> 73\n"
        . "<03> 68\n"
        . "<04> 69\n"
        . "<05> 66\n"
        . "<06> 76\n"
        . "<07> 79\n"
        . "<08> 67\n"
        . "<09> 75\n"
        . "<14> 116\n"
        . "<15> 104\n"
        . "<16> 105\n"
        . "<17> 110\n"
        . "<18> 116\n"
        . "<19> 101\n"
        . "<1A> 120\n"
        . "<1B> 116\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide cmap charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin cmap charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj '
        . '1 0 0 1 118 720 Tm <0506070809> Tj '
        . 'T* 1 0 0 1 72 704 Tm <14151617> Tj '
        . '1 0 0 1 96 704 Tm <18191A1B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CMapCharProcWidth /BaseFont /T3CMapCharProcWidth "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding 3 0 R /CharProcs 4 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /W.wide 10 0 R /I.wide 10 0 R /D.wide 10 0 R /E.wide 10 0 R "
        . "/B.wide 10 0 R /L.wide 10 0 R /O.wide 10 0 R /C.wide 10 0 R /K.wide 10 0 R "
        . "/t.thin 11 0 R /h.thin 11 0 R /i.thin 11 0 R /n.thin 11 0 R "
        . "/e.thin 11 0 R /x.thin 11 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n%%EOF";
};

return [
    'uses direct-referenced Type0 DescendantFonts dictionaries before WordPress width grouping on current base' => static function (
        TestRunner $t
    ) use ($fontType0DirectDescendantDictionaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType0DirectDescendantDictionaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

        $t->same(['WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('WideBlock', $plainText);
        $t->same("WideBlock\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, "\0"));
        $t->same('DirectDescendantSerif_serif_non_symbolic', $firstSpan['font'] ?? null);
        $t->same((1 << 1) | (1 << 5), $firstSpan['font_flags'] ?? null);
    },
    'uses Type3 CMap CharProc widths when Widths arrays are absent on current base' => static function (
        TestRunner $t
    ) use ($fontType3CMapCharProcWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType3CMapCharProcWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WIDEBLOCK', 'thin text'], $extractor->extractTextLines($pdf));
        $t->same(['WIDE', 'BLOCK', 'thin', 'text'], $extractor->extractTextRuns($pdf));
        $t->same("WIDEBLOCK\nthin text", $plainText);
        $t->same("WIDEBLOCK\nthin text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'WIDE BLOCK'));
        $t->true(!str_contains($plainText, 'thintext'));
        $t->true(!str_contains($plainText, 'cmap charproc text leak'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
