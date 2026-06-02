<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontType0VerticalUseCMapCidSetCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf '
        . '1 0 0 1 72 720 Tm <0041> Tj '
        . '1 0 0 1 72 702 Tm <0057006F00720064> Tj '
        . '1 0 0 1 96 720 Tm <0056006500720074> Tj '
        . '1 0 0 1 96 672 Tm <0049006D0070006F00720074> Tj ET';

    $cidSetBytes = str_repeat("\0", 15);
    foreach ([0x44, 0x49, 0x56, 0x57, 0x64, 0x65, 0x6d, 0x6f, 0x70, 0x72, 0x74] as $cid) {
        $byteIndex = intdiv($cid, 8);
        $cidSetBytes[$byteIndex] = chr(ord($cidSetBytes[$byteIndex]) | (1 << (7 - ($cid % 8))));
    }
    $compressedCidSet = gzcompress($cidSetBytes);
    if (!is_string($compressedCidSet)) {
        throw new RuntimeException('Unable to compress focused Type0 vertical UseCMap CIDSet fixture.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /Type0VerticalUseCMapSubset /Encoding 3 0 R /DescendantFonts [4 0 R] >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /Type0VerticalUseCMapDerived-V /UseCMap /Identity-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /Type0VerticalUseCMapSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /FontDescriptor 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /Type0VerticalUseCMapSubset /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";
};

return [
    'inherits predefined Type0 vertical UseCMap codespace before CIDSet width grouping on current base' => static function (TestRunner $t) use ($fontType0VerticalUseCMapCidSetCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType0VerticalUseCMapCidSetCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A Word', 'VertImport'], $extractor->extractTextLines($pdf));
        $t->same(['A', 'Word', 'Vert', 'Import'], $extractor->extractTextRuns($pdf));
        $t->same("A Word\nVertImport", $plainText);
        $t->same("A Word\nVertImport\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'AWord'));
        $t->true(!str_contains($plainText, 'Vert Import'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
