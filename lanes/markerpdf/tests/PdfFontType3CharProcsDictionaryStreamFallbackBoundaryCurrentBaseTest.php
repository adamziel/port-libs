<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDictionaryStreamFallbackBoundaryCurrentBasePdf = static function (): string {
    $glyphProgram = "650 0 d0\nBT /Fghost 9 Tf (GHOST GLYPH LEAK) Tj ET\n";
    $charProcsPayload = "BT /Fghost 9 Tf (CHARPROCS STREAM PAYLOAD LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $charProcsStreamDictionary = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
        . '/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R '
        . '/Length ' . strlen($charProcsPayload) . ' >>';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3InvalidStreamFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($glyphProgram) . " >>\nstream\n{$glyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "21 0 obj\n{$charProcsStreamDictionary}\nstream\n{$charProcsPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProcs stream dictionary glyph references from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDictionaryStreamFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDictionaryStreamFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'GHOST GLYPH LEAK'));
        $t->true(!str_contains($plainText, 'CHARPROCS STREAM PAYLOAD LEAK'));
        $t->true(!str_contains($plainText, 'T3InvalidStreamFallback'));
    },
];
