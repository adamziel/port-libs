<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontCidSetIndirectWidthBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "9 beginbfchar\n"
        . "<0057> <0057>\n"
        . "<0069> <0069>\n"
        . "<0064> <0064>\n"
        . "<0065> <0065>\n"
        . "<0042> <0042>\n"
        . "<006C> <006C>\n"
        . "<006F> <006F>\n"
        . "<0063> <0063>\n"
        . "<006B> <006B>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj '
        . '1 0 0 1 118 720 Tm <0042006C006F0063006B> Tj ET';

    $staleCidSetBytes = array_fill(0, 14, 0);
    foreach ([0x42, 0x63, 0x6b, 0x6c, 0x6f] as $cid) {
        $staleCidSetBytes[intdiv($cid, 8)] |= 1 << (7 - ($cid % 8));
    }
    $staleCidSet = implode('', array_map('chr', $staleCidSetBytes));
    $compressedStaleCidSet = gzcompress($staleCidSet);
    if (!is_string($compressedStaleCidSet)) {
        throw new RuntimeException('Unable to compress stale CIDSet fixture.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectCIDSetGenerationSubset /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectCIDSetGenerationSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /FontDescriptor 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /IndirectCIDSetGenerationSubset /Flags 4 /CIDSet 7 1 R >>\nendobj\n"
        . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedStaleCidSet) . " >>\nstream\n{$compressedStaleCidSet}\nendstream\nendobj\n%%EOF";
};

return [
    'does not use stale-generation indirect CIDSet widths before WordPress text gaps' => static function (TestRunner $t) use ($fontCidSetIndirectWidthBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontCidSetIndirectWidthBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('WideBlock', $plainText);
        $t->same("WideBlock\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
