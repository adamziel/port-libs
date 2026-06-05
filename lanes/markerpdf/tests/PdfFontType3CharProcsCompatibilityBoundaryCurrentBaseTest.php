<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsCompatibilityBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "BX /IgnoredName /IgnoredValue UnknownCompatibilityOperator EX\n"
        . "1000 0 d0\nBT /Fghost 9 Tf (wide compatibility charproc text leak) Tj ET\n";
    $thinCharProc = "BX /IgnoredName /IgnoredValue UnknownCompatibilityOperator EX\n"
        . "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin compatibility charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.compat /i.compat /d.compat /e.compat '
        . '/B.compat /l.compat /o.compat /c.compat /k.compat '
        . '84 /T.compatthin /h.compatthin /i.compatthin /n.compatthin '
        . '/T.compatthin /e.compatthin /x.compatthin /t.compatthin] >>';
    $charProcs = '<< /W.compat 3 0 R /i.compat 3 0 R /d.compat 3 0 R /e.compat 3 0 R '
        . '/B.compat 3 0 R /l.compat 3 0 R /o.compat 3 0 R /c.compat 3 0 R /k.compat 3 0 R '
        . '/T.compatthin 4 0 R /h.compatthin 4 0 R /i.compatthin 4 0 R /n.compatthin 4 0 R '
        . '/e.compatthin 4 0 R /x.compatthin 4 0 R /t.compatthin 4 0 R >>';
    $staleWidths = array_fill(0, 27, 250);
    foreach (range(19, 26) as $index) {
        $staleWidths[$index] = 1000;
    }
    $staleWidthsText = implode(' ', $staleWidths);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CompatibilityBoundary /BaseFont /T3CompatibilityBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 91 /Widths [{$staleWidthsText}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CompatibilityBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'ignores Type3 CharProc compatibility sections before metrics on current base' => static function (TestRunner $t) use ($type3CharProcsCompatibilityBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsCompatibilityBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'WideBlock'));
        $t->true(str_contains($plainText, 'Thin Text'));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'compatibility charproc text leak'));
        $t->true(!str_contains($plainText, 'UnknownCompatibilityOperator'));
    },
];
