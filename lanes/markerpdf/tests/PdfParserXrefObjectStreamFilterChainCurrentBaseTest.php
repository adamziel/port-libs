<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefObjectStreamFilterChainCurrentBasePdf = static function (): string {
    $ascii85Encode = static function (string $bytes): string {
        $encoded = '<~';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 4) {
            $chunk = substr($bytes, $offset, 4);
            $chunkLength = strlen($chunk);
            if ($chunkLength < 4) {
                $chunk = str_pad($chunk, 4, "\0");
            }

            $value = unpack('N', $chunk)[1];
            if ($value === 0 && $chunkLength === 4) {
                $encoded .= 'z';
                continue;
            }

            $chars = '';
            for ($index = 0; $index < 5; $index++) {
                $chars = chr(($value % 85) + 33) . $chars;
                $value = intdiv($value, 85);
            }
            $encoded .= substr($chars, 0, $chunkLength + 1);
        }

        return $encoded . '~>';
    };

    $pngSubPredictorEncode = static function (string $bytes, int $columns): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
            $row = substr($bytes, $offset, $columns);
            if (strlen($row) !== $columns) {
                throw new RuntimeException('Focused object-stream predictor row must be fixed-width.');
            }

            $encoded .= "\x01";
            for ($index = 0; $index < $columns; $index++) {
                $left = $index > 0 ? ord($row[$index - 1]) : 0;
                $encoded .= chr((ord($row[$index]) - $left) & 0xff);
            }
        }

        return $encoded;
    };

    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current chained object stream page) Tj T* (Compressed filter operands recovered) Tj ET';
    $orphanFallbackContent = 'BT /F1 12 Tf 72 704 Td (Stale chained fallback leak) Tj ET';

    $currentMembers = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    ];
    $currentObjectData = '';
    $currentHeaderPairs = [];
    $currentMemberIndexes = [];
    foreach ($currentMembers as $objectNumber => $body) {
        $currentHeaderPairs[] = $objectNumber . ' ' . strlen($currentObjectData);
        $currentMemberIndexes[$objectNumber] = count($currentMemberIndexes);
        $currentObjectData .= $body . "\n";
    }
    $currentHeader = implode(' ', $currentHeaderPairs);
    $currentPlainObjectStream = $currentHeader . "\n" . $currentObjectData;
    $predictedCurrentObjectStream = $pngSubPredictorEncode($currentPlainObjectStream, strlen($currentPlainObjectStream));
    $compressedCurrentObjectStream = gzcompress($predictedCurrentObjectStream);
    if (!is_string($compressedCurrentObjectStream)) {
        throw new RuntimeException('Unable to compress focused current object-stream fixture.');
    }
    $encodedCurrentObjectStream = $ascii85Encode($compressedCurrentObjectStream);

    $helperMembers = [
        30 => '[ /ASCII85Decode /FlateDecode ]',
        31 => '[ null << /Predictor 12 /Columns ' . strlen($currentPlainObjectStream) . ' >> ]',
    ];
    $helperObjectData = '';
    $helperHeaderPairs = [];
    $helperMemberIndexes = [];
    foreach ($helperMembers as $objectNumber => $body) {
        $helperHeaderPairs[] = $objectNumber . ' ' . strlen($helperObjectData);
        $helperMemberIndexes[$objectNumber] = count($helperMemberIndexes);
        $helperObjectData .= $body . "\n";
    }
    $helperHeader = implode(' ', $helperHeaderPairs);
    $helperPlainObjectStream = $helperHeader . "\n" . $helperObjectData;

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, "<< /Type /ObjStm /N " . count($currentMembers) . ' /First ' . (strlen($currentHeader) + 1) . " /Filter 30 0 R /DecodeParms 31 0 R /Length " . strlen($encodedCurrentObjectStream) . " >>\nstream\n{$encodedCurrentObjectStream}\nendstream");
    $addObject(7, 0, "<< /Type /ObjStm /N " . count($helperMembers) . ' /First ' . (strlen($helperHeader) + 1) . ' /Length ' . strlen($helperPlainObjectStream) . " >>\nstream\n{$helperPlainObjectStream}\nendstream");
    $addObject(8, 0, "<< /Length " . strlen($orphanFallbackContent) . " >>\nstream\n{$orphanFallbackContent}\nendstream");

    $xrefRows = ''
        . chr(2) . pack('N', 6) . chr($currentMemberIndexes[1])
        . chr(2) . pack('N', 6) . chr($currentMemberIndexes[2])
        . chr(2) . pack('N', 6) . chr($currentMemberIndexes[3])
        . chr(2) . pack('N', 6) . chr($currentMemberIndexes[4])
        . chr(1) . pack('N', $offsets['5:0']) . chr(0)
        . chr(1) . pack('N', $offsets['6:0']) . chr(0)
        . chr(1) . pack('N', $offsets['7:0']) . chr(0)
        . chr(2) . pack('N', 7) . chr($helperMemberIndexes[30])
        . chr(2) . pack('N', 7) . chr($helperMemberIndexes[31]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 /Root 1 0 R /Index [1 7 30 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'recovers xref-selected object streams whose filter chain operands are compressed helpers' => static function (TestRunner $t) use ($parserXrefObjectStreamFilterChainCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefObjectStreamFilterChainCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current chained object stream page', 'Compressed filter operands recovered'], $extractor->extractTextLines($pdf));
        $t->same(['Current chained object stream page', 'Compressed filter operands recovered'], $extractor->extractTextRuns($pdf));
        $t->same("Current chained object stream page\nCompressed filter operands recovered", $text);
        $t->same("Current chained object stream page\nCompressed filter operands recovered\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale chained fallback leak'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, 'DecodeParms'));
        $t->true(!str_contains($text, "\0"));
    },
];
