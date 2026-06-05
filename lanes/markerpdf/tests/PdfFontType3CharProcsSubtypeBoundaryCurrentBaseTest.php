<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsSubtypeBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /CharProcs << /A 3 0 R >> /Private << /Subtype /Type3 >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'requires top-level Type3 subtype before treating CharProcs streams as glyph programs on current base' => static function (TestRunner $t) use ($type3CharProcsSubtypeBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsSubtypeBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'CharProcs'));
        $t->true(!str_contains($plainText, 'Type3'));
    },
];
