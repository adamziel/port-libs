<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapUnderdeclaredCodeSpaceSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "2 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincodespacerange\n"
        . "<0020> <0027>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<0020> <0041>\n"
        . "<0021> <0042>\n"
        . "<0022> <0043>\n"
        . "<0023> <0044>\n"
        . "<0024> <0045>\n"
        . "<0025> <0046>\n"
        . "<0026> <0047>\n"
        . "<0027> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0020002100220023> Tj '
        . '1 0 0 1 132 720 Tm <0024002500260027> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnderdeclaredCodeSpaceSourceWidth /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /UnderdeclaredCodeSpaceSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 35 1000 36 39 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'recovers later valid CMap codespace rows after underdeclared decoys before source-width fallback on current base' => static function (
        TestRunner $t
    ) use ($cMapUnderdeclaredCodeSpaceSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapUnderdeclaredCodeSpaceSourceWidthCurrentBasePdf();
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
        $t->same([60.0, 0.0, 72.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 72.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, 'begincodespacerange'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
