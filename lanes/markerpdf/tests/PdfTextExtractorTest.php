<?php

declare(strict_types=1);

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
