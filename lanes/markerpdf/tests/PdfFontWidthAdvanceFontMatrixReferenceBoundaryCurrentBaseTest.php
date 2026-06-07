<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceFontMatrixReferenceBoundaryCurrentBasePdf = static function (): string {
    $charProc = "1 0 d0\nBT /Fghost 9 Tf (font matrix reference payload leak) Tj ET\n";
    $content = 'BT /Ft3ref 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 96 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3ref 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FontMatrixReference /BaseFont /T3FontMatrixReference "
        . "/FontBBox [0 0 1000 700] /FontMatrix [99 0 R 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 68 /Widths [500 500 500 500] /Encoding /WinAnsiEncoding "
        . "/CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects unresolved Type3 FontMatrix reference tokens before width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceFontMatrixReferenceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceFontMatrixReferenceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $spanBboxes = array_column($spans, 'bbox');

        $allBboxNumbersFinite = true;
        $maxBboxMagnitude = 0.0;
        foreach (array_merge($spanBboxes, [$line['bbox'] ?? []]) as $bbox) {
            foreach ($bbox as $number) {
                $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
                $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
            }
        }

        $firstEnd = (float) (($spanBboxes[0][2] ?? 0.0));
        $secondStart = (float) (($spanBboxes[1][0] ?? 0.0));

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->true($allBboxNumbersFinite);
        $t->true($maxBboxMagnitude < 100.0);
        $t->true($secondStart - $firstEnd > 12.0);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, 'font matrix reference payload leak'));
        $t->true(!str_contains($plainText, 'T3FontMatrixReference'));
        $t->true(!str_contains($plainText, 'Ft3ref'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
