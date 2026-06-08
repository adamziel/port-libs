<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsTextObjectMetricBoundaryCurrentBasePdf = static function (): string {
    $validCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid text-object metric boundary charproc text leak) Tj ET\n";
    $hiddenD0CharProc = "1000 0 d0\nBT 250 0 d0 ET\n"
        . "BT /Fghost 9 Tf (hidden d0 text-object metric charproc text leak) Tj ET\n";
    $hiddenD1CharProc = "1000 0 0 0 1000 700 d1\nBT 250 0 0 0 250 700 d1 ET\n"
        . "BT /Fghost 9 Tf (hidden d1 text-object metric charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556575859> Tj '
        . '1 0 0 1 118 704 Tm <5A5B5C> Tj '
        . 'T* 1 0 0 1 72 688 Tm <616263646566> Tj '
        . '1 0 0 1 118 688 Tm <676869> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
        . '/W.valid /i.valid /d.valid /e.valid '
        . '84 /H.hiddend0 /i.hiddend0 /d.hiddend0 /d.hiddend0 /e.hiddend0 /n.hiddend0 '
        . '/G.hiddend0 /a.hiddend0 /p.hiddend0 '
        . '97 /M.hiddend1 /e.hiddend1 /t.hiddend1 /r.hiddend1 /i.hiddend1 /c.hiddend1 '
        . '/G.hiddend1 /a.hiddend1 /p.hiddend1] >>';
    $charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
        . '/W.valid 3 0 R /i.valid 3 0 R /e.valid 3 0 R '
        . '/H.hiddend0 4 0 R /i.hiddend0 4 0 R /d.hiddend0 4 0 R '
        . '/e.hiddend0 4 0 R /n.hiddend0 4 0 R /G.hiddend0 4 0 R '
        . '/a.hiddend0 4 0 R /p.hiddend0 4 0 R '
        . '/M.hiddend1 5 0 R /e.hiddend1 5 0 R /t.hiddend1 5 0 R '
        . '/r.hiddend1 5 0 R /i.hiddend1 5 0 R /c.hiddend1 5 0 R '
        . '/G.hiddend1 5 0 R /a.hiddend1 5 0 R /p.hiddend1 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 41, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3TextObjectMetricBoundary /BaseFont /T3TextObjectMetricBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 105 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($hiddenD0CharProc) . " >>\nstream\n{$hiddenD0CharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($hiddenD1CharProc) . " >>\nstream\n{$hiddenD1CharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3TextObjectMetricBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects duplicate Type3 CharProc metrics hidden inside post-metric text objects on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsTextObjectMetricBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsTextObjectMetricBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Hidden Gap', 'Metric Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Hidden', 'Gap', 'Metric', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nHidden Gap\nMetric Gap", $plainText);
        $t->same("GoodWide\nHidden Gap\nMetric Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Hidden Gap'));
        $t->true(str_contains($plainText, 'Metric Gap'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'HiddenGap'));
        $t->true(!str_contains($plainText, 'MetricGap'));
        $t->true(!str_contains($plainText, 'text-object metric charproc text leak'));
    },
];
