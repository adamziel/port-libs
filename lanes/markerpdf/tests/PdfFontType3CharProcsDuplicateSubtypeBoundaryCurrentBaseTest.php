<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDuplicateSubtypeCurrentType3Pdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (current Type3 duplicate subtype charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <454647> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide '
        . '/G.gap /a.gap /p.gap] >>';
    $charProcs = '<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R '
        . '/G.gap 3 0 R /a.gap 3 0 R /p.gap 3 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 7, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /Subtype /Type3 /Name /T3DuplicateSubtypeCurrent "
        . "/BaseFont /T3DuplicateSubtypeCurrent /FontBBox [0 0 1000 700] "
        . "/FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsDuplicateSubtypeCurrentType1PagePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (stale Type3 duplicate subtype charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <454647> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide '
        . '/G.gap /a.gap /p.gap] >>';
    $charProcs = '<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R '
        . '/G.gap 3 0 R /a.gap 3 0 R /p.gap 3 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 7, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Subtype /Type1 /Name /T3DuplicateSubtypeStale "
        . "/BaseFont /T3DuplicateSubtypeStale /FontBBox [0 0 1000 700] "
        . "/FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$type3CharProcsDuplicateSubtypeCurrentType1FallbackPdf = static function (): string {
    $charProcFallback = 'BT /F1 12 Tf 72 720 Td (CharProcs visible fallback content) Tj ET';
    $standaloneFallback = 'BT /F1 12 Tf 72 700 Td (Standalone fallback content) Tj ET';
    $charProcs = '<< /A 3 0 R /C 3 0 R /D 3 0 R /S 3 0 R /V 3 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Subtype /Type1 /BaseFont /Helvetica "
        . "/CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProcFallback) . " >>\nstream\n{$charProcFallback}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($standaloneFallback) . " >>\nstream\n{$standaloneFallback}\nendstream\nendobj\n%%EOF";
};

return [
    'uses the last duplicate top-level Subtype Type3 for CharProc widths before WordPress grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateSubtypeCurrentType3Pdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateSubtypeCurrentType3Pdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideGap'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same('WideGap', $plainText);
        $t->same("WideGap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'WideGap'));
        $t->true(!str_contains($plainText, 'Wide Gap'));
        $t->true(!str_contains($plainText, 'duplicate subtype charproc text leak'));
    },
    'uses the last duplicate top-level Subtype Type1 to avoid stale Type3 CharProc widths on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateSubtypeCurrentType1PagePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateSubtypeCurrentType1PagePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Wide Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same('Wide Gap', $plainText);
        $t->same("Wide Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'Wide Gap'));
        $t->true(!str_contains($plainText, 'WideGap'));
        $t->true(!str_contains($plainText, 'duplicate subtype charproc text leak'));
    },
    'does not suppress fallback streams from CharProcs when the last duplicate top-level Subtype is Type1' => static function (
        TestRunner $t
    ) use ($type3CharProcsDuplicateSubtypeCurrentType1FallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDuplicateSubtypeCurrentType1FallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['CharProcs visible fallback content', 'Standalone fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['CharProcs visible fallback content', 'Standalone fallback content'], $extractor->extractTextRuns($pdf));
        $t->same("CharProcs visible fallback content\nStandalone fallback content", $plainText);
        $t->same("CharProcs visible fallback content\nStandalone fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'CharProcs visible fallback content'));
        $t->true(str_contains($plainText, 'Standalone fallback content'));
        $t->true(!str_contains($plainText, 'T3DuplicateSubtype'));
    },
];
