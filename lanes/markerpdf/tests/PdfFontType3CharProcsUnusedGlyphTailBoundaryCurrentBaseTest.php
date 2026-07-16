<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsUnusedGlyphTailBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide unused-tail charproc text leak) Tj ET\n";
    $unusedCharProc = "250 0 d0\nBT /Fghost 9 Tf (UNUSED GLYPH TAIL LEAK) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.used /i.used /d.used /e.used '
        . '/B.used /l.used /o.used /c.used /k.used] >>';
    $charProcs = '<< /Unused.tail 4 0 R 99 0 R /W.used 3 0 R /i.used 3 0 R '
        . '/d.used 3 0 R /e.used 3 0 R /B.used 3 0 R /l.used 3 0 R '
        . '/o.used 3 0 R /c.used 3 0 R /k.used 3 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 9, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3UnusedGlyphTailBoundary "
        . "/BaseFont /T3UnusedGlyphTailBoundary /FontBBox [0 0 1000 700] "
        . "/FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 73 "
        . "/Widths [{$fallbackWidths}] /Encoding {$encoding} /CharProcs {$charProcs} "
        . "/FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($unusedCharProc) . " >>\nstream\n{$unusedCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3UnusedGlyphTailBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsUnusedGlyphTailFallbackPdf = static function (): string {
    $usedGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (USED GLYPH PROGRAM LEAK) Tj ET\n";
    $unusedGlyphProgram = "250 0 d0\nBT /Fghost 9 Tf (UNUSED GLYPH TAIL LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $charProcs = '<< /Unused.tail 4 0 R 99 0 R /A 3 0 R /B 3 0 R /C 3 0 R '
        . '/D 3 0 R /G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /N 3 0 R '
        . '/O 3 0 R /P 3 0 R /S 3 0 R /T 3 0 R /V 3 0 R >>';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3UnusedGlyphTailFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($usedGlyphProgram) . " >>\nstream\n{$usedGlyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($unusedGlyphProgram) . " >>\nstream\n{$unusedGlyphProgram}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";
};

return [
    'keeps valid Type3 CharProc widths when an unused glyph entry has a malformed tail on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsUnusedGlyphTailBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsUnusedGlyphTailBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('WideBlock', $plainText);
        $t->same("WideBlock\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'unused-tail charproc text leak'));
        $t->true(!str_contains($plainText, 'UNUSED GLYPH TAIL LEAK'));
    },
    'keeps unused malformed Type3 CharProc glyph streams private during fallback extraction' => static function (
        TestRunner $t
    ) use ($type3CharProcsUnusedGlyphTailFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsUnusedGlyphTailFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'USED GLYPH PROGRAM LEAK'));
        $t->true(!str_contains($plainText, 'UNUSED GLYPH TAIL LEAK'));
        $t->true(!str_contains($plainText, 'T3UnusedGlyphTailFallback'));
    },
];
