<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontType0CidSetVerticalSpacingCurrentBasePdf = static function (): string {
    $content = 'BT /Fv 12 Tf '
        . '1 0 0 1 72 720 Tm <0056006500720074> Tj '
        . '1 0 0 1 72 672 Tm <0049006D0070006F00720074> Tj '
        . '1 0 0 1 96 720 Tm <0044006100740061> Tj '
        . '1 0 0 1 96 672 Tm <0046006C006F0077> Tj ET';

    $cidSetBytes = array_fill(0, 16, 0);
    foreach ([0x44, 0x46, 0x49, 0x56, 0x61, 0x64, 0x65, 0x6c, 0x6d, 0x6f, 0x70, 0x72, 0x74, 0x77] as $cid) {
        $cidSetBytes[intdiv($cid, 8)] |= 1 << (7 - ($cid % 8));
    }
    $cidSet = implode('', array_map('chr', $cidSetBytes));
    $compressedCidSet = gzcompress($cidSet);
    if (!is_string($compressedCidSet)) {
        throw new RuntimeException('Unable to compress focused Type0 UCS2 vertical CIDSet fixture.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DirectUcs2VerticalCIDSet /Encoding /UniJIS-UCS2-V /DescendantFonts [4 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DirectUcs2VerticalCIDSet /CIDSystemInfo << /Registry (Adobe) /Ordering (Adobe-Japan1) /Supplement 6 >> /DW2 [880 -1000] /FontDescriptor 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /DirectUcs2VerticalCIDSet /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";
};

return [
    'uses predefined UCS2 vertical Type0 CMap source width before CIDSet spacing on current base' => static function (TestRunner $t) use ($fontType0CidSetVerticalSpacingCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType0CidSetVerticalSpacingCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['VertImport', 'DataFlow'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import', 'Data', 'Flow'], $extractor->extractTextRuns($pdf));
        $t->same("VertImport\nDataFlow", $plainText);
        $t->same("VertImport\nDataFlow\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Vert Import'));
        $t->true(!str_contains($plainText, 'Data Flow'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
