<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsExtGStateSoftMaskBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\nq /Glyph#20Mask gs Q\n"
        . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $softMaskGroupPayload = "q /NestedMaskPaint Do Q\n"
        . "BT /Fghost 9 Tf 0 0 Td (Type3 ExtGState soft mask group text leak) Tj ET\n";
    $nestedMaskPayload = "BT /Fghost 9 Tf 0 0 Td (nested Type3 soft mask form text leak) Tj ET\n";
    $transferFunctionPayload = "BT /Fghost 9 Tf 0 0 Td (Type3 soft mask transfer text leak) Tj ET\n";

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ExtGStateSoftMask "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
        . "/Resources << /ExtGState << /Glyph#20Mask 20 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /ExtGState /ca 0.5 /SMask 21 0 R >>\nendobj\n"
        . "21 0 obj\n<< /Type /Mask /S /Luminosity /G 22 0 R /TR 24 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] "
        . "/Resources << /Font << /Fghost 30 0 R >> /XObject << /NestedMaskPaint 23 0 R >> >> "
        . "/Length " . strlen($softMaskGroupPayload) . " >>\nstream\n{$softMaskGroupPayload}\nendstream\nendobj\n"
        . "23 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] "
        . "/Resources << /Font << /Fghost 30 0 R >> >> /Length " . strlen($nestedMaskPayload) . " >>\n"
        . "stream\n{$nestedMaskPayload}\nendstream\nendobj\n"
        . "24 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1] /Length " . strlen($transferFunctionPayload) . " >>\n"
        . "stream\n{$transferFunctionPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
};

return [
    'excludes Type3 ExtGState soft-mask streams from stream-only fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsExtGStateSoftMaskBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsExtGStateSoftMaskBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'GHOST'));
        $t->true(!str_contains($plainText, 'Type3 ExtGState soft mask group text leak'));
        $t->true(!str_contains($plainText, 'nested Type3 soft mask form text leak'));
        $t->true(!str_contains($plainText, 'Type3 soft mask transfer text leak'));
        $t->true(!str_contains($plainText, 'Glyph Mask'));
        $t->true(!str_contains($plainText, 'NestedMaskPaint'));
    },
];
