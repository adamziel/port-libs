<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsResourceCategoryStreamBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\n/StreamCategoryPaint Do\n"
        . "BT /Fghost 9 Tf (TYPE3 CHARPROC STREAM LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $fontCategoryPayload = 'BT /Fghost 9 Tf 10 10 Td (TYPE3 FONT XOBJECT CATEGORY STREAM LEAK) Tj ET';
    $fontNestedPayload = 'BT /Fghost 9 Tf 10 10 Td (TYPE3 FONT NESTED XOBJECT STREAM LEAK) Tj ET';
    $glyphCategoryPayload = 'BT /Fghost 9 Tf 10 10 Td (TYPE3 GLYPH XOBJECT CATEGORY STREAM LEAK) Tj ET';
    $glyphNestedPayload = 'BT /Fghost 9 Tf 10 10 Td (TYPE3 GLYPH NESTED XOBJECT STREAM LEAK) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3CategoryStreamBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /XObject 8 0 R /Font << /Fghost 1 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Resources << /XObject 10 0 R /Font << /Fghost 1 0 R >> >> "
        . "/Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "8 0 obj\n<< /NestedFontPaint 9 0 R /Length " . strlen($fontCategoryPayload) . " >>\n"
        . "stream\n{$fontCategoryPayload}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> "
        . "/Length " . strlen($fontNestedPayload) . " >>\nstream\n{$fontNestedPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /NestedGlyphPaint 11 0 R /Length " . strlen($glyphCategoryPayload) . " >>\n"
        . "stream\n{$glyphCategoryPayload}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> "
        . "/Length " . strlen($glyphNestedPayload) . " >>\nstream\n{$glyphNestedPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes stream-valued Type3 CharProc resource categories from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsResourceCategoryStreamBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsResourceCategoryStreamBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'TYPE3 CHARPROC STREAM LEAK'));
        $t->true(!str_contains($plainText, 'TYPE3 FONT XOBJECT CATEGORY STREAM LEAK'));
        $t->true(!str_contains($plainText, 'TYPE3 FONT NESTED XOBJECT STREAM LEAK'));
        $t->true(!str_contains($plainText, 'TYPE3 GLYPH XOBJECT CATEGORY STREAM LEAK'));
        $t->true(!str_contains($plainText, 'TYPE3 GLYPH NESTED XOBJECT STREAM LEAK'));
        $t->true(!str_contains($plainText, 'T3CategoryStreamBoundary'));
        $t->true(!str_contains($plainText, 'NestedFontPaint'));
        $t->true(!str_contains($plainText, 'NestedGlyphPaint'));
    },
];
