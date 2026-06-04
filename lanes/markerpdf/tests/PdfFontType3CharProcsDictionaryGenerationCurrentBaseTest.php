<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDictionaryGenerationPagePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide dictionary generation text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin dictionary generation text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $currentCharProcs = '<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R '
        . '/B.wide 3 0 R /l.wide 3 0 R /o.wide 3 0 R /c.wide 3 0 R /k.wide 3 0 R '
        . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
        . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';
    $staleCharProcs = '<< /W.wide 4 0 R /i.wide 4 0 R /d.wide 4 0 R /e.wide 4 0 R '
        . '/B.wide 4 0 R /l.wide 4 0 R /o.wide 4 0 R /c.wide 4 0 R /k.wide 4 0 R '
        . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
        . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsDictGeneration /BaseFont /T3CharProcsDictGeneration /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding << /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide /B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >> "
        . "/CharProcs 21 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "21 0 obj\n{$currentCharProcs}\nendobj\n"
        . "21 1 obj\n{$staleCharProcs}\nendobj\n%%EOF";
};

$type3CharProcsDictionaryGenerationFallbackPdf = static function (): string {
    $charProc = "650 0 d0\nBT /Ft3 9 Tf <47484F5354> Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj ET';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3DictGenerationFallback /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "21 0 obj\n<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >>\nendobj\n"
        . "21 1 obj\n<< /A 5 0 R /B 5 0 R /C 5 0 R /D 5 0 R /G 5 0 R /H 5 0 R /O 5 0 R /S 5 0 R /T 5 0 R >>\nendobj\n%%EOF";
};

return [
    'uses exact indirect Type3 CharProcs dictionary generation for WordPress text widths on current base' => static function (TestRunner $t) use ($type3CharProcsDictionaryGenerationPagePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDictionaryGenerationPagePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'dictionary generation text leak'));
    },
    'excludes exact indirect Type3 CharProcs dictionary streams from fallback text on current base' => static function (TestRunner $t) use ($type3CharProcsDictionaryGenerationFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDictionaryGenerationFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'GHOST'));
    },
];
