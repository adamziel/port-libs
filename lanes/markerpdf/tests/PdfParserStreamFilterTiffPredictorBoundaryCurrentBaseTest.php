<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterTiffPredictorBoundaryPackSamples = static function (array $samples, int $bitsPerComponent): string {
    if (!in_array($bitsPerComponent, [1, 2, 4, 8, 16], true)) {
        throw new RuntimeException('Focused TIFF predictor fixture uses an unsupported sample width.');
    }

    $bitLength = count($samples) * $bitsPerComponent;
    $bytes = str_repeat("\0", intdiv($bitLength + 7, 8));
    $maxSample = (1 << $bitsPerComponent) - 1;
    foreach ($samples as $sampleIndex => $sample) {
        if (!is_int($sample) || $sample < 0 || $sample > $maxSample) {
            throw new RuntimeException('Focused TIFF predictor fixture uses an invalid sample value.');
        }

        for ($bit = 0; $bit < $bitsPerComponent; $bit++) {
            $sourceShift = $bitsPerComponent - 1 - $bit;
            if ((($sample >> $sourceShift) & 1) === 0) {
                continue;
            }

            $absoluteBit = ($sampleIndex * $bitsPerComponent) + $bit;
            $byteIndex = intdiv($absoluteBit, 8);
            $targetShift = 7 - ($absoluteBit % 8);
            $bytes[$byteIndex] = chr(ord($bytes[$byteIndex]) | (1 << $targetShift));
        }
    }

    return $bytes;
};

$parserStreamFilterTiffPredictorBoundaryEncodeSubByteTiff = static function (
    string $bytes,
    int $bitsPerComponent,
    int $colors,
    int $columns
) use ($parserStreamFilterTiffPredictorBoundaryPackSamples): string {
    if ($bitsPerComponent !== 4 || $colors !== 1 || $columns !== strlen($bytes) * 2) {
        throw new RuntimeException('Focused TIFF predictor fixture expects one fixed-width 4-bit row.');
    }

    $samples = [];
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
        $value = ord($bytes[$offset]);
        $samples[] = ($value >> 4) & 0x0f;
        $samples[] = $value & 0x0f;
    }

    $encodedSamples = [];
    foreach ($samples as $index => $sample) {
        $encodedSamples[] = $index < $colors
            ? $sample
            : ($sample - $samples[$index - $colors]) & 0x0f;
    }

    return $parserStreamFilterTiffPredictorBoundaryPackSamples($encodedSamples, $bitsPerComponent);
};

$parserStreamFilterTiffPredictorBoundaryPdf = static function () use (
    $parserStreamFilterTiffPredictorBoundaryEncodeSubByteTiff
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (TIFF Predictor Four Bit Visible) Tj T* (Packed Nibbles Preserved) Tj ET';
    $columns = strlen($content) * 2;
    $encoded = $parserStreamFilterTiffPredictorBoundaryEncodeSubByteTiff($content, 4, 1, $columns);
    $compressed = gzcompress($encoded);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused TIFF predictor fixture.');
    }

    $visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Packed TIFF Boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 2 /Colors 1 /BitsPerComponent 4 /Columns {$columns} >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'applies packed TIFF predictor samples before WordPress paragraph rendering' => static function (TestRunner $t) use (
        $parserStreamFilterTiffPredictorBoundaryPdf
    ): void {
        $pdf = $parserStreamFilterTiffPredictorBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);

        $t->same(
            "TIFF Predictor Four Bit Visible\nPacked Nibbles Preserved\nVisible After Packed TIFF Boundary",
            $text
        );
        $t->same(
            ['TIFF Predictor Four Bit Visible', 'Packed Nibbles Preserved', 'Visible After Packed TIFF Boundary'],
            $extractor->extractTextLines($pdf)
        );
        $t->same(
            ['TIFF Predictor Four Bit Visible', 'Packed Nibbles Preserved', 'Visible After Packed TIFF Boundary'],
            $extractor->extractTextRuns($pdf)
        );
        $t->same(
            "TIFF Predictor Four Bit Visible\nPacked Nibbles Preserved\nVisible After Packed TIFF Boundary\n",
            $extractor->naiveGetText($pdf)
        );
        $t->true(str_contains($text, 'TIFF Predictor Four Bit Visible'));
        $t->true(str_contains($text, 'Packed Nibbles Preserved'));
        $t->true(str_contains($text, 'Visible After Packed TIFF Boundary'));
        $t->true(!str_contains($text, 'BitsPerComponent'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, 'Predictor 2'));
    },
];
