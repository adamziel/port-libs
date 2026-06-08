<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsPostMetricTextScopeBoundaryCurrentBasePdf = static function (): string {
    $validCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid post-metric text scope charproc text leak) Tj ET\n";
    $unmatchedEndTextCharProc = "1000 0 d0\nET\n"
        . "BT /Fghost 9 Tf (unmatched ET post-metric charproc text leak) Tj ET\n";
    $nestedBeginTextCharProc = "1000 0 d0\nBT BT ET\n"
        . "BT /Fghost 9 Tf (nested BT post-metric charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <5455565758> Tj '
        . '1 0 0 1 118 704 Tm <595A5B> Tj '
        . 'T* 1 0 0 1 72 688 Tm <61626364> Tj '
        . '1 0 0 1 118 688 Tm <656667> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
        . '/W.valid /i.valid /d.valid /e.valid '
        . '84 /S.endtext /c.endtext /o.endtext /p.endtext /e.endtext '
        . '/G.endtext /a.endtext /p.endtext '
        . '97 /N.nested /e.nested /s.nested /t.nested /G.nested /a.nested /p.nested] >>';
    $charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
        . '/W.valid 3 0 R /i.valid 3 0 R /e.valid 3 0 R '
        . '/S.endtext 4 0 R /c.endtext 4 0 R /o.endtext 4 0 R '
        . '/p.endtext 4 0 R /e.endtext 4 0 R /G.endtext 4 0 R '
        . '/a.endtext 4 0 R '
        . '/N.nested 5 0 R /e.nested 5 0 R /s.nested 5 0 R '
        . '/t.nested 5 0 R /G.nested 5 0 R /a.nested 5 0 R /p.nested 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 39, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PostMetricTextScopeBoundary /BaseFont /T3PostMetricTextScopeBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 103 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($unmatchedEndTextCharProc) . " >>\nstream\n{$unmatchedEndTextCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($nestedBeginTextCharProc) . " >>\nstream\n{$nestedBeginTextCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3PostMetricTextScopeBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects malformed post-metric Type3 CharProc text scopes before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsPostMetricTextScopeBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsPostMetricTextScopeBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Scope Gap', 'Nest Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Scope', 'Gap', 'Nest', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nScope Gap\nNest Gap", $plainText);
        $t->same("GoodWide\nScope Gap\nNest Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Scope Gap'));
        $t->true(str_contains($plainText, 'Nest Gap'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'ScopeGap'));
        $t->true(!str_contains($plainText, 'NestGap'));
        $t->true(!str_contains($plainText, 'post-metric text scope charproc text leak'));
        $t->true(!str_contains($plainText, 'unmatched ET post-metric charproc text leak'));
        $t->true(!str_contains($plainText, 'nested BT post-metric charproc text leak'));
    },
];
