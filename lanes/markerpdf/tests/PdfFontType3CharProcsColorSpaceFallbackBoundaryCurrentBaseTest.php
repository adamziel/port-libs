<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsColorSpaceFallbackBoundaryCurrentBasePdf = static function (): string {
    $charProc = "1000 0 d0\n/GlyphICC CS 0.25 SCN /GlyphSpot cs 0.75 scn\n"
        . "BT /Fghost 9 Tf (direct color-space charproc text leak) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $iccProfilePayload = 'BT /Fghost 9 Tf 10 10 Td (Type3 ICC profile stream text leak) Tj ET';
    $tintFunctionPayload = 'BT /Fghost 9 Tf 10 10 Td (Type3 tint transform stream text leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ColorSpaceFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /ColorSpace << /GlyphICC [/ICCBased 30 0 R] "
        . "/GlyphSpot [/Separation /Spot#20Glyph /DeviceRGB 31 0 R] >> "
        . "/Font << /Fghost 1 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "30 0 obj\n<< /N 3 /Alternate /DeviceRGB /Length " . strlen($iccProfilePayload) . " >>\nstream\n{$iccProfilePayload}\nendstream\nendobj\n"
        . "31 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1 0 1 0 1] /Length " . strlen($tintFunctionPayload) . " >>\n"
        . "stream\n{$tintFunctionPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProc ColorSpace resource streams from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsColorSpaceFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsColorSpaceFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'direct color-space charproc text leak'));
        $t->true(!str_contains($plainText, 'Type3 ICC profile stream text leak'));
        $t->true(!str_contains($plainText, 'Type3 tint transform stream text leak'));
        $t->true(!str_contains($plainText, 'GlyphICC'));
        $t->true(!str_contains($plainText, 'GlyphSpot'));
    },
];
