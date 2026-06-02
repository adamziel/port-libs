<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontCidSetVerticalSurrogateWidthReviewCurrentBasePdf = static function (): string {
    $cmap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<0001> <2067D83DDE002069>\n"
        . "<0002> <0057006F00720064>\n"
        . "<0003> <0056006500720074>\n"
        . "<0004> <004A006F0069006E>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <0001> Tj 1 0 0 1 72 702 Tm <0002> Tj '
        . '1 0 0 1 96 720 Tm <0003> Tj 1 0 0 1 96 708 Tm <0004> Tj ET';
    $cidSet = "\x38";
    $compressedCidSet = gzcompress($cidSet);
    if (!is_string($compressedCidSet)) {
        throw new RuntimeException('Unable to compress focused vertical surrogate CIDSet fixture.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDSetVerticalSurrogateSubset /Encoding /Identity-V /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDSetVerticalSurrogateSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /DW2 [880 -1000] /FontDescriptor 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /CIDSetVerticalSurrogateSubset /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";
};

return [
    'keeps vertical CIDSet fallback gaps for surrogate ToUnicode glyph boundaries on current base' => static function (TestRunner $t) use ($fontCidSetVerticalSurrogateWidthReviewCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontCidSetVerticalSurrogateWidthReviewCurrentBasePdf();
        $expected = "\u{2067}\u{1F600}\u{2069} Word";
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([$expected, 'VertJoin'], $extractor->extractTextLines($pdf));
        $t->same(["\u{2067}\u{1F600}\u{2069}", 'Word', 'Vert', 'Join'], $extractor->extractTextRuns($pdf));
        $t->same($expected . "\nVertJoin", $plainText);
        $t->true(str_contains($plainText, "\u{1F600}"));
        $t->true(str_contains($plainText, "\u{2067}") && str_contains($plainText, "\u{2069}"));
        $t->true(!str_contains($plainText, "\u{2069}Word"));
        $t->true(!str_contains($plainText, 'Vert Join'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
