<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsResourceSubtypeBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide resource subtype charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin resource subtype charproc text leak) Tj ET\n";
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
        . "<54> <0054>\n"
        . "<55> <0068>\n"
        . "<56> <0069>\n"
        . "<57> <006E>\n"
        . "<58> <0054>\n"
        . "<59> <0065>\n"
        . "<5A> <0078>\n"
        . "<5B> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /BaseEncoding /WinAnsiEncoding '
        . '/Differences [65 /W.nested /i.nested /d.nested /e.nested /B.nested /l.nested /o.nested /c.nested /k.nested '
        . '84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >>';
    $charProcs = '<< /W.nested 3 0 R /i.nested 3 0 R /d.nested 3 0 R /e.nested 3 0 R '
        . '/B.nested 3 0 R /l.nested 3 0 R /o.nested 3 0 R /c.nested 3 0 R /k.nested 3 0 R '
        . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
        . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3ResourceSubtypeBoundary /BaseFont /T3ResourceSubtypeBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs 21 0 R /FontDescriptor 23 0 R /ToUnicode 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " /Resources << /XObject << /GlyphBitmap << /Subtype /Image /Width 1 /Height 1 /BitsPerComponent 1 /ColorSpace /DeviceGray >> >> >> >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "21 0 obj\n{$charProcs}\nendobj\n"
        . "23 0 obj\n<< /Type /FontDescriptor /FontName /T3ResourceSubtypeBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'uses Type3 CharProc widths despite nested image resource subtype decoys on current base' => static function (TestRunner $t) use ($type3CharProcsResourceSubtypeBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsResourceSubtypeBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($plainText, 'WideBlock'));
        $t->true(str_contains($plainText, 'Thin Text'));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'resource subtype charproc text leak'));
    },
];
