<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcEscapedGlyphNameUnicodeCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide escaped glyph charproc payload leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin escaped glyph charproc payload leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 '
        . '/uni#30#30#35#37.wide /uni#30#30#36#39.wide /uni#30#30#36#34.wide '
        . '/uni#30#30#36#35.wide /uni#30#30#34#32.wide /uni#30#30#36#43.wide '
        . '/uni#30#30#36#46.wide /uni#30#30#36#33.wide /uni#30#30#36#42.wide '
        . '84 /u#30#30#37#34.thin /u#30#30#36#38.thin /u#30#30#36#39.thin '
        . '/u#30#30#36#45.thin /u#30#30#37#34.thin /u#30#30#36#35.thin '
        . '/u#30#30#37#38.thin /u#30#30#37#34.thin] >>';
    $charProcs = '<< '
        . '/uni#30#30#35#37.wide 3 0 R /uni#30#30#36#39.wide 3 0 R '
        . '/uni#30#30#36#34.wide 3 0 R /uni#30#30#36#35.wide 3 0 R '
        . '/uni#30#30#34#32.wide 3 0 R /uni#30#30#36#43.wide 3 0 R '
        . '/uni#30#30#36#46.wide 3 0 R /uni#30#30#36#33.wide 3 0 R '
        . '/uni#30#30#36#42.wide 3 0 R /u#30#30#37#34.thin 4 0 R '
        . '/u#30#30#36#38.thin 4 0 R /u#30#30#36#39.thin 4 0 R '
        . '/u#30#30#36#45.thin 4 0 R /u#30#30#36#35.thin 4 0 R '
        . '/u#30#30#37#38.thin 4 0 R >>';
    $widthValues = array_fill(0, 27, 250);
    foreach (range(19, 26) as $index) {
        $widthValues[$index] = 1000;
    }
    $staleWidths = implode(' ', $widthValues);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3EscapedGlyphs /BaseFont /T3EscapedGlyphs "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 91 /Widths [{$staleWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3EscapedGlyphs /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'decodes escaped Type3 Encoding and CharProc glyph names before WordPress extraction on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcEscapedGlyphNameUnicodeCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcEscapedGlyphNameUnicodeCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'thin text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'thin', 'text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nthin text", $plainText);
        $t->same("WideBlock\nthin text\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'WideBlock'));
        $t->true(str_contains($plainText, 'thin text'));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'thintext'));
        $t->true(!str_contains($plainText, 'escaped glyph charproc payload'));
        $t->true(!str_contains($plainText, 'uni#30'));
        $t->true(!str_contains($plainText, 'u#30'));
        $t->true(!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $plainText));
    },
];
