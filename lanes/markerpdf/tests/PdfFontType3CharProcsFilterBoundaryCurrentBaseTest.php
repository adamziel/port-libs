<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsFilterBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide filtered charproc text leak) Tj ET\n";
    $compressedWideCharProc = gzcompress($wideCharProc);
    if (!is_string($compressedWideCharProc)) {
        throw new RuntimeException('Unable to compress focused Type3 CharProc fixture.');
    }

    $malformedFilteredCharProc = "1000 0 d0\nBT /Fghost 9 Tf (malformed filtered charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin charproc text leak) Tj ET\n";
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "17 beginbfchar\n"
        . "<41> <0057>\n"
        . "<42> <0069>\n"
        . "<43> <0064>\n"
        . "<44> <0065>\n"
        . "<45> <0042>\n"
        . "<46> <006C>\n"
        . "<47> <006F>\n"
        . "<48> <0063>\n"
        . "<49> <006B>\n"
        . "<4A> <004A>\n"
        . "<4B> <006F>\n"
        . "<4C> <0069>\n"
        . "<4D> <006E>\n"
        . "<54> <0054>\n"
        . "<55> <0068>\n"
        . "<56> <0069>\n"
        . "<57> <006E>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <4A4B4C4D> Tj ET';
    $encoding = '<< /Type /Encoding /BaseEncoding /WinAnsiEncoding '
        . '/Differences [65 /W.good /i.good /d.good /e.good /B.good /l.good /o.good /c.good /k.good '
        . '74 /J.thin /o.thin /i.thin /n.thin '
        . '84 /T.bad /h.bad /i.bad /n.bad] >>';
    $charProcs = '<< /W.good 3 0 R /i.good 3 0 R /d.good 3 0 R /e.good 3 0 R '
        . '/B.good 3 0 R /l.good 3 0 R /o.good 3 0 R /c.good 3 0 R /k.good 3 0 R '
        . '/J.thin 5 0 R /o.thin 5 0 R /i.thin 5 0 R /n.thin 5 0 R '
        . '/T.bad 4 0 R /h.bad 4 0 R /i.bad 4 0 R /n.bad 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcFilterBoundary /BaseFont /T3CharProcFilterBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs 21 0 R /FontDescriptor 23 0 R /ToUnicode 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedWideCharProc) . " >>\nstream\n{$compressedWideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Filter /FlateDecode /Length " . strlen($malformedFilteredCharProc) . " >>\nstream\n{$malformedFilteredCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "21 0 obj\n{$charProcs}\nendobj\n"
        . "23 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcFilterBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'fails closed on malformed filtered Type3 CharProc widths before WordPress text grouping on current base' => static function (TestRunner $t) use ($type3CharProcsFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsFilterBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Join'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Join'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Join", $plainText);
        $t->same("WideBlock\nThin Join\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'WideBlock'));
        $t->true(str_contains($plainText, 'Thin Join'));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinJoin'));
        $t->true(!str_contains($plainText, 'filtered charproc text leak'));
        $t->true(!str_contains($plainText, 'malformed filtered'));
    },
];
