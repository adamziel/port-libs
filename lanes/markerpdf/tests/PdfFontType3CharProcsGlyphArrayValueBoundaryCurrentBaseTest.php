<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsGlyphArrayValueBoundaryCurrentBasePdf = static function (): string {
    $arrayWrappedCharProc = "1000 0 d0\nBT /Fghost 9 Tf (array-wrapped glyph charproc text leak) Tj ET\n";
    $validWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid glyph-array boundary charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
        . '1 0 0 1 118 720 Tm <44454647> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /B.array /a.array /d.array '
        . '/P.array /a.array /t.array /h.array] >>';
    $charProcs = '<< /B.array [3 0 R] /a.array 4 0 R /d.array 4 0 R '
        . '/P.array 4 0 R /t.array 4 0 R /h.array 4 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 7, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsGlyphArrayValue /BaseFont /T3CharProcsGlyphArrayValue "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($arrayWrappedCharProc) . " >>\nstream\n{$arrayWrappedCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($validWideCharProc) . " >>\nstream\n{$validWideCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsGlyphArrayValue /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsGlyphArrayValueFallbackPdf = static function (): string {
    $arrayWrappedGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (ARRAY WRAPPED GLYPH VALUE LEAK) Tj ET\n";
    $validGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (VALID GLYPH VALUE LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $charProcs = '<< /A [3 0 R] /B 4 0 R /C 4 0 R /D 4 0 R '
        . '/G 4 0 R /H 4 0 R /I 4 0 R /L 4 0 R /N 4 0 R '
        . '/O 4 0 R /P 4 0 R /S 4 0 R /T 4 0 R /V 4 0 R >>';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3GlyphArrayValueFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($arrayWrappedGlyphProgram) . " >>\nstream\n{$arrayWrappedGlyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($validGlyphProgram) . " >>\nstream\n{$validGlyphProgram}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects array-valued Type3 CharProc glyph entries before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsGlyphArrayValueBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsGlyphArrayValueBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Bad Path'], $extractor->extractTextLines($pdf));
        $t->same(['Bad', 'Path'], $extractor->extractTextRuns($pdf));
        $t->same('Bad Path', $plainText);
        $t->same("Bad Path\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'BadPath'));
        $t->true(!str_contains($plainText, 'glyph-array boundary charproc text leak'));
        $t->true(!str_contains($plainText, 'array-wrapped glyph charproc text leak'));
    },
    'keeps array-valued Type3 CharProc glyph streams private during fallback extraction on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsGlyphArrayValueFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsGlyphArrayValueFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'ARRAY WRAPPED GLYPH VALUE LEAK'));
        $t->true(!str_contains($plainText, 'VALID GLYPH VALUE LEAK'));
        $t->true(!str_contains($plainText, 'T3GlyphArrayValueFallback'));
    },
];
