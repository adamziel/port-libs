<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontCidEncodingWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPIndirectWidthCID-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "2 begincidrange\n"
        . "<01> <09> 40\n"
        . "<14> <1B> 60\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "17 beginbfchar\n"
        . "<01> <0057>\n"
        . "<02> <0069>\n"
        . "<03> <0064>\n"
        . "<04> <0065>\n"
        . "<05> <0042>\n"
        . "<06> <006C>\n"
        . "<07> <006F>\n"
        . "<08> <0063>\n"
        . "<09> <006B>\n"
        . "<14> <0044>\n"
        . "<15> <0061>\n"
        . "<16> <0074>\n"
        . "<17> <0061>\n"
        . "<18> <0046>\n"
        . "<19> <006C>\n"
        . "<1A> <006F>\n"
        . "<1B> <0077>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <01020304> Tj '
        . '1 0 0 1 118 720 Tm <0506070809> Tj '
        . 'T* 1 0 0 1 72 704 Tm <14151617> Tj '
        . '1 0 0 1 118 704 Tm <18191A1B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectCIDWidthSubset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectCIDWidthSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [40 [7 0 R 7 0 R 7 0 R 7 0 R 7 0 R 7 0 R 7 0 R 7 0 R 7 0 R] 60 67 8 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "7 0 obj\n1000\nendobj\n"
        . "8 0 obj\n1000\nendobj\n%%EOF";
};

return [
    'resolves indirect CIDFont W list and range widths after Type0 Encoding CMap CIDs on current base' => static function (TestRunner $t) use ($fontCidEncodingWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontCidEncodingWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'DataFlow'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Data', 'Flow'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nDataFlow", $plainText);
        $t->same("WideBlock\nDataFlow\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'Data Flow'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
