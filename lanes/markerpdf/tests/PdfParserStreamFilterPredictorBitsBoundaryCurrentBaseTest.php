<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterPredictorBitsBoundaryPngSub = static function (string $bytes, int $bytesPerPixel): string {
    $encoded = "\x01";
    for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
        $left = $index >= $bytesPerPixel ? ord($bytes[$index - $bytesPerPixel]) : 0;
        $encoded .= chr((ord($bytes[$index]) - $left) & 0xff);
    }

    return $encoded;
};

$parserStreamFilterPredictorBitsBoundaryPdf = static function () use (
    $parserStreamFilterPredictorBitsBoundaryPngSub
): string {
    $unsupported = 'BT /F1 12 Tf 72 720 Td (Unsupported Predictor Bits Leak) Tj ET';
    while ((strlen($unsupported) % 4) !== 0) {
        $unsupported .= ' ';
    }
    $unsupportedEncoded = $parserStreamFilterPredictorBitsBoundaryPngSub($unsupported, 4);
    $unsupportedCompressed = gzcompress($unsupportedEncoded);

    $valid = 'BT /F1 12 Tf 72 700 Td (Sixteen Bit Predictor Imports) Tj ET';
    while ((strlen($valid) % 2) !== 0) {
        $valid .= ' ';
    }
    $validEncoded = $parserStreamFilterPredictorBitsBoundaryPngSub($valid, 2);
    $validCompressed = gzcompress($validEncoded);

    if (!is_string($unsupportedCompressed) || !is_string($validCompressed)) {
        throw new RuntimeException('Unable to compress focused predictor BitsPerComponent fixture.');
    }

    $visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Predictor Bits Boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Colors 1 /BitsPerComponent 32 /Columns " . intdiv(strlen($unsupported), 4) . " >> /Length " . strlen($unsupportedCompressed) . " >>\nstream\n{$unsupportedCompressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Colors 1 /BitsPerComponent 16 /Columns " . intdiv(strlen($valid), 2) . " >> /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects unsupported predictor BitsPerComponent widths before WordPress text import' => static function (
        TestRunner $t
    ) use ($parserStreamFilterPredictorBitsBoundaryPdf): void {
        $pdf = $parserStreamFilterPredictorBitsBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Sixteen Bit Predictor Imports',
            'Visible After Predictor Bits Boundary',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Unsupported Predictor Bits Leak'));
        $t->true(!str_contains($text, 'BitsPerComponent'));
        $t->true(!str_contains($text, 'Predictor 12'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];
