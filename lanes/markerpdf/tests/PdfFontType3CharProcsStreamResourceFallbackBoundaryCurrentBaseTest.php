<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsStreamResourceFallbackBoundaryCurrentBasePdf = static function (): string {
    $glyphProgram = "650 0 d0\nBT /Fghost 9 Tf (INVALID STREAM GLYPH PAYLOAD LEAK) Tj ET\n";
    $charProcsPayload = "BT /Fghost 9 Tf (INVALID CHARPROCS STREAM PAYLOAD LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $privateFormPayload = 'BT /Fghost 9 Tf 0 0 Td (INVALID CHARPROCS RESOURCE FORM LEAK) Tj ET';
    $privateNestedPayload = 'BT /Fghost 9 Tf 0 0 Td (INVALID CHARPROCS NESTED RESOURCE LEAK) Tj ET';
    $charProcsStreamDictionary = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
        . '/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R '
        . '/Resources << /XObject << /InvalidGlyphResource 6 0 R >> /Font << /Fghost 1 0 R >> >> '
        . '/Length ' . strlen($charProcsPayload) . ' >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3InvalidStreamResourceFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($glyphProgram) . " >>\nstream\n{$glyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] "
        . "/Resources << /XObject << /NestedInvalidGlyphResource 7 0 R >> /Font << /Fghost 1 0 R >> >> "
        . "/Length " . strlen($privateFormPayload) . " >>\nstream\n{$privateFormPayload}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] "
        . "/Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($privateNestedPayload)
        . " >>\nstream\n{$privateNestedPayload}\nendstream\nendobj\n"
        . "21 0 obj\n{$charProcsStreamDictionary}\nstream\n{$charProcsPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes malformed Type3 CharProcs stream resources from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsStreamResourceFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsStreamResourceFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'INVALID STREAM GLYPH PAYLOAD LEAK'));
        $t->true(!str_contains($plainText, 'INVALID CHARPROCS STREAM PAYLOAD LEAK'));
        $t->true(!str_contains($plainText, 'INVALID CHARPROCS RESOURCE FORM LEAK'));
        $t->true(!str_contains($plainText, 'INVALID CHARPROCS NESTED RESOURCE LEAK'));
        $t->true(!str_contains($plainText, 'InvalidGlyphResource'));
        $t->true(!str_contains($plainText, 'NestedInvalidGlyphResource'));
        $t->true(!str_contains($plainText, 'T3InvalidStreamResourceFallback'));
    },
];
