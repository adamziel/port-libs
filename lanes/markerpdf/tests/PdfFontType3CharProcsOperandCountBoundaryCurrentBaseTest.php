<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsOperandCountBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid operand-count charproc text leak) Tj ET\n";
    $malformedD0CharProc = "999 1000 0 d0\nBT /Fghost 9 Tf (malformed d0 operand-count charproc text leak) Tj ET\n";
    $malformedD1CharProc = "999 250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (malformed d1 operand-count charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 118 704 Tm <58595A> Tj '
        . 'T* 1 0 0 1 72 688 Tm <61626364> Tj '
        . '1 0 0 1 118 688 Tm <65666768> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.valid /i.valid /d.valid /e.valid '
        . '/B.valid /l.valid /o.valid /c.valid /k.valid '
        . '84 /L.extra0 /a.extra0 /t.extra0 /e.extra0 /G.extra0 /a.extra0 /p.extra0 '
        . '97 /T.extra1 /h.extra1 /i.extra1 /n.extra1 /J.extra1 /o.extra1 /i.extra1 /n.extra1] >>';
    $charProcs = '<< /W.valid 3 0 R /i.valid 3 0 R /d.valid 3 0 R /e.valid 3 0 R '
        . '/B.valid 3 0 R /l.valid 3 0 R /o.valid 3 0 R /c.valid 3 0 R /k.valid 3 0 R '
        . '/L.extra0 4 0 R /a.extra0 4 0 R /t.extra0 4 0 R /e.extra0 4 0 R '
        . '/G.extra0 4 0 R /p.extra0 4 0 R '
        . '/T.extra1 5 0 R /h.extra1 5 0 R /i.extra1 5 0 R /n.extra1 5 0 R '
        . '/J.extra1 5 0 R /o.extra1 5 0 R >>';
    $widthValues = array_fill(0, 40, 250);
    foreach (range(32, 39) as $index) {
        $widthValues[$index] = 1000;
    }
    $fallbackWidths = implode(' ', $widthValues);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3OperandCountBoundary /BaseFont /T3OperandCountBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 104 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($malformedD0CharProc) . " >>\nstream\n{$malformedD0CharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($malformedD1CharProc) . " >>\nstream\n{$malformedD1CharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3OperandCountBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProc d0 d1 metrics with extra operands before WordPress grouping on current base' => static function (TestRunner $t) use ($type3CharProcsOperandCountBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsOperandCountBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Late Gap', 'ThinJoin'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Late', 'Gap', 'Thin', 'Join'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nLate Gap\nThinJoin", $plainText);
        $t->same("WideBlock\nLate Gap\nThinJoin\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'LateGap'));
        $t->true(!str_contains($plainText, 'Thin Join'));
        $t->true(!str_contains($plainText, 'operand-count charproc text leak'));
    },
];
