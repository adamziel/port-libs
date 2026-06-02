<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$tokenStreamObjectBoundaryPdf = static function (): string {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Token Boundary) Tj ET';
    $privatePayload = 'BT /F1 12 Tf 72 700 Td (PieceInfo Token Stream Leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PieceInfo << /WP << /Private 4 0 R >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Note (919 0 obj fake owner before stream) /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'keeps direct stream owners token-aware when object-like text appears before stream data' => static function (TestRunner $t) use ($tokenStreamObjectBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $tokenStreamObjectBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Visible Token Boundary', $plainText);
        $t->same(['Visible Token Boundary'], $extractor->extractTextLines($pdf));
        $t->same(['Visible Token Boundary'], $extractor->extractTextRuns($pdf));
        $t->same("Visible Token Boundary\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'PieceInfo Token Stream Leak'));
        $t->true(!str_contains($plainText, '919 0 obj'));
    },
];
