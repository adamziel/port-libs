<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontCMapCidType3WidthSpacingBundleCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /Type3RawSpaceConflict-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <00FF>\n"
        . "endcodespacerange\n"
        . "2 begincidchar\n"
        . "<0020> 65\n"
        . "<0021> 66\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <00FF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<0020> <0041>\n"
        . "<0021> <0042>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Ft3 12 Tf 16 TL 1 0 0 1 72 720 Tm '
        . '20 0 <0020> " '
        . '1 0 0 1 91 704 Tm <0021> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3RawSpaceConflict /BaseFont /T3RawSpaceConflict /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 66 /Widths [500 500] /Encoding 19 0 R /CharProcs << >> /ToUnicode 20 0 R >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'uses Type3 CMap CIDs rather than raw 0x20 for quote operator word spacing on current base' => static function (TestRunner $t) use ($fontCMapCidType3WidthSpacingBundleCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontCMapCidType3WidthSpacingBundleCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A B'], $extractor->extractTextLines($pdf));
        $t->same(['A', 'B'], $extractor->extractTextRuns($pdf));
        $t->same('A B', $plainText);
        $t->same("A B\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'AB'));
        $t->true(!str_contains($plainText, '0020'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
