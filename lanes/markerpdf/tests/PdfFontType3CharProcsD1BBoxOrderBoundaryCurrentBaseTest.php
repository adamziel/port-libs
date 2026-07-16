<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsD1BBoxOrderBoundaryCurrentBasePdf = static function (): string {
    $validThinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (valid d1 bbox order charproc text leak) Tj ET\n";
    $invertedXCharProc = "1000 0 300 0 100 700 d1\nBT /Fghost 9 Tf (inverted x bbox charproc text leak) Tj ET\n";
    $invertedYCharProc = "1000 0 0 700 1000 100 d1\nBT /Fghost 9 Tf (inverted y bbox charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 96 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 118 704 Tm <58595A> Tj '
        . 'T* 1 0 0 1 72 688 Tm <6162636465> Tj '
        . '1 0 0 1 118 688 Tm <666768> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /T.valid /h.valid /i.valid /n.valid '
        . '/T.valid /e.valid /x.valid /t.valid '
        . '84 /F.xflip /l.xflip /i.xflip /p.xflip /G.xflip /a.xflip /p.xflip '
        . '97 /Y.yflip /f.yflip /l.yflip /i.yflip /p.yflip /G.yflip /a.yflip /p.yflip] >>';
    $charProcs = '<< /T.valid 3 0 R /h.valid 3 0 R /i.valid 3 0 R /n.valid 3 0 R '
        . '/e.valid 3 0 R /x.valid 3 0 R /t.valid 3 0 R '
        . '/F.xflip 4 0 R /l.xflip 4 0 R /i.xflip 4 0 R /p.xflip 4 0 R '
        . '/G.xflip 4 0 R /a.xflip 4 0 R '
        . '/Y.yflip 5 0 R /f.yflip 5 0 R /l.yflip 5 0 R /i.yflip 5 0 R '
        . '/p.yflip 5 0 R /G.yflip 5 0 R /a.yflip 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 40, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3D1BBoxOrderBoundary /BaseFont /T3D1BBoxOrderBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 104 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validThinCharProc) . " >>\nstream\n{$validThinCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($invertedXCharProc) . " >>\nstream\n{$invertedXCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($invertedYCharProc) . " >>\nstream\n{$invertedYCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3D1BBoxOrderBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects inverted Type3 CharProc d1 bbox order before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsD1BBoxOrderBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsD1BBoxOrderBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Thin Text', 'Flip Gap', 'Yflip Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Thin', 'Text', 'Flip', 'Gap', 'Yflip', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("Thin Text\nFlip Gap\nYflip Gap", $plainText);
        $t->same("Thin Text\nFlip Gap\nYflip Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'Thin Text'));
        $t->true(str_contains($plainText, 'Flip Gap'));
        $t->true(str_contains($plainText, 'Yflip Gap'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'FlipGap'));
        $t->true(!str_contains($plainText, 'YflipGap'));
        $t->true(!str_contains($plainText, 'bbox order charproc text leak'));
        $t->true(!str_contains($plainText, 'inverted x bbox charproc text leak'));
        $t->true(!str_contains($plainText, 'inverted y bbox charproc text leak'));
    },
];
