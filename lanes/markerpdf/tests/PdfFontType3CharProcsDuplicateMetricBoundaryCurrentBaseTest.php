<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDuplicateMetricBoundaryCurrentBasePdf = static function (): string {
    $validWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid duplicate-metric boundary charproc text leak) Tj ET\n";
    $duplicateD0CharProc = "1000 0 d0\n250 0 d0\nBT /Fghost 9 Tf (duplicate d0 charproc text leak) Tj ET\n";
    $duplicateD1CharProc = "250 0 0 0 250 700 d1\n1000 0 0 0 1000 700 d1\nBT /Fghost 9 Tf (duplicate d1 charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
        . '1 0 0 1 118 704 Tm <575859> Tj '
        . 'T* 1 0 0 1 72 688 Tm <61626364> Tj '
        . '1 0 0 1 96 688 Tm <65666768> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
        . '/W.valid /i.valid /d.valid /e.valid '
        . '84 /B.dupd0 /a.dupd0 /d.dupd0 /G.dupd0 /a.dupd0 /p.dupd0 '
        . '97 /T.dupd1 /h.dupd1 /i.dupd1 /n.dupd1 /T.dupd1 /e.dupd1 /x.dupd1 /t.dupd1] >>';
    $charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
        . '/W.valid 3 0 R /i.valid 3 0 R /e.valid 3 0 R '
        . '/B.dupd0 4 0 R /a.dupd0 4 0 R /d.dupd0 4 0 R '
        . '/G.dupd0 4 0 R /p.dupd0 4 0 R '
        . '/T.dupd1 5 0 R /h.dupd1 5 0 R /i.dupd1 5 0 R /n.dupd1 5 0 R '
        . '/e.dupd1 5 0 R /x.dupd1 5 0 R /t.dupd1 5 0 R >>';
    $fallbackWidths = array_fill(0, 40, 250);
    foreach (range(32, 39) as $index) {
        $fallbackWidths[$index] = 1000;
    }
    $fallbackWidthsText = implode(' ', $fallbackWidths);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3DuplicateMetricBoundary /BaseFont /T3DuplicateMetricBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 104 /Widths [{$fallbackWidthsText}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validWideCharProc) . " >>\nstream\n{$validWideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($duplicateD0CharProc) . " >>\nstream\n{$duplicateD0CharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($duplicateD1CharProc) . " >>\nstream\n{$duplicateD1CharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3DuplicateMetricBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsDuplicateMetricFallbackPdf = static function (): string {
    $duplicateMetricGlyphProgram = "1000 0 d0\n250 0 d0\nBT /Fghost 9 Tf (DUPLICATE METRIC GLYPH LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $charProcs = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
        . '/E 3 0 R /G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /M 3 0 R '
        . '/N 3 0 R /O 3 0 R /P 3 0 R /S 3 0 R /T 3 0 R /V 3 0 R >>';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3DuplicateMetricFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($duplicateMetricGlyphProgram) . " >>\nstream\n{$duplicateMetricGlyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects duplicate Type3 CharProc d0 and d1 metric operators before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateMetricBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateMetricBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodWide', 'Bad Gap', 'ThinText'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Wide', 'Bad', 'Gap', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("GoodWide\nBad Gap\nThinText", $plainText);
        $t->same("GoodWide\nBad Gap\nThinText\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodWide'));
        $t->true(str_contains($plainText, 'Bad Gap'));
        $t->true(str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'Good Wide'));
        $t->true(!str_contains($plainText, 'BadGap'));
        $t->true(!str_contains($plainText, 'Thin Text'));
        $t->true(!str_contains($plainText, 'duplicate-metric boundary charproc text leak'));
        $t->true(!str_contains($plainText, 'duplicate d0 charproc text leak'));
        $t->true(!str_contains($plainText, 'duplicate d1 charproc text leak'));
    },
    'keeps duplicate-metric Type3 CharProc streams private during fallback extraction on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateMetricFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateMetricFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'DUPLICATE METRIC GLYPH LEAK'));
        $t->true(!str_contains($plainText, 'T3DuplicateMetricFallback'));
    },
];
