<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsPostMetricScopeBoundaryCurrentBasePdf = static function (): string {
    $validPostMetricCharProc = "1000 0 d0\nq 1 0 0 1 0 0 cm 0 0 m 1000 0 l h Q\n"
        . "BT /Fghost 9 Tf (valid post-metric scope charproc text leak) Tj ET\n";
    $unmatchedRestoreCharProc = "1000 0 d0\nQ\n"
        . "BT /Fghost 9 Tf (post-metric unmatched Q charproc text leak) Tj ET\n";
    $unmatchedMarkedContentCharProc = "1000 0 d0\n/Glyph BMC EMC EMC\n"
        . "BT /Fghost 9 Tf (post-metric unmatched EMC charproc text leak) Tj ET\n";
    $unmatchedCompatibilityCharProc = "1000 0 d0\nBX /IgnoredName /IgnoredValue UnknownCompatibilityOperator EX EX\n"
        . "BT /Fghost 9 Tf (post-metric unmatched EX charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 118 704 Tm <58595A> Tj '
        . 'T* 1 0 0 1 72 688 Tm <616263646566> Tj '
        . '1 0 0 1 121 688 Tm <676869> Tj '
        . 'T* 1 0 0 1 72 672 Tm <6A6B6C6D6E6F> Tj '
        . '1 0 0 1 121 672 Tm <707172> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
        . '/W.valid /i.valid /d.valid /e.valid /R.valid '
        . '84 /R.restore /e.restore /s.restore /t.restore /G.restore /a.restore /p.restore '
        . '97 /M.marked /a.marked /r.marked /k.marked /e.marked /d.marked '
        . '/G.marked /a.marked /p.marked '
        . '106 /C.compat /o.compat /m.compat /p.compat /a.compat /t.compat '
        . '/G.compat /a.compat /p.compat] >>';
    $charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
        . '/W.valid 3 0 R /i.valid 3 0 R /e.valid 3 0 R /R.valid 3 0 R '
        . '/R.restore 4 0 R /e.restore 4 0 R /s.restore 4 0 R /t.restore 4 0 R '
        . '/G.restore 4 0 R /a.restore 4 0 R /p.restore 4 0 R '
        . '/M.marked 5 0 R /a.marked 5 0 R /r.marked 5 0 R /k.marked 5 0 R '
        . '/e.marked 5 0 R /d.marked 5 0 R /G.marked 5 0 R /p.marked 5 0 R '
        . '/C.compat 6 0 R /o.compat 6 0 R /m.compat 6 0 R /p.compat 6 0 R '
        . '/a.compat 6 0 R /t.compat 6 0 R /G.compat 6 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 50, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PostMetricScopeBoundary /BaseFont /T3PostMetricScopeBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 114 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 7 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validPostMetricCharProc) . " >>\nstream\n{$validPostMetricCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($unmatchedRestoreCharProc) . " >>\nstream\n{$unmatchedRestoreCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($unmatchedMarkedContentCharProc) . " >>\nstream\n{$unmatchedMarkedContentCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($unmatchedCompatibilityCharProc) . " >>\nstream\n{$unmatchedCompatibilityCharProc}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /FontDescriptor /FontName /T3PostMetricScopeBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects unbalanced post-metric Type3 CharProc scopes before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsPostMetricScopeBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsPostMetricScopeBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWideR', 'Rest Gap', 'Marked Gap', 'Compat Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'WideR', 'Rest', 'Gap', 'Marked', 'Gap', 'Compat', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWideR\nRest Gap\nMarked Gap\nCompat Gap", $plainText);
        $t->same("GoodWideR\nRest Gap\nMarked Gap\nCompat Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWideR'));
        $t->true(str_contains($plainText, 'Rest Gap'));
        $t->true(str_contains($plainText, 'Marked Gap'));
        $t->true(str_contains($plainText, 'Compat Gap'));
        $t->true(!str_contains($plainText, 'Good Wide R'));
        $t->true(!str_contains($plainText, 'RestGap'));
        $t->true(!str_contains($plainText, 'MarkedGap'));
        $t->true(!str_contains($plainText, 'CompatGap'));
        $t->true(!str_contains($plainText, 'post-metric scope charproc text leak'));
        $t->true(!str_contains($plainText, 'post-metric unmatched Q charproc text leak'));
        $t->true(!str_contains($plainText, 'post-metric unmatched EMC charproc text leak'));
        $t->true(!str_contains($plainText, 'post-metric unmatched EX charproc text leak'));
    },
];
