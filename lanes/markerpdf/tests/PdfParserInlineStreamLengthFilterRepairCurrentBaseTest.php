<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineStreamLengthFilterRepairPdf = static function (): string {
    $decodedContent = 'BT /F1 12 Tf 72 720 Td (Inline stream length repair) Tj ET' . "\n"
        . "endstream\nendobj\n"
        . "20 0 obj\n<< /Producer (Stream-owned fake object) >>\nendobj\n"
        . 'BT /F1 12 Tf 72 700 Td (Filter payload stays current) Tj ET';
    $compressed = gzcompress($decodedContent, 0);
    if (!is_string($compressed) || !str_contains($compressed, "\nendstream\nendobj\n20 0 obj")) {
        throw new RuntimeException('Unable to build focused inline stream length/filter fixture.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter /FlateDecode /Length 5 0 R >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n" . strlen($compressed) . "\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'repairs filtered stream boundaries when indirect Length follows inline payload bytes' => static function (TestRunner $t) use ($inlineStreamLengthFilterRepairPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineStreamLengthFilterRepairPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $expected = ['Inline stream length repair', 'Filter payload stays current'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Inline stream length repair\nFilter payload stays current", $plainText);
        $t->same("Inline stream length repair\nFilter payload stays current\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Stream-owned fake object'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
