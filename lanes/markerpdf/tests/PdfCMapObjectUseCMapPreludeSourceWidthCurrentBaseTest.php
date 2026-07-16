<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapObjectUseCMapPreludeSourceWidthCurrentBasePdf = static function (): string {
    $objectBaseCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<10> <13> 40\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $namedExtraCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /NamedExtraSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<20> <23>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<20> <23> 60\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $derivedEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/NamedExtraSourceWidth-H usecmap\n"
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
        . '1 0 0 1 132 720 Tm <20212223> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /HybridUseCMapSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /HybridUseCMapSourceWidth-H /UseCMap 7 0 R /Length " . strlen($derivedEncodingCMap) . " >>\nstream\n{$derivedEncodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /HybridUseCMapSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [40 43 1000 60 63 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /Length " . strlen($objectBaseCMap) . " >>\nstream\n{$objectBaseCMap}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /CMap /CMapName /NamedExtraSourceWidth-H /Length " . strlen($namedExtraCMap) . " >>\nstream\n{$namedExtraCMap}\nendstream\nendobj\n%%EOF";
};

return [
    'keeps local named usecmap source widths after object-valued UseCMap preludes on current base' => static function (
        TestRunner $t
    ) use ($cMapObjectUseCMapPreludeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapObjectUseCMapPreludeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Wide Thin'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Thin'], $runs);
        $t->same('Wide Thin', $plainText);
        $t->same("Wide Thin\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Thin'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'WideThin'));
        $t->true(!str_contains($plainText, 'NamedExtraSourceWidth-H'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
