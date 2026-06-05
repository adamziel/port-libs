<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsEncodingGenerationBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide encoding generation charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin encoding generation charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $currentEncoding = '<< /Type /Encoding /Differences [65 /W.current /i.current /d.current /e.current '
        . '/B.current /l.current /o.current /c.current /k.current 84 /T.thin /h.thin /i.thin '
        . '/n.thin /T.thin /e.thin /x.thin /t.thin] >>';
    $staleEncoding = '<< /Type /Encoding /Differences [65 /A.stale /B.stale /C.stale /D.stale '
        . '/E.stale /F.stale /G.stale /H.stale /I.stale 84 /T.stale /U.stale /V.stale '
        . '/W.stale /X.stale /Y.stale /Z.stale /bracketleft.stale] >>';
    $charProcs = '<< /W.current 3 0 R /i.current 3 0 R /d.current 3 0 R /e.current 3 0 R '
        . '/B.current 3 0 R /l.current 3 0 R /o.current 3 0 R /c.current 3 0 R /k.current 3 0 R '
        . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
        . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3EncodingGeneration /BaseFont /T3EncodingGeneration "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding 21 % selected encoding generation split by PDF comment\n 0 R "
        . "/CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3EncodingGeneration /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "21 0 obj\n{$currentEncoding}\nendobj\n"
        . "21 1 obj\n{$staleEncoding}\nendobj\n%%EOF";
};

return [
    'uses the exact comment-split Type3 Encoding generation before CharProc glyph mapping on current base' => static function (TestRunner $t) use ($type3CharProcsEncodingGenerationBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsEncodingGenerationBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'ABC'));
        $t->true(!str_contains($plainText, 'encoding generation charproc text leak'));
    },
];
