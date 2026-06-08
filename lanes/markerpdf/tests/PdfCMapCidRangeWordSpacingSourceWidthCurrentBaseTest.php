<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapCidRangeWordSpacingSourceWidthCurrentBasePdf = static function (string $kind): array {
    if ($kind === 'raw-space-remapped') {
        $cMapName = 'RawSpaceRemappedCIDRange-H';
        $baseFont = 'RawSpaceRemappedCIDRange';
        $codeSpace = "<20> <21>\n";
        $cidRange = "<20> <21> 65\n";
        $toUnicodeRows = "<20> <0041>\n"
            . "<21> <0042>\n";
        $content = 'BT /Fcid 12 Tf 24 Tw 1 0 0 1 72 720 Tm <2021> Tj ET';
        $widths = '[65 66 500]';
        $expectedText = 'AB';
    } else {
        $cMapName = 'CidSpaceRangeAdvance-H';
        $baseFont = 'CidSpaceRangeAdvance';
        $codeSpace = "<30> <31>\n";
        $cidRange = "<30> <31> 32\n";
        $toUnicodeRows = "<30> <0043>\n"
            . "<31> <0044>\n";
        $content = 'BT /Fcid 12 Tf 24 Tw 1 0 0 1 72 720 Tm <3031> Tj ET';
        $widths = '[32 33 1000]';
        $expectedText = 'CD';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . $codeSpace
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . $cidRange
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . $codeSpace
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . $toUnicodeRows
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    return [
        "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W {$widths} >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF",
        $expectedText,
    ];
};

return [
    'uses CMap cidrange CIDs rather than raw 0x20 for Type0 word-spacing advance on current base' => static function (
        TestRunner $t
    ) use ($cMapCidRangeWordSpacingSourceWidthCurrentBasePdf): void {
        [$pdf, $expectedText] = $cMapCidRangeWordSpacingSourceWidthCurrentBasePdf('raw-space-remapped');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same([$expectedText], $extractor->extractTextLines($pdf));
        $t->same([$expectedText], $extractor->extractTextRuns($pdf));
        $t->same($expectedText, $plainText);
        $t->same("{$expectedText}\n", $extractor->naiveGetText($pdf));
        $t->same([$expectedText], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 12.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([0.0, 0.0, 12.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'A B'));
        $t->true(!str_contains($plainText, 'RawSpaceRemappedCIDRange'));
        $t->true(!str_contains($plainText, "\0"));
    },

    'keeps Type0 CMap cidrange CID 32 eligible for word-spacing advance on current base' => static function (
        TestRunner $t
    ) use ($cMapCidRangeWordSpacingSourceWidthCurrentBasePdf): void {
        [$pdf, $expectedText] = $cMapCidRangeWordSpacingSourceWidthCurrentBasePdf('cid-space');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same([$expectedText], $extractor->extractTextLines($pdf));
        $t->same([$expectedText], $extractor->extractTextRuns($pdf));
        $t->same($expectedText, $plainText);
        $t->same("{$expectedText}\n", $extractor->naiveGetText($pdf));
        $t->same([$expectedText], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([0.0, 0.0, 48.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'C D'));
        $t->true(!str_contains($plainText, 'CidSpaceRangeAdvance'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
