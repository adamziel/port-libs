<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsPatternColorOperandBoundaryCurrentBasePdf = static function (): string {
    $validPatternColorCharProc = "/Pattern cs 0.25 0.75 /GlyphPattern scn\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (valid pattern color charproc text leak) Tj ET\n";
    $malformedPatternColorCharProc = "/Pattern cs /GlyphPattern 0.25 scn\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (malformed pattern color charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
        . '1 0 0 1 109 704 Tm <5758595A> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /G.validpattern /o.validpattern /o.validpattern /d.validpattern '
        . '/P.validpattern /a.validpattern /t.validpattern /h.validpattern '
        . '84 /B.badpattern /a.badpattern /d.badpattern /J.badpattern /o.badpattern /i.badpattern /n.badpattern] >>';
    $charProcs = '<< /G.validpattern 3 0 R /o.validpattern 3 0 R /d.validpattern 3 0 R '
        . '/P.validpattern 3 0 R /a.validpattern 3 0 R /t.validpattern 3 0 R /h.validpattern 3 0 R '
        . '/B.badpattern 4 0 R /a.badpattern 4 0 R /d.badpattern 4 0 R '
        . '/J.badpattern 4 0 R /o.badpattern 4 0 R /i.badpattern 4 0 R /n.badpattern 4 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 26, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PatternColorOperandBoundary /BaseFont /T3PatternColorOperandBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 90 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R "
        . "/Resources << /Pattern << /GlyphPattern 30 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validPatternColorCharProc) . " >>\nstream\n{$validPatternColorCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($malformedPatternColorCharProc) . " >>\nstream\n{$malformedPatternColorCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3PatternColorOperandBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "30 0 obj\n<< /PatternType 1 /PaintType 2 /TilingType 1 /BBox [0 0 8 8] /XStep 8 /YStep 8 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProc pattern color names before numeric components on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsPatternColorOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsPatternColorOperandBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['GoodPath', 'Bad Join'], $extractor->extractTextLines($pdf));
        $t->same(['Good', 'Path', 'Bad', 'Join'], $extractor->extractTextRuns($pdf));
        $t->same("GoodPath\nBad Join", $plainText);
        $t->same("GoodPath\nBad Join\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'GoodPath'));
        $t->true(str_contains($plainText, 'Bad Join'));
        $t->true(!str_contains($plainText, 'Good Path'));
        $t->true(!str_contains($plainText, 'BadJoin'));
        $t->true(!str_contains($plainText, 'pattern color charproc text leak'));
        $t->true(!str_contains($plainText, 'GlyphPattern'));
    },
];
