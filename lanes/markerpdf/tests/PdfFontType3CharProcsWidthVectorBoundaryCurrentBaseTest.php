<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsWidthVectorBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "500 500 d0\nBT /Fghost 9 Tf (wide vector charproc text leak) Tj ET\n";
    $thinCharProc = "125 125 0 0 250 700 d1\nBT /Fghost 9 Tf (thin vector charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.vector /i.vector /d.vector /e.vector '
        . '/B.vector /l.vector /o.vector /c.vector /k.vector 84 /T.thin /h.thin /i.thin '
        . '/n.thin /T.thin /e.thin /x.thin /t.thin] >>';
    $charProcs = '<< /W.vector 3 0 R /i.vector 3 0 R /d.vector 3 0 R /e.vector 3 0 R '
        . '/B.vector 3 0 R /l.vector 3 0 R /o.vector 3 0 R /c.vector 3 0 R /k.vector 3 0 R '
        . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
        . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3WidthVector /BaseFont /T3WidthVector "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0.001 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'transforms Type3 CharProc wx wy vectors through FontMatrix before WordPress text grouping on current base' => static function (TestRunner $t) use ($type3CharProcsWidthVectorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsWidthVectorBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'vector charproc text leak'));
    },
];
