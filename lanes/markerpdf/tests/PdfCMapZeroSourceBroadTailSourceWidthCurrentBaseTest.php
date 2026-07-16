<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapZeroSourceBroadTailSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /ZeroSourceBroadTail-H def\n"
        . "2 begincodespacerange\n"
        . "<00> <7F>\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 begincidchar\n"
        . "<00> 300\n"
        . "<41> 301\n"
        . "endcidchar\n"
        . "1 begincidrange\n"
        . "<0045> <0047> 400\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "2 begincodespacerange\n"
        . "<00> <7F>\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<00> <005A>\n"
        . "<41> <0041>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <00410045> Tj '
        . '1 0 0 1 105 720 Tm <0046> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ZeroSourceBroadTail /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ZeroSourceBroadTail /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [300 301 250 400 402 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'keeps explicit zero source rows before unmapped broad CMap source-width tails on current base' => static function (TestRunner $t) use ($cMapZeroSourceBroadTailSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapZeroSourceBroadTailSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ZAE F'], $extractor->extractTextLines($pdf));
        $t->same(['ZAE', 'F'], $runs);
        $t->same('ZAE F', $plainText);
        $t->same("ZAE F\n", $extractor->naiveGetText($pdf));
        $t->same(['ZAE', 'F'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 18.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([18.0, 0.0, 30.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 30.0, 12.0], $line['bbox'] ?? null);
        $t->true($plainText !== 'AE F');
        $t->true(!str_contains($plainText, "\0"));
        $t->true(!str_contains($plainText, 'ZeroSourceBroadTail'));
    },
];
