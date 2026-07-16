<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapLazyCidRangeSourceWidthFallbackCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LazyCIDRangeSourceWidthFallback-H def\n"
        . "1 begincodespacerange\n"
        . "<200000> <200007>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<000000> <200007> 1000\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<200000> <200007>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<200000> <0041>\n"
        . "<200001> <0042>\n"
        . "<200002> <0043>\n"
        . "<200003> <0044>\n"
        . "<200004> <0045>\n"
        . "<200005> <0046>\n"
        . "<200006> <0047>\n"
        . "<200007> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <000000200000000000200001000000200002000000200003> Tj '
        . '1 0 0 1 132 720 Tm <000000200004000000200005000000200006000000200007> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LazyCIDRangeSourceWidthFallback /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LazyCIDRangeSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1000 1003 1000 1004 1007 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'uses lazy CID-range source widths when zero padding hides direct eager mappings on current base' => static function (
        TestRunner $t
    ) use ($cMapLazyCidRangeSourceWidthFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapLazyCidRangeSourceWidthFallbackCurrentBasePdf();
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
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, 'LazyCIDRangeSourceWidthFallback'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
