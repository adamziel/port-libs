<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsOpenScopeBoundaryCurrentBasePdf = static function (): string {
    $validScopedCharProc = "q 1 0 0 1 0 0 cm\n1000 0 d0\nQ\n"
        . "BT /Fghost 9 Tf (valid scoped charproc text leak) Tj ET\n";
    $unclosedGraphicsCharProc = "q 1 0 0 1 0 0 cm\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (unclosed graphics charproc text leak) Tj ET\n";
    $unclosedMarkedCharProc = "/Glyph BMC\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (unclosed marked-content charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 118 704 Tm <58595A> Tj '
        . 'T* 1 0 0 1 72 688 Tm <616263646566> Tj '
        . '1 0 0 1 121 688 Tm <676869> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.good /o.good /o.good /d.good '
        . '/W.good /i.good /d.good /e.good '
        . '84 /O.open /p.open /e.open /n.open /G.open /a.open /p.open '
        . '97 /M.marked /a.marked /r.marked /k.marked /e.marked /d.marked '
        . '/G.marked /a.marked /p.marked] >>';
    $charProcs = '<< /G.good 3 0 R /o.good 3 0 R /d.good 3 0 R '
        . '/W.good 3 0 R /i.good 3 0 R /e.good 3 0 R '
        . '/O.open 4 0 R /p.open 4 0 R /e.open 4 0 R /n.open 4 0 R '
        . '/G.open 4 0 R /a.open 4 0 R '
        . '/M.marked 5 0 R /a.marked 5 0 R /r.marked 5 0 R /k.marked 5 0 R '
        . '/e.marked 5 0 R /d.marked 5 0 R /G.marked 5 0 R /p.marked 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 42, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3OpenScopeBoundary /BaseFont /T3OpenScopeBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 106 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validScopedCharProc) . " >>\nstream\n{$validScopedCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($unclosedGraphicsCharProc) . " >>\nstream\n{$unclosedGraphicsCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($unclosedMarkedCharProc) . " >>\nstream\n{$unclosedMarkedCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3OpenScopeBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProc metrics inside unclosed scopes before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsOpenScopeBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsOpenScopeBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Open Gap', 'Marked Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Open', 'Gap', 'Marked', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nOpen Gap\nMarked Gap", $plainText);
        $t->same("GoodWide\nOpen Gap\nMarked Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Open Gap'));
        $t->true(str_contains($plainText, 'Marked Gap'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'OpenGap'));
        $t->true(!str_contains($plainText, 'MarkedGap'));
        $t->true(!str_contains($plainText, 'charproc text leak'));
    },
];
