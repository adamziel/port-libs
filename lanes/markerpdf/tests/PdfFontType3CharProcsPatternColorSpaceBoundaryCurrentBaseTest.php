<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsPatternColorSpaceBoundaryCurrentBasePdf = static function (): string {
    $validPatternCharProc = "/Pattern cs /GlyphPattern scn\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (valid pattern color-space charproc text leak) Tj ET\n";
    $deviceRgbPatternNameCharProc = "/DeviceRGB cs /GlyphPattern scn\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (DeviceRGB pattern-name charproc text leak) Tj ET\n";
    $defaultPatternNameCharProc = "/GlyphPattern scn\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (default color-space pattern-name charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
        . '1 0 0 1 109 704 Tm <575859> Tj '
        . 'T* 1 0 0 1 72 688 Tm <616263> Tj '
        . '1 0 0 1 109 688 Tm <646566> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.pattern /i.pattern /d.pattern /e.pattern '
        . '/B.pattern /l.pattern /o.pattern /c.pattern /k.pattern '
        . '84 /B.device /a.device /d.device /G.device /a.device /p.device '
        . '97 /R.default /a.default /w.default /G.default /a.default /p.default] >>';
    $charProcs = '<< /W.pattern 3 0 R /i.pattern 3 0 R /d.pattern 3 0 R /e.pattern 3 0 R '
        . '/B.pattern 3 0 R /l.pattern 3 0 R /o.pattern 3 0 R /c.pattern 3 0 R /k.pattern 3 0 R '
        . '/B.device 4 0 R /a.device 4 0 R /d.device 4 0 R '
        . '/G.device 4 0 R /p.device 4 0 R '
        . '/R.default 5 0 R /a.default 5 0 R /w.default 5 0 R '
        . '/G.default 5 0 R /p.default 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 38, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PatternColorSpaceBoundary /BaseFont /T3PatternColorSpaceBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 102 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validPatternCharProc) . " >>\nstream\n{$validPatternCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($deviceRgbPatternNameCharProc) . " >>\nstream\n{$deviceRgbPatternNameCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($defaultPatternNameCharProc) . " >>\nstream\n{$defaultPatternNameCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3PatternColorSpaceBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'requires active Pattern color space before Type3 CharProc pattern-name scn metrics on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsPatternColorSpaceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsPatternColorSpaceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Bad Gap', 'Raw Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Bad', 'Gap', 'Raw', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nBad Gap\nRaw Gap", $plainText);
        $t->same("WideBlock\nBad Gap\nRaw Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'WideBlock'));
        $t->true(str_contains($plainText, 'Bad Gap'));
        $t->true(str_contains($plainText, 'Raw Gap'));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'BadGap'));
        $t->true(!str_contains($plainText, 'RawGap'));
        $t->true(!str_contains($plainText, 'pattern color-space charproc text leak'));
        $t->true(!str_contains($plainText, 'DeviceRGB pattern-name charproc text leak'));
        $t->true(!str_contains($plainText, 'default color-space pattern-name charproc text leak'));
    },
];
