<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsD1BBoxOperandBoundaryCurrentBasePdf = static function (): string {
    $validThinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (valid d1 bbox charproc text leak) Tj ET\n";
    $malformedStringBBoxCharProc = "250 0 (bad bbox operand) 0 250 700 d1\n"
        . "BT /Fghost 9 Tf (malformed string bbox charproc text leak) Tj ET\n";
    $malformedDictionaryBBoxCharProc = "250 0 0 0 << /Bad 700 >> 700 d1\n"
        . "BT /Fghost 9 Tf (malformed dictionary bbox charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 96 720 Tm <45464748> Tj '
        . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
        . '1 0 0 1 118 704 Tm <5758595A> Tj '
        . 'T* 1 0 0 1 72 688 Tm <61626364> Tj '
        . '1 0 0 1 130 688 Tm <656667> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /T.valid /h.valid /i.valid /n.valid '
        . '/T.valid /e.valid /x.valid /t.valid '
        . '84 /B.string /a.string /d.string /B.string /B.string /o.string /x.string '
        . '97 /D.dict /i.dict /c.dict /t.dict /G.dict /a.dict /p.dict] >>';
    $charProcs = '<< /T.valid 3 0 R /h.valid 3 0 R /i.valid 3 0 R /n.valid 3 0 R '
        . '/e.valid 3 0 R /x.valid 3 0 R /t.valid 3 0 R '
        . '/B.string 4 0 R /a.string 4 0 R /d.string 4 0 R /o.string 4 0 R /x.string 4 0 R '
        . '/D.dict 5 0 R /i.dict 5 0 R /c.dict 5 0 R /t.dict 5 0 R '
        . '/G.dict 5 0 R /a.dict 5 0 R /p.dict 5 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 40, 1000));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3D1BBoxOperandBoundary /BaseFont /T3D1BBoxOperandBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 104 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validThinCharProc) . " >>\nstream\n{$validThinCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($malformedStringBBoxCharProc) . " >>\nstream\n{$malformedStringBBoxCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($malformedDictionaryBBoxCharProc) . " >>\nstream\n{$malformedDictionaryBBoxCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3D1BBoxOperandBoundary /Flags 4 /MissingWidth 1000 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects malformed Type3 CharProc d1 bbox operands before WordPress text grouping on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsD1BBoxOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsD1BBoxOperandBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Thin Text', 'BadBBox', 'DictGap'], $extractor->extractTextLines($pdf));
        $t->same(['Thin', 'Text', 'Bad', 'BBox', 'Dict', 'Gap'], $extractor->extractTextRuns($pdf));
        $t->same("Thin Text\nBadBBox\nDictGap", $plainText);
        $t->same("Thin Text\nBadBBox\nDictGap\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'Thin Text'));
        $t->true(str_contains($plainText, 'BadBBox'));
        $t->true(str_contains($plainText, 'DictGap'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'Bad BBox'));
        $t->true(!str_contains($plainText, 'Dict Gap'));
        $t->true(!str_contains($plainText, 'bbox charproc text leak'));
        $t->true(!str_contains($plainText, 'bad bbox operand'));
    },
];
