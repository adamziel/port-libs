<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsNonStreamObjectBoundaryCurrentBasePdf = static function (): string {
    $validWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid non-stream boundary charproc text leak) Tj ET\n";
    $malformedPlainCharProc = "1000 0 d0\nBT /Fghost 9 Tf (plain object charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <5455565758> Tj '
        . '1 0 0 1 118 704 Tm <595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.stream /o.stream /o.stream /d.stream '
        . '/W.stream /i.stream /d.stream /e.stream '
        . '84 /P.plain /l.plain /a.plain /i.plain /n.plain /G.plain /a.plain /p.plain] >>';
    $charProcs = '<< /G.stream 3 0 R /o.stream 3 0 R /d.stream 3 0 R '
        . '/W.stream 3 0 R /i.stream 3 0 R /e.stream 3 0 R '
        . '/P.plain 4 0 R /l.plain 4 0 R /a.plain 4 0 R /i.plain 4 0 R '
        . '/n.plain 4 0 R /G.plain 4 0 R /p.plain 4 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 32, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3NonStreamBoundary /BaseFont /T3NonStreamBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 91 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validWideCharProc) . " >>\nstream\n{$validWideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n{$malformedPlainCharProc}\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3NonStreamBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'requires Type3 CharProc entries to be stream objects before WordPress width grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsNonStreamObjectBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsNonStreamObjectBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Plain Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Plain', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nPlain Gap", $plainText);
        $t->same("GoodWide\nPlain Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Plain Gap'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'PlainGap'));
        $t->true(!str_contains($plainText, 'charproc text leak'));
    },
];
