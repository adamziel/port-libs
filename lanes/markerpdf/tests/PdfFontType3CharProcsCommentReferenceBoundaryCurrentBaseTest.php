<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsCommentReferenceBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide comment reference charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin comment reference charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.comment /i.comment /d.comment /e.comment '
        . '/B.comment /l.comment /o.comment /c.comment /k.comment 84 /T.thin /h.thin /i.thin '
        . '/n.thin /T.thin /e.thin /x.thin /t.thin] >>';
    $charProcs = "<< /W.comment 3 % object-number/generation split by a PDF comment\n 0 % generation/R split by a PDF comment\n R "
        . "/i.comment 3 0 R /d.comment 3 0 R /e.comment 3 0 R "
        . "/B.comment 3 0 R /l.comment 3 0 R /o.comment 3 0 R /c.comment 3 0 R /k.comment 3 0 R "
        . "/T.thin 4 % generation split by a PDF comment\n 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R "
        . "/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcCommentReference /BaseFont /T3CharProcCommentReference "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs 21 % top-level CharProcs object-number/generation split by PDF comment\n 0 % generation/R split by PDF comment\n R /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcCommentReference /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "21 0 obj\n{$charProcs}\nendobj\n%%EOF";
};

return [
    'treats PDF comments as whitespace inside Type3 CharProcs references before WordPress text grouping on current base' => static function (TestRunner $t) use ($type3CharProcsCommentReferenceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsCommentReferenceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'comment reference charproc text leak'));
        $t->true(!str_contains($plainText, 'top-level CharProcs reference'));
    },
];
