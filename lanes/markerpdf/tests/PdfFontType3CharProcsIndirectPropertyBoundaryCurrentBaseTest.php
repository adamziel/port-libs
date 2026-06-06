<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsIndirectPropertyBoundaryCurrentBasePdf = static function (): string {
    $bdcCharProc = "/Glyph 7 0 R BDC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (indirect BDC charproc text leak) Tj ET\n";
    $dpCharProc = "/GlyphPoint 8 0 R DP\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (indirect DP charproc text leak) Tj ET\n";
    $malformedNumericPropertyCharProc = "/Glyph 123 BDC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (numeric property charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <4142434445464748> Tj '
        . '1 0 0 1 169 720 Tm <494A> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4B4C4D4E4F> Tj '
        . '1 0 0 1 133 704 Tm <5051> Tj '
        . 'T* 1 0 0 1 72 688 Tm <555657> Tj '
        . '1 0 0 1 109 688 Tm <58595A> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /I.bdc /n.bdc /d.bdc /i.bdc '
        . '/r.bdc /e.bdc /c.bdc /t.bdc /O.bdc /k.bdc '
        . '75 /P.dp /o.dp /i.dp /n.dp /t.dp /O.dp /k.dp '
        . '85 /B.badprop /a.badprop /d.badprop /G.badprop /a.badprop /p.badprop] >>';
    $charProcs = '<< /I.bdc 3 0 R /n.bdc 3 0 R /d.bdc 3 0 R /i.bdc 3 0 R '
        . '/r.bdc 3 0 R /e.bdc 3 0 R /c.bdc 3 0 R /t.bdc 3 0 R '
        . '/O.bdc 3 0 R /k.bdc 3 0 R '
        . '/P.dp 4 0 R /o.dp 4 0 R /i.dp 4 0 R /n.dp 4 0 R '
        . '/t.dp 4 0 R /O.dp 4 0 R /k.dp 4 0 R '
        . '/B.badprop 5 0 R /a.badprop 5 0 R /d.badprop 5 0 R '
        . '/G.badprop 5 0 R /p.badprop 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 26, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3IndirectPropertyBoundary /BaseFont /T3IndirectPropertyBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 90 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($bdcCharProc) . " >>\nstream\n{$bdcCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($dpCharProc) . " >>\nstream\n{$dpCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($malformedNumericPropertyCharProc) . " >>\nstream\n{$malformedNumericPropertyCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3IndirectPropertyBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "7 0 obj\n<< /ActualText (250 0 d0 indirect BDC decoy) /Private << /Fake 250 0 d0 >> >>\nendobj\n"
        . "8 0 obj\n<< /ActualText (250 0 d0 indirect DP decoy) /Private << /Fake 250 0 d0 >> >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'accepts indirect Type3 CharProc BDC DP property references before metrics on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsIndirectPropertyBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsIndirectPropertyBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['IndirectOk', 'PointOk', 'Bad Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Indirect', 'Ok', 'Point', 'Ok', 'Bad', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("IndirectOk\nPointOk\nBad Gap", $plainText);
        $t->same("IndirectOk\nPointOk\nBad Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'IndirectOk'));
        $t->true(str_contains($plainText, 'PointOk'));
        $t->true(str_contains($plainText, 'Bad Gap'));
        $t->true(!str_contains($plainText, 'Indirect Ok'));
        $t->true(!str_contains($plainText, 'Point Ok'));
        $t->true(!str_contains($plainText, 'BadGap'));
        $t->true(!str_contains($plainText, 'charproc text leak'));
        $t->true(!str_contains($plainText, 'indirect BDC decoy'));
        $t->true(!str_contains($plainText, 'indirect DP decoy'));
    },
];
