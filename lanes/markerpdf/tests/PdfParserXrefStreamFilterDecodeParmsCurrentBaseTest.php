<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamFilterDecodeParmsCurrentBasePdf = static function (): string {
    $asciiHexEncode = static fn (string $bytes): string => strtoupper(bin2hex($bytes)) . '>';

    $pngSubPredictorEncode = static function (string $bytes, int $columns): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
            $row = substr($bytes, $offset, $columns);
            if (strlen($row) !== $columns) {
                throw new RuntimeException('Focused xref-stream predictor rows must be fixed-width.');
            }

            $encoded .= "\x01";
            for ($index = 0; $index < $columns; $index++) {
                $left = $index > 0 ? ord($row[$index - 1]) : 0;
                $encoded .= chr((ord($row[$index]) - $left) & 0xff);
            }
        }

        return $encoded;
    };

    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref DecodeParms page) Tj T* (Predictor rows selected current base) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale unpredicted generation page) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $addObject(1, 1, '<< /Type /Catalog /Pages 20 1 R /Note (stale latest-generation catalog) >>');
    $addObject(20, 1, '<< /Type /Pages /Kids [21 1 R] /Count 1 >>');
    $addObject(21, 1, '<< /Type /Page /Parent 20 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 22 1 R >>');
    $addObject(22, 1, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $xrefRows = '';
    foreach ([1, 2, 3, 4, 5] as $objectNumber) {
        $xrefRows .= chr(1) . pack('N', $offsets[$objectNumber . ':0']) . chr(0);
    }

    $predictedXrefRows = $pngSubPredictorEncode($xrefRows, 6);
    $compressedXrefRows = gzcompress($predictedXrefRows);
    if (!is_string($compressedXrefRows)) {
        throw new RuntimeException('Unable to compress focused xref-stream fixture.');
    }
    $encodedXrefRows = $asciiHexEncode($compressedXrefRows);

    $xrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 12 /Columns 6 >> ] /Length ' . strlen($encodedXrefRows) . " >>\n"
        . "stream\n{$encodedXrefRows}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'applies xref stream filter DecodeParms before current-base object selection' => static function (TestRunner $t) use ($parserXrefStreamFilterDecodeParmsCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamFilterDecodeParmsCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current xref DecodeParms page', 'Predictor rows selected current base'], $extractor->extractTextLines($pdf));
        $t->same(['Current xref DecodeParms page', 'Predictor rows selected current base'], $extractor->extractTextRuns($pdf));
        $t->same("Current xref DecodeParms page\nPredictor rows selected current base", $text);
        $t->same("Current xref DecodeParms page\nPredictor rows selected current base\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale unpredicted generation page'));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'Predictor 12'));
        $t->true(!str_contains($text, "\0"));
    },
];
