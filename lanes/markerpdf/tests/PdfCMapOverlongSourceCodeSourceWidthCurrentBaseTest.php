<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapOverlongSourceCodeSourceWidthCurrentBasePdf = static function (string $kind): string {
    if ($kind === 'char') {
        $mappingBlock = "4 begincidchar\n"
            . "<0000000010> 40\n"
            . "<0000000011> 41\n"
            . "<0000000012> 42\n"
            . "<0000000013> 43\n"
            . "<10> 60\n"
            . "<11> 61\n"
            . "<12> 62\n"
            . "<13> 63\n"
            . "endcidchar\n";
        $cMapName = 'OverlongSourceCidChar-H';
        $baseFont = 'OverlongSourceCidChar';
    } else {
        $mappingBlock = "1 begincidrange\n"
            . "<0000000010> <0000000013> 40\n"
            . "<10> <13> 60\n"
            . "endcidrange\n";
        $cMapName = 'OverlongSourceCidRange-H';
        $baseFont = 'OverlongSourceCidRange';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<10> <13>\n"
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
        . "<10> <13>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<10> <0057>\n"
        . "<11> <0069>\n"
        . "<12> <0064>\n"
        . "<13> <0065>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <10111213> Tj '
        . '1 0 0 1 96 720 Tm <10111213> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [16 19 1000 40 43 1000 60 63 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$assertOverlongSourceCodeRecovered = static function (TestRunner $t, string $pdf): void {
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $runs = $extractor->extractTextRuns($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    $t->same(['Wide Wide'], $extractor->extractTextLines($pdf));
    $t->same(['Wide', 'Wide'], $runs);
    $t->same('Wide Wide', $plainText);
    $t->same("Wide Wide\n", $extractor->naiveGetText($pdf));
    $t->same(['Wide', 'Wide'], array_column($spans, 'text'));
    $t->same([0.0, 0.0, 12.0, 12.0], $spans[0]['bbox'] ?? null);
    $t->same([12.0, 0.0, 24.0, 12.0], $spans[1]['bbox'] ?? null);
    $t->same([0.0, 0.0, 24.0, 12.0], $line['bbox'] ?? null);
    $t->true(!str_contains($plainText, 'WideWide'));
    $t->true(!str_contains($plainText, 'OverlongSource'));
    $t->true(!str_contains($plainText, "\0"));
};

return [
    'ignores overlong CMap cidrange source codes before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapOverlongSourceCodeSourceWidthCurrentBasePdf, $assertOverlongSourceCodeRecovered): void {
        $assertOverlongSourceCodeRecovered($t, $cMapOverlongSourceCodeSourceWidthCurrentBasePdf('range'));
    },
    'ignores overlong CMap cidchar source codes before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapOverlongSourceCodeSourceWidthCurrentBasePdf, $assertOverlongSourceCodeRecovered): void {
        $assertOverlongSourceCodeRecovered($t, $cMapOverlongSourceCodeSourceWidthCurrentBasePdf('char'));
    },
];
