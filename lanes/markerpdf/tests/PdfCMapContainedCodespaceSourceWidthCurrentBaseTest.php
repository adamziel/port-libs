<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapContainedCodespaceSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /ContainedCodespaceSourceWidth-H def\n"
        . "2 begincodespacerange\n"
        . "<000000> <FF0000>\n"
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
        . "<000000> <FF0000>\n"
        . "<100000> <100000>\n"
        . "endcodespacerange\n"
        . "5 beginbfchar\n"
        . "<000000> <0041>\n"
        . "<010000> <0042>\n"
        . "<020000> <0043>\n"
        . "<030000> <0044>\n"
        . "<100000> <0045>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <000000010000020000030000> Tj '
        . '1 0 0 1 120 720 Tm <100000> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ContainedCodespaceSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ContainedCodespaceSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /W [1000 1003 1000 1016 1016 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'uses enclosing CMap codespace for contained source-width range ranking on current base' => static function (
        TestRunner $t
    ) use ($cMapContainedCodespaceSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapContainedCodespaceSourceWidthCurrentBasePdf();
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
        $t->true(!str_contains($plainText, 'ContainedCodespaceSourceWidth'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
