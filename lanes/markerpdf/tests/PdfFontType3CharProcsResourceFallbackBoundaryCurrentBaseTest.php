<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsResourceFallbackBoundaryCurrentBasePdf = static function (): string {
    $charProc = "1000 0 d0\n/TopGlyphPaint Do /StreamGlyphPaint Do\n"
        . "BT /Fghost 9 Tf (direct charproc resource fallback text leak) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $topLevelGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (top Type3 resource form leak) Tj ET';
    $streamGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (stream Type3 resource form leak) Tj ET';
    $nestedGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (nested Type3 resource form leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ResourceFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /XObject << /TopGlyphPaint 5 0 R >> /Font << /Fghost 1 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Resources << /XObject << /StreamGlyphPaint 6 0 R >> /Font << /Fghost 1 0 R >> >> /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($topLevelGlyphResource) . " >>\nstream\n{$topLevelGlyphResource}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /XObject << /NestedGlyphPaint 7 0 R >> /Font << /Fghost 1 0 R >> >> /Length " . strlen($streamGlyphResource) . " >>\nstream\n{$streamGlyphResource}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($nestedGlyphResource) . " >>\nstream\n{$nestedGlyphResource}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProc resource streams from stream-only fallback WordPress text extraction on current base' => static function (TestRunner $t) use ($type3CharProcsResourceFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsResourceFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'direct charproc resource fallback text leak'));
        $t->true(!str_contains($plainText, 'top Type3 resource form leak'));
        $t->true(!str_contains($plainText, 'stream Type3 resource form leak'));
        $t->true(!str_contains($plainText, 'nested Type3 resource form leak'));
        $t->true(!str_contains($plainText, 'T3ResourceFallback'));
    },
];
