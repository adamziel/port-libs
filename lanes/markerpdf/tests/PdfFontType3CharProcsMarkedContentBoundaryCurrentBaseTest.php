<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsMarkedContentBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "/Glyph << /ActualText (250 0 d0 marked content decoy) /Private << /Fake 250 0 d0 >> >> BDC\n"
        . "1000 0 d0\nEMC\nBT /Fghost 9 Tf (wide marked content charproc text leak) Tj ET\n";
    $thinCharProc = "/Glyph BMC\nEMC\n250 0 0 0 250 700 d1\n"
        . "BT /Fghost 9 Tf (thin marked content charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.marked /i.marked /d.marked /e.marked '
        . '/B.marked /l.marked /o.marked /c.marked /k.marked '
        . '84 /T.markedthin /h.markedthin /i.markedthin /n.markedthin '
        . '/T.markedthin /e.markedthin /x.markedthin /t.markedthin] >>';
    $charProcs = '<< /W.marked 3 0 R /i.marked 3 0 R /d.marked 3 0 R /e.marked 3 0 R '
        . '/B.marked 3 0 R /l.marked 3 0 R /o.marked 3 0 R /c.marked 3 0 R /k.marked 3 0 R '
        . '/T.markedthin 4 0 R /h.markedthin 4 0 R /i.markedthin 4 0 R /n.markedthin 4 0 R '
        . '/e.markedthin 4 0 R /x.markedthin 4 0 R /t.markedthin 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MarkedContentBoundary /BaseFont /T3MarkedContentBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3MarkedContentBoundary /Flags 4 /MissingWidth 500 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'accepts Type3 CharProc metrics after marked-content wrappers before WordPress text grouping on current base' => static function (TestRunner $t) use ($type3CharProcsMarkedContentBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsMarkedContentBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'marked content charproc text leak'));
        $t->true(!str_contains($plainText, 'marked content decoy'));
        $t->true(!str_contains($plainText, 'Private'));
    },
];
