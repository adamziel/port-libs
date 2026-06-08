<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterStackType3CharProcCurrentBaseAsciiHex = static function (string $bytes): string {
    return strtoupper(bin2hex($bytes));
};

$parserStreamFilterStackType3CharProcCurrentBasePdf = static function () use (
    $parserStreamFilterStackType3CharProcCurrentBaseAsciiHex
): string {
    $cleanWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (clean filtered type3 charproc text leak) Tj ET\n";
    $tailedCharProc = "1000 0 d0\nBT /Fghost 9 Tf (tailed filtered type3 charproc text leak) Tj ET\n";
    $cleanEncoded = $parserStreamFilterStackType3CharProcCurrentBaseAsciiHex($cleanWideCharProc) . ">\n \t";
    $tailedEncoded = $parserStreamFilterStackType3CharProcCurrentBaseAsciiHex($tailedCharProc)
        . "> 250 0 d0\nBT /Fghost 9 Tf (post-eod type3 charproc tail leak) Tj ET";

    $content = 'BT /Ft3 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
        . '1 0 0 1 118 704 Tm <575859> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.clean /o.clean /o.clean /d.clean '
        . '/W.clean /i.clean /d.clean /e.clean '
        . '84 /B.tailed /a.tailed /d.tailed /G.tailed /a.tailed /p.tailed] >>';
    $charProcs = '<< /G.clean 3 0 R /o.clean 3 0 R /d.clean 3 0 R '
        . '/W.clean 3 0 R /i.clean 3 0 R /e.clean 3 0 R '
        . '/B.tailed 4 0 R /a.tailed 4 0 R /d.tailed 4 0 R '
        . '/G.tailed 4 0 R /p.tailed 4 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 25, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FilterStackBoundary /BaseFont /T3FilterStackBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 89 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($cleanEncoded) . " >>\nstream\n{$cleanEncoded}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($tailedEncoded) . " >>\nstream\n{$tailedEncoded}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3FilterStackBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects tailed Type3 CharProc stream-filter EOD bytes before WordPress text grouping' => static function (
        TestRunner $t
    ) use ($parserStreamFilterStackType3CharProcCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackType3CharProcCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Bad Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Bad', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nBad Gap", $plainText);
        $t->same("GoodWide\nBad Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Bad Gap'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'BadGap'));
        $t->true(!str_contains($plainText, 'filtered type3 charproc text leak'));
        $t->true(!str_contains($plainText, 'post-eod type3 charproc tail leak'));
        $t->true(!str_contains($plainText, 'ASCIIHexDecode'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
