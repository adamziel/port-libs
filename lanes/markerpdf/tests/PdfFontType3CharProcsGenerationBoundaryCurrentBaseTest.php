<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontType3CharProcsGenerationBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide generation text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin generation text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcGeneration /BaseFont /T3CharProcGeneration /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding << /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide /B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >> "
        . "/CharProcs << /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R /B.wide 3 0 R /l.wide 3 0 R /o.wide 3 0 R /c.wide 3 0 R /k.wide 3 0 R /T.thin 3 1 R /h.thin 3 1 R /i.thin 3 1 R /n.thin 3 1 R /e.thin 3 1 R /x.thin 3 1 R /t.thin 3 1 R >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "3 1 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'keeps Type3 CharProc object generations separate before WordPress text grouping on current base' => static function (TestRunner $t) use ($fontType3CharProcsGenerationBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType3CharProcsGenerationBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'generation text leak'));
    },
];
