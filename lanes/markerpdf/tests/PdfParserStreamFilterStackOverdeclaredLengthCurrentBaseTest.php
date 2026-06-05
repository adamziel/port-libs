<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterStackOverdeclaredLengthCurrentBasePdf = static function (): string {
    $firstContent = 'BT /F1 12 Tf 72 720 Td (Overdeclared Flate Stack Before) Tj ET';
    $compressed = gzcompress($firstContent);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused overdeclared Flate stack fixture.');
    }

    $afterContent = 'BT /F1 12 Tf 72 700 Td (Visible After Overdeclared Stack) Tj ET';
    $afterObject = "5 0 obj\n<< /Length " . strlen($afterContent) . " >>\nstream\n{$afterContent}\nendstream\nendobj\n";
    $tail = "\nendstream\nendobj\n{$afterObject}%%EOF";
    $declaredLength = strlen($compressed) + strlen($tail) - 20;
    if ($declaredLength <= strlen($compressed)) {
        throw new RuntimeException('Focused overdeclared Flate stack fixture did not overrun the stream boundary.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "4 0 obj\n<< /Length {$declaredLength} /Filter /FlateDecode >>\nstream\n{$compressed}{$tail}";
};

return [
    'recovers overdeclared native filter-stack lengths before later content objects' => static function (TestRunner $t) use ($parserStreamFilterStackOverdeclaredLengthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackOverdeclaredLengthCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Overdeclared Flate Stack Before', 'Visible After Overdeclared Stack'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Overdeclared Flate Stack Before\nVisible After Overdeclared Stack", $text);
        $t->same("Overdeclared Flate Stack Before\nVisible After Overdeclared Stack\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, 'endobj'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];
