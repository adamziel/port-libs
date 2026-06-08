<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsResourceValueWrapperBoundaryCurrentBasePdf = static function (): string {
    $charProc = "1000 0 d0\n/WrappedGlyphPaint Do /DictGlyphPaint Do\n"
        . "q /Pattern cs /WrappedPattern scn 0 0 12 12 re f Q\n"
        . "BT /Fghost 9 Tf (wrapped resource charproc text leak) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $arrayWrappedXObject = 'BT /Fghost 7 Tf 10 10 Td (array-wrapped Type3 XObject text leak) Tj ET';
    $dictionaryWrappedXObject = 'BT /Fghost 7 Tf 10 10 Td (dictionary-wrapped Type3 XObject text leak) Tj ET';
    $arrayWrappedPattern = 'BT /Fghost 7 Tf 10 10 Td (array-wrapped Type3 pattern text leak) Tj ET';
    $dictionaryWrappedPattern = 'BT /Fghost 7 Tf 10 10 Td (dictionary-wrapped Type3 pattern text leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ResourceValueWrapper "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /O 3 0 R /P 3 0 R /R 3 0 R /S 3 0 R /T 3 0 R "
        . "/V 3 0 R /W 3 0 R /Y 3 0 R >> "
        . "/Resources << /XObject << /WrappedGlyphPaint [5 0 R] /DictGlyphPaint << /Ref 6 0 R >> >> "
        . "/Pattern << /WrappedPattern [7 0 R] /DictPattern << /Ref 8 0 R >> >> "
        . "/Font << /Fghost 1 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($arrayWrappedXObject) . " >>\nstream\n{$arrayWrappedXObject}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($dictionaryWrappedXObject) . " >>\nstream\n{$dictionaryWrappedXObject}\nendstream\nendobj\n"
        . "7 0 obj\n<< /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 12 12] /XStep 12 /YStep 12 /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($arrayWrappedPattern) . " >>\nstream\n{$arrayWrappedPattern}\nendstream\nendobj\n"
        . "8 0 obj\n<< /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 12 12] /XStep 12 /YStep 12 /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($dictionaryWrappedPattern) . " >>\nstream\n{$dictionaryWrappedPattern}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes wrapped Type3 CharProc XObject and Pattern resource values from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsResourceValueWrapperBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsResourceValueWrapperBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'wrapped resource charproc text leak'));
        $t->true(!str_contains($plainText, 'array-wrapped Type3 XObject text leak'));
        $t->true(!str_contains($plainText, 'dictionary-wrapped Type3 XObject text leak'));
        $t->true(!str_contains($plainText, 'array-wrapped Type3 pattern text leak'));
        $t->true(!str_contains($plainText, 'dictionary-wrapped Type3 pattern text leak'));
        $t->true(!str_contains($plainText, 'T3ResourceValueWrapper'));
    },
];
