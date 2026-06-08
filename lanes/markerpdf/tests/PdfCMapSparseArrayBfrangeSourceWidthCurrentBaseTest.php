<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapSparseArrayBfrangeSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /SparseArrayBfrangeCIDRange-H def\n"
        . "2 begincodespacerange\n"
        . "<10> <11>\n"
        . "<14> <15>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<10> <15> 32\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "2 begincodespacerange\n"
        . "<10> <11>\n"
        . "<14> <15>\n"
        . "endcodespacerange\n"
        . "1 beginbfrange\n"
        . "<10> <15> [<0041> <0042> <0058> <0059> <0043> <0044>]\n"
        . "endbfrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf 24 Tw '
        . '1 0 0 1 72 720 Tm <1011> Tj '
        . '1 0 0 1 111 720 Tm <1415> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SparseArrayBfrangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SparseArrayBfrangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 32 1000 33 35 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'uses dense ToUnicode bfrange array offsets through sparse CMap codespaces before source-width word spacing on current base' => static function (TestRunner $t) use ($cMapSparseArrayBfrangeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSparseArrayBfrangeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $runs);
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 39.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([39.0, 0.0, 45.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 45.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'XY'));
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
