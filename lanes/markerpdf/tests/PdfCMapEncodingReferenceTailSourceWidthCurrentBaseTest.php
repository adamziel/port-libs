<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapEncodingReferenceTailSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /MalformedEncodingRefTailSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<10> <23>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<10> <13> 60\n"
        . "endcidrange\n"
        . "1 begincidrange\n"
        . "<20> <23> 32\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<10> <23>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<10> <004A>\n"
        . "<11> <006F>\n"
        . "<12> <0069>\n"
        . "<13> <006E>\n"
        . "<20> <0053>\n"
        . "<21> <0061>\n"
        . "<22> <0066>\n"
        . "<23> <0065>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <10111213> Tj '
        . '1 0 0 1 120 720 Tm <20212223> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedEncodingRefTailSourceWidth /Encoding 3 0 R 9 /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MalformedEncodingRefTailSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [16 19 1000 32 35 250 60 63 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects malformed Type0 Encoding reference tails before CMap source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapEncodingReferenceTailSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapEncodingReferenceTailSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['JoinSafe'], $extractor->extractTextLines($pdf));
        $t->same(['Join', 'Safe'], $extractor->extractTextRuns($pdf));
        $t->same('JoinSafe', $plainText);
        $t->same("JoinSafe\n", $extractor->naiveGetText($pdf));
        $t->same(['Join', 'Safe'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Join Safe'));
        $t->true(!str_contains($plainText, 'MalformedEncodingRefTailSourceWidth'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
