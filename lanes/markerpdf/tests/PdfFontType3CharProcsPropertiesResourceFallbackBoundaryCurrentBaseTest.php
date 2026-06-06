<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsPropertiesResourceFallbackBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\n/Glyph /FontGlyphProps BDC EMC /Point /StreamGlyphProps DP\n"
        . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $fontPropertyPayload = 'BT /Fghost 8 Tf 0 0 Td (Type3 font property stream text leak) Tj ET';
    $streamPropertyPayload = 'BT /Fghost 8 Tf 0 0 Td (Type3 stream property text leak) Tj ET';
    $nestedPropertyPayload = 'BT /Fghost 8 Tf 0 0 Td (nested Type3 property text leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3PropertiesFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /Properties << /FontGlyphProps 30 0 R >> /Font << /Fghost 1 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Resources << /Properties << /StreamGlyphProps 31 0 R >> "
        . "/Font << /Fghost 1 0 R >> >> /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($fontPropertyPayload) . " >>\nstream\n{$fontPropertyPayload}\nendstream\nendobj\n"
        . "31 0 obj\n<< /PrivateNested 32 0 R /Length " . strlen($streamPropertyPayload) . " >>\nstream\n{$streamPropertyPayload}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($nestedPropertyPayload) . " >>\nstream\n{$nestedPropertyPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 CharProc Properties resource streams from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsPropertiesResourceFallbackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsPropertiesResourceFallbackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'GHOST'));
        $t->true(!str_contains($plainText, 'Type3 font property stream text leak'));
        $t->true(!str_contains($plainText, 'Type3 stream property text leak'));
        $t->true(!str_contains($plainText, 'nested Type3 property text leak'));
        $t->true(!str_contains($plainText, 'FontGlyphProps'));
        $t->true(!str_contains($plainText, 'StreamGlyphProps'));
    },
];
