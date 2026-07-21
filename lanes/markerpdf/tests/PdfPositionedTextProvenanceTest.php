<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$positionedProvenancePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\nbegincmap\n"
        . "1 begincodespacerange\n<0000><FFFF>\nendcodespacerange\n"
        . "1 beginbfchar\n<0079><0079>\nendbfchar\n"
        . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    $content = "BT\n"
        . "/F1 12 Tf 1 0 0 1 72 720 Tm "
        . "/Span << /ActualText (y) >> BDC ( ) Tj EMC\n"
        . "/F0 12 Tf 1 0 0 1 90 720 Tm <0079> Tj\n"
        . "/F1 12 Tf 1 0 0 1 108 720 Tm (y) Tj\n"
        . "ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/Resources << /Font << /F0 5 0 R /F1 8 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ABCDEF+Wingdings "
        . "/Encoding /Identity-H /DescendantFonts [6 0 R] /ToUnicode 7 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ABCDEF+Wingdings "
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> "
        . "/FontDescriptor 9 0 R /DW 500 >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "9 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Wingdings /Flags 4 "
        . "/FontBBox [0 0 1000 1000] /ItalicAngle 0 /Ascent 800 /Descent -200 "
        . "/CapHeight 700 /StemV 80 >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves Type0 and ActualText provenance on positioned runs' => static function (
        TestRunner $t
    ) use ($positionedProvenancePdf): void {
        $runs = array_values(array_filter(
            (new PdfTextExtractor())->extractPositionedTextRuns($positionedProvenancePdf()),
            static fn (array $run): bool => ($run['text'] ?? null) === 'y'
        ));

        $t->same(3, count($runs));
        $t->same('actual-text-replacement', $runs[0]['textOrigin'] ?? null);
        $t->same(true, $runs[0]['actualTextPaintedWhitespaceOnly'] ?? null);
        $t->same('Wingdings', $runs[1]['baseFont'] ?? null);
        $t->same('Type0', $runs[1]['fontSubtype'] ?? null);
        $t->same(4, $runs[1]['fontDescriptorFlags'] ?? null);
        $t->same(true, $runs[1]['fontSymbolic'] ?? null);
        $t->same(false, array_key_exists('textOrigin', $runs[2]));
        $t->same(false, array_key_exists('actualTextPaintedWhitespaceOnly', $runs[2]));
    },
];
