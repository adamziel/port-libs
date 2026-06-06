<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapLazyBfrangeZeroPaddedSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfrange\n"
        . "<1000> <3007> <0041>\n"
        . "endbfrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <00002000000020010000200200002003> Tj '
        . '1 0 0 1 120 720 Tm <00002004000020050000200600002007> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LazyBfrangeZeroPaddedSourceWidth /Encoding /MissingCustom-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LazyBfrangeZeroPaddedSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [8192 8195 1000 8196 8199 250] >>\nendobj\n%%EOF";
};

return [
    'uses lazy ToUnicode bfrange suffixes inside zero-padded source widths on current base' => static function (TestRunner $t) use ($cMapLazyBfrangeZeroPaddedSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapLazyBfrangeZeroPaddedSourceWidthCurrentBasePdf();
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
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
