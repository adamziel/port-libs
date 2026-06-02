<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamTokenBoundaryCurrentBasePdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before nested token boundary) Tj ET\n"
        . "[ [ << /Trap (ignored nested dictionary) /Names [ /A /B ] >> ]\n"
        . "endstream\nendobj\n20 0 obj\n<< /Length 61 >>\nstream\n"
        . "BT /F1 12 Tf 72 650 Td (Nested array fake stream leak) Tj ET\n"
        . "]\n"
        . 'BT /F1 12 Tf 72 700 Td (After nested token boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "5 0 obj\n<< >>\nstream\n{$content}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'keeps nested arrays inside content streams from owning endstream and object tokens' => static function (TestRunner $t) use ($parserStreamTokenBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamTokenBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = ['Before nested token boundary', 'After nested token boundary'];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Before nested token boundary\nAfter nested token boundary", $plainText);
        $t->same("Before nested token boundary\nAfter nested token boundary\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Nested array fake stream leak'));
        $t->true(!str_contains($plainText, '20 0 obj'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
