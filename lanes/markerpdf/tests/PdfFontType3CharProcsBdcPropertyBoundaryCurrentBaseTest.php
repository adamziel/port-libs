<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsBdcPropertyBoundaryCurrentBasePdf = static function (): string {
    $validDictionaryPropertyCharProc = "/Glyph << /ActualText (250 0 d0 BDC dictionary decoy) >> BDC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (valid dictionary BDC charproc text leak) Tj ET\n";
    $validNamePropertyCharProc = "/Glyph /GlyphProps BDC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (valid name BDC charproc text leak) Tj ET\n";
    $malformedNumericPropertyCharProc = "/Glyph 123 BDC\n1000 0 d0\nEMC\n"
        . "BT /Fghost 9 Tf (malformed numeric BDC charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 121 720 Tm <4546> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4B4C4D4E> Tj '
        . '1 0 0 1 121 704 Tm <4F50> Tj '
        . 'T* 1 0 0 1 72 688 Tm <555657> Tj '
        . '1 0 0 1 109 688 Tm <58595A> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /D.dict /i.dict /c.dict /t.dict /O.dict /k.dict '
        . '75 /N.name /a.name /m.name /e.name /O.name /k.name '
        . '85 /B.badprop /a.badprop /d.badprop /G.badprop /a.badprop /p.badprop] >>';
    $charProcs = '<< /D.dict 3 0 R /i.dict 3 0 R /c.dict 3 0 R /t.dict 3 0 R '
        . '/O.dict 3 0 R /k.dict 3 0 R '
        . '/N.name 4 0 R /a.name 4 0 R /m.name 4 0 R /e.name 4 0 R '
        . '/O.name 4 0 R /k.name 4 0 R '
        . '/B.badprop 5 0 R /a.badprop 5 0 R /d.badprop 5 0 R '
        . '/G.badprop 5 0 R /p.badprop 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 26, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3BdcPropertyBoundary /BaseFont /T3BdcPropertyBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 90 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validDictionaryPropertyCharProc) . " >>\nstream\n{$validDictionaryPropertyCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($validNamePropertyCharProc) . " >>\nstream\n{$validNamePropertyCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($malformedNumericPropertyCharProc) . " >>\nstream\n{$malformedNumericPropertyCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3BdcPropertyBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects numeric Type3 CharProc BDC property operands before WordPress grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsBdcPropertyBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsBdcPropertyBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['DictOk', 'NameOk', 'Bad Gap'], $extractor->extractTextLines($pdf));
        $t->same(['Dict', 'Ok', 'Name', 'Ok', 'Bad', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("DictOk\nNameOk\nBad Gap", $plainText);
        $t->same("DictOk\nNameOk\nBad Gap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'DictOk'));
        $t->true(str_contains($plainText, 'NameOk'));
        $t->true(str_contains($plainText, 'Bad Gap'));
        $t->true(!str_contains($plainText, 'Dict Ok'));
        $t->true(!str_contains($plainText, 'Name Ok'));
        $t->true(!str_contains($plainText, 'BadGap'));
        $t->true(!str_contains($plainText, 'BDC dictionary decoy'));
        $t->true(!str_contains($plainText, 'charproc text leak'));
    },
];
