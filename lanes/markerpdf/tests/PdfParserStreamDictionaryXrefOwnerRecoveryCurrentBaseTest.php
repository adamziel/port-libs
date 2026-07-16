<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamDictionaryXrefOwnerRecoveryCurrentBasePdf = static function (): string {
    $pngSubPredictorEncode = static function (string $bytes, int $columns): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
            $row = substr($bytes, $offset, $columns);
            if (strlen($row) !== $columns) {
                throw new RuntimeException('Focused xref-owner rows must be fixed-width.');
            }

            $encoded .= "\x01";
            for ($index = 0; $index < $columns; $index++) {
                $left = $index > 0 ? ord($row[$index - 1]) : 0;
                $encoded .= chr((ord($row[$index]) - $left) & 0xff);
            }
        }

        return $encoded;
    };

    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref owner stream dictionary) Tj T* (Recovered xref stream helpers) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref owner stream dictionary leak) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
    $freeRow = static fn (): string => chr(0) . pack('N', 0) . chr(255);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(7, 0, '00000');
    $addObject(8, 0, '/FlateDecode');
    $addObject(9, 0, '<< /Predictor 12 /Columns 6 >>');

    $xrefOffset = strlen($pdf);
    $rows = ''
        . $freeRow()
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(1, $offsets['4:0'])
        . $xrefRow(1, $offsets['5:0'])
        . $freeRow()
        . $xrefRow(1, $offsets['7:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0'])
        . $xrefRow(1, $xrefOffset);
    $encodedRows = gzcompress($pngSubPredictorEncode($rows, 6));
    if (!is_string($encodedRows)) {
        throw new RuntimeException('Unable to compress focused xref-owner stream dictionary fixture.');
    }
    $encodedLength = str_pad((string) strlen($encodedRows), 5, '0', STR_PAD_LEFT);
    $pdf = str_replace("7 0 obj\n00000\nendobj\n", "7 0 obj\n{$encodedLength}\nendobj\n", $pdf);

    $pdf .= "10 0 obj\n"
        . "<< /Type /XRef /Size 14 /Root 1 0 R /Index [0 11] /W [1 4 1] /Filter 8 0 R /DecodeParms 9 0 R /Length 7 0 R /Note (99 0 obj fake xref dictionary owner) >>\n"
        . "stream\n{$encodedRows}\nendstream\nendobj\n";

    $addObject(7, 1, '1');
    $addObject(8, 1, '/ASCIIHexDecode');
    $addObject(9, 1, '<< /Predictor /Twelve /Columns 1 >>');
    $addObject(1, 1, '<< /Type /Catalog /Pages 11 0 R /Note (stale latest-generation catalog) >>');
    $addObject(11, 0, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
    $addObject(12, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 13 0 R >>');
    $addObject(13, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'recovers xref-stream dictionary helper owners before stale same-number helpers' => static function (TestRunner $t) use ($parserStreamDictionaryXrefOwnerRecoveryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamDictionaryXrefOwnerRecoveryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Current xref owner stream dictionary', 'Recovered xref stream helpers'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Current xref owner stream dictionary\nRecovered xref stream helpers", $text);
        $t->same("Current xref owner stream dictionary\nRecovered xref stream helpers\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale xref owner stream dictionary leak'));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, '99 0 obj'));
        $t->true(!str_contains($text, "\0"));
    },
];
