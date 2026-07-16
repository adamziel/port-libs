<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDirectDictionaryTailPagePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide direct CharProcs tail text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
        . '1 0 0 1 118 720 Tm <44454647> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /B.tail /a.tail /d.tail '
        . '/P.tail /a.tail /t.tail /h.tail] >>';
    $charProcs = '<< /B.tail 3 0 R /a.tail 3 0 R /d.tail 3 0 R '
        . '/P.tail 3 0 R /t.tail 3 0 R /h.tail 3 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 7, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsDirectTail /BaseFont /T3CharProcsDirectTail "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} 99 0 R /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsDirectTail /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsDirectDictionaryTailFallbackPdf = static function (): string {
    $glyphProgram = "650 0 d0\nBT /Fghost 9 Tf (DIRECT TAIL GLYPH LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $charProcs = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
        . '/G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /O 3 0 R /P 3 0 R /S 3 0 R /T 3 0 R '
        . '/V 3 0 R /Y 3 0 R >>';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3DirectTailFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs {$charProcs} 99 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($glyphProgram) . " >>\nstream\n{$glyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProcs direct dictionary tail operands before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDirectDictionaryTailPagePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDirectDictionaryTailPagePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Bad Path'], $extractor->extractTextLines($pdf));
        $t->same(['Bad', 'Path'], $extractor->extractTextRuns($pdf));
        $t->same('Bad Path', $plainText);
        $t->same("Bad Path\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'BadPath'));
        $t->true(!str_contains($plainText, 'direct CharProcs tail text leak'));
    },
    'excludes malformed Type3 CharProcs direct dictionary glyph references from fallback text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDirectDictionaryTailFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDirectDictionaryTailFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'DIRECT TAIL GLYPH LEAK'));
        $t->true(!str_contains($plainText, 'T3DirectTailFallback'));
    },
];
