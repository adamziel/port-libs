<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsFontResourceFallbackBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\n/GlyphStreamFont 9 Tf\n"
        . "BT /GlyphStreamFont 9 Tf (direct Type3 font-resource charproc text leak) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $fontProgramPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 font resource FontFile text leak) Tj ET';
    $streamFontProgramPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 stream font resource FontFile text leak) Tj ET';
    $streamFontCidSetPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 stream font resource CIDSet text leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3FontResourceFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /Font << /GlyphFont 30 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Resources << /Font << /GlyphStreamFont 40 0 R >> >> "
        . "/Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /GlyphPrivate /FontDescriptor 31 0 R >>\nendobj\n"
        . "31 0 obj\n<< /Type /FontDescriptor /FontName /GlyphPrivate /Flags 4 /FontFile2 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($fontProgramPayload) . " >>\nstream\n{$fontProgramPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /GlyphStreamPrivate "
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 41 0 R >>\nendobj\n"
        . "41 0 obj\n<< /Type /FontDescriptor /FontName /GlyphStreamPrivate /Flags 4 "
        . "/FontFile2 42 0 R /CIDSet 43 0 R >>\nendobj\n"
        . "42 0 obj\n<< /Length " . strlen($streamFontProgramPayload) . " >>\nstream\n{$streamFontProgramPayload}\nendstream\nendobj\n"
        . "43 0 obj\n<< /Length " . strlen($streamFontCidSetPayload) . " >>\nstream\n{$streamFontCidSetPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProc Font resource streams from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsFontResourceFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsFontResourceFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'direct Type3 font-resource charproc text leak'));
        $t->true(!str_contains($plainText, 'Type3 font resource FontFile text leak'));
        $t->true(!str_contains($plainText, 'Type3 stream font resource FontFile text leak'));
        $t->true(!str_contains($plainText, 'Type3 stream font resource CIDSet text leak'));
        $t->true(!str_contains($plainText, 'GlyphFont'));
        $t->true(!str_contains($plainText, 'GlyphStreamFont'));
    },
];
