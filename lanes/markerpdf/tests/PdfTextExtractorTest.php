<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\FontStyleCleaner;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithStreams = static function (array $streams): string {
    $pdf = "%PDF-1.4\n";
    foreach (array_values($streams) as $index => $content) {
        $objectNumber = $index + 1;
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf . "%%EOF";
};

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

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length;) {
        $repeatLength = 1;
        while (
            $offset + $repeatLength < $length
            && $bytes[$offset + $repeatLength] === $bytes[$offset]
            && $repeatLength < 128
        ) {
            $repeatLength++;
        }

        if ($repeatLength >= 4) {
            $encoded .= chr(257 - $repeatLength) . $bytes[$offset];
            $offset += $repeatLength;
            continue;
        }

        $literalStart = $offset;
        $literalLength = 0;
        while ($offset < $length && $literalLength < 128) {
            $lookaheadRepeat = 1;
            while (
                $offset + $lookaheadRepeat < $length
                && $bytes[$offset + $lookaheadRepeat] === $bytes[$offset]
                && $lookaheadRepeat < 128
            ) {
                $lookaheadRepeat++;
            }
            if ($lookaheadRepeat >= 4) {
                break;
            }
            $offset++;
            $literalLength++;
        }
        $encoded .= chr($literalLength - 1) . substr($bytes, $literalStart, $literalLength);
    }

    return $encoded . chr(128);
};

$lzwPackCodes = static function (array $codes): string {
    $bits = '';
    foreach ($codes as $code) {
        if (!is_int($code) || $code < 0 || $code > 511) {
            throw new RuntimeException('Focused LZW fixture uses 9-bit code segments only.');
        }

        for ($shift = 8; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $byte = substr($bits, $offset, 8);
        if (strlen($byte) < 8) {
            $byte = str_pad($byte, 8, '0');
        }
        $encoded .= chr(bindec($byte));
    }

    return $encoded;
};

$lzwPackPdfCodes = static function (array $codes, int $earlyChange = 1): string {
    $earlyChange = $earlyChange === 0 ? 0 : 1;
    $dictionary = [];
    $nextCode = 258;
    $codeSize = 9;
    $bits = '';

    $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
        $dictionary = [];
        for ($code = 0; $code < 256; $code++) {
            $dictionary[$code] = chr($code);
        }
        $nextCode = 258;
        $codeSize = 9;
    };

    $writeCode = static function (int $code) use (&$bits, &$codeSize): void {
        if ($code < 0 || $code >= (1 << $codeSize)) {
            throw new RuntimeException('Focused LZW fixture code does not fit the current code size.');
        }

        for ($shift = $codeSize - 1; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    };

    $resetDictionary();
    $previous = null;
    foreach ($codes as $code) {
        if (!is_int($code) || $code < 0 || $code > 4095) {
            throw new RuntimeException('Focused LZW fixture codes must be 12-bit integers.');
        }

        $writeCode($code);
        if ($code === 256) {
            $resetDictionary();
            $previous = null;
            continue;
        }

        if ($code === 257) {
            break;
        }

        if (isset($dictionary[$code])) {
            $entry = $dictionary[$code];
        } elseif ($code === $nextCode && $previous !== null) {
            $entry = $previous . $previous[0];
        } else {
            throw new RuntimeException('Focused LZW fixture references an unknown dictionary code.');
        }

        if ($previous !== null && $nextCode < 4096) {
            $dictionary[$nextCode] = $previous . $entry[0];
            $nextCode++;
            if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                $codeSize++;
            }
        }
        $previous = $entry;
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $byte = substr($bits, $offset, 8);
        if (strlen($byte) < 8) {
            $byte = str_pad($byte, 8, '0');
        }
        $encoded .= chr(bindec($byte));
    }

    return $encoded;
};

$lzwLiteralEncode = static function (string $bytes, int $earlyChange = 1) use ($lzwPackPdfCodes): string {
    return $lzwPackPdfCodes([
        256,
        ...array_map('ord', str_split($bytes)),
        257,
    ], $earlyChange);
};

$pngPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Test predictor rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$pngUpPredictorEncode = static function (array $rows): string {
    $encoded = '';
    $rowLength = null;
    $previous = null;
    foreach ($rows as $row) {
        if (!is_string($row)) {
            throw new RuntimeException('PNG Up predictor rows must be strings.');
        }
        $rowLength ??= strlen($row);
        if (strlen($row) !== $rowLength) {
            throw new RuntimeException('PNG Up predictor rows must be fixed-width.');
        }

        $previous ??= str_repeat("\0", $rowLength);
        $encoded .= "\x02";
        for ($index = 0; $index < $rowLength; $index++) {
            $encoded .= chr((ord($row[$index]) - ord($previous[$index])) & 0xff);
        }
        $previous = $row;
    }

    return $encoded;
};

$tiffPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Test predictor rows must be fixed-width.');
        }

        $raw = $row;
        for ($index = 1; $index < $columns; $index++) {
            $row[$index] = chr((ord($raw[$index]) - ord($raw[$index - 1])) & 0xff);
        }
        $encoded .= $row;
    }

    return $encoded;
};

$toUnicodeCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode CMap fixture text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$objectStreamXrefPdf = static function (): string {
    $content = 'BT /Fcid 12 Tf 72 720 Td <004F0062006A006500630074002000730074007200650061006D00200070006100670065> Tj T* /Fplain 12 Tf (Plain Direct Font) Tj ET';
    $phantom = 'BT /Fplain 12 Tf 72 720 Td (Phantom stale object stream text) Tj ET';
    $members = [
        1 => '<< /Type /Catalog /Pages 10 0 R >>',
        10 => '<< /Type /Page /Contents 9 0 R >>',
        11 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R /Fplain 8 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type0 /BaseFont /ObjectStreamIdentity /Encoding /Identity-H >>',
    ];

    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    $memberIndex = 0;
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = $memberIndex;
        $objectData .= $body . "\n";
        $memberIndex++;
    }

    $objectStreamPlain = implode(' ', $headerPairs) . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);

    $xrefRows = '';
    foreach ([11, 2, 3, 4] as $objectNumber) {
        $xrefRows .= chr(2) . chr(6) . chr($memberIndexes[$objectNumber]);
    }
    $compressedXref = gzcompress($xrefRows);

    return "%PDF-1.5\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /ObjStm /N " . count($members) . " /First " . (strlen(implode(' ', $headerPairs)) + 1) . " /Filter /FlateDecode /Length " . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($phantom) . " >>\nstream\n{$phantom}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XRef /Size 12 /Root 11 0 R /Index [11 1 2 3] /W [1 1 1] /Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\nstream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n0\n%%EOF";
};

$xrefHybridPrevFreeGenerationPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale previous xref page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Hybrid current page) Tj T* (Free generation guard) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n" . $xrefRow(0, 65535, 'f')
        . "1 2\n" . $xrefRow($offsets['1:0']) . $xrefRow($offsets['2:0'])
        . "4 2\n" . $xrefRow($offsets['4:0']) . $xrefRow($offsets['5:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>',
        8 => '<< /Type /Page /Parent 2 0 R /Contents 9 0 R >>',
    ];
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    $memberIndex = 0;
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = $memberIndex;
        $objectData .= $body . "\n";
        $memberIndex++;
    }
    $objectStreamPlain = implode(' ', $headerPairs) . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);

    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen(implode(' ', $headerPairs)) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefRows = ''
        . chr(2) . chr(6) . chr($memberIndexes[1])
        . chr(2) . chr(6) . chr($memberIndexes[2])
        . chr(0) . chr(0) . chr(1)
        . chr(2) . chr(6) . chr($memberIndexes[8]);
    $compressedXref = gzcompress($xrefRows);
    $xrefStreamOffset = strlen($pdf);
    $addObject(7, 0, '<< /Type /XRef /Size 10 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 8 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\nstream\n{$compressedXref}\nendstream");

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "4 1\n" . $xrefRow(0, 1, 'f')
        . "6 2\n" . $xrefRow($offsets['6:0']) . $xrefRow($offsets['7:0'])
        . "9 1\n" . $xrefRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R /Prev {$previousXrefOffset} /XRefStm {$xrefStreamOffset} >>\n"
        . "startxref\n{$latestXrefOffset}\n%%EOF";

    return $pdf;
};

$objectStreamFreeEntryReusePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale freed object stream page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current object generation page) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $members = [
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 5 0 R >>',
    ];
    $objectData = '';
    $headerPairs = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $objectData .= $body . "\n";
    }
    $objectStreamPlain = implode(' ', $headerPairs) . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen(implode(' ', $headerPairs)) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(7, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow($offsets['5:0'])
        . $xrefRow($offsets['6:0'])
        . $xrefRow($offsets['7:0'])
        . $xrefRow($offsets['8:0'])
        . $xrefRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$trailerRootGenerationRecoveryPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale catalog page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Recovered trailer root page) Tj T* (Generation one catalog) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . "trailer\n<< /Size 14 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(10, 1, '<< /Type /Catalog /Pages 11 1 R >>');
    $addObject(11, 1, '<< /Type /Pages /Kids [12 1 R] /Count 1 >>');
    $addObject(12, 1, '<< /Type /Page /Parent 11 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 13 1 R >>');
    $addObject(13, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "10 4\n"
        . $xrefRow($offsets['10:1'], 1)
        . $xrefRow($offsets['11:1'], 1)
        . $xrefRow($offsets['12:1'], 1)
        . $xrefRow($offsets['13:1'], 1)
        . "trailer\n<< /Size 14 /Root 10 1 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$latestXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefStreamIndexWidthCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref stream page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref stream page) Tj T* (Width default import) Tj ET';

    $pdf = "%PDF-1.5\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        return $offset;
    };

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $fontOffset = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $staleContentOffset = $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $staleRows = pack('N', $staleCatalogOffset)
        . pack('N', $stalePagesOffset)
        . pack('N', $stalePageOffset)
        . pack('N', $fontOffset)
        . pack('N', $staleContentOffset);
    $staleCompressed = gzcompress($staleRows);
    $addObject(20, 0, '<< /Type /XRef /Size 21 /Index [1 5] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $currentContentOffset = $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . pack('N', 0)
        . pack('N', $currentCatalogOffset)
        . pack('N', $currentPagesOffset)
        . pack('N', $currentPageOffset)
        . pack('N', $fontOffset)
        . pack('N', $currentContentOffset);
    $currentCompressed = gzcompress($currentRows);
    $currentXrefOffset = strlen($pdf);
    $pdf .= "6 0 obj\n"
        . '<< /Type /XRef /Size 6 /Root 1 0 R /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
        . "stream\n{$currentCompressed}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevStreamGenerationRepairPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale previous stream generation page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation stream page) Tj T* (Offset repaired generation) Tj ET';

    $pdf = "%PDF-1.5\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
        return $offset;
    };
    $xrefStreamRows = static function (array $offsets, int $generationWidth = 1): string {
        $rows = '';
        foreach ($offsets as $offset) {
            $rows .= chr(1) . pack('N', $offset);
            if ($generationWidth > 0) {
                $rows .= chr(0);
            }
        }

        return $rows;
    };

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $fontOffset = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleContentOffset = $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousRows = $xrefStreamRows([
        $staleCatalogOffset,
        $stalePagesOffset,
        $stalePageOffset,
        $fontOffset,
        $staleContentOffset,
    ]);
    $previousCompressed = gzcompress($previousRows);
    if (!is_string($previousCompressed)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }

    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $currentPagesOffset = $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 1 R >>');
    $currentContentOffset = $addObject(5, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = $xrefStreamRows([
        $currentCatalogOffset,
        $currentPagesOffset,
        $currentPageOffset,
        $currentContentOffset,
    ], 0);
    $currentCompressed = gzcompress($currentRows);
    if (!is_string($currentCompressed)) {
        throw new RuntimeException('Unable to compress current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 3 5 1] /W [1 4 0] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
        . "stream\n{$currentCompressed}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$startxrefObjectStreamRebuildPdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current startxref page) Tj T* (Object stream rebuild guard) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale appended object stream page) Tj ET';

    $pdf = "%PDF-1.5\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $offsets = [];
    $offsets[1] = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $offsets[2] = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $offsets[3] = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $offsets[4] = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $offsets[5] = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $stalePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>';
    $objectStreamPlain = "3 0\n{$stalePage}\n";
    $compressedObjectStream = gzcompress($objectStreamPlain);
    $staleObjectStreamOffset = $addObject(7, 0, '<< /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $staleContentOffset = $addObject(6, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $staleXrefRows = ''
        . chr(2) . pack('N', 7) . chr(0)
        . chr(1) . pack('N', $staleContentOffset) . chr(0)
        . chr(1) . pack('N', $staleObjectStreamOffset) . chr(0);
    $compressedXref = gzcompress($staleXrefRows);
    $addObject(20, 0, '<< /Type /XRef /Size 21 /Root 1 0 R /Index [3 1 6 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\nstream\n{$compressedXref}\nendstream");

    return $pdf;
};

$linearizedHintTableFallbackPdf = static function (): string {
    $hintContent = 'BT /F1 12 Tf 72 720 Td (Linearized hint stale leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Linearized current fallback) Tj T* (Hint table boundary) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Linearized 1 /L LLLLLLLLLL /H [ HHHHHHHHHA HHHHHHHHHB ] /O 4 /E EEEEEEEEEE /N 1 /T TTTTTTTTTT >>');
    $addObject(2, 0, "<< /Length " . strlen($hintContent) . " >>\nstream\n{$hintContent}\nendstream");
    $addObject(3, 0, '<< /Type /Catalog /NeedsRendering false >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 5\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . "trailer\n<< /Size 5 /Root 3 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return strtr($pdf, [
        'LLLLLLLLLL' => sprintf('%010d', strlen($pdf)),
        'HHHHHHHHHA' => sprintf('%010d', $offsets[2]),
        'HHHHHHHHHB' => sprintf('%010d', $offsets[3] - $offsets[2]),
        'EEEEEEEEEE' => sprintf('%010d', $offsets[3]),
        'TTTTTTTTTT' => sprintf('%010d', $xrefOffset),
    ]);
};

$encryptedPreflightPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Encrypted cleartext leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <00> /U <00> /P -4 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";
};

return [
    'extracts literal and array text operators from content streams' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td (Hello \\(WP\\)) Tj [(Data) 120 ( Liberation)] TJ ET";
        $runs = (new PdfTextExtractor())->extractTextRuns($pdfWithContent($content));
        $t->same(['Hello (WP)', 'Data Liberation'], $runs);
    },
    'uses marked-content ActualText and Alt replacements before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $actualTextUtf16 = '<FEFF005200650073006F0075007200630065006400200057005000200042006C006F0063006B0073>';
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Intro) Tj T* '
            . '/Span << /ActualText (Accessible WordPress Import) /Alt (Ignored Alt Text) >> BDC (Glyph Noise) Tj EMC T* '
            . '/Span /PActual BDC (Resource Glyph Noise) Tj EMC T* ET '
            . '/Figure << /Alt (Figure: migration workflow screenshot) >> BDC q /Im1 Do Q EMC '
            . 'BT /F1 12 Tf 72 672 Td /Span << /Alt (Inline Alt Summary) >> BDC (Inline Alt Glyphs) Tj EMC ET';
        $imageNoise = 'BT /F1 12 Tf 72 704 Td (Raster Alt Noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /PActual 6 0 R >> /XObject << /Im1 5 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($imageNoise) . " >>\nstream\n{$imageNoise}\nendstream\nendobj\n"
            . "6 0 obj\n<< /ActualText {$actualTextUtf16} >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Visible Intro',
            'Accessible WordPress Import',
            'Resourced WP Blocks',
            'Figure: migration workflow screenshot',
            'Inline Alt Summary',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Visible Intro',
            'Accessible WordPress Import',
            'Resourced WP Blocks',
            'Figure: migration workflow screenshot',
            'Inline Alt Summary',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Visible Intro\nAccessible WordPress Import\nResourced WP Blocks\nFigure: migration workflow screenshot\nInline Alt Summary", $plainText);
        $t->same($plainText . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Glyph Noise'));
        $t->true(!str_contains($plainText, 'Resource Glyph Noise'));
        $t->true(!str_contains($plainText, 'Ignored Alt Text'));
        $t->true(!str_contains($plainText, 'Raster Alt Noise'));
    },
    'extracts flate encoded content streams' => static function (TestRunner $t): void {
        $content = 'BT <48656c6c6f> Tj ET';
        $compressed = gzcompress($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n%%EOF";
        $t->same('Hello', (new PdfTextExtractor())->extractPlainText($pdf));
    },
    'extracts ASCIIHex stream filters before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (ASCII Hex Import) Tj ET';
        $encoded = chunk_split(strtoupper(bin2hex($content)), 16, "\n") . '>';
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (Stacked Filter Import) Tj ET');
        $stacked = strtoupper(bin2hex($compressed)) . '>';
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /ASCIIHexDecode /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same('ASCII Hex Import', $extractor->extractPlainText($pdf));
        $t->same('Stacked Filter Import', $extractor->extractPlainText($stackedPdf));
    },
    'extracts ASCII85 stream filters before WordPress paragraph rendering' => static function (TestRunner $t) use ($ascii85Encode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (ASCII85 Import) Tj T* (Clean Blocks) Tj ET';
        $encoded = chunk_split($ascii85Encode($content), 22, "\n");
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (A85 Flate Import) Tj ET');
        $stacked = $ascii85Encode($compressed);
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /A85 /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $zeroContent = "BT /F1 12 Tf 72 720 Td (Zero\0\0\0\0Group) Tj ET";
        $zeroEncoded = str_replace('!!!!', 'z', $ascii85Encode($zeroContent));
        $zeroPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /A85 /Length " . strlen($zeroEncoded) . " >>\nstream\n{$zeroEncoded}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("ASCII85 Import\nClean Blocks", $extractor->extractPlainText($pdf));
        $t->same('A85 Flate Import', $extractor->extractPlainText($stackedPdf));
        $t->same("Zero\0\0\0\0Group", $extractor->extractPlainText($zeroPdf));
        $t->same('', $extractor->extractPlainText("%PDF-1.4\n1 0 obj\n<< /Filter /A85 >>\nstream\n!~>\nendstream\nendobj\n%%EOF"));
    },
    'extracts RunLength stream filters before WordPress paragraph rendering' => static function (TestRunner $t) use ($runLengthEncode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (RunLength Import) Tj T* (Native Blocks) Tj ET';
        $encoded = $runLengthEncode($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /RunLengthDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (RL Flate Import) Tj ET');
        $stacked = $runLengthEncode($compressed);
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /RL /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $repeatedContent = 'BT /F1 12 Tf 72 720 Td (Queue: AAAAAA) Tj ET';
        $repeatedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /RL /Length " . strlen($runLengthEncode($repeatedContent)) . " >>\nstream\n" . $runLengthEncode($repeatedContent) . "\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("RunLength Import\nNative Blocks", $extractor->extractPlainText($pdf));
        $t->same('RL Flate Import', $extractor->extractPlainText($stackedPdf));
        $t->same('Queue: AAAAAA', $extractor->extractPlainText($repeatedPdf));
        $t->same('', $extractor->extractPlainText("%PDF-1.4\n1 0 obj\n<< /Filter /RunLengthDecode >>\nstream\n\x04bad\nendstream\nendobj\n%%EOF"));
    },
    'uses stream Length boundaries for ASCIIHex and RunLength filter decoding' => static function (TestRunner $t) use ($runLengthEncode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Visible endstream Word) Tj ET';
        $encoded = $runLengthEncode($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /RunLengthDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $indirectLengthPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /RL /Length 2 0 R >>\nstream\n{$encoded}\nendstream\nendobj\n"
            . "2 0 obj\n" . strlen($encoded) . "\nendobj\n%%EOF";

        $stackedContent = 'BT /F1 12 Tf 72 720 Td (ASCIIHex RunLength Stack) Tj T* (Length Safe) Tj ET';
        $stacked = chunk_split(strtoupper(bin2hex($runLengthEncode($stackedContent))), 20, "\r\n");
        $stackedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ /ASCIIHexDecode /RunLengthDecode ] /Length " . strlen($stacked) . " >>\n"
            . "stream\r\n{$stacked}\r\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same('Visible endstream Word', $extractor->extractPlainText($pdf));
        $t->same('Visible endstream Word', $extractor->extractPlainText($indirectLengthPdf));
        $t->same("ASCIIHex RunLength Stack\nLength Safe", $extractor->extractPlainText($stackedPdf));
    },
    'recovers stale stream Length with bounded endstream terminators before WordPress rendering' => static function (TestRunner $t): void {
        $shortContent = 'BT /F1 12 Tf 72 720 Td (Recovered Length Stream) Tj T* (Endstream Fallback) Tj ET';
        $shortCompressed = gzcompress($shortContent);
        if (!is_string($shortCompressed)) {
            throw new RuntimeException('Unable to compress stale short length fixture.');
        }
        $shortPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /FlateDecode /Length " . (strlen($shortCompressed) - 5) . " >>\nstream\n{$shortCompressed}\nendstream\nendobj\n%%EOF";

        $indirectContent = 'BT /F1 12 Tf 72 720 Td (Indirect Length Recovery) Tj ET';
        $indirectCompressed = gzcompress($indirectContent);
        if (!is_string($indirectCompressed)) {
            throw new RuntimeException('Unable to compress stale indirect length fixture.');
        }
        $indirectPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /Length 2 0 R >>\nstream\n{$indirectCompressed}\nendstream\nendobj\n"
            . "2 0 obj\n" . (strlen($indirectCompressed) - 3) . "\nendobj\n%%EOF";

        $rawContent = 'BT /F1 12 Tf 72 720 Td (Raw Length Recovery) Tj ET';
        $rawPdf = "%PDF-1.4\n1 0 obj\n<< /Length " . (strlen($rawContent) - 4) . " >>\nstream\n{$rawContent}\nendstream\nendobj\n%%EOF";

        $missingLengthContent = 'BT /F1 12 Tf 72 720 Td (Literal endstream Word) Tj T* (Missing Length Tail) Tj ET';
        $missingLengthPdf = "%PDF-1.4\n1 0 obj\n<< >>\nstream\n{$missingLengthContent}\nendstream\nendobj\n%%EOF";

        $validLengthContent = 'BT /F1 12 Tf 72 720 Td (Visible endstream Word) Tj T* (Length Still Wins) Tj ET';
        $validLengthPdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($validLengthContent) . " >>\nstream\n{$validLengthContent}\nendstream\nendobj\n%%EOF";

        $unsupportedNoise = 'BT /F1 12 Tf 72 720 Td (Unsupported stale Length leak) Tj ET';
        $unsupportedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /Crypt /Length " . (strlen($unsupportedNoise) - 5) . " >>\nstream\n{$unsupportedNoise}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("Recovered Length Stream\nEndstream Fallback", $extractor->extractPlainText($shortPdf));
        $t->same(['Recovered Length Stream', 'Endstream Fallback'], $extractor->extractTextRuns($shortPdf));
        $t->same("Recovered Length Stream\nEndstream Fallback\n", $extractor->naiveGetText($shortPdf));
        $t->same('Indirect Length Recovery', $extractor->extractPlainText($indirectPdf));
        $t->same('Raw Length Recovery', $extractor->extractPlainText($rawPdf));
        $t->same("Literal endstream Word\nMissing Length Tail", $extractor->extractPlainText($missingLengthPdf));
        $t->same("Visible endstream Word\nLength Still Wins", $extractor->extractPlainText($validLengthPdf));
        $t->same('', $extractor->extractPlainText($unsupportedPdf));
        $t->true(!str_contains($extractor->naiveGetText($unsupportedPdf), 'Unsupported stale Length leak'));
    },
    'extracts LZW stream filters before WordPress paragraph rendering' => static function (TestRunner $t) use ($lzwPackCodes, $lzwLiteralEncode): void {
        $prefix = 'BT /F1 12 Tf 72 720 Td (';
        $suffix = ') Tj T* (Stack Ready) Tj ET';
        $encoded = $lzwPackCodes([
            256,
            ...array_map('ord', str_split($prefix)),
            256,
            65,
            66,
            258,
            260,
            256,
            ...array_map('ord', str_split($suffix)),
            257,
        ]);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /LZWDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (LZW Flate Import) Tj ET');
        $t->true(is_string($compressed), 'LZW-to-Flate fixture should compress.');
        $stacked = $lzwLiteralEncode($compressed);
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /LZW /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("ABABABA\nStack Ready", $extractor->extractPlainText($pdf));
        $t->same(['ABABABA', 'Stack Ready'], $extractor->extractTextRuns($pdf));
        $t->same('LZW Flate Import', $extractor->extractPlainText($stackedPdf));
        $t->same('', $extractor->extractPlainText("%PDF-1.4\n1 0 obj\n<< /Filter /LZWDecode >>\nstream\n\x80\nendstream\nendobj\n%%EOF"));
    },
    'applies LZW DecodeParms boundaries before WordPress paragraph rendering' => static function (TestRunner $t) use ($lzwLiteralEncode, $tiffPredictorEncode): void {
        $longText = str_repeat('Boundary ', 32) . 'EarlyChange Zero';
        $earlyChangeContent = 'BT /F1 12 Tf 72 720 Td (' . $longText . ') Tj ET';
        $earlyChangeEncoded = $lzwLiteralEncode($earlyChangeContent, 0);
        $earlyChangePdf = "%PDF-1.4\n1 0 obj\n<< /Filter /LZWDecode /DecodeParms << /EarlyChange 0 >> /Length " . strlen($earlyChangeEncoded) . " >>\nstream\n{$earlyChangeEncoded}\nendstream\nendobj\n%%EOF";

        $predictorContent = 'BT /F1 12 Tf 72 720 Td (LZW Predictor Import) Tj T* (DecodeParms Rows) Tj ET';
        $predicted = $tiffPredictorEncode($predictorContent, strlen($predictorContent));
        $predictorEncoded = $lzwLiteralEncode($predicted);
        $predictorPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /LZWDecode /DecodeParms << /Predictor 2 /Columns " . strlen($predictorContent) . " >> /Length " . strlen($predictorEncoded) . " >>\nstream\n{$predictorEncoded}\nendstream\nendobj\n%%EOF";

        $invalidContent = 'BT /F1 12 Tf 72 720 Td (Invalid EarlyChange Leak) Tj ET';
        $invalidEncoded = $lzwLiteralEncode($invalidContent);
        $invalidPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /LZWDecode /DecodeParms << /EarlyChange 2 >> /Length " . strlen($invalidEncoded) . " >>\nstream\n{$invalidEncoded}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same($longText, $extractor->extractPlainText($earlyChangePdf));
        $t->same("LZW Predictor Import\nDecodeParms Rows", $extractor->extractPlainText($predictorPdf));
        $t->same('', $extractor->extractPlainText($invalidPdf));
    },
    'skips DCTDecode JPEG streams before WordPress text extraction' => static function (TestRunner $t): void {
        $visibleContent = 'BT /F1 12 Tf 72 720 Td (DCT Boundary Import) Tj T* (Clean Text Stream) Tj ET';
        $jpegLikeNoise = "\xff\xd8\xff\xe0JFIF\0BT /F1 12 Tf 72 720 Td (Raster JPEG Noise) Tj ET\xff\xd9";
        $fallbackPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Filter /DCTDecode /ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 1 /Height 1 /Length " . strlen($jpegLikeNoise) . " >>\nstream\n{$jpegLikeNoise}\nendstream\nendobj\n"
            . "%%EOF";

        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Filter /DCT /ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 1 /Height 1 /Length " . strlen($jpegLikeNoise) . " >>\nstream\n{$jpegLikeNoise}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $fallbackText = $extractor->extractPlainText($fallbackPdf);
        $pageText = $extractor->extractPlainText($pagePdf);

        $t->same("DCT Boundary Import\nClean Text Stream", $fallbackText);
        $t->same(['DCT Boundary Import', 'Clean Text Stream'], $extractor->extractTextRuns($fallbackPdf));
        $t->same("DCT Boundary Import\nClean Text Stream", $pageText);
        $t->same("DCT Boundary Import\nClean Text Stream\n", $extractor->naiveGetText($pagePdf));
        $t->true(!str_contains($fallbackText, 'Raster JPEG Noise'));
        $t->true(!str_contains($pageText, 'Raster JPEG Noise'));
    },
    'skips CCITTFaxDecode image-only filters before WordPress text parsing' => static function (TestRunner $t): void {
        $visibleBefore = 'BT /F1 12 Tf 72 720 Td (CCITT Boundary) Tj ET';
        $visibleAfter = 'BT /F1 12 Tf 72 688 Td (Native Import) Tj ET';
        $faxNoise = 'BT /F1 12 Tf 72 704 Td (Scanned Fax Noise) Tj ET';
        $ccfNoise = 'BT /F1 12 Tf 72 672 Td (CCF Alias Noise) Tj ET';

        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visibleBefore) . " >>\nstream\n{$visibleBefore}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 1728 /Rows 1 /BlackIs1 true >> /Length " . strlen($faxNoise) . " >>\nstream\n{$faxNoise}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Filter /CCF /DecodeParms << /K 0 /Columns 8 /Rows 1 /EncodedByteAlign true >> /Length " . strlen($ccfNoise) . " >>\nstream\n{$ccfNoise}\nendstream\nendobj\n"
            . "%%EOF";

        $fallbackVisible = 'BT /F1 12 Tf 72 720 Td (Fallback Visible Text) Tj ET';
        $fallbackNoise = 'BT /F1 12 Tf 72 704 Td (Fallback Fax Noise) Tj ET';
        $fallbackPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($fallbackVisible) . " >>\nstream\n{$fallbackVisible}\nendstream\nendobj\n"
            . "2 0 obj\n<< /K 0 /Columns 8 /Rows 1 >>\nendobj\n"
            . "3 0 obj\n<< /Filter /CCF /DecodeParms 2 0 R /Length " . strlen($fallbackNoise) . " >>\nstream\n{$fallbackNoise}\nendstream\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $pageText = $extractor->extractPlainText($pagePdf);
        $fallbackText = $extractor->extractPlainText($fallbackPdf);

        $t->same("CCITT Boundary\nNative Import", $pageText);
        $t->same(['CCITT Boundary', 'Native Import'], $extractor->extractTextRuns($pagePdf));
        $t->same("CCITT Boundary\nNative Import\n", $extractor->naiveGetText($pagePdf));
        $t->true(!str_contains($pageText, 'Scanned Fax Noise'));
        $t->true(!str_contains($pageText, 'CCF Alias Noise'));
        $t->same('Fallback Visible Text', $fallbackText);
        $t->same(['Fallback Visible Text'], $extractor->extractTextRuns($fallbackPdf));
        $t->true(!str_contains($fallbackText, 'Fallback Fax Noise'));
    },
    'skips JPXDecode and JBIG2Decode image filters before WordPress text parsing' => static function (TestRunner $t): void {
        $visibleBefore = 'BT /F1 12 Tf 72 720 Td (JPX JBIG2 Boundary) Tj ET';
        $visibleAfter = 'BT /F1 12 Tf 72 688 Td (Native Import) Tj ET';
        $jpxNoise = "\x00\x00\x00\x0cjP  \r\n\x87\nBT /F1 12 Tf 72 704 Td (Raster JPX Noise) Tj ET";
        $jbig2Noise = "\x97JB2\r\n\x1a\nBT /F1 12 Tf 72 672 Td (Raster JBIG2 Noise) Tj ET";

        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visibleBefore) . " >>\nstream\n{$visibleBefore}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Filter /JPXDecode /ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 1 /Height 1 /Length " . strlen($jpxNoise) . " >>\nstream\n{$jpxNoise}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Filter /JBIG2Decode /DecodeParms 8 0 R /Width 1 /Height 1 /Length " . strlen($jbig2Noise) . " >>\nstream\n{$jbig2Noise}\nendstream\nendobj\n"
            . "8 0 obj\n<< /JBIG2Globals 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
            . "%%EOF";

        $fallbackVisible = 'BT /F1 12 Tf 72 720 Td (Fallback JPX Visible) Tj ET';
        $stackedNoise = 'BT /F1 12 Tf 72 704 Td (Stacked JPX Noise) Tj ET';
        $stackedEncodedNoise = strtoupper(bin2hex($stackedNoise)) . '>';
        $fallbackPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($fallbackVisible) . " >>\nstream\n{$fallbackVisible}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Filter /JBIG2Decode /Length " . strlen($jbig2Noise) . " >>\nstream\n{$jbig2Noise}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Filter [ /ASCIIHexDecode /JPXDecode ] /Length " . strlen($stackedEncodedNoise) . " >>\nstream\n{$stackedEncodedNoise}\nendstream\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $pageText = $extractor->extractPlainText($pagePdf);
        $fallbackText = $extractor->extractPlainText($fallbackPdf);

        $t->same("JPX JBIG2 Boundary\nNative Import", $pageText);
        $t->same(['JPX JBIG2 Boundary', 'Native Import'], $extractor->extractTextRuns($pagePdf));
        $t->same("JPX JBIG2 Boundary\nNative Import\n", $extractor->naiveGetText($pagePdf));
        $t->true(!str_contains($pageText, 'Raster JPX Noise'));
        $t->true(!str_contains($pageText, 'Raster JBIG2 Noise'));
        $t->same('Fallback JPX Visible', $fallbackText);
        $t->same(['Fallback JPX Visible'], $extractor->extractTextRuns($fallbackPdf));
        $t->true(!str_contains($fallbackText, 'Raster JBIG2 Noise'));
        $t->true(!str_contains($fallbackText, 'Stacked JPX Noise'));
    },
    'resolves indirect stream filters and benign DecodeParms for WordPress extraction' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Indirect Filter Import) Tj T* (DecodeParms Predictor One) Tj ET';
        $compressed = gzcompress($content);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /DecodeParms 3 0 R /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "2 0 obj\n/FlateDecode\nendobj\n"
            . "3 0 obj\n<< /Predictor 1 >>\nendobj\n"
            . "%%EOF";

        $stackedContent = 'BT /F1 12 Tf 72 720 Td (Indirect Filter Array) Tj ET';
        $stackedCompressed = gzcompress($stackedContent);
        $stackedEncoded = strtoupper(bin2hex($stackedCompressed)) . '>';
        $stackedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /DecodeParms [ null 3 0 R ] /Length " . strlen($stackedEncoded) . " >>\nstream\n{$stackedEncoded}\nendstream\nendobj\n"
            . "2 0 obj\n[ /ASCIIHexDecode /FlateDecode ]\nendobj\n"
            . "3 0 obj\n<< /Predictor 1 /Columns 8 >>\nendobj\n"
            . "%%EOF";

        $unsupportedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 8 >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("Indirect Filter Import\nDecodeParms Predictor One", $extractor->extractPlainText($pdf));
        $t->same('Indirect Filter Array', $extractor->extractPlainText($stackedPdf));
        $t->same('', $extractor->extractPlainText($unsupportedPdf));
    },
    'resolves indirect names inside stream filter arrays before WordPress extraction' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Name Array Indirect Filter) Tj T* (Current Base Import) Tj ET';
        $compressed = gzcompress($content);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress indirect name-array filter fixture.');
        }

        $encoded = strtoupper(bin2hex($compressed)) . '>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ 2 0 R null 3 0 R ] /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n"
            . "2 0 obj\n/ASCIIHexDecode\nendobj\n"
            . "3 0 obj\n/FlateDecode\nendobj\n"
            . "%%EOF";

        $cycleNoise = 'BT /F1 12 Tf 72 704 Td (Cyclic Filter Leak) Tj ET';
        $cyclePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ 2 0 R ] /Length " . strlen($cycleNoise) . " >>\nstream\n{$cycleNoise}\nendstream\nendobj\n"
            . "2 0 obj\n[ 2 0 R ]\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);

        $t->same("Name Array Indirect Filter\nCurrent Base Import", $text);
        $t->same(['Name Array Indirect Filter', 'Current Base Import'], $extractor->extractTextLines($pdf));
        $t->same(['Name Array Indirect Filter', 'Current Base Import'], $extractor->extractTextRuns($pdf));
        $t->same("Name Array Indirect Filter\nCurrent Base Import\n", $extractor->naiveGetText($pdf));
        $t->same('', $extractor->extractPlainText($cyclePdf));
        $t->true(!str_contains($extractor->naiveGetText($cyclePdf), 'Cyclic Filter Leak'));
    },
    'fails closed on unsupported or corrupt stream filters before WordPress text parsing' => static function (TestRunner $t): void {
        $visibleBefore = 'BT /F1 12 Tf 72 720 Td (Filter Boundary Visible) Tj ET';
        $visibleAfter = 'BT /F1 12 Tf 72 688 Td (Filter Boundary Tail) Tj ET';
        $cryptNoise = 'BT /F1 12 Tf 72 704 Td (Unsupported Crypt Leak) Tj ET';
        $corruptFlateNoise = 'BT /F1 12 Tf 72 672 Td (Corrupt Flate Leak) Tj ET';
        $stackedNoise = 'BT /F1 12 Tf 72 656 Td (Stacked Unknown Leak) Tj ET';
        $stackedEncodedNoise = strtoupper(bin2hex($stackedNoise)) . '>';

        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R 8 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visibleBefore) . " >>\nstream\n{$visibleBefore}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Filter /Crypt /Length " . strlen($cryptNoise) . " >>\nstream\n{$cryptNoise}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Filter /FlateDecode /Length " . strlen($corruptFlateNoise) . " >>\nstream\n{$corruptFlateNoise}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Filter [ /ASCIIHexDecode /Crypt ] /Length " . strlen($stackedEncodedNoise) . " >>\nstream\n{$stackedEncodedNoise}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
            . "%%EOF";

        $fallbackVisible = 'BT /F1 12 Tf 72 720 Td (Fallback Filter Visible) Tj ET';
        $missingIndirectNoise = 'BT /F1 12 Tf 72 704 Td (Missing Indirect Filter Leak) Tj ET';
        $directUnknownNoise = 'BT /F1 12 Tf 72 688 Td (Direct Unknown Filter Leak) Tj ET';
        $fallbackStackedNoise = 'BT /F1 12 Tf 72 672 Td (Fallback Stacked Unknown Leak) Tj ET';
        $fallbackStackedEncodedNoise = strtoupper(bin2hex($fallbackStackedNoise)) . '>';
        $fallbackPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($fallbackVisible) . " >>\nstream\n{$fallbackVisible}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Filter 99 0 R /Length " . strlen($missingIndirectNoise) . " >>\nstream\n{$missingIndirectNoise}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Filter /NoSuchDecode /Length " . strlen($directUnknownNoise) . " >>\nstream\n{$directUnknownNoise}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Filter [ /ASCIIHexDecode /NoSuchDecode ] /Length " . strlen($fallbackStackedEncodedNoise) . " >>\nstream\n{$fallbackStackedEncodedNoise}\nendstream\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $pageText = $extractor->extractPlainText($pagePdf);
        $fallbackText = $extractor->extractPlainText($fallbackPdf);

        $t->same("Filter Boundary Visible\nFilter Boundary Tail", $pageText);
        $t->same(['Filter Boundary Visible', 'Filter Boundary Tail'], $extractor->extractTextRuns($pagePdf));
        $t->same("Filter Boundary Visible\nFilter Boundary Tail\n", $extractor->naiveGetText($pagePdf));
        $t->true(!str_contains($pageText, 'Unsupported Crypt Leak'));
        $t->true(!str_contains($pageText, 'Corrupt Flate Leak'));
        $t->true(!str_contains($pageText, 'Stacked Unknown Leak'));
        $t->same('Fallback Filter Visible', $fallbackText);
        $t->same(['Fallback Filter Visible'], $extractor->extractTextRuns($fallbackPdf));
        $t->true(!str_contains($fallbackText, 'Missing Indirect Filter Leak'));
        $t->true(!str_contains($fallbackText, 'Direct Unknown Filter Leak'));
        $t->true(!str_contains($fallbackText, 'Fallback Stacked Unknown Leak'));
    },
    'applies Flate DecodeParms predictors before WordPress paragraph rendering' => static function (TestRunner $t) use ($pngPredictorEncode, $tiffPredictorEncode): void {
        $rowOne = 'BT /F1 12 Tf 72 720 Td (Predictor Import) Tj T* ';
        $rowTwo = '(Block Ready Content) Tj ET                     ';
        $content = $rowOne . $rowTwo;
        $pngEncoded = $pngPredictorEncode($content, strlen($rowOne));
        $pngCompressed = gzcompress($pngEncoded);
        $pngPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns " . strlen($rowOne) . " >> /Length " . strlen($pngCompressed) . " >>\n"
            . "stream\n{$pngCompressed}\nendstream\nendobj\n%%EOF";

        $tiffContent = 'BT /F1 12 Tf 72 720 Td (TIFF Predictor Import) Tj ET';
        $tiffEncoded = $tiffPredictorEncode($tiffContent, strlen($tiffContent));
        $tiffCompressed = gzcompress($tiffEncoded);
        $tiffPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 2 /Columns " . strlen($tiffContent) . " >> /Length " . strlen($tiffCompressed) . " >>\n"
            . "stream\n{$tiffCompressed}\nendstream\nendobj\n%%EOF";

        $badCompressed = gzcompress("\x09" . $rowOne);
        $badPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns " . strlen($rowOne) . " >> /Length " . strlen($badCompressed) . " >>\n"
            . "stream\n{$badCompressed}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("Predictor Import\nBlock Ready Content", $extractor->extractPlainText($pngPdf));
        $t->same('TIFF Predictor Import', $extractor->extractPlainText($tiffPdf));
        $t->same('', $extractor->extractPlainText($badPdf));
    },
    'applies indirect DecodeParms arrays to the current filter in stream chains' => static function (TestRunner $t) use ($ascii85Encode, $pngPredictorEncode): void {
        $rowOne = 'BT /F1 12 Tf 72 720 Td (Chained Params Import) Tj T* ';
        $rowTwo = '(Current Filter Base) Tj ET                          ';
        $content = $rowOne . $rowTwo;
        $predictorEncoded = $pngPredictorEncode($content, strlen($rowOne));
        $compressed = gzcompress($predictorEncoded);
        $t->true(is_string($compressed), 'DecodeParms-array fixture should compress.');
        $encoded = $ascii85Encode($compressed);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms 2 0 R /Length " . strlen($encoded) . " >>\n"
            . "stream\n{$encoded}\nendstream\nendobj\n"
            . "2 0 obj\n[ null << /Predictor 12 /Columns " . strlen($rowOne) . " >> ]\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("Chained Params Import\nCurrent Filter Base", $extractor->extractPlainText($pdf));
        $t->same(['Chained Params Import', 'Current Filter Base'], $extractor->extractTextRuns($pdf));
        $t->same("Chained Params Import\nCurrent Filter Base\n", $extractor->naiveGetText($pdf));
    },
    'resolves indirect numeric DecodeParms values for predictor filters' => static function (TestRunner $t) use ($pngUpPredictorEncode, $lzwLiteralEncode): void {
        $rowOne = 'BT /F1 12 Tf 72 720 Td (Indirect Predictor Params) Tj T* ';
        if (strlen($rowOne) % 2 !== 0) {
            $rowOne .= ' ';
        }
        $rowTwo = str_pad('(PNG Width Objects) Tj ET', strlen($rowOne));
        $columns = intdiv(strlen($rowOne), 2);
        $predicted = $pngUpPredictorEncode([$rowOne, $rowTwo]);
        $compressed = gzcompress($predicted);
        $t->true(is_string($compressed), 'Indirect predictor fixture should compress.');
        $flatePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 2 0 R /Columns 3 0 R /Colors 4 0 R /BitsPerComponent 5 0 R >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "2 0 obj\n12\nendobj\n"
            . "3 0 obj\n{$columns}\nendobj\n"
            . "4 0 obj\n1\nendobj\n"
            . "5 0 obj\n16\nendobj\n"
            . "%%EOF";

        $longText = str_repeat('Boundary ', 36) . 'EarlyChange Object';
        $earlyChangeContent = 'BT /F1 12 Tf 72 720 Td (' . $longText . ') Tj ET';
        $earlyChangeEncoded = $lzwLiteralEncode($earlyChangeContent, 0);
        $lzwPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /LZWDecode /DecodeParms << /EarlyChange 2 0 R >> /Length " . strlen($earlyChangeEncoded) . " >>\nstream\n{$earlyChangeEncoded}\nendstream\nendobj\n"
            . "2 0 obj\n0\nendobj\n"
            . "%%EOF";

        $invalidContent = 'BT /F1 12 Tf 72 720 Td (Invalid Indirect EarlyChange Leak) Tj ET';
        $invalidEncoded = $lzwLiteralEncode($invalidContent);
        $invalidPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /LZWDecode /DecodeParms << /EarlyChange 2 0 R >> /Length " . strlen($invalidEncoded) . " >>\nstream\n{$invalidEncoded}\nendstream\nendobj\n"
            . "2 0 obj\n2\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("Indirect Predictor Params\nPNG Width Objects", $extractor->extractPlainText($flatePdf));
        $t->same(['Indirect Predictor Params', 'PNG Width Objects'], $extractor->extractTextRuns($flatePdf));
        $t->same($longText, $extractor->extractPlainText($lzwPdf));
        $t->same('', $extractor->extractPlainText($invalidPdf));
    },
    'uses ToUnicode CMap codespacerange widths for variable-length WordPress text' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <8141208142> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 begincodespacerange\n"
            . "<20> <20>\n"
            . "<8000> <FFFF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<8141> <0057006F0072006400500072006500730073>\n"
            . "<8142> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VariableSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('WordPress Blocks', $extractor->extractPlainText($pdf));
        $t->same(['WordPress Blocks'], $extractor->extractTextRuns($pdf));
    },
    'uses ToUnicode codespace width before unmapped WordPress CID fallback' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0041> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <00FF>\n"
            . "endcodespacerange\n"
            . "1 beginbfchar\n"
            . "<00> <0058>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CodespaceFallbackSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('A', $extractor->extractPlainText($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'XA'));
    },
    'uses mapped CMap source widths when codespacerange is missing before WordPress text' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <20214142> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "3 beginbfchar\n"
            . "<20> <0049006D0070006F00720074>\n"
            . "<21> <0020>\n"
            . "<4142> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SourceWidthFallbackSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('Import Blocks', $extractor->extractPlainText($pdf));
        $t->same(['Import Blocks'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), "\u{2021}"));
    },
    'decodes simple font Encoding Differences before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fdiff 12 Tf 72 720 Td <202122232425262728292A2B2C2D2E2F> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdiff 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /CustomSubset /Encoding << /Type /Encoding /Differences [32 /W /P /space /I /m /p /o /r /t /space /B /l /o /c /k /s] >> >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('WP Import Blocks', $extractor->extractPlainText($pdf));
        $t->same(['WP Import Blocks'], $extractor->extractTextRuns($pdf));
    },
    'decodes simple font WinAnsiEncoding punctuation before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fwin 12 Tf 72 720 Td <9344617461204C696265726174696F6E94209620575092> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fwin 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /WinAnsiSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $expected = "\u{201c}Data Liberation\u{201d} \u{2013} WP\u{2019}";

        $t->same($expected, $extractor->extractPlainText($pdf));
        $t->same([$expected], $extractor->extractTextRuns($pdf));
    },
    'decodes subset font ligature glyph names before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <202122232425262728292A2B2C2D2E2F30> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsubset 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+SubsetSerif /Encoding << /Type /Encoding /Differences [32 /O /f_f_i.alt /c /e /space /f_i /l /e /space /endash /space /C /a /f /eacute /space /Euro] >> >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $expected = "Office file \u{2013} Caf\u{00E9} \u{20AC}";
        $t->same($expected, $extractor->extractPlainText($pdf));
        $t->same([$expected], $extractor->extractTextRuns($pdf));
    },
    'decodes Standard MacRoman and Symbol simple font encodings before WordPress paragraphs' => static function (TestRunner $t): void {
        $content = 'BT /Fstd 12 Tf 1 0 0 1 72 720 Tm <5750277320AE20AF20E1> Tj ET '
            . 'BT /Fmac 12 Tf 1 0 0 1 72 704 Tm <4D6163208E209F20D6> Tj ET '
            . 'BT /Fsym 12 Tf 1 0 0 1 72 688 Tm <616267202B20B3> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fstd 2 0 R /Fmac 3 0 R /Fsym 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /StandardEncoding >>\nendobj\n"
            . "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /MacRomanSubset /Encoding << /BaseEncoding /MacRomanEncoding >> >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Symbol >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $expected = [
            "WP\u{2019}s \u{FB01} \u{FB02} \u{00C6}",
            "Mac \u{00E9} \u{00FC} \u{00F7}",
            "\u{03B1}\u{03B2}\u{03B3} + \u{2265}",
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same(implode("\n", $expected), $extractor->extractPlainText($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
    },
    'uses Base14 simple font metrics before same-line Tm gap decisions' => static function (TestRunner $t): void {
        $helveticaContent = 'BT /Fhelv 12 Tf 1 0 0 1 72 720 Tm (Ill) Tj 1 0 0 1 93 720 Tm (Word) Tj ET '
            . 'BT /Fhelv 12 Tf 1 0 0 1 72 704 Tm (WWW) Tj 1 0 0 1 104 704 Tm (Import) Tj ET';
        $timesContent = 'BT /Ftimes 12 Tf 1 0 0 1 72 688 Tm (iii) Tj 1 0 0 1 95 688 Tm (Word) Tj ET';
        $courierContent = 'BT /Fcourier 12 Tf 1 0 0 1 72 672 Tm (Courier) Tj 1 0 0 1 126 672 Tm (Text) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhelv 2 0 R /Ftimes 5 0 R /Fcourier 8 0 R >> >> /Contents [3 0 R 6 0 R 9 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($helveticaContent) . " >>\nstream\n{$helveticaContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($timesContent) . " >>\nstream\n{$timesContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier-BoldOblique >>\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($courierContent) . " >>\nstream\n{$courierContent}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Ill Word', 'WWWImport', 'iii Word', 'CourierText'], $extractor->extractTextLines($pdf));
        $t->same("Ill Word\nWWWImport\niii Word\nCourierText", $plainText);
        $t->true(!str_contains($plainText, 'IllWord'));
        $t->true(!str_contains($plainText, 'WWW Import'));
        $t->true(!str_contains($plainText, 'Courier Text'));
    },
    'uses explicit simple font widths before Base14 fallback metrics' => static function (TestRunner $t): void {
        $directContent = 'BT /Fdirect 12 Tf 1 0 0 1 72 720 Tm (AA) Tj 1 0 0 1 98 720 Tm (BB) Tj ET';
        $indirectContent = 'BT /Findirect 12 Tf 1 0 0 1 72 704 Tm (CC) Tj 1 0 0 1 98 704 Tm (DD) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdirect 2 0 R /Findirect 5 0 R >> >> /Contents [3 0 R 6 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /CustomDirect /FirstChar 65 /Widths [1000 200] >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream\nendobj\n"
            . "4 0 obj\n[1000 200]\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /CustomIndirect /FirstChar 67 /Widths 4 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($indirectContent) . " >>\nstream\n{$indirectContent}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['AABB', 'CCDD'], $extractor->extractTextLines($pdf));
        $t->same("AABB\nCCDD", $extractor->extractPlainText($pdf));
    },
    'extracts native FontDescriptor flags into upstream-style span font names' => static function (TestRunner $t): void {
        $content = 'BT /Fplain 12 Tf 72 720 Td (Plain ) Tj /Fitalic 12 Tf (italic segment) Tj /Fplain 12 Tf ( bridge ) Tj /Fbold 12 Tf (bold segment) Tj /Fplain 12 Tf ( outro) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fplain 2 0 R /Fitalic 4 0 R /Fbold 6 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /SourceSerif /Encoding /WinAnsiEncoding /FontDescriptor 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Type /FontDescriptor /FontName /SourceSerif /Flags " . ((1 << 1) | (1 << 5)) . " /FontWeight 400 >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /SourceSerifItalic /Encoding /WinAnsiEncoding /FontDescriptor << /Type /FontDescriptor /FontName /SourceSerifItalic /Flags " . ((1 << 1) | (1 << 5) | (1 << 6)) . " /ItalicAngle -12 >> >>\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /SourceSansForceBold /Encoding /WinAnsiEncoding /FontDescriptor 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /FontDescriptor /FontName /SourceSansForceBold /Flags " . ((1 << 5) | (1 << 18)) . " /FontWeight 700 >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $pages = $extractor->extractStyledTextPages($pdf);

        $t->same('Plain italic segment bridge bold segment outro', $extractor->extractPlainText($pdf));
        $t->same(1, count($pages));
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('SourceSerif_serif_non_symbolic', $spans[0]['font']);
        $t->same('SourceSerifItalic_serif_non_symbolic_italic', $spans[1]['font']);
        $t->same('SourceSansForceBold_non_symbolic_bold', $spans[3]['font']);
        $t->same((1 << 1) | (1 << 5), $spans[0]['font_flags']);
        $t->same((1 << 1) | (1 << 5) | (1 << 6), $spans[1]['font_flags']);
        $t->same((1 << 5) | (1 << 18), $spans[3]['font_flags']);
        $t->same(700.0, $spans[3]['font_weight']);

        $pages[0]['blocks'] = (new FontStyleCleaner())->markBoldItalicSpans($pages[0]['blocks']);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Plain *italic segment* bridge **bold segment** outro', $blocks[0]['text']);
    },
    'resolves indirect FontDescriptor flag fields before WordPress styled span cleanup' => static function (TestRunner $t): void {
        $content = 'BT /Fplain 12 Tf 72 720 Td (Plain ) Tj /Find 12 Tf (italic segment) Tj /Fplain 12 Tf ( bridge ) Tj /Fdesc 12 Tf (bold segment) Tj /Fplain 12 Tf ( outro) Tj ET';
        $italicFlags = (1 << 1) | (1 << 5) | (1 << 6);
        $boldFlags = (1 << 5) | (1 << 18);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Find 2 0 R /Fdesc 7 0 R /Fplain 11 0 R >> >> /Contents 13 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /FallbackSerif /Encoding /WinAnsiEncoding /FontDescriptor 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Type /FontDescriptor /FontName 4 0 R /Flags 5 0 R /FontWeight 6 0 R >>\nendobj\n"
            . "4 0 obj\n/IndirectSerifItalic\nendobj\n"
            . "5 0 obj\n{$italicFlags}\nendobj\n"
            . "6 0 obj\n300\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /FallbackBold /Encoding /WinAnsiEncoding /FontDescriptor 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /FontDescriptor /FontName /DirectForceBold /Flags 9 0 R /FontWeight 10 0 R >>\nendobj\n"
            . "9 0 obj\n{$boldFlags}\nendobj\n"
            . "10 0 obj\n700\nendobj\n"
            . "11 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /PlainBase /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "13 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $pages = $extractor->extractStyledTextPages($pdf);

        $t->same('Plain italic segment bridge bold segment outro', $extractor->extractPlainText($pdf));
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('IndirectSerifItalic_serif_non_symbolic_italic', $spans[1]['font']);
        $t->same('DirectForceBold_non_symbolic_bold', $spans[3]['font']);
        $t->same($italicFlags, $spans[1]['font_flags']);
        $t->same($boldFlags, $spans[3]['font_flags']);
        $t->same(300.0, $spans[1]['font_weight']);
        $t->same(700.0, $spans[3]['font_weight']);

        $pages[0]['blocks'] = (new FontStyleCleaner())->markBoldItalicSpans($pages[0]['blocks']);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Plain *italic segment* bridge **bold segment** outro', $blocks[0]['text']);
    },
    'uses Identity-H and Identity-V font CMap widths before WordPress fallback text extraction' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0057005000200049006D0070006F00720074> Tj T* [<0042006C006F0063006B0073> -120 <0021>] TJ ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentitySubset /Encoding /Identity-H >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $literal = hex2bin('004C00690074006500720061006C00200049006D0070006F00720074');
        $t->true(is_string($literal), 'Identity-V literal fixture should decode from hex.');
        $verticalContent = 'BT /Fv 12 Tf 72 720 Td (' . $literal . ') Tj ET';
        $verticalPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityVerticalSubset /Encoding /Identity-V >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($verticalContent) . " >>\nstream\n{$verticalContent}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();

        $t->same("WP Import\nBlocks!", $extractor->extractPlainText($pdf));
        $t->same(['WP Import', 'Blocks!'], $extractor->extractTextRuns($pdf));
        $t->same('Literal Import', $extractor->extractPlainText($verticalPdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), "\0"));
        $t->true(!str_contains($extractor->extractPlainText($verticalPdf), "\0"));
    },
    'uses CIDFont W widths for WordPress text advance boundaries' => static function (TestRunner $t): void {
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "17 beginbfchar\n"
            . "<0001> <0057>\n"
            . "<0002> <0069>\n"
            . "<0003> <0064>\n"
            . "<0004> <0065>\n"
            . "<0005> <0042>\n"
            . "<0006> <006C>\n"
            . "<0007> <006F>\n"
            . "<0008> <0063>\n"
            . "<0009> <006B>\n"
            . "<0014> <0054>\n"
            . "<0015> <0068>\n"
            . "<0016> <0069>\n"
            . "<0017> <006E>\n"
            . "<0018> <0054>\n"
            . "<0019> <0065>\n"
            . "<001A> <0078>\n"
            . "<001B> <0074>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 118 720 Tm <00050006000700080009> Tj '
            . 'T* 1 0 0 1 72 704 Tm <0014001500160017> Tj 1 0 0 1 96 704 Tm <00180019001A001B> Tj '
            . 'T* 1 0 0 1 72 688 Tm [<0001000200030004>] TJ 1 0 0 1 118 688 Tm <00050006000700080009> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDWidthSubset /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDWidthSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 250 /W [1 [1000 1000 1000 1000] 20 23 250] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text', 'WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text', 'Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text\nWideBlock", $plainText);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
    },
    'uses Type0 Encoding CMap CIDs before raw source-code width fallbacks' => static function (TestRunner $t): void {
        $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CMapName /WPImportCustom-H def\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 begincidrange\n"
            . "<01> <09> 200\n"
            . "<14> <1B> 300\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $toUnicode = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "17 beginbfchar\n"
            . "<01> <0057>\n"
            . "<02> <0069>\n"
            . "<03> <0064>\n"
            . "<04> <0065>\n"
            . "<05> <0042>\n"
            . "<06> <006C>\n"
            . "<07> <006F>\n"
            . "<08> <0063>\n"
            . "<09> <006B>\n"
            . "<14> <0054>\n"
            . "<15> <0068>\n"
            . "<16> <0069>\n"
            . "<17> <006E>\n"
            . "<18> <0054>\n"
            . "<19> <0065>\n"
            . "<1A> <0078>\n"
            . "<1B> <0074>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj 1 0 0 1 118 720 Tm <0506070809> Tj '
            . 'T* 1 0 0 1 72 704 Tm <14151617> Tj 1 0 0 1 96 704 Tm <18191A1B> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CustomCMapSubset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CustomCMapSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 9 250 20 27 1000 200 208 1000 300 307 250] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
    },
    'uses Type0 Encoding CMap code-space boundaries for fallback text widths' => static function (TestRunner $t): void {
        $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CMapName /WPMixedBoundary-H def\n"
            . "2 begincodespacerange\n"
            . "<20> <7F>\n"
            . "<0000> <00FF>\n"
            . "endcodespacerange\n"
            . "2 begincidrange\n"
            . "<0057> <0065> 200\n"
            . "<42> <74> 300\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj 1 0 0 1 118 720 Tm <426C6F636B> Tj '
            . 'T* 1 0 0 1 72 704 Tm <5468696E> Tj 1 0 0 1 96 704 Tm <0054006500780074> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MixedBoundarySubset /Encoding 3 0 R /DescendantFonts [4 0 R] >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MixedBoundarySubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [84 120 250 200 214 1000 300 352 250] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->true(!str_contains($plainText, "\0"));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
    },
    'uses CIDFont vertical W2 metrics for WordPress text advance boundaries' => static function (TestRunner $t): void {
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/WMode 1 def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "18 beginbfchar\n"
            . "<0001> <0056>\n"
            . "<0002> <0065>\n"
            . "<0003> <0072>\n"
            . "<0004> <0074>\n"
            . "<0005> <0049>\n"
            . "<0006> <006D>\n"
            . "<0007> <0070>\n"
            . "<0008> <006F>\n"
            . "<0009> <0072>\n"
            . "<000A> <0074>\n"
            . "<0014> <0044>\n"
            . "<0015> <0061>\n"
            . "<0016> <0074>\n"
            . "<0017> <0061>\n"
            . "<0018> <0046>\n"
            . "<0019> <006C>\n"
            . "<001A> <006F>\n"
            . "<001B> <0077>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 72 672 Tm <00050006000700080009000A> Tj '
            . '1 0 0 1 96 720 Tm <0014001500160017> Tj 1 0 0 1 96 708 Tm <00180019001A001B> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDVerticalSubset /Encoding /Identity-V /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDVerticalSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [20 23 -250 500 880] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['VertImport', 'DataFlow'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import', 'Data', 'Flow'], $extractor->extractTextRuns($pdf));
        $t->same("VertImport\nDataFlow", $plainText);
        $t->true(!str_contains($plainText, "Vert\nImport"));
        $t->true(!str_contains($plainText, 'Data Flow'));
    },
    'uses Type3 CharProc widths for WordPress text advance boundaries' => static function (TestRunner $t): void {
        $wideCharProc = "1000 0 d0\n";
        $thinCharProc = "250 0 0 0 250 700 d1\n";
        $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj 1 0 0 1 118 720 Tm <4546474849> Tj '
            . 'T* 1 0 0 1 72 704 Tm <54555657> Tj 1 0 0 1 96 704 Tm <58595A5B> Tj '
            . 'T* 1 0 0 1 72 688 Tm [<41424344>] TJ 1 0 0 1 118 688 Tm <4546474849> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3Import /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
            . "/Encoding << /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide /B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >> "
            . "/CharProcs << /W.wide 3 0 R /i.wide 4 0 R /d.wide 5 0 R /e.wide 6 0 R /B.wide 7 0 R /l.wide 8 0 R /o.wide 9 0 R /c.wide 10 0 R /k.wide 11 0 R /T.thin 12 0 R /h.thin 13 0 R /i.thin 14 0 R /n.thin 15 0 R /e.thin 16 0 R /x.thin 17 0 R /t.thin 18 0 R >> >>\nendobj\n";
        for ($objectNumber = 3; $objectNumber <= 18; $objectNumber++) {
            $charProc = $objectNumber <= 11 ? $wideCharProc : $thinCharProc;
            $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n";
        }
        $pdf .= "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text', 'WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text', 'Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text\nWideBlock", $plainText);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
    },
    'uses CIDSet subset glyphs for default CIDFont widths before WordPress text' => static function (TestRunner $t): void {
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "9 beginbfchar\n"
            . "<0001> <0057>\n"
            . "<0002> <0069>\n"
            . "<0003> <0064>\n"
            . "<0004> <0065>\n"
            . "<0005> <0042>\n"
            . "<0006> <006C>\n"
            . "<0007> <006F>\n"
            . "<0008> <0063>\n"
            . "<0009> <006B>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 118 720 Tm <00050006000700080009> Tj ET';
        $cidSet = "\xff\xc0";
        $compressedCidSet = gzcompress($cidSet);
        $t->true(is_string($compressedCidSet), 'CIDSet stream fixture should compress.');
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDSetSubset /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDSetSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /CIDSetSubset /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('WideBlock', $plainText);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses CIDFont descriptor default width before WordPress text advance boundaries' => static function (TestRunner $t): void {
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "9 beginbfchar\n"
            . "<0001> <0057>\n"
            . "<0002> <0069>\n"
            . "<0003> <0064>\n"
            . "<0004> <0065>\n"
            . "<0005> <0042>\n"
            . "<0006> <006C>\n"
            . "<0007> <006F>\n"
            . "<0008> <0063>\n"
            . "<0009> <006B>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 118 720 Tm <00050006000700080009> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DefaultCIDDescriptorSubset /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DefaultCIDDescriptorSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /DefaultCIDDescriptorSubset /Flags 4 >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('WideBlock', $plainText);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'extracts current annotation normal appearance streams before WordPress text extraction' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $pageCMap = $toUnicodeCMap(['41' => 'Page Body']);
        $appearanceCMap = $toUnicodeCMap(['41' => 'Current Appearance']);
        $directAppearanceCMap = $toUnicodeCMap(['42' => 'Direct Normal Appearance']);
        $offCMap = $toUnicodeCMap(['41' => 'Off Appearance Noise']);
        $rolloverCMap = $toUnicodeCMap(['41' => 'Rollover Appearance Noise']);
        $unreferencedCMap = $toUnicodeCMap(['41' => 'Unreferenced Appearance Noise']);
        $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $currentAppearance = 'BT /F1 12 Tf 0 0 Td <41> Tj ET';
        $directAppearance = 'BT /F1 12 Tf 0 0 Td <42> Tj ET';
        $offAppearance = 'BT /F1 12 Tf 0 0 Td <41> Tj ET';
        $rolloverAppearance = 'BT /F1 12 Tf 0 0 Td <41> Tj ET';
        $unreferencedAppearance = 'BT /F1 12 Tf 0 0 Td <41> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Annots [5 0 R 6 0 R 7 0 R] /Contents 8 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageSubset /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 680 220 704] /AS /Yes /AP << /N << /Off 10 0 R /Yes 11 0 R >> /R 12 0 R >> >>\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 648 260 672] /AP << /N 13 0 R >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 616 180 640] /Contents (review note only) >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($pageCMap) . " >>\nstream\n{$pageCMap}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 14 0 R >> >> /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n"
            . "11 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 15 0 R >> >> /Length " . strlen($currentAppearance) . " >>\nstream\n{$currentAppearance}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 16 0 R >> >> /Length " . strlen($rolloverAppearance) . " >>\nstream\n{$rolloverAppearance}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($directAppearance) . " >>\nstream\n{$directAppearance}\nendstream\nendobj\n"
            . "14 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OffSubset /Encoding /Identity-H /ToUnicode 18 0 R >>\nendobj\n"
            . "15 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /AppearanceSubset /Encoding /Identity-H /ToUnicode 19 0 R >>\nendobj\n"
            . "16 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /RolloverSubset /Encoding /Identity-H /ToUnicode 20 0 R >>\nendobj\n"
            . "17 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DirectAppearanceSubset /Encoding /Identity-H /ToUnicode 21 0 R >>\nendobj\n"
            . "18 0 obj\n<< /Length " . strlen($offCMap) . " >>\nstream\n{$offCMap}\nendstream\nendobj\n"
            . "19 0 obj\n<< /Length " . strlen($appearanceCMap) . " >>\nstream\n{$appearanceCMap}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Length " . strlen($rolloverCMap) . " >>\nstream\n{$rolloverCMap}\nendstream\nendobj\n"
            . "21 0 obj\n<< /Length " . strlen($directAppearanceCMap) . " >>\nstream\n{$directAppearanceCMap}\nendstream\nendobj\n"
            . "22 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 584 220 608] /AP << /N 23 0 R >> >>\nendobj\n"
            . "23 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 24 0 R >> >> /Length " . strlen($unreferencedAppearance) . " >>\nstream\n{$unreferencedAppearance}\nendstream\nendobj\n"
            . "24 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnreferencedSubset /Encoding /Identity-H /ToUnicode 25 0 R >>\nendobj\n"
            . "25 0 obj\n<< /Length " . strlen($unreferencedCMap) . " >>\nstream\n{$unreferencedCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Page Body', 'Current Appearance', 'Direct Normal Appearance'], $extractor->extractTextLines($pdf));
        $t->same(['Page Body', 'Current Appearance', 'Direct Normal Appearance'], $extractor->extractTextRuns($pdf));
        $t->same("Page Body\nCurrent Appearance\nDirect Normal Appearance", $plainText);
        $t->same("Page Body\nCurrent Appearance\nDirect Normal Appearance\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Off Appearance Noise'));
        $t->true(!str_contains($plainText, 'Rollover Appearance Noise'));
        $t->true(!str_contains($plainText, 'Unreferenced Appearance Noise'));
        $t->true(!str_contains($plainText, 'review note only'));
    },
    'clips annotation appearance Form XObject BBox while preserving scoped resources' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $pageCMap = $toUnicodeCMap(['41' => 'Page Body']);
        $appearanceCMap = $toUnicodeCMap([
            '41' => 'Visible Appearance',
            '42' => 'BBox Noise',
        ]);
        $nestedCMap = $toUnicodeCMap(['43' => 'Nested Resource']);
        $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $appearanceContent = 'BT /F1 12 Tf 0 25 Td <41> Tj 0 75 Td <42> Tj ET q /Nested Do Q';
        $nestedAppearanceContent = 'BT /F1 12 Tf 12 30 Td <43> Tj ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Annots [5 0 R] /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageSubset /Encoding /Identity-H /ToUnicode 7 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 640 220 704] /AP << /N 8 0 R >> >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($pageCMap) . " >>\nstream\n{$pageCMap}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 120 60] /Matrix [1 0 0 1 72 640] /Resources << /Font << /F1 9 0 R >> /XObject << /Nested 10 0 R >> >> /Length " . strlen($appearanceContent) . " >>\nstream\n{$appearanceContent}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /AppearanceSubset /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
            . "10 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 80 50] /Resources << /Font << /F1 12 0 R >> >> /Length " . strlen($nestedAppearanceContent) . " >>\nstream\n{$nestedAppearanceContent}\nendstream\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($appearanceCMap) . " >>\nstream\n{$appearanceCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NestedAppearanceSubset /Encoding /Identity-H /ToUnicode 13 0 R >>\nendobj\n"
            . "13 0 obj\n<< /Length " . strlen($nestedCMap) . " >>\nstream\n{$nestedCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = ['Page Body', 'Visible Appearance', 'Nested Resource'];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'BBox Noise'));
        $t->true(!str_contains($plainText, 'Page Body Visible Appearance'), 'appearance text remains a separate review/import boundary.');
    },
    'falls back to font Encoding when malformed ToUnicode CMap filters are ignored' => static function (TestRunner $t): void {
        $badCMap = 'not valid flate cmap bytes';
        $identityContent = 'BT /Fcid 12 Tf 72 720 Td <0057005000200049006D0070006F00720074> Tj ET';
        $identityPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /BrokenCMapSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Filter /FlateDecode /Length " . strlen($badCMap) . " >>\nstream\n{$badCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($identityContent) . " >>\nstream\n{$identityContent}\nendstream\nendobj\n%%EOF";

        $winAnsiContent = 'BT /Fwin 12 Tf 72 720 Td <9344617461204C696265726174696F6E94209620575092> Tj ET';
        $winAnsiPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fwin 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /BrokenWinAnsiSubset /Encoding /WinAnsiEncoding /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Filter /FlateDecode /Length " . strlen($badCMap) . " >>\nstream\n{$badCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($winAnsiContent) . " >>\nstream\n{$winAnsiContent}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $winAnsiExpected = "\u{201c}Data Liberation\u{201d} \u{2013} WP\u{2019}";

        $t->same('WP Import', $extractor->extractPlainText($identityPdf));
        $t->same(['WP Import'], $extractor->extractTextRuns($identityPdf));
        $t->true(!str_contains($extractor->extractPlainText($identityPdf), "\0"));
        $t->same($winAnsiExpected, $extractor->extractPlainText($winAnsiPdf));
        $t->same([$winAnsiExpected], $extractor->extractTextRuns($winAnsiPdf));
    },
    'uses ToUnicode bfrange arrays for WordPress text extraction' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <202122> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<20> <22>\n"
            . "endcodespacerange\n"
            . "1 beginbfrange\n"
            . "<20> <22> [<0049006D0070006F00720074> <0020> <0042006C006F0063006B0073>]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ArrayRangeSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('Import Blocks', $extractor->extractPlainText($pdf));
        $t->same(['Import Blocks'], $extractor->extractTextRuns($pdf));
    },
    'inherits ToUnicode usecmap mappings before WordPress text extraction' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <202122> Tj ET';
        $baseCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<20> <22>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<20> <0049006D0070006F00720074>\n"
            . "<22> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /BaseImportCMap defineresource pop\n"
            . "/CMapName /BaseImportCMap def\n"
            . "end\n"
            . "end\n";
        $fontCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/BaseImportCMap usecmap\n"
            . "1 beginbfchar\n"
            . "<21> <0020>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /DerivedImportCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UseCMapSubset /Encoding /Identity-H /ToUnicode 4 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($fontCMap) . " >>\nstream\n{$fontCMap}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('Import Blocks', $extractor->extractPlainText($pdf));
        $t->same(['Import Blocks'], $extractor->extractTextRuns($pdf));
    },
    'guards cyclic ToUnicode usecmap inheritance and codespace counts before WordPress text extraction' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <202122230041> Tj ET';
        $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/BaseCycleGuardCMap usecmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "<0000> <00FF>\n"
            . "endcodespacerange\n"
            . "4 beginbfchar\n"
            . "<20> <0049006D0070006F00720074>\n"
            . "<23> <0021>\n"
            . "<00> <0020>\n"
            . "<41> <004F004B>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /DerivedCycleGuardCMap defineresource pop\n"
            . "/CMapName /DerivedCycleGuardCMap def\n"
            . "end\n"
            . "end\n";
        $baseCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/DerivedCycleGuardCMap usecmap\n"
            . "2 beginbfchar\n"
            . "<21> <0020>\n"
            . "<22> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /BaseCycleGuardCMap defineresource pop\n"
            . "/CMapName /BaseCycleGuardCMap def\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CycleGuardSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('Import Blocks! OK', $extractor->extractPlainText($pdf));
        $t->same(['Import Blocks! OK'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Import Blocks!A'));
    },
    'ignores ToUnicode CMap comments before WordPress text extraction' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <41425051> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<41> <0041>\n"
            . "% <41> <004E006F006900730065>\n"
            . "<42> <0043006C00650061006E>\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "<50> <51> <0044>\n"
            . "% <50> <51> <0058>\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentedCMapSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('ACleanDE', $extractor->extractPlainText($pdf));
        $t->same(['ACleanDE'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Noise'));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'XY'));
    },
    'decodes escaped PDF resource names before ToUnicode WordPress text lookup' => static function (TestRunner $t): void {
        $content = 'BT /F#31 12 Tf 72 720 Td <4142> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<41> <0049006D0070006F00720074>\n"
            . "<42> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /F#31 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EscapedSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('ImportBlocks', $extractor->extractPlainText($pdf));
        $t->same(['ImportBlocks'], $extractor->extractTextRuns($pdf));

        $flateContent = 'BT /F1 12 Tf 72 720 Td (Escaped Filter Name) Tj ET';
        $compressed = gzcompress($flateContent);
        $filterPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /Fl#61teDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n%%EOF";
        $t->same('Escaped Filter Name', $extractor->extractPlainText($filterPdf));
    },
    'groups adjacent text operators on the same PDF text line' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Heading) Tj T* (First ) Tj (paragraph) Tj 0 -16 Td (Second line) Tj ET';
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $t->same(['Heading', 'First paragraph', 'Second line'], $lines);
    },
    'uses text advance before same-line Tm gap decisions for WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Data) Tj 1 0 0 1 98 720 Tm (base) Tj 1 0 0 1 146 720 Tm (Import) Tj 1 0 0 1 186 720 Tm (er) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));

        $t->same(['Database Importer'], $lines);
        $t->true(!str_contains($extractor->extractPlainText($pdfWithContent($content)), 'Data base'));
    },
    'uses PDF text-state spacing for same-line Tm gap decisions before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 2 Tc 120 Tz 1 0 0 1 72 720 Tm (Data) Tj 1 0 0 1 112 720 Tm (base) Tj ET '
            . 'BT /F1 12 Tf 100 Tz 16 TL 72 720 Td (Intro) Tj 18 2 (Import Profile) " 1 0 0 1 182 704 Tm (s) Tj ET '
            . 'BT /F1 12 Tf 18 Tw 1 0 0 1 72 688 Tm (Media Import) Tj 1 0 0 1 170 688 Tm (er) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Database', 'Intro', 'Import Profiles', 'Media Importer'], $lines);
        $t->true(!str_contains($plainText, 'Data base'));
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(!str_contains($plainText, 'Import er'));
    },
    'keeps q Q scoped text state from leaking into later positioned WordPress text' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm q 20 Tc (Data) Tj Q 1 0 0 1 180 720 Tm (Import) Tj 1 0 0 1 235 720 Tm (Tool) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Data Import Tool'], $lines);
        $t->true(!str_contains($plainText, 'ImportTool'));
    },
    'skips invisible and clipping-only text rendering modes before WordPress extraction' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Visible Before) Tj '
            . '3 Tr 1 0 0 1 72 704 Tm (Invisible OCR Noise) Tj '
            . '7 Tr 1 0 0 1 72 688 Tm (Clip Only Noise) Tj '
            . '/Span << /ActualText (Clip Alt Noise) >> BDC (Clip Glyph Noise) Tj EMC '
            . '4 Tr 1 0 0 1 72 672 Tm (Filled Clip Visible) Tj '
            . '5 Tr 1 0 0 1 72 656 Tm (Stroked Clip Visible) Tj '
            . '6 Tr 1 0 0 1 72 640 Tm (Fill Stroke Clip Visible) Tj '
            . 'q 3 Tr 1 0 0 1 72 624 Tm (Scoped Hidden Noise) Tj Q '
            . '1 0 0 1 72 608 Tm (Scoped Restore Visible) Tj '
            . '0 Tr 1 0 0 1 72 592 Tm (Visible After) Tj ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithContent($content);
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Visible Before',
            'Filled Clip Visible',
            'Stroked Clip Visible',
            'Fill Stroke Clip Visible',
            'Scoped Restore Visible',
            'Visible After',
        ];
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $pages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same($expected, $styledLines);
        $t->true(!str_contains($plainText, 'Invisible OCR Noise'));
        $t->true(!str_contains($plainText, 'Clip Only Noise'));
        $t->true(!str_contains($plainText, 'Clip Alt Noise'));
        $t->true(!str_contains($plainText, 'Clip Glyph Noise'));
        $t->true(!str_contains($plainText, 'Scoped Hidden Noise'));
        $t->true(!str_contains(json_encode($pages, JSON_THROW_ON_ERROR), 'Clip Only Noise'));
    },
    'applies TJ numeric positioning adjustments before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm [(Import ) -1000 (Profile)] TJ 1 0 0 1 178 720 Tm (s) Tj '
            . '1 0 0 1 72 704 Tm [(Site) 1000 (Map)] TJ 1 0 0 1 124 704 Tm (Index) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Import Profiles', 'SiteMap Index'], $lines);
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(str_contains($plainText, 'SiteMap Index'));
    },
    'ignores PDF comments inside TJ arrays before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT /F1 12 Tf 1 0 0 1 72 720 Tm [(Clean) % ] (Comment Noise) -5000\n"
            . " (Blocks)] TJ 1 0 0 1 150 720 Tm (Ready) Tj T* [(Second) % (Hidden Review Text) 900\n"
            . " (Line)] TJ ET";
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $runs = $extractor->extractTextRuns($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['CleanBlocks Ready', 'SecondLine'], $lines);
        $t->same(['CleanBlocks', 'Ready', 'SecondLine'], $runs);
        $t->true(!str_contains($plainText, 'Comment Noise'));
        $t->true(!str_contains($plainText, 'Hidden Review Text'));
        $t->true(!str_contains($plainText, '5000'));
    },
    'applies Tm horizontal scaling before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1.5 0 0 1 72 720 Tm (Import Profile) Tj 1 0 0 1 204 720 Tm (s) Tj '
            . '0.5 0 0 1 72 704 Tm (SiteMap) Tj 1 0 0 1 106 704 Tm (Index) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Import Profiles', 'SiteMap Index'], $lines);
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(str_contains($plainText, 'SiteMap Index'));
    },
    'decodes literal continuations and UTF-16BE hex strings' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT (WordPress \\\nimport) Tj T* <FEFF00440061007400610020004C0069006200650072006100740069006F006E> Tj ET";
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $t->same(['WordPress import', 'Data Liberation'], $lines);
    },
    'decodes UTF-16 BOM literal strings before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $utf16Be = hex2bin('FEFF0057006F0072006400500072006500730073');
        $utf16Le = hex2bin('FFFE42006C006F0063006B007300');
        $t->true(is_string($utf16Be), 'UTF-16BE literal fixture should decode from hex.');
        $t->true(is_string($utf16Le), 'UTF-16LE literal fixture should decode from hex.');

        $content = "BT /F1 12 Tf 72 720 Td ({$utf16Be}) Tj T* ({$utf16Le}) Tj ET";
        $extractor = new PdfTextExtractor();

        $t->same(['WordPress', 'Blocks'], $extractor->extractTextLines($pdfWithContent($content)));
        $t->same("WordPress\nBlocks", $extractor->extractPlainText($pdfWithContent($content)));
    },
    'decodes PDF literal escapes in Tj and TJ WordPress text' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT (Editor\\'s \\(PDF\\) import\\040notes) Tj T* [(Clean\\053blocks) 120 ( keep nested \\(review\\) text)] TJ T* (Line\\\r\ncontinued and slash\\qkept) Tj ET";
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $runs = $extractor->extractTextRuns($pdfWithContent($content));

        $t->same([
            "Editor's (PDF) import notes",
            'Clean+blocks keep nested (review) text',
            'Linecontinued and slashqkept',
        ], $lines);
        $t->same([
            "Editor's (PDF) import notes",
            'Clean+blocks keep nested (review) text',
            'Linecontinued and slashqkept',
        ], $runs);
        $t->true(!str_contains($extractor->extractPlainText($pdfWithContent($content)), '\\q'));
    },
    'decodes UTF-16 BOM PDF literal strings before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $utf16Be = "\xfe\xff\x00W\x00P\x00 \x00I\x00m\x00p\x00o\x00r\x00t";
        $utf16Le = "\xff\xfeB\x00l\x00o\x00c\x00k\x00s\x00";
        $content = 'BT /F1 12 Tf 72 720 Td (' . $utf16Be . ') Tj T* (' . $utf16Le . ') Tj ET';
        $extractor = new PdfTextExtractor();

        $t->same(['WP Import', 'Blocks'], $extractor->extractTextLines($pdfWithContent($content)));
        $t->same("WP Import\nBlocks", $extractor->extractPlainText($pdfWithContent($content)));
    },
    'extracts block-ready lines from a WordPress import fixture' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-import-content.pdf');
        $t->true(is_string($fixture), 'Fixture should be readable');

        $lines = (new PdfTextExtractor())->extractTextLines($fixture);
        $t->same(['WP Migration', 'Clean blocks from PDF imports', 'Media library captions'], $lines);
    },
    'uses page Contents streams instead of unrelated PDF streams for WordPress text' => static function (TestRunner $t): void {
        $pageOneA = 'BT /F1 12 Tf 72 720 Td (Page One Intro) Tj T* ET';
        $pageOneB = 'BT /F1 12 Tf 72 704 Td (Clean Blocks) Tj ET';
        $pageTwo = 'BT /F1 12 Tf 72 720 Td (Second Page) Tj ET';
        $phantom = 'BT /F1 12 Tf 72 720 Td (Phantom Form Text) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 8 0 R] /Count 2 >>\nendobj\n"
            . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 9 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageOneA) . " >>\nstream\n{$pageOneA}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOneB) . " >>\nstream\n{$pageOneB}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /Length " . strlen($phantom) . " >>\nstream\n{$phantom}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['Page One Intro', 'Clean Blocks', 'Second Page'], $extractor->extractTextLines($pdf));
        $t->same(['Page One Intro', 'Clean Blocks', 'Second Page'], $extractor->extractTextRuns($pdf));
        $t->same("Page One Intro\nClean Blocks\nSecond Page", $extractor->extractPlainText($pdf));
        $t->same("Page One Intro\nClean Blocks\nSecond Page\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Phantom Form Text'));
    },
    'resolves indirect page Contents arrays while preserving page resource stacks' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $pageOne = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $pageTwoA = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $pageTwoB = 'BT /F1 12 Tf 72 704 Td <42> Tj ET';
        $orphan = 'BT /F1 12 Tf 72 720 Td (Indirect Contents fallback leak) Tj ET';
        $cmapOne = $toUnicodeCMap([
            '41' => 'Indirect Array Page One',
        ]);
        $cmapTwo = $toUnicodeCMap([
            '41' => 'Indirect Array Page Two',
            '42' => 'Shared Resource Still Active',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 8 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 30 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageOne /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($orphan) . " >>\nstream\n{$orphan}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 31 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageTwo /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
            . "10 0 obj\n<< /Length " . strlen($pageTwoA) . " >>\nstream\n{$pageTwoA}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Length " . strlen($pageTwoB) . " >>\nstream\n{$pageTwoB}\nendstream\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n"
            . "30 0 obj\n[5 0 R]\nendobj\n"
            . "31 0 obj\n[10 0 R 13 0 R]\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Indirect Array Page One',
            'Indirect Array Page Two',
            'Shared Resource Still Active',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Indirect Array Page One',
            'Indirect Array Page Two',
            'Shared Resource Still Active',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Indirect Array Page One\nIndirect Array Page Two\nShared Resource Still Active", $plainText);
        $t->same("Indirect Array Page One\nIndirect Array Page Two\nShared Resource Still Active\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Indirect Contents fallback leak'));
    },
    'extracts catalog PageLabels number tree for WordPress page boundaries' => static function (TestRunner $t): void {
        $contents = [
            10 => 'BT /F1 12 Tf 72 720 Td (Preface imported) Tj ET',
            11 => 'BT /F1 12 Tf 72 720 Td (Contents imported) Tj ET',
            12 => 'BT /F1 12 Tf 72 720 Td (Chapter starts) Tj ET',
            13 => 'BT /F1 12 Tf 72 720 Td (Chapter continues) Tj ET',
            14 => 'BT /F1 12 Tf 72 720 Td (Appendix imported) Tj ET',
        ];
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R] /Count 5 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 14 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        foreach ($contents as $objectNumber => $content) {
            $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
        }
        $pdf .= "20 0 obj\n<< /Kids [21 0 R 22 0 R] >>\nendobj\n"
            . "21 0 obj\n<< /Nums [0 30 0 R 2 << /S /D /P (Body ) /St 1 >>] >>\nendobj\n"
            . "22 0 obj\n<< /Nums [4 31 0 R] >>\nendobj\n"
            . "30 0 obj\n<< /S /r /P (front-) /St 2 >>\nendobj\n"
            . "31 0 obj\n<< /S /A /P (App-) /St 27 >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $entries = $extractor->extractLabeledPageTexts($pdf);

        $t->same(['front-ii', 'front-iii', 'Body 1', 'Body 2', 'App-AA'], $extractor->extractPageLabels($pdf));
        $t->same(['front-ii', 'front-iii', 'Body 1', 'Body 2', 'App-AA'], array_column($entries, 'page_label'));
        $t->same([0, 1, 2, 3, 4], array_column($entries, 'page_index'));
        $t->same([1, 2, 3, 4, 5], array_column($entries, 'page_number'));
        $t->same('Chapter starts', $entries[2]['text']);
        $t->same("Preface imported\nContents imported\nChapter starts\nChapter continues\nAppendix imported\n", $extractor->naiveGetText($pdf));
    },
    'honors PageLabels number-tree limits and indirect kids before WordPress page boundaries' => static function (TestRunner $t): void {
        $contents = [
            10 => 'BT /F1 12 Tf 72 720 Td (Preface imported) Tj ET',
            11 => 'BT /F1 12 Tf 72 720 Td (Contents imported) Tj ET',
            12 => 'BT /F1 12 Tf 72 720 Td (Body imported) Tj ET',
            13 => 'BT /F1 12 Tf 72 720 Td (End matter imported) Tj ET',
        ];
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R 6 0 R] /Count 4 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        foreach ($contents as $objectNumber => $content) {
            $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
        }
        $pdf .= "20 0 obj\n<< /Kids 40 0 R >>\nendobj\n"
            . "40 0 obj\n[22 0 R 21 0 R]\nendobj\n"
            . "21 0 obj\n<< /Limits [0 1] /Nums [0 << /S /r /P 30 0 R /St 2 >> 3 << /S /D /P (stale-) /St 99 >>] >>\nendobj\n"
            . "22 0 obj\n<< /Limits [2 3] /Nums [2 << /S /D /P (Body ) /St 7 >> 3 31 0 R] >>\nendobj\n"
            . "30 0 obj\n(front-)\nendobj\n"
            . "31 0 obj\n<< /S /A /P (End-) /St 1 >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $entries = $extractor->extractLabeledPageTexts($pdf);

        $t->same(['front-ii', 'front-iii', 'Body 7', 'End-A'], $extractor->extractPageLabels($pdf));
        $t->same(['front-ii', 'front-iii', 'Body 7', 'End-A'], array_column($entries, 'page_label'));
        $t->same(['Preface imported', 'Contents imported', 'Body imported', 'End matter imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-99', array_column($entries, 'page_label'), true));
    },
    'falls back to one-based page labels when PDFs omit PageLabels metadata' => static function (TestRunner $t): void {
        $pageOne = 'BT /F1 12 Tf 72 720 Td (First fallback page) Tj ET';
        $pageTwo = 'BT /F1 12 Tf 72 720 Td (Second fallback page) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n%%EOF";
        $entries = (new PdfTextExtractor())->extractLabeledPageTexts($pdf);

        $t->same(['1', '2'], array_column($entries, 'page_label'));
        $t->same(['First fallback page', 'Second fallback page'], array_column($entries, 'text'));
    },
    'invokes referenced Form XObject content from page Contents before WordPress text' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Page Before Form) Tj ET q /Fm1 Do Q BT /F1 12 Tf 72 672 Td (Page After Form) Tj ET';
        $formContent = 'BT /F1 12 Tf 12 24 Td (Reusable Form Block) Tj T* (Imported Once) Tj ET';
        $unusedFormContent = 'BT /F1 12 Tf 72 720 Td (Dormant Form Text) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> /XObject << /Fm1 5 0 R /FmUnused 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($unusedFormContent) . " >>\nstream\n{$unusedFormContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same([
            'Page Before Form',
            'Reusable Form Block',
            'Imported Once',
            'Page After Form',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Page Before Form',
            'Reusable Form Block',
            'Imported Once',
            'Page After Form',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Page Before Form\nReusable Form Block\nImported Once\nPage After Form", $extractor->extractPlainText($pdf));
        $t->same("Page Before Form\nReusable Form Block\nImported Once\nPage After Form\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Dormant Form Text'));
    },
    'applies Form XObject matrix and BBox clipping before WordPress text extraction' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Page Before Matrix Form) Tj ET q 1 0 0 1 24 0 cm /FmScaled Do Q BT /F1 12 Tf 72 672 Td (Page After Matrix Form) Tj ET';
        $formContent = 'BT /F1 12 Tf (Origin Hidden) Tj 1 0 0 1 0 24 Tm (Data) Tj 1 0 0 1 34 24 Tm (base) Tj 1 0 0 1 10 90 Tm (BBox Noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> /XObject << /FmScaled 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [-5 1 80 50] /Matrix [2 0 0 1 0 0] /Resources << /Font << /F1 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Page Before Matrix Form',
            'Data base',
            'Page After Matrix Form',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Page Before Matrix Form',
            'Data',
            'base',
            'Page After Matrix Form',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Page Before Matrix Form\nData base\nPage After Matrix Form", $plainText);
        $t->same("Page Before Matrix Form\nData base\nPage After Matrix Form\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Database'));
        $t->true(!str_contains($plainText, 'Origin Hidden'));
        $t->true(!str_contains($plainText, 'BBox Noise'));
    },
    'keeps nested Form XObject resource fonts scoped before WordPress text extraction' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $parentCMap = $toUnicodeCMap(['41' => 'Parent Form']);
        $childCMap = $toUnicodeCMap(['42' => 'Child Form']);
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Page Before Nested Form) Tj ET q /ParentForm Do Q BT /F1 12 Tf 72 672 Td (Page After Nested Form) Tj ET';
        $parentFormContent = 'BT /F1 12 Tf 12 24 Td <41> Tj ET q /ChildForm Do Q';
        $childFormContent = 'BT /F1 12 Tf 12 12 Td <42> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /XObject << /ParentForm 5 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 8 0 R >> /XObject << /ChildForm 6 0 R >> >> /Length " . strlen($parentFormContent) . " >>\nstream\n{$parentFormContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 10 0 R >> >> /Length " . strlen($childFormContent) . " >>\nstream\n{$childFormContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentFormSubset /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ChildFormSubset /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($childCMap) . " >>\nstream\n{$childCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Page Before Nested Form',
            'Parent Form',
            'Child Form',
            'Page After Nested Form',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Page Before Nested Form',
            'Parent Form',
            'Child Form',
            'Page After Nested Form',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Page Before Nested Form\nParent Form\nChild Form\nPage After Nested Form", $plainText);
        $t->same("Page Before Nested Form\nParent Form\nChild Form\nPage After Nested Form\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, "\nA\n"));
        $t->true(!str_contains($plainText, "\nB\n"));
    },
    'skips cyclic Form XObject resource re-entry before WordPress text extraction' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $parentCMap = $toUnicodeCMap(['41' => 'Parent Cycle']);
        $childCMap = $toUnicodeCMap(['42' => 'Child Cycle']);
        $selfCMap = $toUnicodeCMap(['43' => 'Self Cycle']);
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Page Cycle Start) Tj ET q /Parent Do Q q /Parent Do Q q /Self Do Q BT /F1 12 Tf 72 672 Td (Page Cycle End) Tj ET';
        $parentFormContent = 'BT /F1 12 Tf 12 24 Td <41> Tj ET q /Child Do Q';
        $childFormContent = 'BT /F1 12 Tf 12 12 Td <42> Tj ET q /Parent Do Q';
        $selfFormContent = 'BT /F1 12 Tf 12 8 Td <43> Tj ET q /Self Do Q';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /XObject << /Parent 5 0 R /Self 6 0 R >> >> /Contents 13 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 8 0 R >> /XObject << /Child 7 0 R >> >> /Length " . strlen($parentFormContent) . " >>\nstream\n{$parentFormContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 12 0 R >> /XObject << /Self 6 0 R >> >> /Length " . strlen($selfFormContent) . " >>\nstream\n{$selfFormContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 10 0 R >> /XObject << /Parent 5 0 R >> >> /Length " . strlen($childFormContent) . " >>\nstream\n{$childFormContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentCycleSubset /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ChildCycleSubset /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($childCMap) . " >>\nstream\n{$childCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SelfCycleSubset /Encoding /Identity-H /ToUnicode 14 0 R >>\nendobj\n"
            . "13 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "14 0 obj\n<< /Length " . strlen($selfCMap) . " >>\nstream\n{$selfCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Page Cycle Start',
            'Parent Cycle',
            'Child Cycle',
            'Parent Cycle',
            'Child Cycle',
            'Self Cycle',
            'Page Cycle End',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(2, substr_count($plainText, 'Parent Cycle'));
        $t->same(2, substr_count($plainText, 'Child Cycle'));
        $t->same(1, substr_count($plainText, 'Self Cycle'));
        $t->true(!str_contains($plainText, "\nA\n"));
        $t->true(!str_contains($plainText, "\nB\n"));
        $t->true(!str_contains($plainText, "\nC\n"));
    },
    'honors optional content group layer visibility before WordPress text extraction' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Base Visible Text) Tj ET '
            . '/OC /LayerOff BDC BT /F1 12 Tf 72 704 Td (Hidden Layer Text) Tj ET q /VisibleForm Do Q EMC '
            . '/OC /LayerOn BDC BT /F1 12 Tf 72 688 Td (Layer Visible Text) Tj ET q /HiddenForm Do Q EMC '
            . 'q /VisibleForm Do Q q /HiddenForm Do Q';
        $visibleForm = 'BT /F1 12 Tf 12 24 Td (Visible Form Text) Tj ET';
        $hiddenForm = 'BT /F1 12 Tf 12 24 Td (Hidden Form Text) Tj ET';
        $visibleAnnotation = 'BT /F1 12 Tf 0 0 Td (Visible Annotation Text) Tj ET';
        $hiddenAnnotation = 'BT /F1 12 Tf 0 0 Td (Hidden Annotation Text) Tj ET';
        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /LayerOn 20 0 R /LayerOff 21 0 R >> /XObject << /VisibleForm 8 0 R /HiddenForm 9 0 R >> >> /Annots [10 0 R 11 0 R] /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Form /OC 20 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($visibleForm) . " >>\nstream\n{$visibleForm}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Form /OC 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($hiddenForm) . " >>\nstream\n{$hiddenForm}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Widget /OC 20 0 R /AP << /N 12 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /Widget /OC 21 0 R /AP << /N 13 0 R >> >>\nendobj\n"
            . "12 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($visibleAnnotation) . " >>\nstream\n{$visibleAnnotation}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($hiddenAnnotation) . " >>\nstream\n{$hiddenAnnotation}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /OCG /Name (Visible Import Layer) >>\nendobj\n"
            . "21 0 obj\n<< /Type /OCG /Name (Hidden Review Layer) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Base Visible Text',
            'Layer Visible Text',
            'Visible Form Text',
            'Visible Annotation Text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Hidden Layer Text'));
        $t->true(!str_contains($plainText, 'Hidden Form Text'));
        $t->true(!str_contains($plainText, 'Hidden Annotation Text'));
    },
    'applies optional content usage application and intent state before WordPress text extraction' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Base Current View) Tj ET '
            . '/OC /DesignOnly BDC BT /F1 12 Tf 72 704 Td (Design Layer Noise) Tj ET EMC '
            . '/OC /ViewUsageOff BDC BT /F1 12 Tf 72 688 Td (Usage Hidden Text) Tj ET EMC '
            . '/OC /ViewUsageOn BDC BT /F1 12 Tf 72 672 Td (Usage View Visible) Tj ET q /VisibleUsageForm Do Q EMC '
            . '/OC /ConfigOff BDC BT /F1 12 Tf 72 656 Td (Off Array Usage Ignored) Tj ET EMC '
            . '/OC /MixedIntent BDC BT /F1 12 Tf 72 640 Td (Mixed Intent Visible) Tj ET EMC '
            . '/OC /AllOnMembership BDC BT /F1 12 Tf 72 624 Td (Membership Hidden Text) Tj ET EMC';
        $visibleForm = 'BT /F1 12 Tf 12 24 Td (Visible Usage Form) Tj ET';
        $hiddenForm = 'BT /F1 12 Tf 12 24 Td (Hidden Usage Form) Tj ET';
        $visibleAnnotation = 'BT /F1 12 Tf 0 0 Td (Visible Usage Annotation) Tj ET';
        $hiddenAnnotation = 'BT /F1 12 Tf 0 0 Td (Hidden Usage Annotation) Tj ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R 22 0 R 24 0 R 25 0 R] /D << /Intent /View /BaseState /ON /OFF [24 0 R] /AS [<< /Event /View /Category [/View] /OCGs [21 0 R 22 0 R] >> << /Event /Print /Category [/View] /OCGs [20 0 R] >>] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /DesignOnly 20 0 R /ViewUsageOff 21 0 R /ViewUsageOn 22 0 R /ConfigOff 24 0 R /MixedIntent 25 0 R /AllOnMembership << /Type /OCMD /OCGs [20 0 R 22 0 R] /P /AllOn >> >> /XObject << /VisibleUsageForm 8 0 R /HiddenUsageForm 9 0 R >> >> /Annots [10 0 R 11 0 R] /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Form /OC 22 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($visibleForm) . " >>\nstream\n{$visibleForm}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Form /OC 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($hiddenForm) . " >>\nstream\n{$hiddenForm}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Widget /OC 22 0 R /AP << /N 12 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /Widget /OC 21 0 R /AP << /N 13 0 R >> >>\nendobj\n"
            . "12 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($visibleAnnotation) . " >>\nstream\n{$visibleAnnotation}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($hiddenAnnotation) . " >>\nstream\n{$hiddenAnnotation}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /OCG /Name (Design Only Layer) /Intent /Design /Usage << /View << /ViewState /ON >> >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /OCG /Name (View Usage Hidden) /Intent /View /Usage << /View << /ViewState /OFF >> >> >>\nendobj\n"
            . "22 0 obj\n<< /Type /OCG /Name (View Usage Visible) /Intent /View /Usage << /View << /ViewState /ON >> >> >>\nendobj\n"
            . "24 0 obj\n<< /Type /OCG /Name (Config Off Layer) /Intent /View /Usage << /View << /ViewState /ON >> >> >>\nendobj\n"
            . "25 0 obj\n<< /Type /OCG /Name (Mixed Intent Layer) /Intent [/Design /View] >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Base Current View',
            'Usage View Visible',
            'Visible Usage Form',
            'Mixed Intent Visible',
            'Visible Usage Annotation',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Design Layer Noise'));
        $t->true(!str_contains($plainText, 'Usage Hidden Text'));
        $t->true(!str_contains($plainText, 'Off Array Usage Ignored'));
        $t->true(!str_contains($plainText, 'Membership Hidden Text'));
        $t->true(!str_contains($plainText, 'Hidden Usage Form'));
        $t->true(!str_contains($plainText, 'Hidden Usage Annotation'));
    },
    'skips Image XObject streams in fallback before WordPress text extraction' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Visible Text) Tj ET';
        $imageBytes = 'BT /F1 12 Tf 72 720 Td (Raster Image Noise) Tj ET';
        $compressedImageBytes = (string) gzcompress('BT /F1 12 Tf 72 720 Td (Compressed Image Noise) Tj ET');
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($imageBytes) . " >>\nstream\n{$imageBytes}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImageBytes) . " >>\nstream\n{$compressedImageBytes}\nendstream\nendobj\n"
            . "%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible Text'], $extractor->extractTextLines($pdf));
        $t->same(['Visible Text'], $extractor->extractTextRuns($pdf));
        $t->same('Visible Text', $plainText);
        $t->true(!str_contains($plainText, 'Raster Image Noise'));
        $t->true(!str_contains($plainText, 'Compressed Image Noise'));
    },
    'skips device Indexed and ICCBased image streams before WordPress text extraction' => static function (TestRunner $t): void {
        $visibleContent = 'BT /F1 12 Tf 72 720 Td (ColorSpace Import) Tj T* (Device Paragraph) Tj ET';
        $deviceNoise = 'BT /F1 12 Tf 72 720 Td (Device RGB Noise) Tj ET';
        $indexedNoise = 'BT /F1 12 Tf 72 704 Td (Indexed Palette Noise) Tj ET';
        $iccNoise = 'BT /F1 12 Tf 72 688 Td (ICC Profile Noise) Tj ET';
        $fallbackPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Width 2 /Height 1 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Length " . strlen($deviceNoise) . " >>\nstream\n{$deviceNoise}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Width 2 /Height 1 /BitsPerComponent 8 /ColorSpace [/Indexed /DeviceRGB 1 <000000FFFFFF>] /Length " . strlen($indexedNoise) . " >>\nstream\n{$indexedNoise}\nendstream\nendobj\n"
            . "4 0 obj\n<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nICCFAKE\nendstream\nendobj\n"
            . "5 0 obj\n<< /Width 2 /Height 1 /BitsPerComponent 8 /ColorSpace [/ICCBased 4 0 R] /Length " . strlen($iccNoise) . " >>\nstream\n{$iccNoise}\nendstream\nendobj\n"
            . "%%EOF";

        $pageBefore = 'BT /F1 12 Tf 72 720 Td (Page Device Start) Tj ET';
        $pageAfter = 'BT /F1 12 Tf 72 688 Td (Page Device End) Tj ET';
        $pageDeviceNoise = 'BT /F1 12 Tf 72 704 Td (Device CMYK Noise) Tj ET';
        $pageIndexedNoise = 'BT /F1 12 Tf 72 704 Td (Indirect Indexed Noise) Tj ET';
        $pageIccNoise = 'BT /F1 12 Tf 72 704 Td (Indirect ICC Noise) Tj ET';
        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 12 0 R >> >> /Contents [4 0 R 5 0 R 7 0 R 8 0 R 10 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageBefore) . " >>\nstream\n{$pageBefore}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Width 1 /Height 1 /BitsPerComponent 8 /ColorSpace /DeviceCMYK /Length " . strlen($pageDeviceNoise) . " >>\nstream\n{$pageDeviceNoise}\nendstream\nendobj\n"
            . "6 0 obj\n[/Indexed /DeviceGray 1 <00FF>]\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($pageAfter) . " >>\nstream\n{$pageAfter}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Width 1 /Height 1 /BitsPerComponent 8 /ColorSpace 6 0 R /Length " . strlen($pageIndexedNoise) . " >>\nstream\n{$pageIndexedNoise}\nendstream\nendobj\n"
            . "9 0 obj\n[/ICCBased 11 0 R]\nendobj\n"
            . "10 0 obj\n<< /Width 1 /Height 1 /BitsPerComponent 8 /ColorSpace 9 0 R /Length " . strlen($pageIccNoise) . " >>\nstream\n{$pageIccNoise}\nendstream\nendobj\n"
            . "11 0 obj\n<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nICCFAKE\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $fallbackText = $extractor->extractPlainText($fallbackPdf);
        $pageText = $extractor->extractPlainText($pagePdf);

        $t->same("ColorSpace Import\nDevice Paragraph", $fallbackText);
        $t->same(['ColorSpace Import', 'Device Paragraph'], $extractor->extractTextRuns($fallbackPdf));
        $t->same("Page Device Start\nPage Device End", $pageText);
        $t->same(['Page Device Start', 'Page Device End'], $extractor->extractTextRuns($pagePdf));
        $t->same("Page Device Start\nPage Device End\n", $extractor->naiveGetText($pagePdf));
        $t->true(!str_contains($fallbackText, 'Device RGB Noise'));
        $t->true(!str_contains($fallbackText, 'Indexed Palette Noise'));
        $t->true(!str_contains($fallbackText, 'ICC Profile Noise'));
        $t->true(!str_contains($pageText, 'Device CMYK Noise'));
        $t->true(!str_contains($pageText, 'Indirect Indexed Noise'));
        $t->true(!str_contains($pageText, 'Indirect ICC Noise'));
    },
    'skips inline image data in page Contents before WordPress text extraction' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (Visible Before Image) Tj ET\n"
            . "BI /W 3 /H 1 /CS /DeviceGray /BPC 8 ID \n"
            . "rawEIbytes BT /F1 12 Tf 72 720 Td (Inline Image Noise) Tj ET\n"
            . "EI\n"
            . "BT /F1 12 Tf 72 704 Td (Visible After Image) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible Before Image', 'Visible After Image'], $extractor->extractTextLines($pdf));
        $t->same(['Visible Before Image', 'Visible After Image'], $extractor->extractTextRuns($pdf));
        $t->same("Visible Before Image\nVisible After Image", $plainText);
        $t->same("Visible Before Image\nVisible After Image\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Inline Image Noise'));
        $t->true(!str_contains($plainText, 'rawEIbytes'));
    },
    'uses inline image abbreviations and DecodeParms before accepting EI bytes in compressed data' => static function (TestRunner $t): void {
        $imageRow = 'raw EI BT /F1 12 Tf 72 690 Td (Inline DP Image Noise) Tj ET';
        $compressedImage = gzcompress("\0" . $imageRow, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build inline image fixture.');
        }
        $t->true(str_contains($compressedImage, ' EI '));

        $content = "BT /F1 12 Tf 72 720 Td (Before DP Inline Image) Tj ET\n"
            . 'BI /W ' . strlen($imageRow) . ' /H 1 /CS /G /BPC 8 /F /Fl '
            . '/DP << /Predictor 12 /Columns ' . strlen($imageRow) . " /Colors 1 /BitsPerComponent 8 >> ID "
            . $compressedImage . "\nEI\n"
            . 'BT /F1 12 Tf 72 704 Td (After DP Inline Image) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before DP Inline Image', 'After DP Inline Image'], $extractor->extractTextLines($pdf));
        $t->same(['Before DP Inline Image', 'After DP Inline Image'], $extractor->extractTextRuns($pdf));
        $t->same("Before DP Inline Image\nAfter DP Inline Image", $plainText);
        $t->same("Before DP Inline Image\nAfter DP Inline Image\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Inline DP Image Noise'));
        $t->true(!str_contains($plainText, 'raw EI'));
    },
    'inherits page tree font resources per page before WordPress text extraction' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $pageOne = 'BT /F1 12 Tf 72 720 Td <4142> Tj ET';
        $pageTwo = 'BT /F1 12 Tf 72 720 Td <4142> Tj ET';
        $cmapOne = $toUnicodeCMap([
            '41' => 'Inherited',
            '42' => ' One',
        ]);
        $cmapTwo = $toUnicodeCMap([
            '41' => 'Inherited',
            '42' => ' Two',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 2 >>\nendobj\n"
            . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
            . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [8 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedOne /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedTwo /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['Inherited One', 'Inherited Two'], $extractor->extractTextLines($pdf));
        $t->same(['Inherited One', 'Inherited Two'], $extractor->extractTextRuns($pdf));
        $t->same("Inherited One\nInherited Two", $extractor->extractPlainText($pdf));
        $t->same("Inherited One\nInherited Two\n", $extractor->naiveGetText($pdf));
    },
    'walks indirect page tree Kids arrays and ignores stale Count before WordPress text extraction' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $pageOne = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $pageTwo = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $cmapOne = $toUnicodeCMap([
            '41' => 'Kids First Branch',
        ]);
        $cmapTwo = $toUnicodeCMap([
            '41' => 'Kids Second Branch',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids 30 0 R /Count 99 >>\nendobj\n"
            . "30 0 obj\n[20 0 R 10 0 R]\nendobj\n"
            . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 77 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
            . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids 31 0 R /Count 88 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
            . "31 0 obj\n[8 0 R]\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 7 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 5 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidsFirst /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidsSecond /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['Kids First Branch', 'Kids Second Branch'], $extractor->extractTextLines($pdf));
        $t->same(['Kids First Branch', 'Kids Second Branch'], $extractor->extractTextRuns($pdf));
        $t->same("Kids First Branch\nKids Second Branch", $extractor->extractPlainText($pdf));
        $t->same("Kids First Branch\nKids Second Branch\n", $extractor->naiveGetText($pdf));
        $t->same(['1', '2'], $extractor->extractPageLabels($pdf));
        $t->same(2, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'guards cyclic page tree Kids while preserving inherited resources before WordPress text extraction' => static function (TestRunner $t) use ($toUnicodeCMap): void {
        $pageOne = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $pageTwo = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
        $orphan = 'BT /F1 12 Tf 72 720 Td (Orphan fallback leak) Tj ET';
        $cmapOne = $toUnicodeCMap([
            '41' => 'Cycle Resource First',
        ]);
        $cmapTwo = $toUnicodeCMap([
            '41' => 'Cycle Resource Second',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 2 0 R 10 0 R 20 0 R] /Count 99 >>\nendobj\n"
            . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R 10 0 R 3 0 R] /Count 77 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
            . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [8 0 R 20 0 R] /Count 88 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 9 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CycleFirst /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CycleSecond /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($orphan) . " >>\nstream\n{$orphan}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Cycle Resource First', 'Cycle Resource Second'], $extractor->extractTextLines($pdf));
        $t->same(['Cycle Resource First', 'Cycle Resource Second'], $extractor->extractTextRuns($pdf));
        $t->same("Cycle Resource First\nCycle Resource Second", $plainText);
        $t->same("Cycle Resource First\nCycle Resource Second\n", $extractor->naiveGetText($pdf));
        $t->same(['1', '2'], $extractor->extractPageLabels($pdf));
        $t->same(2, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(1, substr_count($plainText, 'Cycle Resource First'));
        $t->same(1, substr_count($plainText, 'Cycle Resource Second'));
        $t->true(!str_contains($plainText, 'Orphan fallback leak'));
    },
    'uses tagged PDF StructTreeRoot MCID order before content stream order' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf '
            . '/P << /MCID 1 >> BDC 72 704 Td (Body paragraph second) Tj EMC '
            . '/H1 << /MCID 0 >> BDC 72 720 Td (Tagged heading first) Tj EMC '
            . '/Artifact << /MCID 9 >> BDC 72 688 Td (Artifact footer noise) Tj EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /StructTreeRoot /K [21 0 R 22 0 R] >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /H1 /Pg 3 0 R /K 0 >>\nendobj\n"
            . "22 0 obj\n<< /Type /StructElem /S /P /K << /Type /MCR /Pg 3 0 R /MCID 1 >> >>\nendobj\n"
            . "%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Tagged heading first', 'Body paragraph second'], $extractor->extractTextLines($pdf));
        $t->same(['Tagged heading first', 'Body paragraph second'], $extractor->extractTextRuns($pdf));
        $t->same("Tagged heading first\nBody paragraph second", $plainText);
        $t->same("Tagged heading first\nBody paragraph second\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Artifact footer noise'));
    },
    'resolves StructTreeRoot RoleMap and tagged-content replacements before WordPress review' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf '
            . '/BodyCopy /BodyProp BDC 72 704 Td (Body glyph noise) Tj EMC '
            . '/Chap#54itle << /MCID 0 >> BDC 72 720 Td (Chapter heading glyphs) Tj EMC '
            . '/Illustration << /MCID 2 /Alt (Figure dashboard alt text) >> BDC q /Im1 Do Q EMC '
            . '/Artifact << /MCID 9 >> BDC 72 688 Td (Artifact footer noise) Tj EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /BodyProp 30 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 24 0 R /K [21 0 R 22 0 R 23 0 R] >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /Chap#54itle /Pg 3 0 R /K 0 >>\nendobj\n"
            . "22 0 obj\n<< /Type /StructElem /S /BodyCopy /Pg 3 0 R /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "23 0 obj\n<< /Type /StructElem /S /Illustration /Pg 3 0 R /K [<< /Type /MCR /Pg 3 0 R /MCID 2 >>] >>\nendobj\n"
            . "24 0 obj\n<< /Chap#54itle /H1 /BodyCopy /P /Illustration /Figure >>\nendobj\n"
            . "30 0 obj\n<< /MCID 1 /ActualText (Mapped paragraph replacement) >>\nendobj\n"
            . "%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $tagged = $extractor->extractTaggedContent($pdf);

        $t->same([
            'Chapter heading glyphs',
            'Mapped paragraph replacement',
            'Figure dashboard alt text',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Chapter heading glyphs',
            'Mapped paragraph replacement',
            'Figure dashboard alt text',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Chapter heading glyphs\nMapped paragraph replacement\nFigure dashboard alt text", $plainText);
        $t->true(!str_contains($plainText, 'Body glyph noise'));
        $t->true(!str_contains($plainText, 'Artifact footer noise'));
        $t->same(['H1', 'P', 'Figure'], array_column($tagged, 'role'));
        $t->same(['ChapTitle', 'BodyCopy', 'Illustration'], array_column($tagged, 'raw_role'));
        $t->same([true, true, true], array_column($tagged, 'role_mapped'));
        $t->same([0, 1, 2], array_column($tagged, 'mcid'));
        $t->same([
            ['ChapTitle'],
            ['BodyCopy'],
            ['Illustration'],
        ], array_column($tagged, 'content_tags'));
        $t->same([
            'Chapter heading glyphs',
            'Mapped paragraph replacement',
            'Figure dashboard alt text',
        ], array_column($tagged, 'text'));
    },
    'uses catalog Threads bead order before raw page content order' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Article part one) Tj ET '
            . 'BT /F1 12 Tf 320 720 Td (Article part three) Tj ET '
            . 'BT /F1 12 Tf 72 640 Td (Article part two) Tj ET '
            . 'BT /F1 12 Tf 320 640 Td (Article part four) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (WordPress Article Thread) >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 620 250 740] /N 22 0 R /V 22 0 R >>\nendobj\n"
            . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [300 620 520 740] /N 21 0 R /V 21 0 R >>\nendobj\n"
            . "%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Article part one',
            'Article part two',
            'Article part three',
            'Article part four',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, "Article part three\nArticle part two"));
    },
    'parses object streams through xref stream entries before WordPress text extraction' => static function (TestRunner $t) use ($objectStreamXrefPdf): void {
        $pdf = $objectStreamXrefPdf();
        $extractor = new PdfTextExtractor();

        $t->same(['Object stream page', 'Plain Direct Font'], $extractor->extractTextLines($pdf));
        $t->same(['Object stream page', 'Plain Direct Font'], $extractor->extractTextRuns($pdf));
        $t->same("Object stream page\nPlain Direct Font", $extractor->extractPlainText($pdf));
        $t->same("Object stream page\nPlain Direct Font\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Phantom stale object stream text'));
        $t->true(!str_contains($extractor->extractPlainText($pdf), "\0"));
    },
    'honors hybrid xref Prev free entries and encrypted PDF preflight before WordPress text extraction' => static function (TestRunner $t) use ($xrefHybridPrevFreeGenerationPdf, $encryptedPreflightPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridPrevFreeGenerationPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Hybrid current page', 'Free generation guard'], $extractor->extractTextLines($pdf));
        $t->same(['Hybrid current page', 'Free generation guard'], $extractor->extractTextRuns($pdf));
        $t->same("Hybrid current page\nFree generation guard", $text);
        $t->same("Hybrid current page\nFree generation guard\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($text, 'Stale previous xref page'));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));

        $encrypted = $encryptedPreflightPdf();
        $t->same('', $extractor->extractPlainText($encrypted));
        $t->same([], $extractor->extractTextRuns($encrypted));
        $t->same('', $extractor->naiveGetText($encrypted));
        $t->same([], $extractor->extractPageLabels($encrypted));
        $t->same([
            'pdf_toc' => [],
            'document_info' => [],
            'pages' => 0,
        ], $extractor->extractOutlineMetadata($encrypted));
    },
    'suppresses object stream members when current xref free entries reserve a reused generation' => static function (TestRunner $t) use ($objectStreamFreeEntryReusePdf): void {
        $pdf = $objectStreamFreeEntryReusePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current object generation page'], $extractor->extractTextLines($pdf));
        $t->same(['Current object generation page'], $extractor->extractTextRuns($pdf));
        $t->same('Current object generation page', $text);
        $t->same("Current object generation page\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($text, 'Stale freed object stream page'));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
    },
    'recovers the latest trailer Root catalog generation before stale catalog order' => static function (TestRunner $t) use ($trailerRootGenerationRecoveryPdf): void {
        $pdf = $trailerRootGenerationRecoveryPdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Recovered trailer root page', 'Generation one catalog'], $extractor->extractTextLines($pdf));
        $t->same(['Recovered trailer root page', 'Generation one catalog'], $extractor->extractTextRuns($pdf));
        $t->same("Recovered trailer root page\nGeneration one catalog", $text);
        $t->same("Recovered trailer root page\nGeneration one catalog\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale catalog page'));
    },
    'honors startxref current section before stale appended object-stream rebuild entries' => static function (TestRunner $t) use ($startxrefObjectStreamRebuildPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $startxrefObjectStreamRebuildPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current startxref page', 'Object stream rebuild guard'], $extractor->extractTextLines($pdf));
        $t->same(['Current startxref page', 'Object stream rebuild guard'], $extractor->extractTextRuns($pdf));
        $t->same("Current startxref page\nObject stream rebuild guard", $text);
        $t->same("Current startxref page\nObject stream rebuild guard\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale appended object stream page'));
    },
    'honors current xref stream Index and zero-width W defaults before WordPress text extraction' => static function (TestRunner $t) use ($xrefStreamIndexWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamIndexWidthCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current xref stream page', 'Width default import'], $extractor->extractTextLines($pdf));
        $t->same(['Current xref stream page', 'Width default import'], $extractor->extractTextRuns($pdf));
        $t->same("Current xref stream page\nWidth default import", $text);
        $t->same("Current xref stream page\nWidth default import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale xref stream page'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs xref stream Prev generation rows by exact offsets before WordPress text extraction' => static function (TestRunner $t) use ($xrefPrevStreamGenerationRepairPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefPrevStreamGenerationRepairPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current generation stream page', 'Offset repaired generation'], $extractor->extractTextLines($pdf));
        $t->same(['Current generation stream page', 'Offset repaired generation'], $extractor->extractTextRuns($pdf));
        $t->same("Current generation stream page\nOffset repaired generation", $text);
        $t->same("Current generation stream page\nOffset repaired generation\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale previous stream generation page'));
    },
    'skips linearized hint table streams at xref boundaries before fallback WordPress text extraction' => static function (TestRunner $t) use ($linearizedHintTableFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $linearizedHintTableFallbackPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Linearized current fallback', 'Hint table boundary'], $extractor->extractTextLines($pdf));
        $t->same(['Linearized current fallback', 'Hint table boundary'], $extractor->extractTextRuns($pdf));
        $t->same("Linearized current fallback\nHint table boundary", $text);
        $t->same("Linearized current fallback\nHint table boundary\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($text, 'Linearized hint stale leak'));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
    },
    'extracts native PDF outline and Info metadata before WordPress import' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Outlines /First 8 0 R /Last 10 0 R /Count 3 >>\nendobj\n"
            . "8 0 obj\n<< /Title (Migration \\(Runbook\\)) /Parent 7 0 R /Dest [3 0 R /Fit] /First 9 0 R /Last 9 0 R /Next 10 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Title <FEFF004300680069006C0064002000530065006300740069006F006E> /Parent 8 0 R /Dest [4 0 R /XYZ null null null] >>\nendobj\n"
            . "10 0 obj\n<< /Title (Review Queue) /Parent 7 0 R /A << /S /GoTo /D [4 0 R /FitH 720] >> >>\nendobj\n"
            . "11 0 obj\n<< /Title (WP PDF Import) /Author (Data Liberation Team) /Keywords (outline metadata) /CreationDate (D:20260602004006Z) >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Info 11 0 R >>\n%%EOF";

        $metadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);

        $t->same(2, $metadata['pages']);
        $t->same([
            ['title' => 'Migration (Runbook)', 'level' => 1, 'page' => 0],
            ['title' => 'Child Section', 'level' => 2, 'page' => 1],
            ['title' => 'Review Queue', 'level' => 1, 'page' => 1],
        ], $metadata['pdf_toc']);
        $t->same('WP PDF Import', $metadata['document_info']['title']);
        $t->same('Data Liberation Team', $metadata['document_info']['author']);
        $t->same('outline metadata', $metadata['document_info']['keywords']);
        $t->same('D:20260602004006Z', $metadata['document_info']['creation_date']);
    },
    'replays upstream naive_get_text page suffix and get_length_of_text trim boundary' => static function (TestRunner $t) use ($pdfWithStreams): void {
        $pdf = $pdfWithStreams([
            'BT (First page) Tj T* (Second line) Tj ET',
            'BT (Second page) Tj ET',
        ]);
        $extractor = new PdfTextExtractor();
        $expectedText = "First page\nSecond line\nSecond page\n";

        $t->same($expectedText, $extractor->naiveGetText($pdf));

        $path = sys_get_temp_dir() . '/markerpdf-text-length-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, $pdf);
        try {
            $t->same(strlen(trim($expectedText)), $extractor->getLengthOfText($path));
        } finally {
            @unlink($path);
        }
    },
];
