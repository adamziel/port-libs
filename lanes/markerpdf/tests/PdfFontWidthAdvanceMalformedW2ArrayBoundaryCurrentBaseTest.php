<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceMalformedW2ArrayBoundaryCurrentBasePdf = static function (string $w2ArraySegment): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/WMode 1 def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 begincidrange\n"
        . "<0001> <0004> 40\n"
        . "<0005> <000A> 50\n"
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
        . "10 beginbfchar\n"
        . "<0001> <0056>\n"
        . "<0002> <0065>\n"
        . "<0003> <0072>\n"
        . "<0004> <0074>\n"
        . "<0005> <0049>\n"
        . "<0006> <006D>\n"
        . "<0007> <0070>\n"
        . "<0008> <006F>\n"
        . "<0009> <0072>\n"
        . "<000A> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fvbad 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '0 -24 Td <00050006000700080009000A> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fvbad 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedVerticalArrayCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /MalformedVerticalArrayCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MalformedVerticalArrayCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 {$w2ArraySegment} 50 55 -250 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects malformed direct CIDFont W2 array triples before vertical styled span bboxes on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceMalformedW2ArrayBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceMalformedW2ArrayBoundaryCurrentBasePdf(
            '[-500 /Bad 880 -500 500 880 -500 500 880 -500 500 880]'
        );
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Vert Import'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import'], $extractor->extractTextRuns($pdf));
        $t->same('Vert Import', $plainText);
        $t->same("Vert Import\n", $extractor->naiveGetText($pdf));
        $t->same(['Vert', 'Import'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 18.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 48.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'VertImport'));
        $t->true(!str_contains($plainText, 'MalformedVerticalArrayCID'));
        $t->true(!str_contains($plainText, 'Fvbad'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects incomplete direct CIDFont W2 array triples before vertical styled span bboxes on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceMalformedW2ArrayBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceMalformedW2ArrayBoundaryCurrentBasePdf(
            '[-500 500 880 -500 500 880 -500 500 880 -500 500]'
        );
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Vert Import'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import'], $extractor->extractTextRuns($pdf));
        $t->same('Vert Import', $plainText);
        $t->same("Vert Import\n", $extractor->naiveGetText($pdf));
        $t->same(['Vert', 'Import'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 18.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 48.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'VertImport'));
        $t->true(!str_contains($plainText, 'MalformedVerticalArrayCID'));
        $t->true(!str_contains($plainText, 'Fvbad'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
