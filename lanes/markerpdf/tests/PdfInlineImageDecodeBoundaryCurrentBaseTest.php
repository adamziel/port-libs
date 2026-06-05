<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

$inlineImageDecodeBoundaryPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$ascii85Encode = static function (string $bytes, bool $includeTerminator = true): string {
    $encoded = '<~';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        $digits = [];
        for ($index = 0; $index < 5; $index++) {
            $digits[] = chr(($value % 85) + 33);
            $value = intdiv($value, 85);
        }

        $encoded .= implode('', array_slice(array_reverse($digits), 0, $chunkLength + 1));
    }

    return $encoded . ($includeTerminator ? '~>' : '');
};

$lzwPackCodes = static function (array $codes, int $earlyChange = 1): string {
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
            throw new RuntimeException('Focused inline LZW fixture code does not fit the current code size.');
        }

        for ($shift = $codeSize - 1; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    };

    $resetDictionary();
    $previous = null;
    foreach ($codes as $code) {
        if (!is_int($code) || $code < 0 || $code > 4095) {
            throw new RuntimeException('Focused inline LZW fixture codes must be 12-bit integers.');
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
            throw new RuntimeException('Focused inline LZW fixture references an unknown dictionary code.');
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

$lzwLiteralEncode = static function (string $bytes, int $earlyChange = 1) use ($lzwPackCodes): string {
    return $lzwPackCodes([
        256,
        ...array_map('ord', str_split($bytes)),
        257,
    ], $earlyChange);
};

$tiffPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Focused inline predictor rows must be fixed-width.');
        }

        $raw = $row;
        for ($index = 1; $index < $columns; $index++) {
            $row[$index] = chr((ord($raw[$index]) - ord($raw[$index - 1])) & 0xff);
        }
        $encoded .= $row;
    }

    return $encoded;
};

