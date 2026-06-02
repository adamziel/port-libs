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

$lzwLiteralEncode = static function (string $bytes) use ($lzwPackCodes): string {
    return $lzwPackCodes([
        256,
        ...array_map('ord', str_split($bytes)),
        257,
    ]);
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

return [
    'extracts literal and array text operators from content streams' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td (Hello \\(WP\\)) Tj [(Data) 120 ( Liberation)] TJ ET";
        $runs = (new PdfTextExtractor())->extractTextRuns($pdfWithContent($content));
        $t->same(['Hello (WP)', 'Data Liberation'], $runs);
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
