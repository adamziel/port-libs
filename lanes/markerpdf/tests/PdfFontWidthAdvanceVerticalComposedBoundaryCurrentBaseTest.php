<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceVerticalComposedBoundaryCurrentBasePdf = static function (): array {
    $sourceHex = str_repeat('0001', 100);
    $expectedText = str_repeat('A', 100);

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/WMode 1 def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0001> <0001> 40\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <0041>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = "BT /Fvbound 12 Tf 1 0 0 1 72 720 Tm <{$sourceHex}> Tj ET";

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fvbound 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalComposedAdvanceCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /VerticalComposedAdvanceCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalComposedAdvanceCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 40 -100000 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

    return [$pdf, $expectedText];
};

return [
    'bounds composed vertical CIDFont W2 advances before styled span bboxes on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceVerticalComposedBoundaryCurrentBasePdf): void {
        [$pdf, $expectedText] = $fontWidthAdvanceVerticalComposedBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same([$expectedText], $extractor->extractTextLines($pdf));
        $t->same([$expectedText], $extractor->extractTextRuns($pdf));
        $t->same($expectedText, $plainText);
        $t->same($expectedText . "\n", $extractor->naiveGetText($pdf));
        $t->same([$expectedText], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 1200.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 12.0, 1200.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'VerticalComposedAdvanceCID'));
        $t->true(!str_contains($plainText, 'Fvbound'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
