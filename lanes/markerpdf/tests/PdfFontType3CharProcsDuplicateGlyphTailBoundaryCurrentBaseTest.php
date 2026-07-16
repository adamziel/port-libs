<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDuplicateGlyphTailBoundaryCurrentBasePdf = static function (): string {
    $currentWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (current duplicate glyph-tail charproc text leak) Tj ET\n";
    $staleThinCharProc = "250 0 d0\nBT /Fghost 9 Tf (stale duplicate glyph-tail charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.dup /i.dup /d.dup /e.dup '
        . '/B.dup /l.dup /o.dup /c.dup /k.dup] >>';
    $charProcs = '<< /W.dup 4 0 R 99 0 R /W.dup 3 0 R '
        . '/i.dup 3 0 R /d.dup 3 0 R /e.dup 3 0 R '
        . '/B.dup 3 0 R /l.dup 3 0 R /o.dup 3 0 R /c.dup 3 0 R /k.dup 3 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 9, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3DuplicateGlyphTailBoundary /BaseFont /T3DuplicateGlyphTailBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 73 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($currentWideCharProc) . " >>\nstream\n{$currentWideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleThinCharProc) . " >>\nstream\n{$staleThinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3DuplicateGlyphTailBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsDuplicateGlyphTailFallbackPdf = static function (): string {
    $currentGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (CURRENT DUPLICATE GLYPH TAIL LEAK) Tj ET\n";
    $staleGlyphProgram = "250 0 d0\nBT /Fghost 9 Tf (STALE DUPLICATE GLYPH TAIL LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $charProcs = '<< /A 4 0 R 99 0 R /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
        . '/G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /N 3 0 R /O 3 0 R /P 3 0 R '
        . '/S 3 0 R /T 3 0 R /V 3 0 R >>';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3DuplicateGlyphTailFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($currentGlyphProgram) . " >>\nstream\n{$currentGlyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleGlyphProgram) . " >>\nstream\n{$staleGlyphProgram}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";
};

return [
    'allows a later valid duplicate Type3 CharProc glyph entry to replace a malformed stale tail on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateGlyphTailBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateGlyphTailBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('WideBlock', $plainText);
        $t->same("WideBlock\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'duplicate glyph-tail charproc text leak'));
        $t->true(!str_contains($plainText, 'T3DuplicateGlyphTailBoundary'));
    },
    'keeps stale and current duplicate Type3 CharProc glyph streams private during fallback extraction' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateGlyphTailFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateGlyphTailFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'CURRENT DUPLICATE GLYPH TAIL LEAK'));
        $t->true(!str_contains($plainText, 'STALE DUPLICATE GLYPH TAIL LEAK'));
        $t->true(!str_contains($plainText, 'T3DuplicateGlyphTailFallback'));
    },
];
