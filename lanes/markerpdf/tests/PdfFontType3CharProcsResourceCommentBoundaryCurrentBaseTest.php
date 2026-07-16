<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsResourceCommentBoundaryCurrentBasePdf = static function (): string {
    $charProc = "1000 0 d0\n/TopGlyphPaint Do /StreamGlyphPaint Do\n"
        . "BT /Fghost 9 Tf (comment-split charproc text leak) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $topLevelGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (top comment-split Type3 resource leak) Tj ET';
    $streamGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (stream comment-split Type3 resource leak) Tj ET';
    $nestedGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (nested comment-split Type3 resource leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ResourceCommentBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /XObject << /TopGlyphPaint 5 % object/generation split by PDF comment\n 0 % generation/R split by PDF comment\n R >> /Font << /Fghost 1 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Resources << /XObject << /StreamGlyphPaint 6 % object/generation split by PDF comment\n 0 % generation/R split by PDF comment\n R >> /Font << /Fghost 1 0 R >> >> /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($topLevelGlyphResource) . " >>\nstream\n{$topLevelGlyphResource}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /XObject << /NestedGlyphPaint 7 % object/generation split by PDF comment\n 0 R >> /Font << /Fghost 1 0 R >> >> /Length " . strlen($streamGlyphResource) . " >>\nstream\n{$streamGlyphResource}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($nestedGlyphResource) . " >>\nstream\n{$nestedGlyphResource}\nendstream\nendobj\n%%EOF";
};

return [
    'treats PDF comments as whitespace inside Type3 CharProc resource references before fallback extraction on current base' => static function (TestRunner $t) use ($type3CharProcsResourceCommentBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsResourceCommentBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'comment-split charproc text leak'));
        $t->true(!str_contains($plainText, 'top comment-split Type3 resource leak'));
        $t->true(!str_contains($plainText, 'stream comment-split Type3 resource leak'));
        $t->true(!str_contains($plainText, 'nested comment-split Type3 resource leak'));
    },
];
