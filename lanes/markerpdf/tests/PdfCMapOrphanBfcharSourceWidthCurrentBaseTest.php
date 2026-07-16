<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$orphanBfcharSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /OrphanBfcharSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "3 beginbfchar\n"
        . "<0009>\n"
        . "<0001> <0041>\n"
        . "<0002> <0042>\n"
        . "<0003> <0043>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <00010002> Tj '
        . '1 0 0 1 96 720 Tm <0003> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Pages /Kids [5 0 R] /Count 1 >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 7 0 R /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OrphanBfcharSourceWidth /Encoding /Identity-H /DescendantFonts [3 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OrphanBfcharSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 2 1000 3 3 250] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'recovers later ToUnicode bfchar rows after orphan singleton source before source-width fallback on current base' => static function (TestRunner $t) use ($orphanBfcharSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $orphanBfcharSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABC'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'C'], $runs);
        $t->same('ABC', $plainText);
        $t->same("ABC\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'C'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 24.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([24.0, 0.0, 27.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 27.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, "\0"));
        $t->true(!str_contains($plainText, "\u{0001}"));
        $t->true(!str_contains($plainText, 'beginbfchar'));
    },
];
