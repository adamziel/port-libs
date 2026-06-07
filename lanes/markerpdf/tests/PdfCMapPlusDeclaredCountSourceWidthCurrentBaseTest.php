<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapPlusDeclaredCountSourceWidthCurrentBasePdf = static function (string $mappingKind): string {
    if ($mappingKind === 'char') {
        $mappingBlock = "+4 begincidchar\n"
            . "<20> 100\n"
            . "<21> 101\n"
            . "<22> 102\n"
            . "<23> 103\n"
            . "<24> 200\n"
            . "<25> 201\n"
            . "<26> 202\n"
            . "<27> 203\n"
            . "endcidchar\n";
        $cMapName = 'PlusDeclaredCountCidChar-H';
        $baseFont = 'PlusDeclaredCountCidChar';
    } else {
        $mappingBlock = "+1 begincidrange\n"
            . "<20> <23> 100\n"
            . "<24> <27> 200\n"
            . "endcidrange\n";
        $cMapName = 'PlusDeclaredCountCidRange-H';
        $baseFont = 'PlusDeclaredCountCidRange';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . $mappingBlock
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
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 103 250 200 203 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$assertPlusDeclaredCountSourceWidth = static function (TestRunner $t, string $pdf): void {
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
    $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
    $t->same('ABCD EFGH', $plainText);
    $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
    $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
    $t->same([0.0, 0.0, 12.0, 12.0], $spans[0]['bbox'] ?? null);
    $t->same([12.0, 0.0, 36.0, 12.0], $spans[1]['bbox'] ?? null);
    $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
    $t->true(!str_contains($plainText, 'ABCDEFGH'));
    $t->true(!str_contains($plainText, 'PlusDeclaredCount'));
    $t->true(!str_contains($plainText, "\0"));
};

return [
    'honors plus-signed CMap cidchar declared counts before source-width fallback on current base' => static function (TestRunner $t) use (
        $cMapPlusDeclaredCountSourceWidthCurrentBasePdf,
        $assertPlusDeclaredCountSourceWidth
    ): void {
        $assertPlusDeclaredCountSourceWidth($t, $cMapPlusDeclaredCountSourceWidthCurrentBasePdf('char'));
    },

    'honors plus-signed CMap cidrange declared counts before source-width fallback on current base' => static function (TestRunner $t) use (
        $cMapPlusDeclaredCountSourceWidthCurrentBasePdf,
        $assertPlusDeclaredCountSourceWidth
    ): void {
        $assertPlusDeclaredCountSourceWidth($t, $cMapPlusDeclaredCountSourceWidthCurrentBasePdf('range'));
    },
];
