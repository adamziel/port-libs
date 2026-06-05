<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsPreMetricOperandBoundaryCurrentBasePdf = static function (): string {
    $validCharProc = "q 1 0 0 1 0 0 cm 0 0 m 1000 0 l h Q\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (valid premetric operand charproc text leak) Tj ET\n";
    $malformedPathCharProc = "(bad path operand) 0 0 m\n1000 0 d0\n"
        . "BT /Fghost 9 Tf (malformed premetric operand charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <4142434445> Tj '
        . '1 0 0 1 118 720 Tm <46474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
        . '1 0 0 1 118 704 Tm <5758595A> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /V.validsetup /a.validsetup /l.validsetup /i.validsetup /d.validsetup '
        . '/P.validsetup /a.validsetup /t.validsetup /h.validsetup '
        . '84 /B.badsetup /a.badsetup /d.badsetup /P.badsetup /a.badsetup /t.badsetup /h.badsetup] >>';
    $charProcs = '<< /V.validsetup 3 0 R /a.validsetup 3 0 R /l.validsetup 3 0 R /i.validsetup 3 0 R '
        . '/d.validsetup 3 0 R /P.validsetup 3 0 R /t.validsetup 3 0 R /h.validsetup 3 0 R '
        . '/B.badsetup 4 0 R /a.badsetup 4 0 R /d.badsetup 4 0 R /P.badsetup 4 0 R '
        . '/t.badsetup 4 0 R /h.badsetup 4 0 R >>';
    $widthValues = array_fill(0, 26, 250);
    $fallbackWidths = implode(' ', $widthValues);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PreMetricOperandBoundary /BaseFont /T3PreMetricOperandBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 90 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($malformedPathCharProc) . " >>\nstream\n{$malformedPathCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3PreMetricOperandBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects malformed Type3 CharProc pre-metric path operands before WordPress text grouping on current base' => static function (TestRunner $t) use ($type3CharProcsPreMetricOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsPreMetricOperandBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['ValidPath', 'Bad Path'], $extractor->extractTextLines($pdf));
        $t->same(['Valid', 'Path', 'Bad', 'Path'], $extractor->extractTextRuns($pdf));
        $t->same("ValidPath\nBad Path", $plainText);
        $t->same("ValidPath\nBad Path\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'ValidPath'));
        $t->true(str_contains($plainText, 'Bad Path'));
        $t->true(!str_contains($plainText, 'Valid Path'));
        $t->true(!str_contains($plainText, 'BadPath'));
        $t->true(!str_contains($plainText, 'premetric operand charproc text leak'));
        $t->true(!str_contains($plainText, 'bad path operand'));
    },
];
