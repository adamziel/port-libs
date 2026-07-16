<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsMarkedContentBalanceBoundaryCurrentBasePdf = static function (): string {
    $validCharProc = "/Glyph BMC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (valid marked-content balance charproc text leak) Tj ET\n";
    $unmatchedEmcCharProc = "EMC\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (unmatched EMC charproc text leak) Tj ET\n";
    $extraEmcCharProc = "/Glyph BMC\nEMC\nEMC\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (extra EMC charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
        . '1 0 0 1 109 704 Tm <575859> Tj '
        . 'T* 1 0 0 1 72 688 Tm <6162636465> Tj '
        . '1 0 0 1 121 688 Tm <666768> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.good /o.good /o.good /d.good '
        . '/W.good /i.good /d.good /e.good '
        . '84 /B.bad /a.bad /d.bad /G.bad /a.bad /p.bad '
        . '97 /E.extra /x.extra /t.extra /r.extra /a.extra /G.extra /a.extra /p.extra] >>';
    $charProcs = '<< /G.good 3 0 R /o.good 3 0 R /d.good 3 0 R '
        . '/W.good 3 0 R /i.good 3 0 R /e.good 3 0 R '
        . '/B.bad 4 0 R /a.bad 4 0 R /d.bad 4 0 R /G.bad 4 0 R /p.bad 4 0 R '
        . '/E.extra 5 0 R /x.extra 5 0 R /t.extra 5 0 R /r.extra 5 0 R '
        . '/a.extra 5 0 R /G.extra 5 0 R /p.extra 5 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MarkedContentBalance /BaseFont /T3MarkedContentBalance "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($unmatchedEmcCharProc) . " >>\nstream\n{$unmatchedEmcCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($extraEmcCharProc) . " >>\nstream\n{$extraEmcCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3MarkedContentBalance /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects unmatched Type3 CharProc EMC before metrics while preserving balanced marked content on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsMarkedContentBalanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsMarkedContentBalanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Bad Gap', 'Extra Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Bad', 'Gap', 'Extra', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nBad Gap\nExtra Gap", $plainText);
        $t->same("GoodWide\nBad Gap\nExtra Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Bad Gap'));
        $t->true(str_contains($plainText, 'Extra Gap'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'BadGap'));
        $t->true(!str_contains($plainText, 'ExtraGap'));
        $t->true(!str_contains($plainText, 'charproc text leak'));
    },
];
