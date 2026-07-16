<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsShadingResourceFallbackBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\nq /GlyphShade sh /FunctionShade sh Q\n"
        . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
    $visibleFallback = 'BT /Ft3 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $shadingPayload = "BT /Fghost 7 Tf 0 0 Td (Type3 shading resource text leak) Tj ET\n";
    $functionPayload = "BT /Fghost 7 Tf 0 0 Td (Type3 shading function text leak) Tj ET\n";

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ShadingFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /Shading << /GlyphShade 30 0 R /FunctionShade 32 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "30 0 obj\n<< /ShadingType 4 /ColorSpace /DeviceRGB /BitsPerCoordinate 8 "
        . "/BitsPerComponent 8 /BitsPerFlag 8 /Length " . strlen($shadingPayload) . " >>\n"
        . "stream\n{$shadingPayload}\nendstream\nendobj\n"
        . "31 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1] "
        . "/Length " . strlen($functionPayload) . " >>\n"
        . "stream\n{$functionPayload}\nendstream\nendobj\n"
        . "32 0 obj\n<< /ShadingType 1 /ColorSpace /DeviceRGB /Function 31 0 R >>\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProc shading resource streams from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsShadingResourceFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsShadingResourceFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'GHOST'));
        $t->true(!str_contains($plainText, 'Type3 shading resource text leak'));
        $t->true(!str_contains($plainText, 'Type3 shading function text leak'));
        $t->true(!str_contains($plainText, 'GlyphShade'));
        $t->true(!str_contains($plainText, 'FunctionShade'));
    },
];
