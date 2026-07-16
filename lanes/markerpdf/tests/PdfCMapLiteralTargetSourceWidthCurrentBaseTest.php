<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapLiteralTargetSourceWidthCurrentBasePdf = static function (string $targetKind): array {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LiteralTargetSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<10> <13> 60\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    if ($targetKind === 'bfchar') {
        $mappingBlock = "8 beginbfchar\n"
            . "<10> (W)\n"
            . "<11> (i)\n"
            . "<12> (d)\n"
            . "<13> (e)\n"
            . "<20> (T)\n"
            . "<21> (h)\n"
            . "<22> (i)\n"
            . "<23> (n)\n"
            . "endbfchar\n";
        $expectedRuns = ['Wide', 'Thin'];
    } elseif ($targetKind === 'array') {
        $mappingBlock = "2 beginbfrange\n"
            . "<10> <13> [(W) (i) (d) (e)]\n"
            . "<20> <23> [(T) (h) (i) (n)]\n"
            . "endbfrange\n";
        $expectedRuns = ['Wide', 'Thin'];
    } else {
        $mappingBlock = "2 beginbfrange\n"
            . "<10> <13> (W)\n"
            . "<20> <23> (T)\n"
            . "endbfrange\n";
        $expectedRuns = ['WXYZ', 'TUVW'];
    }

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <10111213> Tj '
        . '1 0 0 1 96 720 Tm <20212223> Tj ET';

    return [
        "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralTargetSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LiteralTargetSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 35 1000 60 63 250] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF",
        $expectedRuns,
    ];
};

$assertCMapLiteralTargetSourceWidthCurrentBase = static function (TestRunner $t, string $pdf, array $expectedRuns): void {
    $extractor = new PdfTextExtractor();
    $expectedText = implode(' ', $expectedRuns);
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    $t->same([$expectedText], $extractor->extractTextLines($pdf));
    $t->same($expectedRuns, $extractor->extractTextRuns($pdf));
    $t->same($expectedText, $plainText);
    $t->same($expectedText . "\n", $extractor->naiveGetText($pdf));
    $t->same($expectedRuns, array_column($spans, 'text'));
    $t->same([0.0, 0.0, 12.0, 12.0], $spans[0]['bbox'] ?? null);
    $t->same([12.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
    $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
    $t->true(!str_contains($plainText, implode('', $expectedRuns)));
    $t->true(!str_contains($plainText, 'beginbfchar'));
    $t->true(!str_contains($plainText, 'beginbfrange'));
    $t->true(!str_contains($plainText, "\0"));
};

return [
    'decodes literal ToUnicode bfchar targets before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapLiteralTargetSourceWidthCurrentBasePdf, $assertCMapLiteralTargetSourceWidthCurrentBase): void {
        [$pdf, $expectedRuns] = $cMapLiteralTargetSourceWidthCurrentBasePdf('bfchar');
        $assertCMapLiteralTargetSourceWidthCurrentBase($t, $pdf, $expectedRuns);
    },

    'decodes literal ToUnicode bfrange scalar targets before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapLiteralTargetSourceWidthCurrentBasePdf, $assertCMapLiteralTargetSourceWidthCurrentBase): void {
        [$pdf, $expectedRuns] = $cMapLiteralTargetSourceWidthCurrentBasePdf('scalar');
        $assertCMapLiteralTargetSourceWidthCurrentBase($t, $pdf, $expectedRuns);
    },

    'decodes literal ToUnicode bfrange array targets before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapLiteralTargetSourceWidthCurrentBasePdf, $assertCMapLiteralTargetSourceWidthCurrentBase): void {
        [$pdf, $expectedRuns] = $cMapLiteralTargetSourceWidthCurrentBasePdf('array');
        $assertCMapLiteralTargetSourceWidthCurrentBase($t, $pdf, $expectedRuns);
    },
];
