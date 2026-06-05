<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontType3CharProcPrivateGlyphBoundaryCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /Type3PrivateUnusedGlyph-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "15 begincidchar\n"
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
        . "<18> 101\n"
        . "<19> 120\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide private boundary charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin private boundary charproc text leak) Tj ET\n";
    $privateUnusedCharProc = "900 0 d0\nBT /Fghost 9 Tf (unused private glyph text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj '
        . '1 0 0 1 118 720 Tm <0506070809> Tj '
        . 'T* 1 0 0 1 72 704 Tm <14151617> Tj '
        . '1 0 0 1 96 704 Tm <14181914> Tj ET';
    $charProcs = '<< /W 3 0 R /I 3 0 R /D 3 0 R /E 3 0 R '
        . '/B 3 0 R /L 3 0 R /O 3 0 R /C 3 0 R /K 3 0 R '
        . '/t 4 0 R /h 4 0 R /i 4 0 R /n 4 0 R /e 4 0 R /x 4 0 R '
        . '/Private.UnusedGlyph 5 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PrivateUnusedGlyph /BaseFont /T3PrivateUnusedGlyph "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding 19 0 R /CharProcs 21 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($privateUnusedCharProc) . " >>\nstream\n{$privateUnusedCharProc}\nendstream\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "21 0 obj\n{$charProcs}\nendobj\n"
        . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'ignores unused private Type3 CharProcs when building no-ToUnicode glyph fallback on current base' => static function (TestRunner $t) use ($fontType3CharProcPrivateGlyphBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType3CharProcPrivateGlyphBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WIDEBLOCK', 'thin text'], $extractor->extractTextLines($pdf));
        $t->same(['WIDE', 'BLOCK', 'thin', 'text'], $extractor->extractTextRuns($pdf));
        $t->same("WIDEBLOCK\nthin text", $plainText);
        $t->same("WIDEBLOCK\nthin text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'WIDE BLOCK'));
        $t->true(!str_contains($plainText, 'thintext'));
        $t->true(!str_contains($plainText, 'private boundary charproc text leak'));
        $t->true(!str_contains($plainText, 'unused private glyph text leak'));
        $t->true(!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $plainText));
    },
];
