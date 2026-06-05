<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsPatternResourceFallbackBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\nq /Pattern cs /GlyphPattern scn 0 0 12 12 re f Q\n"
        . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
    $visibleFallback = 'BT /Ft3 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $patternPaint = "q 0 0 12 12 re f /NestedGlyphPaint Do "
        . "BT /Fghost 7 Tf 0 0 Td (Type3 pattern resource text leak) Tj ET Q\n";
    $nestedGlyphPaint = "BT /Fghost 7 Tf 0 0 Td (nested Type3 pattern XObject text leak) Tj ET\n";

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3PatternFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /Pattern << /GlyphPattern 30 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "30 0 obj\n<< /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 12 12] "
        . "/XStep 12 /YStep 12 /Resources << /Font << /Fghost 31 0 R >> "
        . "/XObject << /NestedGlyphPaint 32 0 R >> >> /Length " . strlen($patternPaint) . " >>\n"
        . "stream\n{$patternPaint}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "32 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] "
        . "/Resources << /Font << /Fghost 31 0 R >> >> /Length " . strlen($nestedGlyphPaint) . " >>\n"
        . "stream\n{$nestedGlyphPaint}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProc pattern resource streams from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsPatternResourceFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsPatternResourceFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'GHOST'));
        $t->true(!str_contains($plainText, 'Type3 pattern resource text leak'));
        $t->true(!str_contains($plainText, 'nested Type3 pattern XObject text leak'));
        $t->true(!str_contains($plainText, 'GlyphPattern'));
        $t->true(!str_contains($plainText, 'NestedGlyphPaint'));
    },
];
