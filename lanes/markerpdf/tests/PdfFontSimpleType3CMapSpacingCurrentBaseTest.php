<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$simpleType3CMapSpacingCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<F000> <F0FF>\n"
        . "endcodespacerange\n"
        . "7 beginbfchar\n"
        . "<F020> <2060>\n"
        . "<F041> <0041>\n"
        . "<F042> <0042>\n"
        . "<F043> <0043>\n"
        . "<F044> <0044>\n"
        . "<F045> <0045>\n"
        . "<F046> <0046>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /SimpleType3CMapSpacingCurrentBase-H def\n"
        . "1 begincodespacerange\n"
        . "<F000> <F0FF>\n"
        . "endcodespacerange\n"
        . "7 begincidchar\n"
        . "<F020> 32\n"
        . "<F041> 65\n"
        . "<F042> 66\n"
        . "<F043> 67\n"
        . "<F044> 68\n"
        . "<F045> 69\n"
        . "<F046> 70\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $widths = array_fill(0, 39, 500.0);
    $widthArray = implode(' ', array_map(static fn (float $width): string => rtrim(rtrim(sprintf('%.1F', $width), '0'), '.'), $widths));

    $content = 'BT /Ft3 12 Tf 18 Tw 1 0 0 1 72 720 Tm <F041F020F042> Tj '
        . '1 0 0 1 119 720 Tm <F043> Tj '
        . 'T* 1 0 0 1 72 704 Tm [<F044F020F045>] TJ '
        . '1 0 0 1 119 704 Tm <F046> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CMapSpacing /BaseFont /T3CMapSpacing /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 32 /LastChar 70 /Widths 22 0 R /Encoding 19 0 R /CharProcs << >> /ToUnicode 20 0 R >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "22 0 obj\n[{$widthArray}]\nendobj\n"
        . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'uses Type3 Encoding CMap CID 32 as source word spacing before WordPress grouping on current base' => static function (TestRunner $t) use ($simpleType3CMapSpacingCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $simpleType3CMapSpacingCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expectedLines = ["A\u{2060}BC", "D\u{2060}EF"];

        $t->same($expectedLines, $extractor->extractTextLines($pdf));
        $t->same($expectedLines, explode("\n", $plainText));
        $t->same("A\u{2060}BC\nD\u{2060}EF\n", $extractor->naiveGetText($pdf));
        $t->same(["A\u{2060}B", 'C', "D\u{2060}E", 'F'], $extractor->extractTextRuns($pdf));
        $t->true(str_contains($plainText, "\u{2060}"));
        $t->true(!str_contains($plainText, 'B C'));
        $t->true(!str_contains($plainText, 'E F'));
        $t->true(!str_contains($plainText, 'F020'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
