<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontType3CharProcsFallbackBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\nBT /Ft3 9 Tf <47484F5354> Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj ET';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3FallbackBoundary /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProc streams from stream-only fallback WordPress text extraction on current base' => static function (TestRunner $t) use ($fontType3CharProcsFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType3CharProcsFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'GHOST'));
    },
];
