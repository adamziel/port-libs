<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapMalformedCodespaceRowSourceWidthCurrentBasePdf = static function (string $mappingKind): string {
    if ($mappingKind === 'char') {
        $mappingBlock = "4 begincidchar\n"
            . "<10> 60\n"
            . "<11> 61\n"
            . "<12> 62\n"
            . "<13> 63\n"
            . "endcidchar\n";
        $cMapName = 'MalformedCodespaceRowCidCharSourceWidth-H';
        $baseFont = 'MalformedCodespaceRowCidCharSourceWidth';
    } else {
        $mappingBlock = "1 begincidrange\n"
            . "<10> <13> 60\n"
            . "endcidrange\n";
        $cMapName = 'MalformedCodespaceRowCidRangeSourceWidth-H';
        $baseFont = 'MalformedCodespaceRowCidRangeSourceWidth';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<< /BadCodespace true >>\n"
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
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<10> <0057>\n"
        . "<11> <0069>\n"
        . "<12> <0064>\n"
        . "<13> <0065>\n"
        . "<20> <0054>\n"
        . "<21> <0068>\n"
        . "<22> <0069>\n"
        . "<23> <006E>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <10111213> Tj '
        . '1 0 0 1 96 720 Tm <20212223> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [16 19 250 32 35 1000 60 63 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$assertMalformedCodespaceRowSourceWidth = static function (TestRunner $t, string $pdf): void {
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    $t->same(['Wide Thin'], $extractor->extractTextLines($pdf));
    $t->same(['Wide', 'Thin'], $extractor->extractTextRuns($pdf));
    $t->same('Wide Thin', $plainText);
    $t->same("Wide Thin\n", $extractor->naiveGetText($pdf));
    $t->same(['Wide', 'Thin'], array_column($spans, 'text'));
    $t->same([0.0, 0.0, 12.0, 12.0], $spans[0]['bbox'] ?? null);
    $t->same([12.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
    $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
    $t->true(!str_contains($plainText, 'WideThin'));
    $t->true(!str_contains($plainText, 'BadCodespace'));
    $t->true(!str_contains($plainText, 'MalformedCodespaceRow'));
    $t->true(!str_contains($plainText, "\0"));
};

return [
    'rejects Encoding CMap cidchar rows after malformed codespace rows before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapMalformedCodespaceRowSourceWidthCurrentBasePdf, $assertMalformedCodespaceRowSourceWidth): void {
        $assertMalformedCodespaceRowSourceWidth($t, $cMapMalformedCodespaceRowSourceWidthCurrentBasePdf('char'));
    },

    'rejects Encoding CMap cidrange rows after malformed codespace rows before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapMalformedCodespaceRowSourceWidthCurrentBasePdf, $assertMalformedCodespaceRowSourceWidth): void {
        $assertMalformedCodespaceRowSourceWidth($t, $cMapMalformedCodespaceRowSourceWidthCurrentBasePdf('range'));
    },
];
