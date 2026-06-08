<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapSparseOverflowCidRangeSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /SparseOverflowCIDRangeSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<1000> <1003>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<1000> <1003> 32\n"
        . "endcidrange\n"
        . "1 begincidrange\n"
        . "<1000> <10FF> 65534\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<1000> <1003>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<1000> <0041>\n"
        . "<1001> <0042>\n"
        . "<1002> <0043>\n"
        . "<1003> <0044>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf 24 Tw 1 0 0 1 72 720 Tm <1000100110021003> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SparseOverflowCIDRangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SparseOverflowCIDRangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [32 35 1000 65534 65535 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects sparse CMap cidrange overflow before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapSparseOverflowCidRangeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSparseOverflowCidRangeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD'], $runs);
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 72.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([0.0, 0.0, 72.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'A BCD'));
        $t->true(!str_contains($plainText, 'SparseOverflowCIDRangeSourceWidth'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
