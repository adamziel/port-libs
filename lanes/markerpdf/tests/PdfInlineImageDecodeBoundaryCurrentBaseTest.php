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

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    if ($columns < 1 || strlen($bytes) % $columns !== 0) {
        throw new RuntimeException('Focused inline PNG predictor rows must be fixed-width.');
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
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

$jpxCodestreamWithLengthSegmentFalseEoc = static function (): string {
    $leak = 'BT /F1 12 Tf 72 690 Td (Inline JPX Segment Noise) Tj ET';
    $segmentPayload = "SIZ segment before false EOC \xff\xd9 EI {$leak} still inside segment";

    return "\xff\x4f"
        . "\xff\x51" . pack('n', strlen($segmentPayload) + 2) . $segmentPayload
        . "\xff\xd9";
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
    'keeps ASCII85 post-EOD inline image surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $postEodSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (A85 Post EOD Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before A85 Post EOD Inline Image) Tj ET\n"
            . "BI /W 4 /H 1 /CS /G /BPC 8 /F /A85 ID z~>"
            . $postEodSurplus . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After A85 Post EOD Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before A85 Post EOD Inline Image',
            'After A85 Post EOD Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($postEodSurplus, ' EI '));
        $t->true(str_contains($content, 'z~>ZZ EI'));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'A85 Post EOD Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'z~>ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'keeps malformed ASCIIHex surplus inline bytes closed until EOD after sample floor' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $payload = '414243 EI BT /F1 12 Tf 72 700 Td (ASCIIHex surplus inline image leak) Tj ET >';
        $content = "BT /F1 12 Tf 72 720 Td (Before AHx Surplus Inline Image) Tj ET\n"
            . "BI /W 3 /H 1 /CS /G /BPC 8 /F /AHx ID\n{$payload}\nEI\n"
            . "BT /F1 12 Tf 72 680 Td (After AHx Surplus Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before AHx Surplus Inline Image',
            'After AHx Surplus Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($payload, ' EI '));
        $t->true(str_contains($payload, '>'));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'ASCIIHex surplus inline image leak'));
        $t->true(!str_contains($plainText, '414243 EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                '/W 3 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]',
                $payload,
                [],
                3
            )
        );
    },
    'keeps ASCIIHex post-EOD inline image surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]';
        $cleanPayload = 'F>';
        $postEodSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (ASCIIHex Post EOD Inline Noise) Tj ET rawtail';
        $payload = $cleanPayload . $postEodSurplus;
        $content = "BT /F1 12 Tf 72 720 Td (Before AHx Post EOD Inline) Tj ET\n"
            . "BI {$dictionary} ID {$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After AHx Post EOD Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before AHx Post EOD Inline',
            'After AHx Post EOD Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($postEodSurplus, ' EI '));
        $t->true(str_ends_with($cleanPayload, '>'));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'ASCIIHex Post EOD Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1)
        );

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $cleanPayload, [], 1);
        $t->same(['ASCIIHexDecode'], $preview['image_stream']['filters']);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same('F0', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([240.0], $preview['pixels'][0]['raw_sample']);
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
    'treats NUL as PDF whitespace around inline native filter EOD boundaries' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $ascii85Dictionary = '/W 4 /H 1 /CS /G /BPC 8 /F /A85 /D [0 1]';
        $asciiHexDictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]';
        $ascii85Payload = "z~>\0";
        $asciiHexPayload = "41\0>";
        $content = "BT /F1 12 Tf 72 720 Td (Before NUL Filter Boundary) Tj ET\n"
            . "BI {$ascii85Dictionary} ID {$ascii85Payload}EI\n"
            . "BT /F1 12 Tf 72 704 Td (Between NUL Filter Boundary) Tj ET\n"
            . "BI {$asciiHexDictionary} ID {$asciiHexPayload}\nEI\n"
            . "BT /F1 12 Tf 72 688 Td (After NUL Filter Boundary) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $ascii85Preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($ascii85Dictionary, $ascii85Payload, [], 4);
        $asciiHexPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($asciiHexDictionary, $asciiHexPayload, [], 1);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before NUL Filter Boundary',
            'Between NUL Filter Boundary',
            'After NUL Filter Boundary',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'z~>'));
        $t->true(!str_contains($plainText, '41'));
        $t->same(['ASCII85Decode'], $ascii85Preview['image_stream']['filters']);
        $t->same(4, $ascii85Preview['image_stream']['decoded_length']);
        $t->same('00000000', $ascii85Preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $ascii85Preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $ascii85Preview['image_stream']['decode_failed']);
        $t->same([[0.0], [0.0], [0.0], [0.0]], array_column($ascii85Preview['pixels'], 'raw_sample'));
        $t->same(0, $ascii85Preview['image_sample_boundary']['surplus_byte_count']);
        $t->same(['ASCIIHexDecode'], $asciiHexPreview['image_stream']['filters']);
        $t->same(1, $asciiHexPreview['image_stream']['decoded_length']);
        $t->same('41', $asciiHexPreview['image_stream']['decoded_preview_hex']);
        $t->same(true, $asciiHexPreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $asciiHexPreview['image_stream']['decode_failed']);
        $t->same([65.0], $asciiHexPreview['pixels'][0]['raw_sample']);
        $t->same(0, $asciiHexPreview['image_sample_boundary']['surplus_byte_count']);
    },
    'treats PDF comments after inline native filter EOD as whitespace before real EI boundaries' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /AHx /D [1 0]';
        $payload = "414243>% fake EI BT /F1 12 Tf 72 690 Td (Inline EOD Comment Noise) Tj ET\n";
        $content = "BT /F1 12 Tf 72 720 Td (Before EOD Comment Inline) Tj ET\n"
            . "BI {$dictionary} ID {$payload}EI\n"
            . "BT /F1 12 Tf 72 704 Td (After EOD Comment Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            $dictionary,
            $payload,
            [],
            3
        );
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before EOD Comment Inline',
            'After EOD Comment Inline',
        ];
        $t->true(str_contains($payload, '% fake EI BT'));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Inline EOD Comment Noise'));
        $t->true(!str_contains($plainText, 'fake EI'));
        $t->same(['ASCIIHexDecode'], $preview['image_stream']['filters']);
        $t->same(strlen($payload), $preview['image_stream']['raw_length']);
        $t->same(3, $preview['image_stream']['decoded_length']);
        $t->same('414243', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([[65.0], [66.0], [67.0]], array_column($preview['pixels'], 'raw_sample'));
        $t->same([190 / 255, 189 / 255, 188 / 255], array_column($preview['pixels'], 'decoded_gray'));
        $t->same(0, $preview['image_sample_boundary']['surplus_byte_count']);
        $t->contains('image_decode_inverts_components_before_rgb', implode(',', $preview['notes']));
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
    'ignores false inline JPX EOC markers inside length-coded codestream segments' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $jpxCodestreamWithLengthSegmentFalseEoc): void {
        $extractor = new PdfTextExtractor();
        $jpxPayload = $jpxCodestreamWithLengthSegmentFalseEoc();
        $content = "BT /F1 12 Tf 72 720 Td (Before JPX Segment Inline Image) Tj ET\n"
            . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode ID\n"
            . $jpxPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After JPX Segment Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before JPX Segment Inline Image',
            'After JPX Segment Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($jpxPayload, "\xff\xd9 EI"));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Inline JPX Segment Noise'));
        $t->true(!str_contains($plainText, 'SIZ segment before false EOC'));
        $t->true(!str_contains($plainText, 'still inside segment'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'keeps raw JPX post-EOC surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $postEocSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (JPX Post EOC Inline Noise) Tj ET rawtail';
        $jpxPayload = "\xff\x4f\xff\xd9" . $postEocSurplus;
        $content = "BT /F1 12 Tf 72 720 Td (Before JPX Post EOC Inline) Tj ET\n"
            . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode ID\n"
            . $jpxPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After JPX Post EOC Inline) Tj ET\n"
            . "BI /W 1 /H 1 /CS /G /BPC 8 ID Q\nEI\n"
            . "BT /F1 12 Tf 72 688 Td (After Followup Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before JPX Post EOC Inline',
            'After JPX Post EOC Inline',
            'After Followup Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_starts_with($jpxPayload, "\xff\x4f"));
        $t->true(str_contains($postEocSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'JPX Post EOC Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'decodes native inline prefix filters before preview-only JPX handoff' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $jpxBytes = "\xFF\x4FWrapped JPX bytes with EI and BT inside\xFF\xD9";
        $payload = strtoupper(bin2hex($jpxBytes)) . '>';
        $dictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F [/AHx /JPXDecode] /D [0 1 1 0 0 1] /Mask [0 0 120 140 200 255]';
        $content = "BT /F1 12 Tf 72 720 Td (Before wrapped JPX) Tj ET\n"
            . "BI {$dictionary} ID {$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After wrapped JPX) Tj ET";
        $preview = $renderer->inlineJpxColorKeyOutputPreviewRows(
            $dictionary,
            $payload,
            [[0, 128, 240], [40, 64, 180]],
            [],
            2
        );
        $plainText = $extractor->extractPlainText($inlineImageDecodeBoundaryPdf($content));

        $t->same('Before wrapped JPX' . "\n" . 'After wrapped JPX', $plainText);
        $t->same(['ASCIIHexDecode', 'JPXDecode'], $preview['image_stream']['filters']);
        $t->same(['JPXDecode'], $preview['image_stream']['preview_only_filters']);
        $t->same(['JPXDecode'], $preview['image_stream']['unsupported_filters']);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(true, $preview['image_stream']['native_prefix_decoded']);
        $t->same(strlen($jpxBytes), $preview['image_stream']['native_prefix_decoded_length']);
        $t->same(hash('sha256', $jpxBytes), $preview['image_stream']['native_prefix_decoded_sha256']);
        $t->same(strtoupper(bin2hex(substr($jpxBytes, 0, 16))), $preview['image_stream']['native_prefix_decoded_preview_hex']);
        $t->same('JPXDecode', $preview['image_stream']['stopped_before_filter']);
        $t->same(['red' => 0, 'green' => 127, 'blue' => 240, 'alpha' => 0.0], $preview['pixels'][0]['output_rgba']);
        $t->same(['red' => 40, 'green' => 191, 'blue' => 180, 'alpha' => 1.0], $preview['pixels'][1]['output_rgba']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineJpxColorKeyOutputPreviewRows(
                $dictionary,
                substr($payload, 0, -1),
                [[0, 128, 240], [40, 64, 180]],
                [],
                2
            )
        );
    },
    'keeps native prefix preview-only inline image surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $jpxBytes = "\xFF\x4FWrapped JPX bytes after native prefix\xFF\xD9";
        $postPrefixSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Wrapped JPX Prefix Surplus Noise) Tj ET rawtail';
        $payload = strtoupper(bin2hex($jpxBytes)) . '>' . $postPrefixSurplus;
        $dictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F [/AHx /JPXDecode] /D [0 1 0 1 0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Wrapped JPX Prefix Surplus) Tj ET\n"
            . "BI {$dictionary} ID {$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Wrapped JPX Prefix Surplus) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before Wrapped JPX Prefix Surplus',
            'After Wrapped JPX Prefix Surplus',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($postPrefixSurplus, ' EI '));
        $t->true(str_ends_with(strtoupper(bin2hex($jpxBytes)) . '>', '>'));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wrapped JPX Prefix Surplus Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'keeps Flate wrapped JPX surplus closed without decoded sample floor' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $jpxBytes = "\xFF\x4F\xFF\xD9";
        $compressedJpx = gzcompress($jpxBytes, 0);
        if (!is_string($compressedJpx)) {
            throw new RuntimeException('Unable to build Flate wrapped JPX inline image fixture.');
        }

        $postStreamSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Flate Wrapped JPX Inline Noise) Tj ET rawtail';
        $payload = $compressedJpx . $postStreamSurplus;
        $dictionary = '/W 2 /H 1 /F [/Fl /JPXDecode]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Flate JPX No Floor) Tj ET\n"
            . "BI {$dictionary} ID "
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Flate JPX No Floor) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Flate JPX No Floor',
            'After Flate JPX No Floor',
        ];
        $t->true(str_contains($postStreamSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Flate Wrapped JPX Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'aligns null filter DecodeParms slots before inline image RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $decodedImageBytes = 'ABC';
        $payload = gzcompress("\0" . $decodedImageBytes);
        if (!is_string($payload)) {
            throw new RuntimeException('Unable to compress null-filter inline image fixture.');
        }

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            '/W 3 /H 1 /CS /G /BPC 8 /F [null /Fl] /DP [null << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /D [0 1]',
            $payload,
            [],
            3
        );

        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['preview_only_filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($payload), $preview['image_stream']['raw_length']);
        $t->same(3, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $decodedImageBytes), $preview['image_stream']['decoded_sha256']);
        $t->same('414243', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same([[65.0], [66.0], [67.0]], array_column($preview['pixels'], 'raw_sample'));
        $t->same([65 / 255, 66 / 255, 67 / 255], array_column($preview['pixels'], 'decoded_gray'));
        $t->contains('inline_image_stream_filters_decoded_before_output_preview', implode(',', $preview['stream_notes']));
        $t->contains('image_decode_applied_before_rgb', implode(',', $preview['notes']));
    },
    'fails closed on Flate EarlyChange inline DecodeParms while preserving PNG predictor boundaries' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $pngSubPredictorEncode): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $decodedImageBytes = 'ABC';
        $validPayload = gzcompress($pngSubPredictorEncode($decodedImageBytes, 3), 0);
        $invalidPayload = gzcompress($decodedImageBytes, 0);
        if (!is_string($validPayload) || !is_string($invalidPayload)) {
            throw new RuntimeException('Unable to build inline Flate EarlyChange DecodeParms fixture.');
        }

        $validDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 /EarlyChange 1 >> /D [0 1]';
        $invalidDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Columns 3 /Colors 1 /BitsPerComponent 8 /EarlyChange 0 >> /D [0 1]';
        $invalidSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Flate EarlyChange Inline Noise) Tj ET rawtail';
        $validSurplus = 'ZZ EI BT /F1 12 Tf 72 674 Td (PNG Predictor Sub Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Flate EarlyChange Inline) Tj ET\n"
            . "BI {$invalidDictionary} ID {$invalidPayload}{$invalidSurplus}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Flate EarlyChange Inline) Tj ET\n"
            . "BI {$validDictionary} ID {$validPayload}{$validSurplus}\nEI\n"
            . "BT /F1 12 Tf 72 688 Td (After PNG Predictor Sub Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $validDecodeParms = [
            'type' => 'FlateDecode',
            'predictor' => 12,
            'columns' => 3,
            'colors' => 1,
            'bits_per_component' => 8,
            'early_change' => 1,
            'valid_decode_parms' => true,
        ];
        $invalidDecodeParms = [
            'type' => 'FlateDecode',
            'predictor' => null,
            'columns' => 3,
            'colors' => 1,
            'bits_per_component' => 8,
            'early_change' => 0,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['early_change'],
            'decode_parms_review' => 'invalid_native_decodeparms_fail_closed',
        ];

        $t->true(str_contains($invalidSurplus, ' EI '));
        $t->true(str_contains($validSurplus, ' EI '));
        $t->same([
            'Before Flate EarlyChange Inline',
            'After Flate EarlyChange Inline',
            'After PNG Predictor Sub Inline',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before Flate EarlyChange Inline\nAfter Flate EarlyChange Inline\nAfter PNG Predictor Sub Inline", $plainText);
        $t->true(!str_contains($plainText, 'Flate EarlyChange Inline Noise'));
        $t->true(!str_contains($plainText, 'PNG Predictor Sub Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));

        $invalidReview = $renderer->inlineImageReviewPlan($invalidDictionary, $invalidPayload);
        $t->same(['FlateDecode'], $invalidReview['image_filters']);
        $t->same(['FlateDecode'], $invalidReview['inline_image']['unsupported_filters']);
        $t->same(false, $invalidReview['inline_image']['native_raster_decode']);
        $t->same($invalidDecodeParms, $invalidReview['image_filter_details'][0]['decode_parms'] ?? null);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($invalidDictionary, $invalidPayload, [], 3)
        );

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($validDictionary, $validPayload, [], 3);
        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same($validDecodeParms, $preview['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same($validDecodeParms, $preview['image_stream']['filter_details'][0]['decode_parms'] ?? null);
        $t->same(3, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $decodedImageBytes), $preview['image_stream']['decoded_sha256']);
        $t->same('414243', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([[65.0], [66.0], [67.0]], array_column($preview['pixels'], 'raw_sample'));
        $t->same([65 / 255, 66 / 255, 67 / 255], array_column($preview['pixels'], 'decoded_gray'));
        $t->same(0, $preview['image_sample_boundary']['surplus_byte_count']);
        $t->same(false, $preview['image_sample_boundary']['truncated_to_declared_samples']);
        $t->contains('inline_image_stream_filters_decoded_before_output_preview', implode(',', $preview['stream_notes']));
    },
    'fails closed on duplicate inline image DecodeParms declarations before text extraction and preview' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $decodedImageBytes = 'ABCDEFGH';
        $compressedPayload = gzcompress($decodedImageBytes, 0);
        if (!is_string($compressedPayload)) {
            throw new RuntimeException('Unable to build duplicate inline DecodeParms fixture.');
        }

        $duplicateDictionary = '/W 8 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 1 >> /DecodeParms << /Predictor 12 /Columns 0 >> /D [0 1]';
        $cleanDictionary = '/W 8 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 1 >> /D [0 1]';
        $duplicateDecodeParms = [
            'type' => 'FlateDecode',
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_declaration'],
            'decode_parms_review' => 'duplicate_native_decodeparms_declaration_fail_closed',
            'duplicate_decode_parms_declaration_count' => 1,
            'decode_parms_declaration_policy' => 'reject_duplicate_decodeparms_declarations',
        ];
        $payload = 'abc EI BT /F1 12 Tf 72 690 Td (Duplicate Inline DecodeParms Payload Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Duplicate Inline DecodeParms) Tj ET\n"
            . "BI {$duplicateDictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Duplicate Inline DecodeParms) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Duplicate Inline DecodeParms',
            'After Duplicate Inline DecodeParms',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $duplicateReview = $renderer->inlineImageReviewPlan($duplicateDictionary, $compressedPayload);

        $t->true(str_contains($payload, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        foreach (['Duplicate Inline DecodeParms Payload Noise', 'rawtail', 'abc EI'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(['FlateDecode'], $duplicateReview['image_filters']);
        $t->same(['FlateDecode'], $duplicateReview['inline_image']['unsupported_filters']);
        $t->same(false, $duplicateReview['inline_image']['native_raster_decode']);
        $t->same(false, $duplicateReview['image_filter_boundary']['native_raster_decode']);
        $t->same(true, $duplicateReview['inline_image_review_only']);
        $t->same($duplicateDecodeParms, $duplicateReview['image_filter_details'][0]['decode_parms'] ?? null);
        $t->contains('inline_unsupported_image_filter_review_only', implode(',', $duplicateReview['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($duplicateDictionary, $compressedPayload, [], 8)
        );

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($cleanDictionary, $compressedPayload, [], 8);
        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(8, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $decodedImageBytes), $preview['image_stream']['decoded_sha256']);
        $t->same('4142434445464748', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([[65.0], [66.0], [67.0], [68.0], [69.0], [70.0], [71.0], [72.0]], array_column($preview['pixels'], 'raw_sample'));
        $t->same([65 / 255, 66 / 255, 67 / 255, 68 / 255, 69 / 255, 70 / 255, 71 / 255, 72 / 255], array_column($preview['pixels'], 'decoded_gray'));
    },
    'fails closed on extra non-null inline image DecodeParms slots before text extraction and preview' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $decodedImageBytes = 'ABC';
        $compressedPayload = gzcompress($decodedImageBytes, 0);
        if (!is_string($compressedPayload)) {
            throw new RuntimeException('Unable to build extra inline DecodeParms fixture.');
        }

        $extraDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP [null << /Predictor 1 >>] /D [0 1]';
        $cleanDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 1 >> /D [0 1]';
        $extraDecodeParms = [
            'type' => 'FlateDecode',
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_alignment'],
            'decode_parms_review' => 'unaligned_native_decodeparms_fail_closed',
            'decode_parms_alignment' => 'unapplied_filter_slot',
            'filter_slot_count' => 1,
            'decode_parms_slot_count' => 2,
            'unapplied_decode_parms_slots' => [1],
        ];
        $cleanDecodeParms = [
            'type' => 'FlateDecode',
            'predictor' => 1,
            'columns' => null,
            'colors' => null,
            'bits_per_component' => null,
            'early_change' => null,
            'valid_decode_parms' => true,
        ];
        $payload = 'abc EI BT /F1 12 Tf 72 690 Td (Extra Inline DecodeParms Payload Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Extra Inline DecodeParms) Tj ET\n"
            . "BI {$extraDictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Extra Inline DecodeParms) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Extra Inline DecodeParms',
            'After Extra Inline DecodeParms',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $extraReview = $renderer->inlineImageReviewPlan($extraDictionary, $compressedPayload);

        $t->true(str_contains($payload, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        foreach (['Extra Inline DecodeParms Payload Noise', 'rawtail', 'abc EI'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(['FlateDecode'], $extraReview['image_filters']);
        $t->same(['FlateDecode'], $extraReview['inline_image']['unsupported_filters']);
        $t->same(false, $extraReview['inline_image']['native_raster_decode']);
        $t->same(false, $extraReview['image_filter_boundary']['native_raster_decode']);
        $t->same(true, $extraReview['inline_image_review_only']);
        $t->same($extraDecodeParms, $extraReview['image_filter_details'][0]['decode_parms'] ?? null);
        $t->contains('inline_unsupported_image_filter_review_only', implode(',', $extraReview['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($extraDictionary, $compressedPayload, [], 3)
        );

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($cleanDictionary, $compressedPayload, [], 3);
        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same($cleanDecodeParms, $preview['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same($cleanDecodeParms, $preview['image_stream']['filter_details'][0]['decode_parms'] ?? null);
        $t->same(3, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $decodedImageBytes), $preview['image_stream']['decoded_sha256']);
        $t->same('414243', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([[65.0], [66.0], [67.0]], array_column($preview['pixels'], 'raw_sample'));
        $t->same([65 / 255, 66 / 255, 67 / 255], array_column($preview['pixels'], 'decoded_gray'));
    },
    'treats direct null inline Filter operands as absent before raw sample boundaries' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $rawSamples = 'A EI BT Z';
        $dictionary = '/W ' . strlen($rawSamples) . ' /H 1 /CS /G /BPC 8 /F null /DP << /Predictor 12 /Columns 0 >> /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Direct Null Filter Inline) Tj ET\n"
            . "BI {$dictionary} ID {$rawSamples}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Direct Null Filter Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Direct Null Filter Inline',
            'After Direct Null Filter Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            $dictionary,
            $rawSamples,
            [],
            strlen($rawSamples)
        );

        $t->true(str_contains($rawSamples, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'A EI BT Z'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['preview_only_filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($rawSamples), $preview['image_stream']['raw_length']);
        $t->same(strlen($rawSamples), $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $rawSamples), $preview['image_stream']['decoded_sha256']);
        $t->same(strtoupper(bin2hex($rawSamples)), $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(strlen($rawSamples), $preview['preview_pixel_count']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(
            array_map(static fn (string $char): array => [(float) ord($char)], str_split($rawSamples)),
            array_column($preview['pixels'], 'raw_sample')
        );
        $t->same(0, $preview['image_sample_boundary']['surplus_byte_count']);
        $t->same(false, $preview['image_sample_boundary']['truncated_to_declared_samples']);
    },
    'aligns inline image DecodeParms after null Filter slots before native raster review' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $decodedSample = 'A';
        $compressedSample = gzcompress("\x01" . $decodedSample, 0);
        if (!is_string($compressedSample)) {
            throw new RuntimeException('Unable to build null-slot DecodeParms inline image fixture.');
        }

        $surplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Null Slot DecodeParms Inline Noise) Tj ET rawtail';
        $payload = $compressedSample . $surplus;
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F [null /Fl] /DP [null << /Predictor 12 /Columns 1 >>] /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Null Slot DecodeParms Inline) Tj ET\n"
            . "BI {$dictionary} ID {$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Null Slot DecodeParms Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, $compressedSample);
        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedSample, [], 1);

        $t->true(str_contains($surplus, ' EI '));
        $t->same([
            'Before Null Slot DecodeParms Inline',
            'After Null Slot DecodeParms Inline',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before Null Slot DecodeParms Inline\nAfter Null Slot DecodeParms Inline", $plainText);
        foreach (['Null Slot DecodeParms Inline Noise', 'ZZ EI', 'rawtail'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(['FlateDecode'], $review['image_filters']);
        $t->same([], $review['inline_image']['unsupported_filters']);
        $t->same([], $review['image_filter_boundary']['unsupported_filters'] ?? []);
        $t->same(true, $review['inline_image']['native_raster_decode']);
        $t->same(false, $review['inline_image_review_only']);
        $t->same('FlateDecode', $review['image_filter_details'][0]['filter'] ?? null);
        $t->same(12, $review['image_filter_details'][0]['decode_parms']['predictor'] ?? null);
        $t->same(1, $review['image_filter_details'][0]['decode_parms']['columns'] ?? null);

        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(12, $preview['image_stream']['filter_details'][0]['decode_parms']['predictor'] ?? null);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same('41', $preview['image_stream']['decoded_preview_hex']);
        $t->same(hash('sha256', $decodedSample), $preview['image_stream']['decoded_sha256']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(true, $preview['inline_image']['native_raster_decode']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same([65.0], $preview['pixels'][0]['raw_sample']);
        $t->same(65 / 255, $preview['pixels'][0]['decoded_gray']);
    },
    'keeps unfiltered inline image indirect geometry operands closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Indirect Geometry Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Indirect Geometry Inline) Tj ET\n"
            . "BI /W 101 0 R /H 102 0 R /CS /G /BPC 8 ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Indirect Geometry Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before Indirect Geometry Inline',
            'After Indirect Geometry Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($payload, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Indirect Geometry Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'abc EI'));
        $t->true(!str_contains($plainText, '101 0 R'));
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
    'keeps Flate post-stream inline image surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $imageByte = 'Z';
        $compressedImage = gzcompress($imageByte, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build Flate post-stream inline image fixture.');
        }
        $postStreamSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Flate Post Stream Inline Noise) Tj ET rawtail';
        $payload = $compressedImage . $postStreamSurplus;
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Flate Post Stream Inline) Tj ET\n"
            . "BI {$dictionary} ID "
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Flate Post Stream Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Flate Post Stream Inline',
            'After Flate Post Stream Inline',
        ];
        $t->true(str_contains($postStreamSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Flate Post Stream Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1)
        );

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedImage, [], 1);
        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $imageByte), $preview['image_stream']['decoded_sha256']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same([90.0], $preview['pixels'][0]['raw_sample']);
    },
    'treats PDF comments after bounded Flate inline members as EOD whitespace' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $imageByte = 'A';
        $compressedImage = gzcompress($imageByte, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build Flate comment EOD inline image fixture.');
        }

        $postStreamComment = "% markerpdf comment after bounded flate member\n";
        $payload = $compressedImage . $postStreamComment;
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Flate Comment EOD Inline) Tj ET\n"
            . "BI {$dictionary} ID "
            . $payload . "EI\n"
            . "BT /F1 12 Tf 72 704 Td (After Flate Comment EOD Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Flate Comment EOD Inline',
            'After Flate Comment EOD Inline',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'markerpdf comment after bounded flate member'));
        $t->true(!str_contains($plainText, 'FlateDecode'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1);
        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $imageByte), $preview['image_stream']['decoded_sha256']);
        $t->same('41', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([65.0], $preview['pixels'][0]['raw_sample']);
        $t->same(0, $preview['image_sample_boundary']['surplus_byte_count']);
        $t->same(false, $preview['image_sample_boundary']['truncated_to_declared_samples']);
    },
    'keeps Flate predictor short-row surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $inflatedShortRow = "\0AB";
        $compressedImage = gzcompress($inflatedShortRow, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build Flate predictor short-row inline image fixture.');
        }

        $postStreamSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Predictor Short Row Inline Noise) Tj ET rawtail';
        $payload = $compressedImage . $postStreamSurplus;
        $dictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >> /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Predictor Short Row Inline) Tj ET\n"
            . "BI {$dictionary} ID "
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Predictor Short Row Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Predictor Short Row Inline',
            'After Predictor Short Row Inline',
        ];
        $t->true(str_contains($postStreamSurplus, ' EI '));
        $t->true(!str_contains($compressedImage, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Predictor Short Row Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedImage, [], 3)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 3)
        );
    },
    'keeps short decoded Flate post-stream surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $shortSamples = 'AB';
        $compressedImage = gzcompress($shortSamples, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build short decoded Flate inline image fixture.');
        }

        $postStreamSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Flate Short Sample Inline Noise) Tj ET rawtail';
        $payload = $compressedImage . $postStreamSurplus;
        $dictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Flate Short Sample Inline) Tj ET\n"
            . "BI {$dictionary} ID "
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Flate Short Sample Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Flate Short Sample Inline',
            'After Flate Short Sample Inline',
        ];
        $t->true(str_contains($postStreamSurplus, ' EI '));
        $t->true(!str_contains($compressedImage, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Flate Short Sample Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedImage, [], 3)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 3)
        );
    },
    'keeps stacked native filter surplus closed after first EOD until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $imageByte = 'Z';
        $compressedImage = gzcompress($imageByte, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build stacked native inline image fixture.');
        }
        $postFirstFilterSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Stacked Native Surplus Noise) Tj ET rawtail';
        $payload = strtoupper(bin2hex($compressedImage)) . '>' . $postFirstFilterSurplus;
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F [/AHx /Fl] /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Stacked Native Surplus) Tj ET\n"
            . "BI {$dictionary} ID "
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Stacked Native Surplus) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Stacked Native Surplus',
            'After Stacked Native Surplus',
        ];
        $t->true(str_contains($payload, '>ZZ EI'));
        $t->true(str_contains($postFirstFilterSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Stacked Native Surplus Noise'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1)
        );

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            $dictionary,
            strtoupper(bin2hex($compressedImage)) . '>',
            [],
            1
        );
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $imageByte), $preview['image_stream']['decoded_sha256']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same([90.0], $preview['pixels'][0]['raw_sample']);
    },
    'keeps wrapped terminal Flate decoded surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $imageByte = 'K';
        $compressedImage = gzcompress($imageByte, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build wrapped terminal Flate inline image fixture.');
        }

        $decodedPostStreamSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Wrapped Terminal Flate Inline Noise) Tj ET rawtail';
        $encodedPayload = strtoupper(bin2hex($compressedImage . $decodedPostStreamSurplus)) . '>';
        $encodedCleanPayload = strtoupper(bin2hex($compressedImage)) . '>';
        $cases = [
            'native' => [
                '/W 1 /H 1 /CS /G /BPC 8 /F [/AHx /Fl] /D [0 1]',
                ['ASCIIHexDecode', 'FlateDecode'],
                'Before Wrapped Terminal Flate',
                'After Wrapped Terminal Flate',
            ],
            'identity crypt native' => [
                '/W 1 /H 1 /CS /G /BPC 8 /F [/Crypt /AHx /Fl] /DP [<< /Name /Identity >> null null] /D [0 1]',
                ['Crypt', 'ASCIIHexDecode', 'FlateDecode'],
                'Before Identity Crypt Wrapped Terminal Flate',
                'After Identity Crypt Wrapped Terminal Flate',
            ],
        ];

        $t->true(str_contains($decodedPostStreamSurplus, ' EI '));
        $t->true(str_contains($encodedPayload, '>'));
        $t->true(!str_contains($encodedPayload, ' EI '));
        $t->true(!str_contains($encodedPayload, 'ZZ EI'));

        foreach ($cases as [$dictionary, $expectedFilters, $before, $after]) {
            $content = "BT /F1 12 Tf 72 720 Td ({$before}) Tj ET\n"
                . "BI {$dictionary} ID "
                . $encodedPayload . "\nEI\n"
                . "BT /F1 12 Tf 72 704 Td ({$after}) Tj ET";
            $pdf = $inlineImageDecodeBoundaryPdf($content);
            $plainText = $extractor->extractPlainText($pdf);
            $expected = [$before, $after];

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same(implode("\n", $expected), $plainText);
            $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Wrapped Terminal Flate Inline Noise'));
            $t->true(!str_contains($plainText, 'ZZ EI'));
            $t->true(!str_contains($plainText, 'rawtail'));
            $t->same(['1'], $extractor->extractPageLabels($pdf));
            $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $encodedPayload, [], 1)
            );

            $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $encodedCleanPayload, [], 1);
            $t->same($expectedFilters, $preview['image_stream']['filters']);
            $t->same([], $preview['image_stream']['preview_only_filters']);
            $t->same([], $preview['image_stream']['unsupported_filters']);
            $t->same(1, $preview['image_stream']['decoded_length']);
            $t->same(hash('sha256', $imageByte), $preview['image_stream']['decoded_sha256']);
            $t->same('4B', $preview['image_stream']['decoded_preview_hex']);
            $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
            $t->same(false, $preview['image_stream']['decode_failed']);
            $t->same([75.0], $preview['pixels'][0]['raw_sample']);
        }
    },
    'keeps wrapped terminal EOD-filter surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $lzwLiteralEncode, $runLengthLiteralEncode): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $imageByte = 'K';
        $cases = [
            'LZW' => [
                '/W 1 /H 1 /CS /G /BPC 8 /F [/AHx /LZW] /DP [null << /EarlyChange 0 >>] /D [0 1]',
                $lzwLiteralEncode($imageByte, 0),
                ['ASCIIHexDecode', 'LZWDecode'],
                'Before Wrapped Terminal LZW',
                'After Wrapped Terminal LZW',
                'Wrapped Terminal LZW Inline Noise',
            ],
            'RunLength' => [
                '/W 1 /H 1 /CS /G /BPC 8 /F [/AHx /RL] /D [0 1]',
                $runLengthLiteralEncode($imageByte, true),
                ['ASCIIHexDecode', 'RunLengthDecode'],
                'Before Wrapped Terminal RunLength',
                'After Wrapped Terminal RunLength',
                'Wrapped Terminal RunLength Inline Noise',
            ],
        ];

        foreach ($cases as [$dictionary, $encodedImage, $expectedFilters, $before, $after, $leak]) {
            $decodedPostEodSurplus = "ZZ EI BT /F1 12 Tf 72 690 Td ({$leak}) Tj ET rawtail";
            $encodedPayload = strtoupper(bin2hex($encodedImage . $decodedPostEodSurplus)) . '>';
            $encodedCleanPayload = strtoupper(bin2hex($encodedImage)) . '>';
            $content = "BT /F1 12 Tf 72 720 Td ({$before}) Tj ET\n"
                . "BI {$dictionary} ID {$encodedPayload}\nEI\n"
                . "BT /F1 12 Tf 72 704 Td ({$after}) Tj ET";
            $pdf = $inlineImageDecodeBoundaryPdf($content);
            $plainText = $extractor->extractPlainText($pdf);
            $expected = [$before, $after];

            $t->true(str_contains($decodedPostEodSurplus, ' EI '));
            $t->true(!str_contains($encodedPayload, ' EI '));
            $t->true(!str_contains($encodedPayload, 'ZZ EI'));
            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same(implode("\n", $expected), $plainText);
            $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, $leak));
            $t->true(!str_contains($plainText, 'ZZ EI'));
            $t->true(!str_contains($plainText, 'rawtail'));
            $t->same(['1'], $extractor->extractPageLabels($pdf));
            $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $encodedPayload, [], 1)
            );

            $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $encodedCleanPayload, [], 1);
            $t->same($expectedFilters, $preview['image_stream']['filters']);
            $t->same([], $preview['image_stream']['preview_only_filters']);
            $t->same([], $preview['image_stream']['unsupported_filters']);
            $t->same(1, $preview['image_stream']['decoded_length']);
            $t->same(hash('sha256', $imageByte), $preview['image_stream']['decoded_sha256']);
            $t->same('4B', $preview['image_stream']['decoded_preview_hex']);
            $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
            $t->same(false, $preview['image_stream']['decode_failed']);
            $t->same([75.0], $preview['pixels'][0]['raw_sample']);
        }
    },
    'reports decoded inline image surplus bytes as review-only sample boundary metadata' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $payloadText = "X EI BT /F1 12 Tf 72 690 Td (Inline Surplus Preview Noise) Tj ET";
        $compressedImage = gzcompress($payloadText, 0);
        if (!is_string($compressedImage) || !str_contains($compressedImage, ' EI ')) {
            throw new RuntimeException('Unable to build surplus filtered inline image fixture.');
        }

        $content = "BT /F1 12 Tf 72 720 Td (Before Surplus Inline Preview) Tj ET\n"
            . "BI /W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1] ID "
            . $compressedImage . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Surplus Inline Preview) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1]',
            $compressedImage,
            [],
            1
        );
        $boundary = $preview['image_sample_boundary'];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Before Surplus Inline Preview',
            'After Surplus Inline Preview',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before Surplus Inline Preview\nAfter Surplus Inline Preview", $plainText);
        $t->true(!str_contains($plainText, 'Inline Surplus Preview Noise'));
        $t->same(true, $preview['native_raster_decode']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(1, $preview['preview_pixel_count']);
        $t->same([88.0], $preview['pixels'][0]['raw_sample']);
        $t->same(88 / 255, $preview['pixels'][0]['decoded_gray']);
        $t->same(1, $boundary['expected_pixel_count']);
        $t->same(strlen($payloadText), $boundary['available_pixel_count']);
        $t->same(1, $boundary['expected_sample_count']);
        $t->same(strlen($payloadText), $boundary['available_sample_count']);
        $t->same(strlen($payloadText) - 1, $boundary['surplus_sample_count']);
        $t->same(1, $boundary['expected_byte_count']);
        $t->same(strlen($payloadText), $boundary['decoded_byte_count']);
        $t->same(strlen($payloadText) - 1, $boundary['surplus_byte_count']);
        $t->same(true, $boundary['truncated_to_declared_samples']);
        $t->contains('inline_image_decoded_surplus_samples_review_only', implode(',', $preview['stream_notes']));
        $t->contains('inline_image_decoded_surplus_samples_review_only', implode(',', $preview['notes']));
    },
    'keeps terminal whitespace samples inside unfiltered inline images before text extraction' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $content = "BT /F1 12 Tf 72 720 Td (Before Space Sample Inline Image) Tj ET\n"
            . "BI /W 1 /H 1 /CS /G /BPC 8 ID  EI\n"
            . "BT /F1 12 Tf 72 704 Td (After Space Sample Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before Space Sample Inline Image',
            'After Space Sample Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'BI /W'));
    },
    'keeps terminal whitespace samples inside named-colorspace inline image floors' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $content = "BT /F1 12 Tf 72 720 Td (Before Named Space Sample Inline Image) Tj ET\n"
            . "BI /W 1 /H 1 /CS /CSWordPress /BPC 8 ID  EI\n"
            . "BT /F1 12 Tf 72 704 Td (After Named Space Sample Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before Named Space Sample Inline Image',
            'After Named Space Sample Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'CSWordPress'));
    },
    'uses inline array color-space component counts before accepting unfiltered EI boundaries' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $deviceNPayload = "\x01EI BT /F1 12 Tf 72 690 Td (DeviceN Inline Decode Noise) Tj ET \x02";
        $calRgbPayload = "\x10EI BT /F1 12 Tf 72 650 Td (CalRGB Inline Decode Noise) Tj ET \x20\x30";
        $content = "BT /F1 12 Tf 72 720 Td (Before DeviceN Inline Image) Tj ET\n"
            . "BI /W 1 /H 1 /CS [/DeviceN [/Spot#20Red /Spot#20Blue] /CMYK 99 0 R << /Subtype /NChannel >>] /BPC 8 ID\n"
            . $deviceNPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After DeviceN Inline Image) Tj ET\n"
            . "BT /F1 12 Tf 72 680 Td (Before CalRGB Inline Image) Tj ET\n"
            . "BI /W 1 /H 1 /CS [/CalRGB << /WhitePoint [1 1 1] >>] /BPC 8 ID\n"
            . $calRgbPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 664 Td (After CalRGB Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before DeviceN Inline Image',
            'After DeviceN Inline Image',
            'Before CalRGB Inline Image',
            'After CalRGB Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($deviceNPayload, 'EI BT'));
        $t->true(str_contains($calRgbPayload, 'EI BT'));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'DeviceN Inline Decode Noise'));
        $t->true(!str_contains($plainText, 'CalRGB Inline Decode Noise'));
        $t->true(!str_contains($plainText, 'Spot Red'));
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
    'requires bounded LZW EOD before accepting inline image EI terminators' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $lzwLiteralEncode): void {
        $extractor = new PdfTextExtractor();
        $encodedImage = $lzwLiteralEncode('X', 0);
        $postEodSurplus = 'X EI BT /F1 12 Tf 72 690 Td (LZW Post EOD Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before LZW Post EOD Inline Image) Tj ET\n"
            . 'BI /W 1 /H 1 /CS /G /BPC 8 /F /LZW /DP << /EarlyChange 0 >> ID '
            . $encodedImage . $postEodSurplus . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After LZW Post EOD Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before LZW Post EOD Inline Image',
            'After LZW Post EOD Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($postEodSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'LZW Post EOD Inline Noise'));
        $t->true(!str_contains($plainText, 'X EI'));
        $t->true(!str_contains($plainText, 'rawtail'));
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
    'keeps RunLength post-EOD inline image surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $runLengthLiteralEncode): void {
        $extractor = new PdfTextExtractor();
        $encodedImage = $runLengthLiteralEncode('Z', true);
        $postEodSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (RunLength Post EOD Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before RunLength Post EOD Inline Image) Tj ET\n"
            . 'BI /W 1 /H 1 /CS /G /BPC 8 /F /RL ID '
            . $encodedImage . $postEodSurplus . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After RunLength Post EOD Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before RunLength Post EOD Inline Image',
            'After RunLength Post EOD Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($postEodSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'RunLength Post EOD Inline Noise'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'keeps native filtered inline images without sample floors closed until real EI terminators' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $ascii85Encode, $lzwLiteralEncode, $runLengthLiteralEncode): void {
        $extractor = new PdfTextExtractor();
        $cases = [
            'ASCII85' => [
                '/W 1 /H 1 /F /A85',
                $ascii85Encode('X', true),
                'Before A85 No Floor Inline Image',
                'After A85 No Floor Inline Image',
                'A85 No Floor Inline Noise',
            ],
            'ASCIIHex' => [
                '/W 1 /H 1 /F /AHx',
                '58>',
                'Before AHx No Floor Inline Image',
                'After AHx No Floor Inline Image',
                'AHx No Floor Inline Noise',
            ],
            'Flate' => [
                '/W 1 /H 1 /F /Fl',
                (static function (): string {
                    $compressed = gzcompress('X', 0);
                    if (!is_string($compressed)) {
                        throw new RuntimeException('Unable to build no-floor Flate inline image fixture.');
                    }

                    return $compressed;
                })(),
                'Before Flate No Floor Inline Image',
                'After Flate No Floor Inline Image',
                'Flate No Floor Inline Noise',
            ],
            'LZW' => [
                '/W 1 /H 1 /F /LZW',
                $lzwLiteralEncode('X'),
                'Before LZW No Floor Inline Image',
                'After LZW No Floor Inline Image',
                'LZW No Floor Inline Noise',
            ],
            'RunLength' => [
                '/W 1 /H 1 /F /RL',
                $runLengthLiteralEncode('X', true),
                'Before RunLength No Floor Inline Image',
                'After RunLength No Floor Inline Image',
                'RunLength No Floor Inline Noise',
            ],
        ];

        $content = '';
        $expected = [];
        foreach ($cases as [$dictionary, $payloadPrefix, $before, $after, $leak]) {
            $payload = $payloadPrefix . "ZZ EI BT /F1 12 Tf 72 690 Td ({$leak}) Tj ET rawtail";
            $content .= "BT /F1 12 Tf 72 720 Td ({$before}) Tj ET\n"
                . "BI {$dictionary} ID {$payload}\nEI\n"
                . "BT /F1 12 Tf 72 704 Td ({$after}) Tj ET\n";
            $expected[] = $before;
            $expected[] = $after;
            $t->true(str_contains($payload, ' EI '));
        }

        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        foreach ($cases as [, , , , $leak]) {
            $t->true(!str_contains($plainText, $leak));
        }
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);

        $renderer = new PdfImageRenderer();
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                '/W 1 /H 1 /F /Fl',
                $cases['Flate'][1] . 'ZZ EI BT /F1 12 Tf 72 690 Td (Flate No Floor Inline Noise) Tj ET rawtail',
                [],
                1
            )
        );
    },
    'keeps native-filter Identity Crypt suffix images without sample floors closed until real EI terminators' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf, $runLengthLiteralEncode): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $compressed = gzcompress('K', 0);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to build Identity Crypt suffix Flate inline image fixture.');
        }

        $cases = [
            'Flate' => [
                '/W 1 /H 1 /F [/Fl /Crypt] /DP [null << /Name /Identity >>]',
                $compressed,
                'Before Flate Crypt Suffix No Floor',
                'After Flate Crypt Suffix No Floor',
                'Flate Crypt Suffix No Floor Inline Noise',
            ],
            'RunLength' => [
                '/W 1 /H 1 /F [/RL /Crypt] /DP [null << /Name /Identity >>]',
                $runLengthLiteralEncode('L', true),
                'Before RunLength Crypt Suffix No Floor',
                'After RunLength Crypt Suffix No Floor',
                'RunLength Crypt Suffix No Floor Inline Noise',
            ],
        ];

        $content = '';
        $expected = [];
        foreach ($cases as [$dictionary, $payloadPrefix, $before, $after, $leak]) {
            $payload = $payloadPrefix . "ZZ EI BT /F1 12 Tf 72 690 Td ({$leak}) Tj ET rawtail";
            $content .= "BT /F1 12 Tf 72 720 Td ({$before}) Tj ET\n"
                . "BI {$dictionary} ID {$payload}\nEI\n"
                . "BT /F1 12 Tf 72 704 Td ({$after}) Tj ET\n";
            $expected[] = $before;
            $expected[] = $after;
            $t->true(str_contains($payload, ' EI '));
        }

        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        foreach ($cases as [$dictionary, $payloadPrefix, , , $leak]) {
            $payload = $payloadPrefix . "ZZ EI BT /F1 12 Tf 72 690 Td ({$leak}) Tj ET rawtail";
            $t->true(!str_contains($plainText, $leak));
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1)
            );
        }
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'keeps wrapped terminal Flate inline images without sample floors closed until real EI terminators' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $compressed = gzcompress('K', 0);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to build wrapped no-floor Flate inline image fixture.');
        }

        $decodedSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Wrapped No Floor Flate Inline Noise) Tj ET rawtail';
        $payload = strtoupper(bin2hex($compressed . $decodedSurplus)) . '>';
        $content = "BT /F1 12 Tf 72 720 Td (Before Wrapped No Floor Flate Inline) Tj ET\n"
            . "BI /W 1 /H 1 /F [/AHx /Fl] ID {$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Wrapped No Floor Flate Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Wrapped No Floor Flate Inline',
            'After Wrapped No Floor Flate Inline',
        ];

        $t->true(str_contains($decodedSurplus, ' EI '));
        $t->true(!str_contains($payload, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Wrapped No Floor Flate Inline Noise'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->true(!str_contains($plainText, 'rawtail'));

        $renderer = new PdfImageRenderer();
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                '/W 1 /H 1 /F [/AHx /Fl]',
                $payload,
                [],
                1
            )
        );
    },
    'fails closed on inline filter EOD surplus before native image previews' => static function (TestRunner $t) use ($lzwLiteralEncode): void {
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 4 /H 1 /CS /G /BPC 8 /D [0 1]';

        $postEodSurplusCases = [
            'ASCII85Decode' => [$dictionary . ' /F /A85', "z~> EI BT /F1 12 Tf 72 690 Td (A85 Post EOD Inline Noise) Tj ET"],
            'ASCIIHexDecode' => [$dictionary . ' /F /AHx', '41424344> EI BT /F1 12 Tf 72 674 Td (AHx Post EOD Inline Noise) Tj ET'],
            'RunLengthDecode' => [$dictionary . ' /F /RL', chr(3) . 'ABCD' . chr(128) . ' EI BT /F1 12 Tf 72 658 Td (RunLength Post EOD Inline Noise) Tj ET'],
            'LZWDecode' => [$dictionary . ' /F /LZW', $lzwLiteralEncode('ABCD') . ' EI BT /F1 12 Tf 72 642 Td (LZW Post EOD Inline Noise) Tj ET'],
        ];

        foreach ($postEodSurplusCases as [$caseDictionary, $payload]) {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                    $caseDictionary,
                    $payload,
                    [],
                    4
                )
            );
        }

        $ascii85Preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            $dictionary . ' /F /A85',
            "z~>",
            [],
            4
        );
        $t->same(true, $ascii85Preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $ascii85Preview['image_stream']['decode_failed']);
        $t->same(4, $ascii85Preview['image_stream']['decoded_length']);
        $t->same([[0.0], [0.0], [0.0], [0.0]], array_column($ascii85Preview['pixels'], 'raw_sample'));

        $runLengthPayloadWithEodByteSample = chr(3) . 'A' . chr(128) . 'CD' . chr(128);
        $runLengthPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            $dictionary . ' /F /RL',
            $runLengthPayloadWithEodByteSample,
            [],
            4
        );
        $t->same(true, $runLengthPreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $runLengthPreview['image_stream']['decode_failed']);
        $t->same(strlen($runLengthPayloadWithEodByteSample), $runLengthPreview['image_stream']['raw_length']);
        $t->same(4, $runLengthPreview['image_stream']['decoded_length']);
        $t->same([[65.0], [128.0], [67.0], [68.0]], array_column($runLengthPreview['pixels'], 'raw_sample'));
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
    'fails closed on invalid native inline image DecodeParms before text extraction and preview' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Invalid DecodeParms Inline Noise) Tj ET rawtail';
        $dictionary = '/W 8 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 12 /Columns 0 /Colors 1 /BitsPerComponent 8 >> /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Invalid DecodeParms Inline) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Invalid DecodeParms Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Invalid DecodeParms Inline',
            'After Invalid DecodeParms Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, $payload);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Invalid DecodeParms Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'abc EI'));
        $t->same(['FlateDecode'], $review['image_filters']);
        $t->same(['FlateDecode'], $review['image_filter_boundary']['unsupported_filters']);
        $t->same(false, $review['image_filter_boundary']['native_raster_decode']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->same(['FlateDecode'], $review['inline_image']['unsupported_filters']);
        $t->contains('inline_unsupported_image_filter_review_only', implode(',', $review['notes']));
        $t->contains('inline_flatedecode_image_filter_review_only', implode(',', $review['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1)
        );
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
    'marks unsupported inline image filters as review-only before RGB preview metadata' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Crypt Inline Decode Noise) Tj ET rawtail';
        $dictionary = '/W 8 /H 1 /CS /G /BPC 8 /F /Crypt /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Crypt Inline Review) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Crypt Inline Review) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, $payload);

        $t->same([
            'Before Crypt Inline Review',
            'After Crypt Inline Review',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before Crypt Inline Review\nAfter Crypt Inline Review", $plainText);
        $t->true(!str_contains($plainText, 'Crypt Inline Decode Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->same(['Crypt'], $review['image_filters']);
        $t->same(['Crypt'], $review['image_filter_boundary']['unsupported_filters']);
        $t->same(false, $review['image_filter_boundary']['native_raster_decode']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->same(['Crypt'], $review['inline_image']['unsupported_filters']);
        $t->contains('inline_unsupported_image_filter_review_only', implode(',', $review['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1)
        );
    },
    'treats identity Crypt inline image filters as pass-through before RGB preview' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $payload = "ABC EI BT /F1 12 Tf 72 660 Td (Identity Crypt Inline Noise) Tj ET rawtail";
        $dictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F /Crypt /DP << /Name /Identity >> /D [0 1 0 1 0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Identity Crypt Inline) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Identity Crypt Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 2);

        $t->same([
            'Before Identity Crypt Inline',
            'After Identity Crypt Inline',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before Identity Crypt Inline\nAfter Identity Crypt Inline", $plainText);
        $t->true(!str_contains($plainText, 'Identity Crypt Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->same(['Crypt'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['preview_only_filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($payload), $preview['image_stream']['raw_length']);
        $t->same(strlen($payload), $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $payload), $preview['image_stream']['decoded_sha256']);
        $t->same('414243204549204254202F4631203132', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['native_raster_decode']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(2, $preview['preview_pixel_count']);
        $t->same([65.0, 66.0, 67.0], $preview['pixels'][0]['raw_sample']);
        $t->same([32.0, 69.0, 73.0], $preview['pixels'][1]['raw_sample']);
        $t->same([65 / 255, 66 / 255, 67 / 255], $preview['pixels'][0]['decoded_components']);
        $t->same(['red' => 65, 'green' => 66, 'blue' => 67, 'alpha' => 1.0], $preview['pixels'][0]['output_rgba']);
        $t->same([32 / 255, 69 / 255, 73 / 255], $preview['pixels'][1]['decoded_components']);
        $t->same(['red' => 32, 'green' => 69, 'blue' => 73, 'alpha' => 1.0], $preview['pixels'][1]['output_rgba']);
        $t->same(2, $preview['image_sample_boundary']['expected_pixel_count']);
        $t->same(intdiv(strlen($payload), 3), $preview['image_sample_boundary']['available_pixel_count']);
        $t->same(6, $preview['image_sample_boundary']['expected_sample_count']);
        $t->same(strlen($payload), $preview['image_sample_boundary']['available_sample_count']);
        $t->same(strlen($payload) - 6, $preview['image_sample_boundary']['surplus_sample_count']);
        $t->same(6, $preview['image_sample_boundary']['expected_byte_count']);
        $t->same(strlen($payload), $preview['image_sample_boundary']['decoded_byte_count']);
        $t->same(strlen($payload) - 6, $preview['image_sample_boundary']['surplus_byte_count']);
        $t->same(true, $preview['image_sample_boundary']['truncated_to_declared_samples']);
        $t->contains('inline_image_stream_filters_decoded_before_output_preview', implode(',', $preview['stream_notes']));
        $t->contains('inline_image_decoded_surplus_samples_review_only', implode(',', $preview['stream_notes']));
    },
    'keeps Identity Crypt Flate post-stream surplus closed until the real EI terminator' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $imageByte = 'K';
        $compressedImage = gzcompress($imageByte, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build Identity Crypt Flate inline image fixture.');
        }

        $postStreamSurplus = 'ZZ EI BT /F1 12 Tf 72 660 Td (Identity Crypt Flate Inline Noise) Tj ET rawtail';
        $payload = $compressedImage . $postStreamSurplus;
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F [/Crypt /Fl] /DP [<< /Name /Identity >> null] /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Identity Crypt Flate Inline) Tj ET\n"
            . "BI {$dictionary} ID "
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Identity Crypt Flate Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Identity Crypt Flate Inline',
            'After Identity Crypt Flate Inline',
        ];
        $t->true(str_contains($postStreamSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Identity Crypt Flate Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1)
        );

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedImage, [], 1);
        $t->same(['Crypt', 'FlateDecode'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['preview_only_filters']);
        $t->same([], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($compressedImage), $preview['image_stream']['raw_length']);
        $t->same(1, $preview['image_stream']['decoded_length']);
        $t->same(hash('sha256', $imageByte), $preview['image_stream']['decoded_sha256']);
        $t->same('4B', $preview['image_stream']['decoded_preview_hex']);
        $t->same(true, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([75.0], $preview['pixels'][0]['raw_sample']);
    },
    'rejects Identity Crypt JPX post-EOC surplus before supplied preview handoff' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $jpxBytes = "\xFF\x4F\xFF\xD9";
        $postEocSurplus = 'ZZ EI BT /F1 12 Tf 72 660 Td (Identity Crypt JPX Inline Noise) Tj ET rawtail';
        $payload = $jpxBytes . $postEocSurplus;
        $dictionary = '/W 1 /H 1 /CS /RGB /BPC 8 /F [/Crypt /JPXDecode] /DP [<< /Name /Identity >> null] /D [0 1 0 1 0 1] /Mask [0 0 0 0 0 0]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Identity Crypt JPX Inline) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Identity Crypt JPX Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before Identity Crypt JPX Inline',
            'After Identity Crypt JPX Inline',
        ];
        $t->true(str_contains($postEocSurplus, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Identity Crypt JPX Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineJpxColorKeyOutputPreviewRows(
                $dictionary,
                $payload,
                [[0, 128, 255]],
                [],
                1
            )
        );

        $preview = $renderer->inlineJpxColorKeyOutputPreviewRows($dictionary, $jpxBytes, [[0, 128, 255]], [], 1);
        $t->same(['Crypt', 'JPXDecode'], $preview['image_stream']['filters']);
        $t->same(['JPXDecode'], $preview['image_stream']['preview_only_filters']);
        $t->same(['JPXDecode'], $preview['image_stream']['unsupported_filters']);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same(true, $preview['image_stream']['native_prefix_decoded']);
        $t->same(strlen($jpxBytes), $preview['image_stream']['native_prefix_decoded_length']);
        $t->same(hash('sha256', $jpxBytes), $preview['image_stream']['native_prefix_decoded_sha256']);
        $t->same('JPXDecode', $preview['image_stream']['stopped_before_filter']);
        $t->same(['red' => 0, 'green' => 128, 'blue' => 255, 'alpha' => 1.0], $preview['pixels'][0]['output_rgba']);
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
    'resolves generation-exact inline image Decode operands before preview rows' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $objects = [
            103 => '[0 3]',
            '103 1' => '[1 0]',
            104 => '[0 1]',
            '104:1' => '[1 0]',
        ];
        $grayDictionary = '/W 1 /H 1 /CS /G /BPC 8 /D 103 1 R';
        $missingGenerationDictionary = '/W 1 /H 1 /CS /G /BPC 8 /D 103 2 R';
        $maskDictionary = '/W 1 /H 1 /IM true /D 104 1 R';
        $payload = "\x80BT /F1 12 Tf 72 690 Td (Generation Decode Payload Noise) Tj ET";
        $content = "BT /F1 12 Tf 72 720 Td (Before Generation Decode Inline) Tj ET\n"
            . "BI {$grayDictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Generation Decode Inline) Tj ET";
        $plainText = $extractor->extractPlainText($inlineImageDecodeBoundaryPdf($content));

        $grayReview = $renderer->inlineImageReviewPlan($grayDictionary, "\x80", $objects);
        $grayPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($grayDictionary, "\x80", $objects, 1);
        $maskPreview = $renderer->inlineImageMaskPreviewRows($maskDictionary, "\x80", $objects, 1);
        $missingGenerationReview = $renderer->inlineImageReviewPlan($missingGenerationDictionary, "\x80", $objects);

        $expectedDecode = [
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ];
        $t->same("Before Generation Decode Inline\nAfter Generation Decode Inline", $plainText);
        $t->true(!str_contains($plainText, 'Generation Decode Payload Noise'));
        $t->true(str_contains($payload, 'BT /F1'));
        $t->same($expectedDecode, $grayReview['image_decode']);
        $t->same($expectedDecode, $grayPreview['image_decode']);
        $t->same(1, $grayPreview['preview_pixel_count']);
        $t->same([128.0], $grayPreview['pixels'][0]['raw_sample']);
        $t->same(1 - (128 / 255), $grayPreview['pixels'][0]['decoded_gray']);
        $t->contains('image_decode_inverts_components_before_rgb', implode(',', $grayPreview['notes']));
        $t->same($expectedDecode, $maskPreview['image_mask']['decode']);
        $t->same([1.0], array_column($maskPreview['pixels'], 'raw_sample'));
        $t->same([0.0], array_column($maskPreview['pixels'], 'opacity'));
        $t->contains('image_mask_decode_inverts_stencil', implode(',', $maskPreview['notes']));
        $t->same(true, str_contains($grayReview['inline_image']['canonical_dictionary'], '/Decode 103 1 R'));
        $t->same('invalid', $missingGenerationReview['image_decode']['source']);
        $t->same(0, $missingGenerationReview['image_decode']['component_count']);
        $t->same(false, $missingGenerationReview['image_decode']['valid_for_components']);
        $t->same(true, $missingGenerationReview['image_decode_component_mismatch']);
        $t->same(true, $missingGenerationReview['inline_image_review_only']);
        $t->same(false, $missingGenerationReview['inline_image']['native_raster_decode']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($missingGenerationDictionary, "\x80", $objects, 1)
        );
    },
    'ignores PDF comment numbers inside inline image Decode arrays before preview rows' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $objects = [
            91 => '<000000FF000000FF000000FF>',
        ];
        $indexedDictionary = "/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 3 % decoy 0 1 0 1\n]";
        $maskDictionary = "/W 2 /H 1 /IM true /D [1 0 % decoy 0 1\n]";
        $content = "BT /F1 12 Tf 72 720 Td (Before Comment Decode Inline) Tj ET\n"
            . "BI {$indexedDictionary} ID\n\x00\nEI\n"
            . "BI {$maskDictionary} ID\n\x80\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Comment Decode Inline) Tj ET";
        $plainText = $extractor->extractPlainText($inlineImageDecodeBoundaryPdf($content));

        $indexedPreview = $renderer->inlineIndexedImageStreamPreviewRows(
            $indexedDictionary,
            "\x00",
            $objects,
            1
        );
        $maskPreview = $renderer->inlineImageMaskPreviewRows(
            $maskDictionary,
            "\x80",
            [],
            2
        );
        $literalDecoyReview = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 3 (0 1 0 1)]',
            "\x00",
            $objects
        );
        $hexDecoyReview = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 3 <3031203031>]',
            "\x00",
            $objects
        );

        $t->same("Before Comment Decode Inline\nAfter Comment Decode Inline", $plainText);
        $t->same(true, str_contains($indexedDictionary, '% decoy 0 1 0 1'));
        $t->same(true, str_contains($maskDictionary, '% decoy 0 1'));
        $t->same(false, $indexedPreview['review_only_image_stream']);
        $t->same(true, $indexedPreview['inline_image']['native_raster_decode']);
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
        ], $indexedPreview['image_decode']);
        $t->same(1, $indexedPreview['preview_pixel_count']);
        $t->same([0.0], array_column($indexedPreview['pixels'], 'raw_sample'));
        $t->same([0.0], array_column($indexedPreview['pixels'], 'decoded_index'));
        $t->same([0], array_column($indexedPreview['pixels'], 'palette_index'));
        $t->contains('image_decode_applied_before_rgb_conversion', implode(',', $indexedPreview['notes']));
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
        ], $maskPreview['image_mask']['decode']);
        $t->same([1.0, 0.0], array_column($maskPreview['pixels'], 'raw_sample'));
        $t->same([0.0, 1.0], array_column($maskPreview['pixels'], 'opacity'));
        $t->contains('image_mask_decode_inverts_stencil', implode(',', $maskPreview['notes']));
        foreach ([$literalDecoyReview, $hexDecoyReview] as $review) {
            $t->same(true, $review['image_decode_component_mismatch']);
            $t->same(0, $review['image_decode']['component_count']);
            $t->same(false, $review['image_decode']['valid_for_components']);
            $t->same(true, $review['inline_image_review_only']);
            $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));
        }
    },
    'treats direct null inline image Decode operands as omitted before preview rows' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $grayPayload = "\x80BT /F1 12 Tf 72 690 Td (Null Decode Inline Noise) Tj ET";
        $content = "BT /F1 12 Tf 72 720 Td (Before Null Decode Inline) Tj ET\n"
            . "BI /W 1 /H 1 /CS /G /BPC 8 /D null ID\n"
            . $grayPayload . "\nEI\n"
            . "BI /W 1 /H 1 /IM true /D null ID\n"
            . "\x80\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Null Decode Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $grayPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            '/W 1 /H 1 /CS /G /BPC 8 /D null',
            "\x80",
            [],
            1
        );
        $maskPreview = $renderer->inlineImageMaskPreviewRows(
            '/W 1 /H 1 /IM true /D null',
            "\x80",
            [],
            1
        );
        $grayReview = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS /G /BPC 8 /D null',
            "\x80"
        );

        $t->same("Before Null Decode Inline\nAfter Null Decode Inline", $plainText);
        $t->true(!str_contains($plainText, 'Null Decode Inline Noise'));
        $t->same(null, $grayReview['image_decode']);
        $t->same(false, $grayReview['image_decode_component_mismatch']);
        $t->same(false, $grayReview['inline_image_review_only']);
        $t->same(true, $grayReview['inline_image']['native_raster_decode']);
        $t->true(!str_contains(implode(',', $grayReview['notes']), 'inline_image_decode_operand_review_only'));
        $t->same(null, $grayPreview['image_decode']);
        $t->same(true, $grayPreview['inline_image']['native_raster_decode']);
        $t->same(false, $grayPreview['review_only_image_stream']);
        $t->same([128.0], $grayPreview['pixels'][0]['raw_sample']);
        $t->same(128 / 255, $grayPreview['pixels'][0]['decoded_gray']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 1.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => true,
            'inverted_components' => [],
            'source' => 'default',
        ], $maskPreview['image_mask']['decode']);
        $t->same(true, $maskPreview['inline_image']['native_raster_decode']);
        $t->same([1.0], array_column($maskPreview['pixels'], 'opacity'));
    },
    'resolves exponent-form inline image Decode numbers before preview rows' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $grayDictionary = '/W 1 /H 1 /CS /G /BPC 8 /D [0 5e-1]';
        $maskDictionary = '/W 2 /H 1 /IM true /D [5e-1 0e0]';
        $grayPayload = "\xffBT /F1 12 Tf 72 690 Td (Exponent Decode Inline Noise) Tj ET";
        $content = "BT /F1 12 Tf 72 720 Td (Before Exponent Decode Inline) Tj ET\n"
            . "BI {$grayDictionary} ID\n"
            . $grayPayload . "\nEI\n"
            . "BI {$maskDictionary} ID\n"
            . "\x80\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Exponent Decode Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $grayReview = $renderer->inlineImageReviewPlan($grayDictionary, "\xff");
        $grayPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            $grayDictionary,
            "\xff",
            [],
            1
        );
        $maskPreview = $renderer->inlineImageMaskPreviewRows(
            $maskDictionary,
            "\x80",
            [],
            2
        );

        $t->same("Before Exponent Decode Inline\nAfter Exponent Decode Inline", $plainText);
        $t->true(!str_contains($plainText, 'Exponent Decode Inline Noise'));
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 0.5],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [],
            'source' => 'explicit',
        ], $grayReview['image_decode']);
        $t->same(true, $grayReview['image_decode_applied_before_rgb']);
        $t->same(false, $grayReview['image_decode_component_mismatch']);
        $t->same(false, $grayReview['inline_image_review_only']);
        $t->same(true, $grayReview['inline_image']['native_raster_decode']);
        $t->same(0.5, $grayPreview['pixels'][0]['decoded_gray']);
        $t->same([[255.0]], array_column($grayPreview['pixels'], 'raw_sample'));
        $t->contains('image_decode_applied_before_rgb_conversion', implode(',', $grayPreview['notes']));
        $t->same([
            'ranges' => [
                ['min' => 0.5, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $maskPreview['image_mask']['decode']);
        $t->same([1.0, 0.0], array_column($maskPreview['pixels'], 'raw_sample'));
        $t->same([0.0, 0.5], array_column($maskPreview['pixels'], 'opacity'));
        $t->contains('image_mask_decode_inverts_stencil', implode(',', $maskPreview['notes']));
    },
    'fails closed on trailing inline image Decode operands before text extraction and preview rows' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $dictionary = '/W 12 /H 1 /CS /G /BPC 8 /D [1 0] 99 0 R';
        $payload = 'raw EI BT /F1 12 Tf 72 690 Td (Trailing Decode Operand Inline Noise) Tj ET tail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Trailing Decode Operand Inline) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Trailing Decode Operand Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, "\x80");

        $t->true(str_contains($payload, ' EI '));
        $t->same([
            'Before Trailing Decode Operand Inline',
            'After Trailing Decode Operand Inline',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before Trailing Decode Operand Inline\nAfter Trailing Decode Operand Inline", $plainText);
        foreach (['Trailing Decode Operand Inline Noise', 'raw EI', 'tail'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }
        $t->same(true, $review['image_decode_component_mismatch']);
        $t->same('invalid', $review['image_decode']['source']);
        $t->same(0, $review['image_decode']['component_count']);
        $t->same(1, $review['image_decode']['expected_components']);
        $t->same(false, $review['image_decode']['valid_for_components']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, "\x80", [], 1)
        );
    },
    'fails closed on malformed inline image Decode operands before RGB preview rows' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $objects = [
            91 => '<000000FF000000FF000000FF>',
        ];
        $payload = "\x00BT /F1 12 Tf 72 690 Td (Malformed Inline Decode Payload Noise) Tj ET";
        $content = "BT /F1 12 Tf 72 720 Td (Before Malformed Inline Decode) Tj ET\n"
            . "BI /W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 1 0 1] ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Malformed Inline Decode) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 1 0 1]',
            "\x00",
            $objects
        );
        $unresolvedReview = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D 99 0 R',
            "\x00",
            $objects
        );

        $t->same("Before Malformed Inline Decode\nAfter Malformed Inline Decode", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Inline Decode Payload Noise'));
        $t->same(true, $review['image_decode_component_mismatch']);
        $t->same('explicit', $review['image_decode']['source']);
        $t->same(2, $review['image_decode']['component_count']);
        $t->same(1, $review['image_decode']['expected_components']);
        $t->same(false, $review['image_decode']['valid_for_components']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->contains('image_decode_component_mismatch', implode(',', $review['notes']));
        $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));
        $t->same(true, $unresolvedReview['image_decode_component_mismatch']);
        $t->same('invalid', $unresolvedReview['image_decode']['source']);
        $t->same(0, $unresolvedReview['image_decode']['component_count']);
        $t->same(1, $unresolvedReview['image_decode']['expected_components']);
        $t->same(false, $unresolvedReview['image_decode']['valid_for_components']);
        $t->same(true, $unresolvedReview['inline_image_review_only']);
        $t->same(false, $unresolvedReview['inline_image']['native_raster_decode']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineIndexedImageStreamPreviewRows(
                '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 1 0 1]',
                "\x00",
                $objects,
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D 99 0 R',
                "\x00",
                $objects,
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageMaskPreviewRows(
                '/W 1 /H 1 /IM true /D [0 1 0 1]',
                "\x80",
                [],
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineJpxColorKeyOutputPreviewRows(
                '/W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1] /Mask [0 0 0 0 0 0]',
                "\xff\x4f\xff\xd9",
                [[0, 128, 255]],
                [],
                1
            )
        );
    },
    'fails closed on duplicate inline image Decode operands before RGB preview rows' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $objects = [
            91 => '<000000FF000000FF000000FF>',
        ];
        $payload = "\x00BT /F1 12 Tf 72 690 Td (Duplicate Inline Decode Payload Noise) Tj ET";
        $dictionary = '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 3] /Decode [0 1 0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Duplicate Inline Decode) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Duplicate Inline Decode) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan(
            $dictionary,
            "\x00",
            $objects
        );
        $duplicateMaskReview = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /IM true /D [1 0] /D [0 1]',
            "\x80"
        );

        $t->same("Before Duplicate Inline Decode\nAfter Duplicate Inline Decode", $plainText);
        $t->true(!str_contains($plainText, 'Duplicate Inline Decode Payload Noise'));
        $t->same(true, $review['image_decode_component_mismatch']);
        $t->same('duplicate', $review['image_decode']['source']);
        $t->same(0, $review['image_decode']['component_count']);
        $t->same(1, $review['image_decode']['expected_components']);
        $t->same(false, $review['image_decode']['valid_for_components']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));
        $t->same('duplicate', $duplicateMaskReview['image_mask']['decode']['source']);
        $t->same(false, $duplicateMaskReview['image_mask']['decode']['valid_for_components']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineIndexedImageStreamPreviewRows(
                $dictionary,
                "\x00",
                $objects,
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageMaskPreviewRows(
                '/W 1 /H 1 /IM true /D [1 0] /D [0 1]',
                "\x80",
                [],
                1
            )
        );
    },
    'marks invalid inline image Decode operands as review-only before native raster metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            91 => '<000000FF000000FF000000FF>',
        ];

        $explicitMismatch = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 1 0 1]',
            "\x00",
            $objects
        );
        $unresolvedDecode = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D 99 0 R',
            "\x00",
            $objects
        );

        foreach ([$explicitMismatch, $unresolvedDecode] as $review) {
            $t->same(true, $review['image_decode_component_mismatch']);
            $t->same(false, $review['image_decode']['valid_for_components']);
            $t->same(true, $review['inline_image_review_only']);
            $t->same(false, $review['inline_image']['native_raster_decode']);
            $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));
            $t->same(true, $review['inline_image_payload_excluded_from_text']);
        }

        $t->same('explicit', $explicitMismatch['image_decode']['source']);
        $t->same('invalid', $unresolvedDecode['image_decode']['source']);
    },
    'rejects overlarge inline image geometry operands before text extraction and native preview' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $hugeInteger = '9' . str_repeat('0', 40);
        $maxInteger = (string) PHP_INT_MAX;
        $widthPayload = 'abc EI BT /F1 12 Tf 72 660 Td (Overlarge Width Inline Noise) Tj ET rawtail';
        $heightPayload = 'def EI BT /F1 12 Tf 72 640 Td (Overlarge Height Inline Noise) Tj ET rawtail';
        $bpcPayload = 'ghi EI BT /F1 12 Tf 72 620 Td (Overlarge BPC Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before Overlarge Geometry Inline) Tj ET\n"
            . "BI /W {$hugeInteger} /H 1 /CS /G /BPC 8 ID\n"
            . $widthPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (Between Overlarge Geometry Inline) Tj ET\n"
            . "BI /W 1 /H {$hugeInteger} /CS /G /BPC 8 ID\n"
            . $heightPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 688 Td (Between Overlarge BPC Inline) Tj ET\n"
            . "BI /W 1 /H 1 /CS /G /BPC {$hugeInteger} ID\n"
            . $bpcPayload . "\nEI\n"
            . "BT /F1 12 Tf 72 672 Td (After Overlarge Geometry Inline) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $expected = [
            'Before Overlarge Geometry Inline',
            'Between Overlarge Geometry Inline',
            'Between Overlarge BPC Inline',
            'After Overlarge Geometry Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        foreach (['Overlarge Width Inline Noise', 'Overlarge Height Inline Noise', 'Overlarge BPC Inline Noise', 'rawtail', 'abc EI', 'def EI', 'ghi EI'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $overlargeWidthReview = $renderer->inlineImageReviewPlan(
            "/W {$hugeInteger} /H 1 /CS /G /BPC 8 /D [0 1]",
            "abc"
        );
        $overlargeBpcReview = $renderer->inlineImageReviewPlan(
            "/W 1 /H 1 /CS /G /BPC {$hugeInteger} /D [0 1]",
            "g"
        );
        $overlargeInRangeBpcReview = $renderer->inlineImageReviewPlan(
            "/W 1 /H 1 /CS /G /BPC {$maxInteger} /D [0 1]",
            "g"
        );

        foreach ([$overlargeWidthReview, $overlargeBpcReview, $overlargeInRangeBpcReview] as $review) {
            $t->same(true, $review['inline_image_geometry_operand_invalid']);
            $t->same(true, $review['inline_image']['geometry_operand_invalid']);
            $t->same(true, $review['inline_image_review_only']);
            $t->same(false, $review['inline_image']['native_raster_decode']);
            $t->same(false, $review['image_filter_boundary']['native_raster_decode']);
            $t->contains('inline_image_geometry_operand_review_only', implode(',', $review['notes']));
            $t->same(true, $review['inline_image_payload_excluded_from_text']);
        }

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                "/W {$hugeInteger} /H 1 /CS /G /BPC 8 /D [0 1]",
                "abc",
                [],
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineIndexedImageStreamPreviewRows(
                "/W {$hugeInteger} /H 1 /CS [/I /RGB 1 91 0 R] /BPC 8 /D [0 1]",
                "\x00",
                [91 => '<000000FFFFFF>'],
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageMaskPreviewRows(
                "/W 1 /H {$hugeInteger} /IM true /D [0 1]",
                "\x80",
                [],
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineJpxColorKeyOutputPreviewRows(
                "/W 1 /H 1 /CS /RGB /BPC {$hugeInteger} /F /JPXDecode /D [0 1 0 1 0 1] /Mask [0 0 0 0 0 0]",
                "\xff\x4f\xff\xd9",
                [[0, 0, 0]],
                [],
                1
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                "/W 1 /H 1 /CS /G /BPC {$maxInteger} /D [0 1]",
                "\x00",
                [],
                1
            )
        );
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'fails closed on malformed inline image dictionary tail operands before native raster metadata' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $payload = "\x7f EI BT /F1 12 Tf 72 690 Td (Malformed Tail Inline Noise) Tj ET rawtail";
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 99 /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Malformed Inline Tail) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Malformed Inline Tail) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, "\x7f");

        $t->same([
            'Before Malformed Inline Tail',
            'After Malformed Inline Tail',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before Malformed Inline Tail\nAfter Malformed Inline Tail", $plainText);
        foreach (['Malformed Tail Inline Noise', 'rawtail', '99 /D', "\x7f EI"] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(true, $review['inline_image_dictionary_operand_invalid']);
        $t->same(true, $review['inline_image']['dictionary_operand_invalid']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->same(false, $review['image_filter_boundary']['native_raster_decode']);
        $t->contains('inline_image_dictionary_operand_review_only', implode(',', $review['notes']));
        $t->same(true, $review['inline_image_payload_excluded_from_text']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
                $dictionary,
                "\x7f",
                [],
                1
            )
        );
    },
];
