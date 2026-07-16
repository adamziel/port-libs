<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDuplicateKeyBoundaryCurrentBasePdf = static function (): string {
    $staleWideCharProc = "250 0 d0\nBT /Fghost 9 Tf (stale duplicate charproc text leak) Tj ET\n";
    $currentWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (current duplicate charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin duplicate charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide '
        . '/B.wide /l.wide /o.wide /c.wide /k.wide '
        . '84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >>';
    $staleCharProcs = '<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R '
        . '/B.wide 3 0 R /l.wide 3 0 R /o.wide 3 0 R /c.wide 3 0 R /k.wide 3 0 R '
        . '/T.thin 3 0 R /h.thin 3 0 R /n.thin 3 0 R /x.thin 3 0 R /t.thin 3 0 R >>';
    $currentCharProcs = '<< /W.wide 4 0 R /i.wide 4 0 R /d.wide 4 0 R /e.wide 4 0 R '
        . '/B.wide 4 0 R /l.wide 4 0 R /o.wide 4 0 R /c.wide 4 0 R /k.wide 4 0 R '
        . '/T.thin 5 0 R /h.thin 5 0 R /i.thin 5 0 R /n.thin 5 0 R '
        . '/e.thin 5 0 R /x.thin 5 0 R /t.thin 5 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3DuplicateCharProcs /BaseFont /T3DuplicateCharProcs "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs {$staleCharProcs} /CharProcs {$currentCharProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($staleWideCharProc) . " >>\nstream\n{$staleWideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($currentWideCharProc) . " >>\nstream\n{$currentWideCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3DuplicateCharProcs /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsDuplicateKeyFallbackBoundaryCurrentBasePdf = static function (): string {
    $staleGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (STALE DUPLICATE CHARPROC GLYPH LEAK) Tj ET\n";
    $currentGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (CURRENT DUPLICATE CHARPROC GLYPH LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $staleCharProcs = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
        . '/G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /O 3 0 R /P 3 0 R /S 3 0 R /T 3 0 R >>';
    $currentCharProcs = '<< /A 4 0 R /B 4 0 R /C 4 0 R /D 4 0 R '
        . '/G 4 0 R /H 4 0 R /I 4 0 R /L 4 0 R /O 4 0 R /P 4 0 R /S 4 0 R /T 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3DuplicateCharProcsFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs {$staleCharProcs} /CharProcs {$currentCharProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($staleGlyphProgram) . " >>\nstream\n{$staleGlyphProgram}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($currentGlyphProgram) . " >>\nstream\n{$currentGlyphProgram}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";
};

return [
    'uses the last duplicate top-level Type3 CharProcs dictionary before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateKeyBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateKeyBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'WideBlock'));
        $t->true(str_contains($plainText, 'Thin Text'));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'duplicate charproc text leak'));
    },
    'keeps stale duplicate Type3 CharProcs glyph streams private during fallback extraction on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateKeyFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateKeyFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'STALE DUPLICATE CHARPROC GLYPH LEAK'));
        $t->true(!str_contains($plainText, 'CURRENT DUPLICATE CHARPROC GLYPH LEAK'));
        $t->true(!str_contains($plainText, 'T3DuplicateCharProcsFallback'));
    },
];
