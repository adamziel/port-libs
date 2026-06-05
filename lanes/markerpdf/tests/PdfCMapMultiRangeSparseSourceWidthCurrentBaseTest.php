<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapMultiRangeSparseSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /MultiRangeSparseSourceWidth-H def\n"
        . "2 begincodespacerange\n"
        . "<000000> <000003>\n"
        . "<100000> <100000>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<000000> <100000> 1000\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "2 begincodespacerange\n"
        . "<000000> <000003>\n"
        . "<100000> <100000>\n"
        . "endcodespacerange\n"
        . "5 beginbfchar\n"
        . "<000000> <0041>\n"
        . "<000001> <0042>\n"
        . "<000002> <0043>\n"
        . "<000003> <0044>\n"
        . "<100000> <0045>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <000000000001000002000003> Tj '
        . '1 0 0 1 120 720 Tm <100000> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MultiRangeSparseSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MultiRangeSparseSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1000 1003 1000 1004 1004 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'ranks sparse multi-range CMap codespaces before source-width fallback on current base' => static function (TestRunner $t) use ($cMapMultiRangeSparseSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapMultiRangeSparseSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDE'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'E'], $runs);
        $t->same('ABCDE', $plainText);
        $t->same("ABCDE\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'E'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 51.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 51.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD E'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