$runLengthLiteralEncode = static function (string $bytes, bool $includeEod = true): string {
    if ($bytes === '') {
        return $includeEod ? chr(128) : '';
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . ($includeEod ? chr(128) : '');
};

return [
    'requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payload = '87cURDc^jtCh* EI BT /F1 12 Tf 72 700 Td (ASCII85 inline image leak) Tj ET ~>';
        $content = "BT /F1 12 Tf 72 720 Td (Before A85 Inline Image) Tj ET\n"
            . "BI /F /A85 ID\n{$payload}\nEI\n"
            . "BT /F1 12 Tf 72 680 Td (After A85 Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before A85 Inline Image',
            'After A85 Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'ASCII85 inline image leak'));
        $t->true(!str_contains($plainText, '87cURDc'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'requires ASCII85 inline image review payload terminator before RGB preview decoding' => static function (TestRunner $t) use ($ascii85Encode): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            91 => '<000000FF000000FF000000FF>',
        ];
        $dictionary = '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F /A85 /D [0 3]';
        $imageBytes = "\x1c";
        $completePayload = $ascii85Encode($imageBytes, true);
        $incompletePayload = $ascii85Encode($imageBytes, false);

        $preview = $renderer->inlineIndexedImageStreamPreviewRows($dictionary, $completePayload, $objects, 3);

        $t->same(['ASCII85Decode'], $preview['image_stream']['filters']);
        $t->same(strlen($completePayload), $preview['image_stream']['raw_length']);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $imageBytes), $preview['image_stream']['decoded_sha256']);
        $t->same('1C', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same([0.0, 1.0, 3.0], array_column($preview['pixels'], 'raw_sample'));
        $t->same(true, str_contains($completePayload, '~>'));
        $t->same(false, str_contains($incompletePayload, '~>'));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineIndexedImageStreamPreviewRows($dictionary, $incompletePayload, $objects, 3)
        );
    },
    'decodes Flate DecodeParms inline image payload before accepting EI boundaries' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payloadText = 'raw EI BT /F1 12 Tf 72 690 Td (Inline DP Image Noise) Tj ET';
        $compressedImage = gzcompress("\0" . $payloadText, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build inline image DecodeParms fixture.');
        }

        $content = "BT /F1 12 Tf 72 720 Td (Before DP Inline Image) Tj ET\n"
            . 'BI /W ' . strlen($payloadText) . ' /H 1 /CS /G /BPC 8 /F /Fl '
            . '/DP << /Predictor 12 /Columns ' . strlen($payloadText) . " /Colors 1 /BitsPerComponent 8 >> ID "
            . $compressedImage . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After DP Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before DP Inline Image',
            'After DP Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($compressedImage, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Inline DP Image Noise'));
        $t->true(!str_contains($plainText, 'raw EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'accepts filtered inline image EI after decoded sample floor is reached' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payloadText = "X EI BT /F1 12 Tf 72 690 Td (Oversized Flate Inline Noise) Tj ET";
        $compressedImage = gzcompress($payloadText, 0);
        if (!is_string($compressedImage) || !str_contains($compressedImage, ' EI ')) {
            throw new RuntimeException('Unable to build oversized filtered inline image fixture.');
        }

        $content = "BT /F1 12 Tf 72 720 Td (Before Oversized Inline Image) Tj ET\n"
            . "BI /W 1 /H 1 /CS /G /BPC 8 /F /Fl ID "
            . $compressedImage . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Oversized Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before Oversized Inline Image',
            'After Oversized Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Oversized Flate Inline Noise'));
        $t->true(!str_contains($plainText, 'X EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'decodes LZW DecodeParms inline image payload before Indexed RGB preview' => static function (TestRunner $t) use ($lzwLiteralEncode, $tiffPredictorEncode): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            91 => '<000000FF000000FF000000FF>',
        ];
        $decodedImageBytes = "\x00\x55\xff";
        $predictedImageBytes = $tiffPredictorEncode($decodedImageBytes, 3);
        $payload = $lzwLiteralEncode($predictedImageBytes, 0);

        $preview = $renderer->inlineIndexedImageStreamPreviewRows(
            '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /F /LZW /DP << /Predictor 2 /Columns 3 /Colors 1 /BitsPerComponent 8 /EarlyChange 0 >> /D [0 3]',
            $payload,
            $objects,
            3
        );

        $t->same(true, $preview['inline_image']['uses_abbreviations']);
        $t->same('<< /Width 3 /Height 1 /ColorSpace [/Indexed /DeviceRGB 3 91 0 R] /BitsPerComponent 8 /Filter /LZWDecode /DecodeParms << /Predictor 2 /Columns 3 /Colors 1 /BitsPerComponent 8 /EarlyChange 0 >> /Decode [0 3] >>', $preview['inline_image']['canonical_dictionary']);
        $t->same(['LZWDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['preview_only_filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($payload), $preview['image_stream']['raw_length']);
        $t->same(3, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $decodedImageBytes), $preview['image_stream']['decoded_sha256']);
        $t->same('0055FF', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same([0.0, 85.0, 255.0], array_column($preview['pixels'], 'raw_sample'));
        $t->same([0, 1, 3], array_column($preview['pixels'], 'palette_index'));
        $t->same([0.0, 0.0, 0.0], $preview['pixels'][0]['base_components']);
        $t->same([1.0, 0.0, 0.0], $preview['pixels'][1]['base_components']);
        $t->same([0.0, 0.0, 1.0], $preview['pixels'][2]['base_components']);
        $t->contains('inline_indexed_image_stream_filters_decoded_before_rgb_conversion', implode(',', $preview['stream_notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineIndexedImageStreamPreviewRows(
                '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /F /LZW /DP << /EarlyChange 2 >> /D [0 3]',
                $lzwLiteralEncode("\x00"),
                $objects,
                1
            )
        );
    },
    'requires RunLength EOD before inline image decode preview accepts supplied samples' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $runLengthLiteralEncode): void {
        $extractor = new PdfTextExtractor();
        $payloadText = 'RL EI BT /F1 12 Tf 72 690 Td (RunLength Inline Noise) Tj ET';
        $encodedPayload = $runLengthLiteralEncode($payloadText, true);
        $content = "BT /F1 12 Tf 72 720 Td (Before RunLength Inline Image) Tj ET\n"
            . 'BI /W ' . strlen($payloadText) . ' /H 1 /CS /G /BPC 8 /F /RL ID '
            . $encodedPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After RunLength Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before RunLength Inline Image',
            'After RunLength Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($payloadText, ' EI '));
        $t->true(str_contains($encodedPayload, chr(128)));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->true(!str_contains($plainText, 'RunLength Inline Noise'));
        $t->true(!str_contains($plainText, 'RL EI'));

        $renderer = new PdfImageRenderer();
        $objects = [
            91 => '<000000FF000000FF000000FF>',
        ];
        $preview = $renderer->inlineIndexedImageStreamPreviewRows(
            '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F /RL /D [0 3]',
            $runLengthLiteralEncode("\x1c", true),
            $objects,
            3
        );

        $t->same(['RunLengthDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['preview_only_filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(3, $preview['image_stream']['raw_length']);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', "\x1c"), $preview['image_stream']['decoded_sha256']);
        $t->same('1C', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same([0.0, 1.0, 3.0], array_column($preview['pixels'], 'raw_sample'));
        $t->same([0, 1, 3], array_column($preview['pixels'], 'palette_index'));

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                '/W 1 /H 1 /CS /RGB /BPC 8 /F /RL /D [0 1 0 1 0 1]',
                $runLengthLiteralEncode("\x01\x02\x03", false),
                [],
                1,
                [[1, 2, 3]]
            )
        );
    },
    'fails closed on malformed inline image filter operands before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Malformed Filter Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Malformed Filter Inline) Tj ET\n"
            . "BI /W 8 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Malformed Filter Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Malformed Filter Inline',
            'After Malformed Filter Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Malformed Filter Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'abc EI'));
    },
    'fails closed on unresolved inline image filter operands before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Unresolved Filter Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Unresolved Filter Inline) Tj ET\n"
            . "BI /W 8 /H 1 /CS /G /BPC 8 /F 99 0 R ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Unresolved Filter Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Unresolved Filter Inline',
            'After Unresolved Filter Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Unresolved Filter Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'abc EI'));
    },
    'closes malformed inline image filter fallbacks before the next inline image preamble' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $malformedPayload = 'abcdefgh EI BT /F1 12 Tf 72 660 Td (First Malformed Inline Noise) Tj ET rawtail';
        $unresolvedPayload = 'ijklmnop EI BT /F1 12 Tf 72 628 Td (Second Unresolved Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Consecutive Inline Images) Tj ET\n"
            . "BI /W 8 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] ID\n"
            . $malformedPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 688 Td (Between Consecutive Inline Images) Tj ET\n"
            . "BI /W 8 /H 1 /CS /G /BPC 8 /F 99 0 R ID\n"
            . $unresolvedPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 656 Td (After Consecutive Inline Images) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Consecutive Inline Images',
            'Between Consecutive Inline Images',
            'After Consecutive Inline Images',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'First Malformed Inline Noise'));
        $t->true(!str_contains($plainText, 'Second Unresolved Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'resolves current indirect inline image decode operands before Indexed RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x1c";
        $compressedImage = gzcompress($imageBytes);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to compress indirect inline image fixture.');
        }

        $payload = strtoupper(bin2hex($compressedImage)) . '>';
        $objects = [
            91 => '<000000FF000000FF000000FF>',
            101 => '3',
            102 => '1',
            103 => '2',
            104 => '[0 3]',
        ];
        $preview = $renderer->inlineIndexedImageStreamPreviewRows(
            '/W 101 0 R /H 102 0 R /CS [/I /RGB 3 91 0 R] /BPC 103 0 R /F [/AHx /Fl] /D 104 0 R',
            $payload,
            $objects,
            3
        );

        $t->same(true, $preview['inline_image']['uses_abbreviations']);
        $t->same('<< /Width 101 0 R /Height 102 0 R /ColorSpace [/Indexed /DeviceRGB 3 91 0 R] /BitsPerComponent 103 0 R /Filter [/ASCIIHexDecode /FlateDecode] /Decode 104 0 R >>', $preview['inline_image']['canonical_dictionary']);
        $t->same(3, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(2, $preview['bits_per_component']);
        $t->same(3, $preview['expected_pixel_count']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(hash('sha256', $imageBytes), $preview['image_stream']['decoded_sha256']);
        $t->same('1C', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 3.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [],
            'source' => 'explicit',
        ], $preview['image_decode']);
        $t->same([0.0, 1.0, 3.0], array_column($preview['pixels'], 'raw_sample'));
        $t->same([0, 1, 3], array_column($preview['pixels'], 'palette_index'));
        $t->same([0.0, 0.0, 0.0], $preview['pixels'][0]['base_components']);
        $t->same([1.0, 0.0, 0.0], $preview['pixels'][1]['base_components']);
        $t->same([0.0, 0.0, 1.0], $preview['pixels'][2]['base_components']);
    },
    'resolves current indirect inline ImageMask geometry before stencil preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            101 => '4',
            102 => '1',
            103 => '[1 0]',
            104 => '1',
        ];
        $preview = $renderer->inlineImageMaskPreviewRows(
            '/W 101 0 R /H 102 0 R /IM true /D 103 0 R /BPC 104 0 R',
            "\xa0",
            $objects,
            4
        );

        $t->same('ImageMask', $preview['source_color_space']);
        $t->same(4, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(1, $preview['bits_per_component']);
        $t->same(4, $preview['expected_pixel_count']);
        $t->same(4, $preview['preview_pixel_count']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $preview['image_mask']['decode']);
        $t->same([1.0, 0.0, 1.0, 0.0], array_column($preview['pixels'], 'raw_sample'));
        $t->same([0.0, 1.0, 0.0, 1.0], array_column($preview['pixels'], 'opacity'));
        $t->contains('image_mask_decode_inverts_stencil', implode(',', $preview['notes']));
    },
];
