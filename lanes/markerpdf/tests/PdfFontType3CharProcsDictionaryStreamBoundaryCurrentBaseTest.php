<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDictionaryStreamBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide malformed CharProcs stream text leak) Tj ET\n";
    $charProcsStreamPayload = "BT /Fghost 9 Tf (malformed CharProcs dictionary stream payload leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
        . '1 0 0 1 118 720 Tm <44454647> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /B.badstream /a.badstream /d.badstream '
        . '/P.badstream /a.badstream /t.badstream /h.badstream] >>';
    $charProcsStreamDict = '<< /B.badstream 3 0 R /a.badstream 3 0 R /d.badstream 3 0 R '
        . '/P.badstream 3 0 R /t.badstream 3 0 R /h.badstream 3 0 R '
        . '/Length ' . strlen($charProcsStreamPayload) . ' >>';
    $fallbackWidths = implode(' ', array_fill(0, 7, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsStreamBoundary /BaseFont /T3CharProcsStreamBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs 21 0 R /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsStreamBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "21 0 obj\n% PDF comments before the dictionary do not make a stream object a CharProcs map\n{$charProcsStreamDict}\nstream\n{$charProcsStreamPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProcs stream dictionaries before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDictionaryStreamBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDictionaryStreamBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Bad Path'], $extractor->extractTextLines($pdf));
        $t->same(['Bad', 'Path'], $extractor->extractTextRuns($pdf));
        $t->same('Bad Path', $plainText);
        $t->same("Bad Path\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'BadPath'));
        $t->true(!str_contains($plainText, 'CharProcs dictionary stream payload leak'));
        $t->true(!str_contains($plainText, 'malformed CharProcs stream text leak'));
    },
];
