<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsMarkedContentOperandBoundaryCurrentBasePdf = static function (): string {
    $malformedBmcCharProc = "999 /Glyph BMC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (malformed BMC charproc text leak) Tj ET\n";
    $malformedBdcCharProc = "999 /Glyph << /ActualText (250 0 d0 marked-content decoy) >> BDC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (malformed BDC charproc text leak) Tj ET\n";
    $validBmcCharProc = "/Glyph BMC\nEMC\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (valid BMC charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
        . '1 0 0 1 109 720 Tm <444546> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4B4C4D> Tj '
        . '1 0 0 1 109 704 Tm <4E4F50> Tj '
        . 'T* 1 0 0 1 72 688 Tm <55565758> Tj '
        . '1 0 0 1 121 688 Tm <595A> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /B.badbmc /m.badbmc /c.badbmc '
        . '/G.badbmc /a.badbmc /p.badbmc '
        . '75 /B.badbdc /d.badbdc /c.badbdc /G.badbdc /a.badbdc /p.badbdc '
        . '85 /W.goodbmc /i.goodbmc /d.goodbmc /e.goodbmc /O.goodbmc /k.goodbmc] >>';
    $charProcs = '<< /B.badbmc 3 0 R /m.badbmc 3 0 R /c.badbmc 3 0 R '
        . '/G.badbmc 3 0 R /a.badbmc 3 0 R /p.badbmc 3 0 R '
        . '/B.badbdc 4 0 R /d.badbdc 4 0 R /c.badbdc 4 0 R /G.badbdc 4 0 R '
        . '/a.badbdc 4 0 R /p.badbdc 4 0 R '
        . '/W.goodbmc 5 0 R /i.goodbmc 5 0 R /d.goodbmc 5 0 R '
        . '/e.goodbmc 5 0 R /O.goodbmc 5 0 R /k.goodbmc 5 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MarkedContentOperandBoundary /BaseFont /T3MarkedContentOperandBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($malformedBmcCharProc) . " >>\nstream\n{$malformedBmcCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($malformedBdcCharProc) . " >>\nstream\n{$malformedBdcCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($validBmcCharProc) . " >>\nstream\n{$validBmcCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3MarkedContentOperandBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProc metrics after malformed marked-content operands before WordPress grouping on current base' => static function (TestRunner $t) use ($type3CharProcsMarkedContentOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsMarkedContentOperandBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Bmc Gap', 'Bdc Gap', 'WideOk'], $extractor->extractTextLines($pdf));
        $t->same(['Bmc', 'Gap', 'Bdc', 'Gap', 'Wide', 'Ok'], $extractor->extractTextRuns($pdf));
        $t->same("Bmc Gap\nBdc Gap\nWideOk", $plainText);
        $t->same("Bmc Gap\nBdc Gap\nWideOk\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'BmcGap'));
        $t->true(!str_contains($plainText, 'BdcGap'));
        $t->true(!str_contains($plainText, 'Wide Ok'));
        $t->true(!str_contains($plainText, 'charproc text leak'));
        $t->true(!str_contains($plainText, 'marked-content decoy'));
    },
];
