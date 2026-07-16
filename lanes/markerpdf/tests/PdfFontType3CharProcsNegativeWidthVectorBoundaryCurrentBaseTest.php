<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsNegativeWidthVectorBoundaryCurrentBasePdf = static function (): string {
    $validWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid negative-width boundary charproc text leak) Tj ET\n";
    $negativeD0CharProc = "-1000 0 d0\nBT /Fghost 9 Tf (negative d0 charproc text leak) Tj ET\n";
    $negativeD1CharProc = "-1000 0 0 0 1000 700 d1\nBT /Fghost 9 Tf (negative d1 charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 118 704 Tm <58595A> Tj '
        . 'T* 1 0 0 1 72 688 Tm <6162636465> Tj '
        . '1 0 0 1 118 688 Tm <666768> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.good /o.good /o.good /d.good '
        . '/W.good /i.good /d.good /e.good '
        . '84 /H.d0 /u.d0 /g.d0 /e.d0 /G.d0 /a.d0 /p.d0 '
        . '97 /D.d1 /e.d1 /l.d1 /t.d1 /a.d1 /G.d1 /a.d1 /p.d1] >>';
    $charProcs = '<< /G.good 3 0 R /o.good 3 0 R /d.good 3 0 R /W.good 3 0 R '
        . '/i.good 3 0 R /e.good 3 0 R '
        . '/H.d0 4 0 R /u.d0 4 0 R /g.d0 4 0 R /e.d0 4 0 R /G.d0 4 0 R /a.d0 4 0 R /p.d0 4 0 R '
        . '/D.d1 5 0 R /e.d1 5 0 R /l.d1 5 0 R /t.d1 5 0 R /a.d1 5 0 R /G.d1 5 0 R /p.d1 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 40, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3NegativeWidthVector /BaseFont /T3NegativeWidthVector "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 104 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validWideCharProc) . " >>\nstream\n{$validWideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($negativeD0CharProc) . " >>\nstream\n{$negativeD0CharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($negativeD1CharProc) . " >>\nstream\n{$negativeD1CharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3NegativeWidthVector /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects negative Type3 CharProc d0 and d1 width vectors before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsNegativeWidthVectorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsNegativeWidthVectorBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Huge Gap', 'Delta Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Huge', 'Gap', 'Delta', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nHuge Gap\nDelta Gap", $plainText);
        $t->same("GoodWide\nHuge Gap\nDelta Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Huge Gap'));
        $t->true(str_contains($plainText, 'Delta Gap'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'HugeGap'));
        $t->true(!str_contains($plainText, 'DeltaGap'));
        $t->true(!str_contains($plainText, 'negative-width boundary charproc text leak'));
        $t->true(!str_contains($plainText, 'negative d0 charproc text leak'));
        $t->true(!str_contains($plainText, 'negative d1 charproc text leak'));
    },
];
