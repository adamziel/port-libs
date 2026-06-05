<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$largeCidRangeSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LargeCIDRangeSourceWidth-H def\n"
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
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LargeCIDRangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LargeCIDRangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [7144 7147 1000 7148 7151 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'uses large CID CMap ranges past eager expansion cap before source-width fallback on current base' => static function (TestRunner $t) use ($largeCidRangeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $largeCidRangeSourceWidthCurrentBasePdf();
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
