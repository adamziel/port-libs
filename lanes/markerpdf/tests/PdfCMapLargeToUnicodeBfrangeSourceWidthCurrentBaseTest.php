<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$largeToUnicodeBfrangeSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LargeToUnicodeBfrangeSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <1FFF>\n"
        . "endcodespacerange\n"
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
        . "1 beginbfrange\n"
        . "<0000> <1FFF> <0041>\n"
        . "endbfrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <1000100110021003> Tj '
        . '1 0 0 1 120 720 Tm <1004100510061007> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LargeToUnicodeBfrangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LargeToUnicodeBfrangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [5096 5099 1000 5100 5103 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$largeArrayToUnicodeBfrangeSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LargeArrayToUnicodeBfrangeSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0000> <1007> 1000\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $targets = [];
    for ($index = 0; $index <= 0x1007; $index++) {
        $targets[] = sprintf('<%04X>', 0x0041 + $index);
    }

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfrange\n"
        . "<0000> <1007> [" . implode(' ', $targets) . "]\n"
        . "endbfrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <1000100110021003> Tj '
        . '1 0 0 1 120 720 Tm <1004100510061007> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LargeArrayToUnicodeBfrangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LargeArrayToUnicodeBfrangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [5096 5099 1000 5100 5103 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'uses lazy large ToUnicode bfrange rows past eager expansion cap before source-width fallback on current base' => static function (TestRunner $t) use ($largeToUnicodeBfrangeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $largeToUnicodeBfrangeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $firstRun = "\u{1041}\u{1042}\u{1043}\u{1044}";
        $secondRun = "\u{1045}\u{1046}\u{1047}\u{1048}";
        $expected = $firstRun . $secondRun;

        $t->same([$expected], $extractor->extractTextLines($pdf));
        $t->same([$firstRun, $secondRun], $runs);
        $t->same($expected, $plainText);
        $t->same($expected . "\n", $extractor->naiveGetText($pdf));
        $t->same([$firstRun, $secondRun], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, "\u{1000}\u{1001}\u{1002}\u{1003}"));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses lazy large ToUnicode bfrange array rows past eager expansion cap before source-width fallback on current base' => static function (TestRunner $t) use ($largeArrayToUnicodeBfrangeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $largeArrayToUnicodeBfrangeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $firstRun = "\u{1041}\u{1042}\u{1043}\u{1044}";
        $secondRun = "\u{1045}\u{1046}\u{1047}\u{1048}";
        $expected = $firstRun . $secondRun;

        $t->same([$expected], $extractor->extractTextLines($pdf));
        $t->same([$firstRun, $secondRun], $runs);
        $t->same($expected, $plainText);
        $t->same($expected . "\n", $extractor->naiveGetText($pdf));
        $t->same([$firstRun, $secondRun], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, "\u{1000}\u{1001}\u{1002}\u{1003}"));
        $t->true(!str_contains($plainText, "\0"));
    },
];
