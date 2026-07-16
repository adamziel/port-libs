<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$nestedType0EncodingSourceWidthPdf = static function (): string {
    $actualEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /ActualNestedType0EncodingSourceWidth-H def\n"
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

    $decoyEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DecoyNestedType0EncodingSourceWidth-H def\n"
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
        . "2 0 obj\n"
        . "<< /Type /Font /Subtype /Type0 /BaseFont /NestedType0EncodingSourceWidth "
        . "/DescendantFonts [<< /Subtype /CIDFontType2 /Encoding /DecoyNestedType0EncodingSourceWidth-H >> 4 0 R] "
        . "/Encoding 3 0 R /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($actualEncodingCMap) . " >>\nstream\n{$actualEncodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NestedType0EncodingSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [40 43 1000 60 63 250 32 35 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($decoyEncodingCMap) . " >>\nstream\n{$decoyEncodingCMap}\nendstream\nendobj\n%%EOF";
};

return [
    'uses top-level Type0 Encoding for CMap source widths instead of nested descendant decoy on current base' => static function (
        TestRunner $t
    ) use ($nestedType0EncodingSourceWidthPdf): void {
        $pdf = $nestedType0EncodingSourceWidthPdf();
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
        $t->true(!str_contains($plainText, 'DecoyNestedType0EncodingSourceWidth'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
