<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsMetadataStreamBoundaryCurrentBasePdf = static function (): string {
    $charProc = "650 0 d0\nBT /Fghost 8 Tf (direct Type3 metadata charproc text leak) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $fontMetadataPayload = 'BT /Fghost 8 Tf 0 0 Td (Type3 font metadata stream text leak) Tj ET';
    $charProcMetadataPayload = 'BT /Fghost 8 Tf 0 0 Td (Type3 CharProc metadata stream text leak) Tj ET';
    $nestedMetadataPayload = 'BT /Fghost 8 Tf 0 0 Td (nested Type3 metadata stream text leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3MetadataBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Metadata 29 0 R /Encoding /WinAnsiEncoding "
        . "/CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
        . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> >>\nendobj\n"
        . "3 0 obj\n<< /Metadata 30 0 R /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "29 0 obj\n<< /Subtype /XML /Length " . strlen($fontMetadataPayload) . " >>\nstream\n{$fontMetadataPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Subtype /XML /PrivateNested 31 0 R /Length " . strlen($charProcMetadataPayload) . " >>\nstream\n{$charProcMetadataPayload}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($nestedMetadataPayload) . " >>\nstream\n{$nestedMetadataPayload}\nendstream\nendobj\n%%EOF";
};

return [
    'excludes Type3 font and CharProc metadata sidecar streams from fallback WordPress text on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsMetadataStreamBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsMetadataStreamBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'direct Type3 metadata charproc text leak'));
        $t->true(!str_contains($plainText, 'Type3 font metadata stream text leak'));
        $t->true(!str_contains($plainText, 'Type3 CharProc metadata stream text leak'));
        $t->true(!str_contains($plainText, 'nested Type3 metadata stream text leak'));
        $t->true(!str_contains($plainText, 'T3MetadataBoundary'));
    },
];
