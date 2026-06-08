<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapCidSetZeroPaddingSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /CidSetZeroPaddingFallback-H def\n"
        . "2 begincodespacerange\n"
        . "<00> <FF>\n"
        . "<0000> <00FF>\n"
        . "endcodespacerange\n"
        . "16 begincidchar\n"
        . "<41> 65\n"
        . "<42> 66\n"
        . "<43> 67\n"
        . "<44> 68\n"
        . "<45> 69\n"
        . "<46> 70\n"
        . "<47> 71\n"
        . "<48> 72\n"
        . "<0041> 1000\n"
        . "<0042> 1001\n"
        . "<0043> 1002\n"
        . "<0044> 1003\n"
        . "<0045> 2000\n"
        . "<0046> 2001\n"
        . "<0047> 2002\n"
        . "<0048> 2003\n"
        . "endcidchar\n"
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
        . "<41> <0041>\n"
        . "<42> <0042>\n"
        . "<43> <0043>\n"
        . "<44> <0044>\n"
        . "<45> <0045>\n"
        . "<46> <0046>\n"
        . "<47> <0047>\n"
        . "<48> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $cidSetBytes = array_fill(0, 10, 0);
    foreach (range(65, 72) as $cid) {
        $cidSetBytes[intdiv($cid, 8)] |= 1 << (7 - ($cid % 8));
    }
    $cidSet = implode('', array_map('chr', $cidSetBytes));
    $compressedCidSet = gzcompress($cidSet);
    if (!is_string($compressedCidSet)) {
        throw new RuntimeException('Unable to compress focused CIDSet source-width fixture.');
    }

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CidSetZeroPaddingFallback /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CidSetZeroPaddingFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] /FontDescriptor 7 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /FontDescriptor /FontName /CidSetZeroPaddingFallback /Flags 4 /CIDSet 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";
};

return [
    'falls back from zero-padded Encoding CMap CIDs absent from CIDSet to present suffix CIDs on current base' => static function (
        TestRunner $t
    ) use ($cMapCidSetZeroPaddingSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidSetZeroPaddingSourceWidthCurrentBasePdf();
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
        $t->true(($spans[0]['bbox'] ?? null) !== [0.0, 0.0, 24.0, 12.0]);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, 'CidSetZeroPaddingFallback'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
