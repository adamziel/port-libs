<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsCompatibilityMetricBoundaryCurrentBasePdf = static function (): string {
    $validWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid compatibility metric charproc text leak) Tj ET\n";
    $compatibilityDuplicateD0CharProc = "1000 0 d0\nBX 250 0 d0 EX\n"
        . "BT /Fghost 9 Tf (compatibility duplicate d0 charproc text leak) Tj ET\n";
    $compatibilityDuplicateD1CharProc = "250 0 0 0 250 700 d1\nBX 1000 0 0 0 1000 700 d1 EX\n"
        . "BT /Fghost 9 Tf (compatibility duplicate d1 charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556575859> Tj '
        . '1 0 0 1 118 704 Tm <5A5B5C> Tj '
        . 'T* 1 0 0 1 72 688 Tm <61626364> Tj '
        . '1 0 0 1 96 688 Tm <65666768> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
        . '/W.valid /i.valid /d.valid /e.valid '
        . '84 /C.compat /o.compat /m.compat /p.compat /a.compat /t.compat '
        . '/G.compat /a.compat /p.compat '
        . '97 /T.compatd1 /h.compatd1 /i.compatd1 /n.compatd1 '
        . '/T.compatd1 /e.compatd1 /x.compatd1 /t.compatd1] >>';
    $charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
        . '/W.valid 3 0 R /i.valid 3 0 R /e.valid 3 0 R '
        . '/C.compat 4 0 R /o.compat 4 0 R /m.compat 4 0 R '
        . '/p.compat 4 0 R /a.compat 4 0 R /t.compat 4 0 R '
        . '/G.compat 4 0 R '
        . '/T.compatd1 5 0 R /h.compatd1 5 0 R /i.compatd1 5 0 R '
        . '/n.compatd1 5 0 R /e.compatd1 5 0 R /x.compatd1 5 0 R /t.compatd1 5 0 R >>';
    $fallbackWidths = array_fill(0, 40, 250);
    foreach (range(32, 39) as $index) {
        $fallbackWidths[$index] = 1000;
    }
    $fallbackWidthsText = implode(' ', $fallbackWidths);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CompatibilityMetricBoundary "
        . "/BaseFont /T3CompatibilityMetricBoundary /FontBBox [0 0 1000 700] "
        . "/FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 104 "
        . "/Widths [{$fallbackWidthsText}] /Encoding {$encoding} /CharProcs {$charProcs} "
        . "/FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validWideCharProc) . " >>\nstream\n{$validWideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($compatibilityDuplicateD0CharProc) . " >>\nstream\n{$compatibilityDuplicateD0CharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($compatibilityDuplicateD1CharProc) . " >>\nstream\n{$compatibilityDuplicateD1CharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CompatibilityMetricBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProc duplicate metrics hidden inside compatibility sections on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsCompatibilityMetricBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsCompatibilityMetricBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Compat Gap', 'ThinText'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Compat', 'Gap', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nCompat Gap\nThinText", $plainText);
        $t->same("GoodWide\nCompat Gap\nThinText\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Compat Gap'));
        $t->true(str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'CompatGap'));
        $t->true(!str_contains($plainText, 'Thin Text'));
        $t->true(!str_contains($plainText, 'compatibility metric charproc text leak'));
    },
];
