<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapLateCidCharOverrideSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LateCIDCharOverrideSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<20> <27> 100\n"
        . "endcidrange\n"
        . "4 begincidchar\n"
        . "<20> 300\n"
        . "<21> 301\n"
        . "<22> 302\n"
        . "<23> 303\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<20> <0041>\n"
        . "<21> <0042>\n"
        . "<22> <0043>\n"
        . "<23> <0044>\n"
        . "<24> <0045>\n"
        . "<25> <0046>\n"
        . "<26> <0047>\n"
        . "<27> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <20212223> Tj '
        . '1 0 0 1 120 720 Tm <24252627> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LateCIDCharOverrideSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LateCIDCharOverrideSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 107 1000 300 303 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapLateLargeCidRangeOverrideSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LateLargeCIDRangeOverrideSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <1FFF>\n"
        . "endcodespacerange\n"
        . "4 begincidchar\n"
        . "<1800> 100\n"
        . "<1801> 101\n"
        . "<1802> 102\n"
        . "<1803> 103\n"
        . "endcidchar\n"
        . "1 begincidrange\n"
        . "<0000> <1FFF> 1000\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <1FFF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<1800> <0041>\n"
        . "<1801> <0042>\n"
        . "<1802> <0043>\n"
        . "<1803> <0044>\n"
        . "<1804> <0045>\n"
        . "<1805> <0046>\n"
        . "<1806> <0047>\n"
        . "<1807> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <1800180118021803> Tj '
        . '1 0 0 1 120 720 Tm <1804180518061807> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LateLargeCIDRangeOverrideSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LateLargeCIDRangeOverrideSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 103 250 7144 7147 1000 7148 7151 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'honors later CMap cidchar rows over earlier cidrange rows before source-width fallback on current base' => static function (TestRunner $t) use ($cMapLateCidCharOverrideSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapLateCidCharOverrideSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 12.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([12.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'honors later large CMap cidrange rows over earlier cidchar rows before source-width fallback on current base' => static function (TestRunner $t) use ($cMapLateLargeCidRangeOverrideSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapLateLargeCidRangeOverrideSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD EFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
