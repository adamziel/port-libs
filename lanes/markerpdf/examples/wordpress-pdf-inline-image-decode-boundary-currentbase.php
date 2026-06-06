<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
            throw new RuntimeException('Focused inline LZW smoke code does not fit the current code size.');
        }

        for ($shift = $codeSize - 1; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    };

    $resetDictionary();
    $previous = null;
    foreach ($codes as $code) {
        if (!is_int($code) || $code < 0 || $code > 4095) {
            throw new RuntimeException('Focused inline LZW smoke codes must be 12-bit integers.');
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
            throw new RuntimeException('Focused inline LZW smoke references an unknown dictionary code.');
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
            throw new RuntimeException('Focused inline predictor smoke rows must be fixed-width.');
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

$imageRow = 'raw EI BT /F1 12 Tf 72 690 Td (Inline DP Image Noise) Tj ET';
$compressedImage = gzcompress("\0" . $imageRow, 0);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to build inline image fixture.');
}
$oversizedImageRow = 'X EI BT /F1 12 Tf 72 646 Td (Oversized Flate Inline Noise) Tj ET';
$oversizedCompressedImage = gzcompress($oversizedImageRow, 0);
if (!is_string($oversizedCompressedImage)) {
    throw new RuntimeException('Unable to build oversized inline image fixture.');
}
$predictorShortRowInflated = "\0AB";
$predictorShortRowCompressed = gzcompress($predictorShortRowInflated, 0);
if (!is_string($predictorShortRowCompressed)) {
    throw new RuntimeException('Unable to build predictor short-row inline image fixture.');
}
$predictorShortRowSurplusPayload = $predictorShortRowCompressed
    . 'ZZ EI BT /F1 12 Tf 72 642 Td (Predictor Short Row Inline Noise) Tj ET rawtail';
$predictorShortRowDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >> /D [0 1]';
$shortFlateDecodedSamples = 'AB';
$shortFlateCompressed = gzcompress($shortFlateDecodedSamples, 0);
if (!is_string($shortFlateCompressed)) {
    throw new RuntimeException('Unable to build short decoded Flate inline image fixture.');
}
$shortFlatePostStreamSurplusPayload = $shortFlateCompressed
    . 'ZZ EI BT /F1 12 Tf 72 638 Td (Flate Short Sample Inline Noise) Tj ET rawtail';
$shortFlateDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1]';
$ascii85PostEodSurplusPayload = 'z~>ZZ EI BT /F1 12 Tf 72 652 Td (A85 Post EOD Inline Noise) Tj ET rawtail';
$ascii85NulEodDictionary = '/W 4 /H 1 /CS /G /BPC 8 /F /A85 /D [0 1]';
$ascii85NulEodPayload = "z~>\0";
$asciiHexSurplusPayload = '414243 EI BT /F1 12 Tf 72 635 Td (ASCIIHex Surplus Inline Noise) Tj ET >';
$asciiHexNulWhitespaceDictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]';
$asciiHexNulWhitespacePayload = "41\0>";
$asciiHexCommentEodDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /AHx /D [1 0]';
$asciiHexCommentEodPayload = "414243>% fake EI BT /F1 12 Tf 72 642 Td (ASCIIHex Comment EOD Inline Noise) Tj ET\n";
$runLengthImageRow = 'RL EI BT /F1 12 Tf 72 618 Td (RunLength Inline Noise) Tj ET';
$runLengthPayload = $runLengthLiteralEncode($runLengthImageRow, true);
$runLengthPostEodSurplusPayload = $runLengthLiteralEncode('Z', true)
    . 'ZZ EI BT /F1 12 Tf 72 582 Td (RunLength Post EOD Inline Noise) Tj ET rawtail';
$lzwPostEodSurplusPayload = $lzwLiteralEncode('X', 0) . 'X EI BT /F1 12 Tf 72 586 Td (LZW Post EOD Inline Noise) Tj ET rawtail';
$malformedFilterPayload = 'abc EI BT /F1 12 Tf 72 574 Td (Malformed Filter Inline Noise) Tj ET rawtail';
$unresolvedFilterPayload = 'abc EI BT /F1 12 Tf 72 546 Td (Unresolved Filter Inline Noise) Tj ET rawtail';
$unsupportedCryptFilterPayload = 'abc EI BT /F1 12 Tf 72 530 Td (Crypt Inline Decode Noise) Tj ET rawtail';
$unsupportedCryptFilterDictionary = '/W 8 /H 1 /CS /G /BPC 8 /F /Crypt /D [0 1]';
$identityCryptFilterPayload = 'ABC EI BT /F1 12 Tf 72 516 Td (Identity Crypt Inline Noise) Tj ET rawtail';
$identityCryptFilterDictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F /Crypt /DP << /Name /Identity >> /D [0 1 0 1 0 1]';
$identityCryptFlateImageByte = 'K';
$identityCryptFlateCompressed = gzcompress($identityCryptFlateImageByte, 0);
if (!is_string($identityCryptFlateCompressed)) {
    throw new RuntimeException('Unable to build Identity Crypt Flate inline image fixture.');
}
$identityCryptFlatePostStreamSurplusPayload = $identityCryptFlateCompressed
    . 'ZZ EI BT /F1 12 Tf 72 502 Td (Identity Crypt Flate Inline Noise) Tj ET rawtail';
$identityCryptFlateDictionary = '/W 1 /H 1 /CS /G /BPC 8 /F [/Crypt /Fl] /DP [<< /Name /Identity >> null] /D [0 1]';
$identityCryptJpxBytes = "\xFF\x4F\xFF\xD9";
$identityCryptJpxPostEocSurplusPayload = $identityCryptJpxBytes
    . 'ZZ EI BT /F1 12 Tf 72 494 Td (Identity Crypt JPX Inline Noise) Tj ET rawtail';
$identityCryptJpxDictionary = '/W 1 /H 1 /CS /RGB /BPC 8 /F [/Crypt /JPXDecode] /DP [<< /Name /Identity >> null] /D [0 1 0 1 0 1] /Mask [0 0 0 0 0 0]';
$directNullFilterSamples = 'A EI BT Z';
$directNullFilterDictionary = '/W ' . strlen($directNullFilterSamples) . ' /H 1 /CS /G /BPC 8 /F null /DP << /Predictor 12 /Columns 0 >> /D [0 1]';
$indirectGeometryInlinePayload = 'abc EI BT /F1 12 Tf 72 506 Td (Indirect Geometry Inline Noise) Tj ET rawtail';
$indirectGeometryInlineDictionary = '/W 101 0 R /H 102 0 R /CS /G /BPC 8';
$deviceNArrayColorSpacePayload = "\x01EI BT /F1 12 Tf 72 506 Td (DeviceN Array Inline Noise) Tj ET \x02";
$calRgbArrayColorSpacePayload = "\x10EI BT /F1 12 Tf 72 498 Td (CalRGB Array Inline Noise) Tj ET \x20\x30";
$wrappedJpxPrefixBytes = "\xFF\x4FWordPress wrapped JPX prefix bytes with EI and BT inside\xFF\xD9";
$wrappedJpxPrefixPayload = strtoupper(bin2hex($wrappedJpxPrefixBytes)) . '>';
$wrappedJpxPrefixDictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F [/AHx /JPXDecode] /D [0 1 1 0 0 1] /Mask [0 0 120 140 200 255]';
$wrappedJpxPrefixSurplusPayload = $wrappedJpxPrefixPayload
    . 'ZZ EI BT /F1 12 Tf 72 594 Td (Wrapped JPX Prefix Surplus Inline Noise) Tj ET rawtail';
$jpxPostEocSurplusPayload = "\xFF\x4F\xFF\xD9"
    . 'ZZ EI BT /F1 12 Tf 72 594 Td (JPX Post EOC Inline Noise) Tj ET rawtail';
$jpxPostEocDictionary = '/W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode';
$flateWrappedJpxBytes = "\xFF\x4F\xFF\xD9";
$flateWrappedJpxCompressed = gzcompress($flateWrappedJpxBytes, 0);
if (!is_string($flateWrappedJpxCompressed)) {
    throw new RuntimeException('Unable to build Flate wrapped JPX inline image fixture.');
}
$flateWrappedJpxDictionary = '/W 2 /H 1 /F [/Fl /JPXDecode]';
$flateWrappedJpxSurplusPayload = $flateWrappedJpxCompressed
    . 'ZZ EI BT /F1 12 Tf 72 594 Td (Flate Wrapped JPX Inline Noise) Tj ET rawtail';
$stackedNativeFilterImageByte = 'Z';
$stackedNativeFilterCompressed = gzcompress($stackedNativeFilterImageByte, 0);
if (!is_string($stackedNativeFilterCompressed)) {
    throw new RuntimeException('Unable to build stacked native inline image fixture.');
}
$stackedNativeFilterDictionary = '/W 1 /H 1 /CS /G /BPC 8 /F [/AHx /Fl] /D [0 1]';
$stackedNativeFilterSurplusPayload = strtoupper(bin2hex($stackedNativeFilterCompressed))
    . '>ZZ EI BT /F1 12 Tf 72 596 Td (Stacked Native Inline Noise) Tj ET rawtail';
$wrappedTerminalFlateImageByte = 'K';
$wrappedTerminalFlateCompressed = gzcompress($wrappedTerminalFlateImageByte, 0);
if (!is_string($wrappedTerminalFlateCompressed)) {
    throw new RuntimeException('Unable to build wrapped terminal Flate inline image fixture.');
}
$wrappedTerminalFlateDecodedSurplus = 'ZZ EI BT /F1 12 Tf 72 594 Td (Wrapped Terminal Flate Inline Noise) Tj ET rawtail';
$wrappedTerminalFlateDictionary = '/W 1 /H 1 /CS /G /BPC 8 /F [/AHx /Fl] /D [0 1]';
$wrappedTerminalFlatePayload = strtoupper(bin2hex($wrappedTerminalFlateCompressed . $wrappedTerminalFlateDecodedSurplus)) . '>';
$wrappedTerminalFlateCleanPayload = strtoupper(bin2hex($wrappedTerminalFlateCompressed)) . '>';
$identityCryptWrappedTerminalFlateDictionary = '/W 1 /H 1 /CS /G /BPC 8 /F [/Crypt /AHx /Fl] /DP [<< /Name /Identity >> null null] /D [0 1]';
$noFloorNativeInlineCases = [
    'ascii85' => [
        'dictionary' => '/W 1 /H 1 /F /A85',
        'payload_prefix' => $ascii85Encode('X', true),
        'before' => 'Before A85 No Floor Inline Image',
        'after' => 'After A85 No Floor Inline Image',
        'leak' => 'A85 No Floor Inline Noise',
    ],
    'asciihex' => [
        'dictionary' => '/W 1 /H 1 /F /AHx',
        'payload_prefix' => '58>',
        'before' => 'Before AHx No Floor Inline Image',
        'after' => 'After AHx No Floor Inline Image',
        'leak' => 'AHx No Floor Inline Noise',
    ],
    'flate' => [
        'dictionary' => '/W 1 /H 1 /F /Fl',
        'payload_prefix' => gzcompress('X', 0),
        'before' => 'Before Flate No Floor Inline Image',
        'after' => 'After Flate No Floor Inline Image',
        'leak' => 'Flate No Floor Inline Noise',
    ],
    'lzw' => [
        'dictionary' => '/W 1 /H 1 /F /LZW',
        'payload_prefix' => $lzwLiteralEncode('X'),
        'before' => 'Before LZW No Floor Inline Image',
        'after' => 'After LZW No Floor Inline Image',
        'leak' => 'LZW No Floor Inline Noise',
    ],
    'runlength' => [
        'dictionary' => '/W 1 /H 1 /F /RL',
        'payload_prefix' => $runLengthLiteralEncode('X', true),
        'before' => 'Before RunLength No Floor Inline Image',
        'after' => 'After RunLength No Floor Inline Image',
        'leak' => 'RunLength No Floor Inline Noise',
    ],
];
if (!is_string($noFloorNativeInlineCases['flate']['payload_prefix'])) {
    throw new RuntimeException('Unable to build no-floor native Flate inline image fixture.');
}
$wrappedNoFloorTerminalFlateDecodedSurplus = 'ZZ EI BT /F1 12 Tf 72 488 Td (Wrapped No Floor Flate Inline Noise) Tj ET rawtail';
$wrappedNoFloorTerminalFlatePayload = strtoupper(bin2hex($wrappedTerminalFlateCompressed . $wrappedNoFloorTerminalFlateDecodedSurplus)) . '>';

$content = "BT /F1 12 Tf 72 720 Td (Before DP Inline Image) Tj ET\n"
    . 'BI /W ' . strlen($imageRow) . ' /H 1 /CS /G /BPC 8 /F /Fl '
    . '/DP << /Predictor 12 /Columns ' . strlen($imageRow) . " /Colors 1 /BitsPerComponent 8 >> ID "
    . $compressedImage . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After DP Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 688 Td (Before A85 Inline Image) Tj ET\n"
    . "BI /F /A85 ID\n"
    . "87cURDc^jtCh* EI BT /F1 12 Tf 72 672 Td (ASCII85 Inline Noise) Tj ET ~>\nEI\n"
    . "BT /F1 12 Tf 72 656 Td (After A85 Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 654 Td (Before A85 Post EOD Inline Image) Tj ET\n"
    . "BI /W 4 /H 1 /CS /G /BPC 8 /F /A85 ID "
    . $ascii85PostEodSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 652 Td (After A85 Post EOD Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 650 Td (Before NUL Filter Boundary) Tj ET\n"
    . "BI {$ascii85NulEodDictionary} ID {$ascii85NulEodPayload}EI\n"
    . "BT /F1 12 Tf 72 648 Td (Between NUL Filter Boundary) Tj ET\n"
    . "BI {$asciiHexNulWhitespaceDictionary} ID {$asciiHexNulWhitespacePayload}\nEI\n"
    . "BT /F1 12 Tf 72 646 Td (After NUL Filter Boundary) Tj ET\n"
    . "BT /F1 12 Tf 72 642 Td (Before AHx Comment EOD Inline) Tj ET\n"
    . "BI {$asciiHexCommentEodDictionary} ID {$asciiHexCommentEodPayload}EI\n"
    . "BT /F1 12 Tf 72 640 Td (After AHx Comment EOD Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 640 Td (Before Oversized Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 /F /Fl ID "
    . $oversizedCompressedImage . "\nEI\n"
    . "BT /F1 12 Tf 72 624 Td (After Oversized Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 644 Td (Before Predictor Short Row Inline) Tj ET\n"
    . "BI {$predictorShortRowDictionary} ID "
    . $predictorShortRowSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 641 Td (After Predictor Short Row Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 640 Td (Before Flate Short Sample Inline) Tj ET\n"
    . "BI {$shortFlateDictionary} ID "
    . $shortFlatePostStreamSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 637 Td (After Flate Short Sample Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 636 Td (Before AHx Surplus Inline Image) Tj ET\n"
    . "BI /W 3 /H 1 /CS /G /BPC 8 /F /AHx ID\n"
    . $asciiHexSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 622 Td (After AHx Surplus Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 620 Td (Before Space Sample Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 ID  EI\n"
    . "BT /F1 12 Tf 72 616 Td (After Space Sample Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 612 Td (Before Named Space Sample Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS /CSWordPress /BPC 8 ID  EI\n"
    . "BT /F1 12 Tf 72 609 Td (After Named Space Sample Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 608 Td (Before DeviceN Array Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS [/DeviceN [/Spot#20Red /Spot#20Blue] /CMYK 99 0 R << /Subtype /NChannel >>] /BPC 8 ID\n"
    . $deviceNArrayColorSpacePayload . "\nEI\n"
    . "BT /F1 12 Tf 72 605 Td (After DeviceN Array Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 604 Td (Before CalRGB Array Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS [/CalRGB << /WhitePoint [1 1 1] >>] /BPC 8 ID\n"
    . $calRgbArrayColorSpacePayload . "\nEI\n"
    . "BT /F1 12 Tf 72 601 Td (After CalRGB Array Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 600 Td (Before Wrapped JPX Prefix Inline) Tj ET\n"
    . "BI {$wrappedJpxPrefixDictionary} ID {$wrappedJpxPrefixPayload}\nEI\n"
    . "BT /F1 12 Tf 72 597 Td (After Wrapped JPX Prefix Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 596 Td (Before Wrapped JPX Prefix Surplus Inline) Tj ET\n"
    . "BI {$wrappedJpxPrefixDictionary} ID {$wrappedJpxPrefixSurplusPayload}\nEI\n"
    . "BT /F1 12 Tf 72 595 Td (After Wrapped JPX Prefix Surplus Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 596 Td (Before JPX Post EOC Inline) Tj ET\n"
    . "BI {$jpxPostEocDictionary} ID\n"
    . $jpxPostEocSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 595 Td (After JPX Post EOC Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 596 Td (Before Flate JPX No Floor) Tj ET\n"
    . "BI {$flateWrappedJpxDictionary} ID {$flateWrappedJpxSurplusPayload}\nEI\n"
    . "BT /F1 12 Tf 72 595 Td (After Flate JPX No Floor) Tj ET\n"
    . "BT /F1 12 Tf 72 596 Td (Before Stacked Native Inline) Tj ET\n"
    . "BI {$stackedNativeFilterDictionary} ID {$stackedNativeFilterSurplusPayload}\nEI\n"
    . "BT /F1 12 Tf 72 595 Td (After Stacked Native Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 594 Td (Before Wrapped Terminal Flate Inline) Tj ET\n"
    . "BI {$wrappedTerminalFlateDictionary} ID {$wrappedTerminalFlatePayload}\nEI\n"
    . "BT /F1 12 Tf 72 593 Td (After Wrapped Terminal Flate Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 592 Td (Before Identity Crypt Wrapped Terminal Flate Inline) Tj ET\n"
    . "BI {$identityCryptWrappedTerminalFlateDictionary} ID {$wrappedTerminalFlatePayload}\nEI\n"
    . "BT /F1 12 Tf 72 591 Td (After Identity Crypt Wrapped Terminal Flate Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 594 Td (Before Direct Null Filter Inline) Tj ET\n"
    . "BI {$directNullFilterDictionary} ID {$directNullFilterSamples}\nEI\n"
    . "BT /F1 12 Tf 72 593 Td (After Direct Null Filter Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 592 Td (Before Indirect Geometry Inline) Tj ET\n"
    . "BI {$indirectGeometryInlineDictionary} ID\n"
    . $indirectGeometryInlinePayload . "\nEI\n"
    . "BT /F1 12 Tf 72 591 Td (After Indirect Geometry Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 608 Td (Before RunLength Inline Image) Tj ET\n"
    . 'BI /W ' . strlen($runLengthImageRow) . ' /H 1 /CS /G /BPC 8 /F /RL ID '
    . $runLengthPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 592 Td (After RunLength Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 588 Td (Before RunLength Post EOD Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 /F /RL ID "
    . $runLengthPostEodSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 581 Td (After RunLength Post EOD Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 590 Td (Before LZW Post EOD Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 /F /LZW /DP << /EarlyChange 0 >> ID "
    . $lzwPostEodSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 584 Td (After LZW Post EOD Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 576 Td (Before Malformed Filter Inline) Tj ET\n"
    . "BI /W 8 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] ID\n"
    . $malformedFilterPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 560 Td (After Malformed Filter Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 548 Td (Before Unresolved Filter Inline) Tj ET\n"
    . "BI /W 8 /H 1 /CS /G /BPC 8 /F 99 0 R ID\n"
    . $unresolvedFilterPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 532 Td (After Unresolved Filter Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 531 Td (Before Crypt Inline Review) Tj ET\n"
    . "BI {$unsupportedCryptFilterDictionary} ID\n"
    . $unsupportedCryptFilterPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 528 Td (After Crypt Inline Review) Tj ET\n"
    . "BT /F1 12 Tf 72 520 Td (Before Identity Crypt Inline) Tj ET\n"
    . "BI {$identityCryptFilterDictionary} ID\n"
    . $identityCryptFilterPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 512 Td (After Identity Crypt Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 508 Td (Before Identity Crypt Flate Inline) Tj ET\n"
    . "BI {$identityCryptFlateDictionary} ID "
    . $identityCryptFlatePostStreamSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 500 Td (After Identity Crypt Flate Inline) Tj ET";

foreach ($noFloorNativeInlineCases as $case) {
    $payload = $case['payload_prefix']
        . 'ZZ EI BT /F1 12 Tf 72 490 Td (' . $case['leak'] . ') Tj ET rawtail';
    $content .= "\nBT /F1 12 Tf 72 488 Td (" . $case['before'] . ") Tj ET\n"
        . 'BI ' . $case['dictionary'] . ' ID ' . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 486 Td (" . $case['after'] . ') Tj ET';
}
$content .= "\nBT /F1 12 Tf 72 484 Td (Before Wrapped No Floor Flate Inline) Tj ET\n"
    . "BI /W 1 /H 1 /F [/AHx /Fl] ID {$wrappedNoFloorTerminalFlatePayload}\nEI\n"
    . "BT /F1 12 Tf 72 482 Td (After Wrapped No Floor Flate Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 496 Td (Before Identity Crypt JPX Inline) Tj ET\n"
    . "BI {$identityCryptJpxDictionary} ID\n"
    . $identityCryptJpxPostEocSurplusPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 492 Td (After Identity Crypt JPX Inline) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$renderer = new PdfImageRenderer();
$inlineReviewDictionary = '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F /A85 /D [0 3]';
$inlineReviewObjects = [
    91 => '<000000FF000000FF000000FF>',
];
$completeInlineReview = $renderer->inlineIndexedImageStreamPreviewRows(
    $inlineReviewDictionary,
    $ascii85Encode("\x1c", true),
    $inlineReviewObjects,
    3
);
$ascii85NulEodPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $ascii85NulEodDictionary,
    $ascii85NulEodPayload,
    [],
    4
);
$asciiHexNulWhitespacePreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $asciiHexNulWhitespaceDictionary,
    $asciiHexNulWhitespacePayload,
    [],
    1
);
$asciiHexCommentEodPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $asciiHexCommentEodDictionary,
    $asciiHexCommentEodPayload,
    [],
    3
);
$indirectInlineObjects = [
    91 => '<000000FF000000FF000000FF>',
    101 => '3',
    102 => '1',
    103 => '2',
    104 => '[0 3]',
];
$indirectCompressedImage = gzcompress("\x1c");
if (!is_string($indirectCompressedImage)) {
    throw new RuntimeException('Unable to build indirect inline image preview fixture.');
}
$indirectIndexedReview = $renderer->inlineIndexedImageStreamPreviewRows(
    '/W 101 0 R /H 102 0 R /CS [/I /RGB 3 91 0 R] /BPC 103 0 R /F [/AHx /Fl] /D 104 0 R',
    strtoupper(bin2hex($indirectCompressedImage)) . '>',
    $indirectInlineObjects,
    3
);
$indirectMaskReview = $renderer->inlineImageMaskPreviewRows(
    '/W 101 0 R /H 102 0 R /IM true /D 103 0 R /BPC 104 0 R',
    "\xa0",
    [
        101 => '4',
        102 => '1',
        103 => '[1 0]',
        104 => '1',
    ],
    4
);
$lzwDecodedImageBytes = "\x00\x55\xff";
$lzwIndexedReview = $renderer->inlineIndexedImageStreamPreviewRows(
    '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /F /LZW /DP << /Predictor 2 /Columns 3 /Colors 1 /BitsPerComponent 8 /EarlyChange 0 >> /D [0 3]',
    $lzwLiteralEncode($tiffPredictorEncode($lzwDecodedImageBytes, 3), 0),
    $inlineReviewObjects,
    3
);
$nullFilterPredictorPayload = gzcompress("\0ABC");
if (!is_string($nullFilterPredictorPayload)) {
    throw new RuntimeException('Unable to build null-filter inline predictor smoke payload.');
}
$nullFilterPredictorReview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    '/W 3 /H 1 /CS /G /BPC 8 /F [null /Fl] /DP [null << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /D [0 1]',
    $nullFilterPredictorPayload,
    [],
    3
);
$directNullFilterReview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $directNullFilterDictionary,
    $directNullFilterSamples,
    [],
    strlen($directNullFilterSamples)
);
$wrappedJpxPrefixReview = $renderer->inlineJpxColorKeyOutputPreviewRows(
    $wrappedJpxPrefixDictionary,
    $wrappedJpxPrefixPayload,
    [[0, 128, 240], [40, 64, 180]],
    [],
    2
);
$wrappedJpxPrefixMissingEodRejected = false;
try {
    $renderer->inlineJpxColorKeyOutputPreviewRows(
        $wrappedJpxPrefixDictionary,
        substr($wrappedJpxPrefixPayload, 0, -1),
        [[0, 128, 240], [40, 64, 180]],
        [],
        2
    );
} catch (InvalidArgumentException) {
    $wrappedJpxPrefixMissingEodRejected = true;
}
$wrappedJpxPrefixSurplusPreviewRejected = false;
try {
    $renderer->inlineJpxColorKeyOutputPreviewRows(
        $wrappedJpxPrefixDictionary,
        $wrappedJpxPrefixSurplusPayload,
        [[0, 128, 240], [40, 64, 180]],
        [],
        2
    );
} catch (InvalidArgumentException) {
    $wrappedJpxPrefixSurplusPreviewRejected = true;
}
$stackedNativeFilterSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $stackedNativeFilterDictionary,
        $stackedNativeFilterSurplusPayload,
        [],
        1
    );
} catch (InvalidArgumentException) {
    $stackedNativeFilterSurplusPreviewRejected = true;
}
$stackedNativeFilterCleanPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $stackedNativeFilterDictionary,
    strtoupper(bin2hex($stackedNativeFilterCompressed)) . '>',
    [],
    1
);
$wrappedTerminalFlateSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $wrappedTerminalFlateDictionary,
        $wrappedTerminalFlatePayload,
        [],
        1
    );
} catch (InvalidArgumentException) {
    $wrappedTerminalFlateSurplusPreviewRejected = true;
}
$identityCryptWrappedTerminalFlateSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $identityCryptWrappedTerminalFlateDictionary,
        $wrappedTerminalFlatePayload,
        [],
        1
    );
} catch (InvalidArgumentException) {
    $identityCryptWrappedTerminalFlateSurplusPreviewRejected = true;
}
$wrappedTerminalFlateCleanPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $wrappedTerminalFlateDictionary,
    $wrappedTerminalFlateCleanPayload,
    [],
    1
);
$identityCryptWrappedTerminalFlateCleanPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $identityCryptWrappedTerminalFlateDictionary,
    $wrappedTerminalFlateCleanPayload,
    [],
    1
);
$oversizedInlinePreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1]',
    $oversizedCompressedImage,
    [],
    1
);
$oversizedInlineBoundary = $oversizedInlinePreview['image_sample_boundary'] ?? [];
$predictorShortRowPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $predictorShortRowDictionary,
        $predictorShortRowCompressed,
        [],
        3
    );
} catch (InvalidArgumentException) {
    $predictorShortRowPreviewRejected = true;
}
$predictorShortRowSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $predictorShortRowDictionary,
        $predictorShortRowSurplusPayload,
        [],
        3
    );
} catch (InvalidArgumentException) {
    $predictorShortRowSurplusPreviewRejected = true;
}
$shortFlatePreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $shortFlateDictionary,
        $shortFlateCompressed,
        [],
        3
    );
} catch (InvalidArgumentException) {
    $shortFlatePreviewRejected = true;
}
$shortFlateSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $shortFlateDictionary,
        $shortFlatePostStreamSurplusPayload,
        [],
        3
    );
} catch (InvalidArgumentException) {
    $shortFlateSurplusPreviewRejected = true;
}
$runLengthIndexedReview = $renderer->inlineIndexedImageStreamPreviewRows(
    '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F /RL /D [0 3]',
    $runLengthLiteralEncode("\x1c", true),
    $inlineReviewObjects,
    3
);
$runLengthSuppliedSampleBypassRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 1 /H 1 /CS /RGB /BPC 8 /F /RL /D [0 1 0 1 0 1]',
        $runLengthLiteralEncode("\x01\x02\x03", false),
        [],
        1,
        [[1, 2, 3]]
    );
} catch (InvalidArgumentException) {
    $runLengthSuppliedSampleBypassRejected = true;
}
$incompleteAscii85ReviewDecodeFailed = false;
try {
    $renderer->inlineIndexedImageStreamPreviewRows(
        $inlineReviewDictionary,
        $ascii85Encode("\x1c", false),
        $inlineReviewObjects,
        3
    );
} catch (InvalidArgumentException) {
    $incompleteAscii85ReviewDecodeFailed = true;
}
$asciiHexSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 3 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]',
        $asciiHexSurplusPayload,
        [],
        3
    );
} catch (InvalidArgumentException) {
    $asciiHexSurplusPreviewRejected = true;
}
$ascii85PostEodSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 4 /H 1 /CS /G /BPC 8 /F /A85 /D [0 1]',
        $ascii85PostEodSurplusPayload,
        [],
        4
    );
} catch (InvalidArgumentException) {
    $ascii85PostEodSurplusPreviewRejected = true;
}
$asciiHexPostEodSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 4 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]',
        '41424344> EI BT /F1 12 Tf 72 674 Td (AHx Post EOD Inline Noise) Tj ET',
        [],
        4
    );
} catch (InvalidArgumentException) {
    $asciiHexPostEodSurplusPreviewRejected = true;
}
$runLengthPostEodSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 4 /H 1 /CS /G /BPC 8 /F /RL /D [0 1]',
        chr(3) . 'ABCD' . chr(128) . ' EI BT /F1 12 Tf 72 658 Td (RunLength Post EOD Inline Noise) Tj ET',
        [],
        4
    );
} catch (InvalidArgumentException) {
    $runLengthPostEodSurplusPreviewRejected = true;
}
$lzwPostEodSurplusPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 1 /H 1 /CS /G /BPC 8 /F /LZW /DP << /EarlyChange 0 >> /D [0 1]',
        $lzwPostEodSurplusPayload,
        [],
        1
    );
} catch (InvalidArgumentException) {
    $lzwPostEodSurplusPreviewRejected = true;
}
$invalidLzwEarlyChangeDecodeFailed = false;
try {
    $renderer->inlineIndexedImageStreamPreviewRows(
        '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /F /LZW /DP << /EarlyChange 2 >> /D [0 3]',
        $lzwLiteralEncode("\0"),
        $inlineReviewObjects,
        1
    );
} catch (InvalidArgumentException) {
    $invalidLzwEarlyChangeDecodeFailed = true;
}
$malformedInlineDecodeReview = $renderer->inlineImageReviewPlan(
    '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 1 0 1]',
    "\x00",
    $inlineReviewObjects
);
$unresolvedInlineDecodeReview = $renderer->inlineImageReviewPlan(
    '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D 99 0 R',
    "\x00",
    $inlineReviewObjects
);
$malformedInlineDecodeRejected = false;
try {
    $renderer->inlineIndexedImageStreamPreviewRows(
        '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 1 0 1]',
        "\x00",
        $inlineReviewObjects,
        1
    );
} catch (InvalidArgumentException) {
    $malformedInlineDecodeRejected = true;
}
$unresolvedInlineDecodeRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D 99 0 R',
        "\x00",
        $inlineReviewObjects,
        1
    );
} catch (InvalidArgumentException) {
    $unresolvedInlineDecodeRejected = true;
}
$unsupportedCryptFilterReview = $renderer->inlineImageReviewPlan(
    $unsupportedCryptFilterDictionary,
    $unsupportedCryptFilterPayload
);
$identityCryptFilterPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $identityCryptFilterDictionary,
    $identityCryptFilterPayload,
    [],
    2
);
$identityCryptBoundary = $identityCryptFilterPreview['image_sample_boundary'] ?? [];
$identityCryptFlatePreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $identityCryptFlateDictionary,
        $identityCryptFlatePostStreamSurplusPayload,
        [],
        1
    );
} catch (InvalidArgumentException) {
    $identityCryptFlatePreviewRejected = true;
}
$identityCryptFlateCleanPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $identityCryptFlateDictionary,
    $identityCryptFlateCompressed,
    [],
    1
);
$identityCryptJpxPreviewRejected = false;
try {
    $renderer->inlineJpxColorKeyOutputPreviewRows(
        $identityCryptJpxDictionary,
        $identityCryptJpxPostEocSurplusPayload,
        [[0, 128, 255]],
        [],
        1
    );
} catch (InvalidArgumentException) {
    $identityCryptJpxPreviewRejected = true;
}
$identityCryptJpxCleanPreview = $renderer->inlineJpxColorKeyOutputPreviewRows(
    $identityCryptJpxDictionary,
    $identityCryptJpxBytes,
    [[0, 128, 255]],
    [],
    1
);
$noFloorNativeFlatePreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $noFloorNativeInlineCases['flate']['dictionary'],
        $noFloorNativeInlineCases['flate']['payload_prefix']
            . 'ZZ EI BT /F1 12 Tf 72 490 Td (Flate No Floor Inline Noise) Tj ET rawtail',
        [],
        1
    );
} catch (InvalidArgumentException) {
    $noFloorNativeFlatePreviewRejected = true;
}
$wrappedNoFloorTerminalFlatePreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        '/W 1 /H 1 /F [/AHx /Fl]',
        $wrappedNoFloorTerminalFlatePayload,
        [],
        1
    );
} catch (InvalidArgumentException) {
    $wrappedNoFloorTerminalFlatePreviewRejected = true;
}
$unsupportedCryptFilterPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
        $unsupportedCryptFilterDictionary,
        $unsupportedCryptFilterPayload,
        [],
        1
    );
} catch (InvalidArgumentException) {
    $unsupportedCryptFilterPreviewRejected = true;
}
$malformedInlineMaskDecodeRejected = false;
try {
    $renderer->inlineImageMaskPreviewRows(
        '/W 1 /H 1 /IM true /D [0 1 0 1]',
        "\x80",
        [],
        1
    );
} catch (InvalidArgumentException) {
    $malformedInlineMaskDecodeRejected = true;
}
$malformedInlineJpxDecodeRejected = false;
try {
    $renderer->inlineJpxColorKeyOutputPreviewRows(
        '/W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1] /Mask [0 0 0 0 0 0]',
        "\xff\x4f\xff\xd9",
        [[0, 128, 255]],
        [],
        1
    );
} catch (InvalidArgumentException) {
    $malformedInlineJpxDecodeRejected = true;
}

$noFloorNativePayloadsExcluded = true;
$noFloorNativePayloadsHaveFakeEi = true;
foreach ($noFloorNativeInlineCases as $case) {
    $payload = $case['payload_prefix']
        . 'ZZ EI BT /F1 12 Tf 72 490 Td (' . $case['leak'] . ') Tj ET rawtail';
    $noFloorNativePayloadsHaveFakeEi = $noFloorNativePayloadsHaveFakeEi && str_contains($payload, ' EI ');
    $noFloorNativePayloadsExcluded = $noFloorNativePayloadsExcluded
        && in_array($case['after'], $lines, true)
        && !str_contains($plainText, $case['leak']);
}

echo '<!-- markerpdf-inline-image-decode-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page /Contents BI inline image filter decode boundary before Gutenberg paragraph rendering',
    'uses_inline_image_abbreviations' => true,
    'fake_ei_inside_compressed_payload' => str_contains($compressedImage, ' EI '),
    'fake_ei_inside_ascii85_payload' => true,
    'fake_ei_inside_oversized_filtered_payload' => str_contains($oversizedCompressedImage, ' EI '),
    'fake_ei_inside_predictor_short_row_surplus_payload' => str_contains($predictorShortRowSurplusPayload, ' EI '),
    'fake_ei_inside_short_flate_post_stream_surplus_payload' => str_contains($shortFlatePostStreamSurplusPayload, ' EI '),
    'fake_ei_inside_ascii85_post_eod_surplus_payload' => str_contains($ascii85PostEodSurplusPayload, ' EI '),
    'fake_ei_inside_asciihex_surplus_payload' => str_contains($asciiHexSurplusPayload, ' EI '),
    'fake_ei_inside_asciihex_eod_comment' => str_contains($asciiHexCommentEodPayload, '% fake EI BT'),
    'fake_ei_inside_wrapped_jpx_prefix_surplus_payload' => str_contains($wrappedJpxPrefixSurplusPayload, ' EI '),
    'fake_ei_inside_jpx_post_eoc_surplus_payload' => str_contains($jpxPostEocSurplusPayload, ' EI '),
    'fake_ei_inside_flate_wrapped_jpx_surplus_payload' => str_contains($flateWrappedJpxSurplusPayload, ' EI '),
    'fake_ei_inside_stacked_native_filter_surplus_payload' => str_contains($stackedNativeFilterSurplusPayload, ' EI '),
    'fake_ei_inside_wrapped_terminal_flate_decoded_surplus_payload' => str_contains($wrappedTerminalFlateDecodedSurplus, ' EI '),
    'wrapped_terminal_flate_raw_payload_has_no_fake_ei' => !str_contains($wrappedTerminalFlatePayload, ' EI ')
        && !str_contains($wrappedTerminalFlatePayload, 'ZZ EI'),
    'fake_ei_inside_runlength_post_eod_surplus_payload' => str_contains($runLengthPostEodSurplusPayload, ' EI '),
    'fake_ei_inside_identity_crypt_flate_post_stream_surplus_payload' => str_contains($identityCryptFlatePostStreamSurplusPayload, ' EI '),
    'fake_ei_inside_identity_crypt_jpx_post_eoc_surplus_payload' => str_contains($identityCryptJpxPostEocSurplusPayload, ' EI '),
    'fake_ei_inside_direct_null_filter_samples' => str_contains($directNullFilterSamples, ' EI '),
    'fake_ei_inside_indirect_geometry_inline_payload' => str_contains($indirectGeometryInlinePayload, ' EI '),
    'fake_ei_inside_no_floor_native_filter_payloads' => $noFloorNativePayloadsHaveFakeEi,
    'fake_ei_inside_wrapped_no_floor_terminal_flate_decoded_surplus_payload' => str_contains($wrappedNoFloorTerminalFlateDecodedSurplus, ' EI '),
    'wrapped_no_floor_terminal_flate_raw_payload_has_no_fake_ei' => !str_contains($wrappedNoFloorTerminalFlatePayload, ' EI ')
        && !str_contains($wrappedNoFloorTerminalFlatePayload, 'ZZ EI'),
    'asciihex_surplus_eod_present' => str_contains($asciiHexSurplusPayload, '>'),
    'wrapped_jpx_prefix_surplus_first_eod_present' => str_contains($wrappedJpxPrefixSurplusPayload, '>ZZ EI'),
    'jpx_post_eoc_surplus_has_raw_eoc_before_fake_ei' => str_contains($jpxPostEocSurplusPayload, "\xFF\xD9ZZ EI"),
    'flate_wrapped_jpx_surplus_native_filter_eod_before_fake_ei' => str_contains($flateWrappedJpxSurplusPayload, 'ZZ EI'),
    'stacked_native_filter_first_eod_present' => str_contains($stackedNativeFilterSurplusPayload, '>ZZ EI'),
    'visible_text_imported' => $lines === [
        'Before DP Inline Image',
        'After DP Inline Image',
        'Before A85 Inline Image',
        'After A85 Inline Image',
        'Before A85 Post EOD Inline Image',
        'After A85 Post EOD Inline Image',
        'Before NUL Filter Boundary',
        'Between NUL Filter Boundary',
        'After NUL Filter Boundary',
        'Before AHx Comment EOD Inline',
        'After AHx Comment EOD Inline',
        'Before Oversized Inline Image',
        'After Oversized Inline Image',
        'Before Predictor Short Row Inline',
        'After Predictor Short Row Inline',
        'Before Flate Short Sample Inline',
        'After Flate Short Sample Inline',
        'Before AHx Surplus Inline Image',
        'After AHx Surplus Inline Image',
        'Before Space Sample Inline Image',
        'After Space Sample Inline Image',
        'Before Named Space Sample Inline Image',
        'After Named Space Sample Inline Image',
        'Before DeviceN Array Inline Image',
        'After DeviceN Array Inline Image',
        'Before CalRGB Array Inline Image',
        'After CalRGB Array Inline Image',
        'Before Wrapped JPX Prefix Inline',
        'After Wrapped JPX Prefix Inline',
        'Before Wrapped JPX Prefix Surplus Inline',
        'After Wrapped JPX Prefix Surplus Inline',
        'Before JPX Post EOC Inline',
        'After JPX Post EOC Inline',
        'Before Flate JPX No Floor',
        'After Flate JPX No Floor',
        'Before Stacked Native Inline',
        'After Stacked Native Inline',
        'Before Wrapped Terminal Flate Inline',
        'After Wrapped Terminal Flate Inline',
        'Before Identity Crypt Wrapped Terminal Flate Inline',
        'After Identity Crypt Wrapped Terminal Flate Inline',
        'Before Direct Null Filter Inline',
        'After Direct Null Filter Inline',
        'Before Indirect Geometry Inline',
        'After Indirect Geometry Inline',
        'Before RunLength Inline Image',
        'After RunLength Inline Image',
        'Before RunLength Post EOD Inline Image',
        'After RunLength Post EOD Inline Image',
        'Before LZW Post EOD Inline Image',
        'After LZW Post EOD Inline Image',
        'Before Malformed Filter Inline',
        'After Malformed Filter Inline',
        'Before Unresolved Filter Inline',
        'After Unresolved Filter Inline',
        'Before Crypt Inline Review',
        'After Crypt Inline Review',
        'Before Identity Crypt Inline',
        'After Identity Crypt Inline',
        'Before Identity Crypt Flate Inline',
        'After Identity Crypt Flate Inline',
        'Before A85 No Floor Inline Image',
        'After A85 No Floor Inline Image',
        'Before AHx No Floor Inline Image',
        'After AHx No Floor Inline Image',
        'Before Flate No Floor Inline Image',
        'After Flate No Floor Inline Image',
        'Before LZW No Floor Inline Image',
        'After LZW No Floor Inline Image',
        'Before RunLength No Floor Inline Image',
        'After RunLength No Floor Inline Image',
        'Before Wrapped No Floor Flate Inline',
        'After Wrapped No Floor Flate Inline',
        'Before Identity Crypt JPX Inline',
        'After Identity Crypt JPX Inline',
    ],
    'requires_ascii85_end_marker_before_ei' => true,
    'ascii85_post_eod_surplus_payload_excluded_until_real_ei' => in_array('After A85 Post EOD Inline Image', $lines, true)
        && str_contains($ascii85PostEodSurplusPayload, 'z~>ZZ EI')
        && !str_contains($plainText, 'A85 Post EOD Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'pdf_nul_whitespace_inline_filter_boundary' => in_array('After NUL Filter Boundary', $lines, true)
        && !str_contains($plainText, 'z~>')
        && !str_contains($plainText, "41\0>"),
    'ascii85_nul_eod_preview_decoded' => ($ascii85NulEodPreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($ascii85NulEodPreview['image_stream']['decoded_preview_hex'] ?? null) === '00000000'
        && (($ascii85NulEodPreview['image_sample_boundary']['surplus_byte_count'] ?? null) === 0),
    'asciihex_nul_whitespace_preview_decoded' => ($asciiHexNulWhitespacePreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($asciiHexNulWhitespacePreview['image_stream']['decoded_preview_hex'] ?? null) === '41'
        && (($asciiHexNulWhitespacePreview['image_sample_boundary']['surplus_byte_count'] ?? null) === 0),
    'asciihex_comment_eod_payload_excluded_until_real_ei' => in_array('After AHx Comment EOD Inline', $lines, true)
        && !str_contains($plainText, 'ASCIIHex Comment EOD Inline Noise')
        && !str_contains($plainText, 'fake EI'),
    'asciihex_comment_eod_preview_decoded' => ($asciiHexCommentEodPreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($asciiHexCommentEodPreview['image_stream']['decoded_preview_hex'] ?? null) === '414243'
        && array_column($asciiHexCommentEodPreview['pixels'] ?? [], 'decoded_gray') === [190 / 255, 189 / 255, 188 / 255]
        && (($asciiHexCommentEodPreview['image_sample_boundary']['surplus_byte_count'] ?? null) === 0),
    'accepts_filtered_inline_sample_floor_before_real_ei' => true,
    'accepts_asciihex_sample_floor_only_after_eod_marker' => in_array('After AHx Surplus Inline Image', $lines, true),
    'asciihex_surplus_preview_decode_rejected' => $asciiHexSurplusPreviewRejected,
    'ascii85_post_eod_surplus_preview_rejected' => $ascii85PostEodSurplusPreviewRejected,
    'asciihex_post_eod_surplus_preview_rejected' => $asciiHexPostEodSurplusPreviewRejected,
    'runlength_post_eod_surplus_preview_rejected' => $runLengthPostEodSurplusPreviewRejected,
    'lzw_post_eod_surplus_preview_rejected' => $lzwPostEodSurplusPreviewRejected,
    'inline_filter_post_eod_surplus_preview_rejected' => $ascii85PostEodSurplusPreviewRejected
        && $asciiHexPostEodSurplusPreviewRejected
        && $runLengthPostEodSurplusPreviewRejected
        && $lzwPostEodSurplusPreviewRejected,
    'terminal_whitespace_inline_sample_preserved' => in_array('After Space Sample Inline Image', $lines, true),
    'named_colorspace_terminal_whitespace_sample_preserved' => in_array('After Named Space Sample Inline Image', $lines, true),
    'array_colorspace_tight_ei_payloads_present' => str_contains($deviceNArrayColorSpacePayload, 'EI BT')
        && str_contains($calRgbArrayColorSpacePayload, 'EI BT'),
    'array_colorspace_component_floor_preserved' => in_array('After DeviceN Array Inline Image', $lines, true)
        && in_array('After CalRGB Array Inline Image', $lines, true)
        && !str_contains($plainText, 'DeviceN Array Inline Noise')
        && !str_contains($plainText, 'CalRGB Array Inline Noise'),
    'complete_ascii85_review_decoded' => ($completeInlineReview['image_stream']['decoded_with_current_filters'] ?? false) === true,
    'complete_ascii85_review_preview_pixels' => $completeInlineReview['preview_pixel_count'] ?? null,
    'incomplete_ascii85_review_decode_failed' => $incompleteAscii85ReviewDecodeFailed,
    'requires_ascii85_review_end_marker_before_rgb_preview' => true,
    'oversized_inline_preview_declared_byte_floor' => $oversizedInlineBoundary['expected_byte_count'] ?? null,
    'oversized_inline_preview_decoded_byte_count' => $oversizedInlineBoundary['decoded_byte_count'] ?? null,
    'oversized_inline_preview_surplus_byte_count' => $oversizedInlineBoundary['surplus_byte_count'] ?? null,
    'oversized_inline_preview_truncated_to_declared_samples' => $oversizedInlineBoundary['truncated_to_declared_samples'] ?? null,
    'oversized_inline_preview_first_raw_sample' => $oversizedInlinePreview['pixels'][0]['raw_sample'] ?? null,
    'oversized_inline_surplus_review_note' => in_array('inline_image_decoded_surplus_samples_review_only', $oversizedInlinePreview['notes'] ?? [], true),
    'predictor_short_row_inflated_byte_count' => strlen($predictorShortRowInflated),
    'predictor_short_row_payload_excluded_until_real_ei' => in_array('After Predictor Short Row Inline', $lines, true)
        && !str_contains($plainText, 'Predictor Short Row Inline Noise')
        && !str_contains($plainText, 'ZZ EI')
        && !str_contains($plainText, 'rawtail'),
    'predictor_short_row_preview_rejected' => $predictorShortRowPreviewRejected,
    'predictor_short_row_surplus_preview_rejected' => $predictorShortRowSurplusPreviewRejected,
    'short_flate_decoded_byte_count' => strlen($shortFlateDecodedSamples),
    'short_flate_post_stream_payload_excluded_until_real_ei' => in_array('After Flate Short Sample Inline', $lines, true)
        && !str_contains($plainText, 'Flate Short Sample Inline Noise')
        && !str_contains($plainText, 'ZZ EI')
        && !str_contains($plainText, 'rawtail'),
    'short_flate_preview_rejected' => $shortFlatePreviewRejected,
    'short_flate_surplus_preview_rejected' => $shortFlateSurplusPreviewRejected,
    'resolves_current_indirect_inline_preview_operands' => ($indirectIndexedReview['width'] ?? null) === 3
        && ($indirectIndexedReview['height'] ?? null) === 1
        && ($indirectIndexedReview['bits_per_component'] ?? null) === 2
        && ($indirectIndexedReview['preview_pixel_count'] ?? null) === 3,
    'indirect_inline_decode_source' => $indirectIndexedReview['image_decode']['source'] ?? null,
    'indirect_inline_palette_indexes' => array_column($indirectIndexedReview['pixels'] ?? [], 'palette_index'),
    'lzw_inline_decodeparms_preview_decoded' => ($lzwIndexedReview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($lzwIndexedReview['image_stream']['decoded_preview_hex'] ?? null) === '0055FF',
    'lzw_inline_palette_indexes' => array_column($lzwIndexedReview['pixels'] ?? [], 'palette_index'),
    'lzw_post_eod_surplus_has_fake_ei' => str_contains($lzwPostEodSurplusPayload, ' EI '),
    'lzw_post_eod_surplus_payload_excluded_until_real_ei' => in_array('After LZW Post EOD Inline Image', $lines, true)
        && !str_contains($plainText, 'LZW Post EOD Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'null_filter_inline_decodeparms_aligned' => ($nullFilterPredictorReview['image_stream']['decoded_preview_hex'] ?? null) === '414243'
        && array_column($nullFilterPredictorReview['pixels'] ?? [], 'decoded_gray') === [65 / 255, 66 / 255, 67 / 255],
    'null_filter_inline_public_filters' => $nullFilterPredictorReview['image_stream']['filters'] ?? [],
    'null_filter_inline_decode_failed' => $nullFilterPredictorReview['image_stream']['decode_failed'] ?? null,
    'direct_null_filter_payload_excluded_until_sample_floor' => in_array('After Direct Null Filter Inline', $lines, true)
        && !str_contains($plainText, $directNullFilterSamples),
    'direct_null_filter_treated_as_unfiltered' => ($directNullFilterReview['image_stream']['filters'] ?? null) === []
        && ($directNullFilterReview['image_stream']['decoded_preview_hex'] ?? null) === strtoupper(bin2hex($directNullFilterSamples))
        && ($directNullFilterReview['image_stream']['decode_failed'] ?? null) === false
        && ($directNullFilterReview['image_sample_boundary']['surplus_byte_count'] ?? null) === 0,
    'indirect_geometry_inline_payload_excluded_until_real_ei' => in_array('After Indirect Geometry Inline', $lines, true)
        && !str_contains($plainText, 'Indirect Geometry Inline Noise')
        && !str_contains($plainText, 'abc EI')
        && !str_contains($plainText, '101 0 R')
        && !str_contains($plainText, 'rawtail'),
    'wrapped_jpx_prefix_payload_has_eod' => str_ends_with($wrappedJpxPrefixPayload, '>'),
    'wrapped_jpx_prefix_payload_excluded_from_text' => in_array('After Wrapped JPX Prefix Inline', $lines, true)
        && !str_contains($plainText, 'WordPress wrapped JPX prefix bytes'),
    'wrapped_jpx_prefix_surplus_payload_excluded_until_real_ei' => in_array('After Wrapped JPX Prefix Surplus Inline', $lines, true)
        && !str_contains($plainText, 'Wrapped JPX Prefix Surplus Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'jpx_post_eoc_surplus_payload_excluded_until_real_ei' => in_array('After JPX Post EOC Inline', $lines, true)
        && !str_contains($plainText, 'JPX Post EOC Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'wrapped_jpx_prefix_native_filter_decoded_before_preview_only' => ($wrappedJpxPrefixReview['image_stream']['native_prefix_decoded'] ?? false) === true
        && ($wrappedJpxPrefixReview['image_stream']['native_prefix_decoded_length'] ?? null) === strlen($wrappedJpxPrefixBytes)
        && ($wrappedJpxPrefixReview['image_stream']['native_prefix_decoded_sha256'] ?? null) === hash('sha256', $wrappedJpxPrefixBytes),
    'wrapped_jpx_prefix_stopped_before_filter' => $wrappedJpxPrefixReview['image_stream']['stopped_before_filter'] ?? null,
    'wrapped_jpx_prefix_preview_only_filter' => $wrappedJpxPrefixReview['image_stream']['preview_only_filters'] ?? [],
    'wrapped_jpx_prefix_decoded_with_current_filters' => $wrappedJpxPrefixReview['image_stream']['decoded_with_current_filters'] ?? null,
    'wrapped_jpx_prefix_decode_failed' => $wrappedJpxPrefixReview['image_stream']['decode_failed'] ?? null,
    'wrapped_jpx_prefix_missing_eod_rejected' => $wrappedJpxPrefixMissingEodRejected,
    'wrapped_jpx_prefix_surplus_preview_rejected' => $wrappedJpxPrefixSurplusPreviewRejected,
    'wrapped_jpx_prefix_uses_supplied_samples' => ($wrappedJpxPrefixReview['uses_supplied_jpx_samples'] ?? false) === true,
    'wrapped_jpx_prefix_first_output_rgba' => $wrappedJpxPrefixReview['pixels'][0]['output_rgba'] ?? null,
    'flate_wrapped_jpx_no_sample_floor_payload_excluded_until_real_ei' => in_array('After Flate JPX No Floor', $lines, true)
        && !str_contains($plainText, 'Flate Wrapped JPX Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'flate_wrapped_jpx_no_colorspace_or_bpc_sample_floor' => !str_contains($flateWrappedJpxDictionary, '/CS')
        && !str_contains($flateWrappedJpxDictionary, '/BPC'),
    'flate_wrapped_jpx_decoded_preview_framing_complete' => $flateWrappedJpxBytes === "\xFF\x4F\xFF\xD9",
    'no_floor_native_filter_payloads_excluded_until_real_ei' => $noFloorNativePayloadsExcluded
        && !str_contains($plainText, 'ZZ EI')
        && !str_contains($plainText, 'rawtail'),
    'no_floor_native_filter_preview_remains_fail_closed' => $noFloorNativeFlatePreviewRejected,
    'wrapped_no_floor_terminal_flate_payload_excluded_until_real_ei' => in_array('After Wrapped No Floor Flate Inline', $lines, true)
        && !str_contains($plainText, 'Wrapped No Floor Flate Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'wrapped_no_floor_terminal_flate_preview_remains_fail_closed' => $wrappedNoFloorTerminalFlatePreviewRejected,
    'stacked_native_filter_surplus_payload_excluded_until_real_ei' => in_array('After Stacked Native Inline', $lines, true)
        && !str_contains($plainText, 'Stacked Native Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'stacked_native_filter_surplus_preview_rejected' => $stackedNativeFilterSurplusPreviewRejected,
    'stacked_native_filter_clean_preview_decoded' => ($stackedNativeFilterCleanPreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($stackedNativeFilterCleanPreview['image_stream']['decoded_sha256'] ?? null) === hash('sha256', $stackedNativeFilterImageByte),
    'stacked_native_filter_clean_preview_filters' => $stackedNativeFilterCleanPreview['image_stream']['filters'] ?? [],
    'wrapped_terminal_flate_decoded_surplus_payload_excluded_until_real_ei' => in_array('After Wrapped Terminal Flate Inline', $lines, true)
        && !str_contains($plainText, 'Wrapped Terminal Flate Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'identity_crypt_wrapped_terminal_flate_decoded_surplus_payload_excluded_until_real_ei' => in_array('After Identity Crypt Wrapped Terminal Flate Inline', $lines, true)
        && !str_contains($plainText, 'Wrapped Terminal Flate Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'wrapped_terminal_flate_decoded_surplus_preview_rejected' => $wrappedTerminalFlateSurplusPreviewRejected,
    'identity_crypt_wrapped_terminal_flate_decoded_surplus_preview_rejected' => $identityCryptWrappedTerminalFlateSurplusPreviewRejected,
    'wrapped_terminal_flate_clean_preview_decoded' => ($wrappedTerminalFlateCleanPreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($wrappedTerminalFlateCleanPreview['image_stream']['decoded_sha256'] ?? null) === hash('sha256', $wrappedTerminalFlateImageByte),
    'identity_crypt_wrapped_terminal_flate_clean_preview_decoded' => ($identityCryptWrappedTerminalFlateCleanPreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($identityCryptWrappedTerminalFlateCleanPreview['image_stream']['decoded_sha256'] ?? null) === hash('sha256', $wrappedTerminalFlateImageByte),
    'wrapped_terminal_flate_clean_preview_filters' => $wrappedTerminalFlateCleanPreview['image_stream']['filters'] ?? [],
    'identity_crypt_wrapped_terminal_flate_clean_preview_filters' => $identityCryptWrappedTerminalFlateCleanPreview['image_stream']['filters'] ?? [],
    'runlength_inline_eod_present' => str_contains($runLengthPayload, chr(128)),
    'runlength_inline_preview_decoded' => ($runLengthIndexedReview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($runLengthIndexedReview['image_stream']['decoded_preview_hex'] ?? null) === '1C',
    'runlength_inline_palette_indexes' => array_column($runLengthIndexedReview['pixels'] ?? [], 'palette_index'),
    'runlength_missing_eod_supplied_sample_bypass_rejected' => $runLengthSuppliedSampleBypassRejected,
    'runlength_post_eod_surplus_payload_excluded_until_real_ei' => in_array('After RunLength Post EOD Inline Image', $lines, true)
        && !str_contains($plainText, 'RunLength Post EOD Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'malformed_inline_filter_operand_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Malformed Filter Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'unresolved_inline_filter_operand_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Unresolved Filter Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'unsupported_inline_filter_review_only' => ($unsupportedCryptFilterReview['inline_image_review_only'] ?? null) === true
        && ($unsupportedCryptFilterReview['inline_image']['native_raster_decode'] ?? null) === false
        && ($unsupportedCryptFilterReview['inline_image']['unsupported_filters'] ?? []) === ['Crypt']
        && ($unsupportedCryptFilterReview['image_filter_boundary']['native_raster_decode'] ?? null) === false
        && ($unsupportedCryptFilterReview['image_filter_boundary']['unsupported_filters'] ?? []) === ['Crypt'],
    'unsupported_inline_filter_preview_rejected' => $unsupportedCryptFilterPreviewRejected,
    'identity_crypt_inline_filter_payload_excluded_until_sample_floor' => in_array('After Identity Crypt Inline', $lines, true)
        && !str_contains($plainText, 'Identity Crypt Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'identity_crypt_inline_filter_native_decode' => ($identityCryptFilterPreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($identityCryptFilterPreview['image_stream']['unsupported_filters'] ?? []) === [],
    'identity_crypt_inline_filter_preview_pixels' => $identityCryptFilterPreview['preview_pixel_count'] ?? null,
    'identity_crypt_inline_filter_first_pixel' => $identityCryptFilterPreview['pixels'][0]['output_rgba'] ?? null,
    'identity_crypt_inline_filter_second_raw_sample' => $identityCryptFilterPreview['pixels'][1]['raw_sample'] ?? null,
    'identity_crypt_inline_filter_surplus_byte_count' => $identityCryptBoundary['surplus_byte_count'] ?? null,
    'identity_crypt_flate_post_stream_payload_excluded_until_real_ei' => in_array('After Identity Crypt Flate Inline', $lines, true)
        && !str_contains($plainText, 'Identity Crypt Flate Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'identity_crypt_flate_post_stream_preview_rejected' => $identityCryptFlatePreviewRejected,
    'identity_crypt_flate_clean_preview_decoded' => ($identityCryptFlateCleanPreview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($identityCryptFlateCleanPreview['image_stream']['decoded_sha256'] ?? null) === hash('sha256', $identityCryptFlateImageByte),
    'identity_crypt_flate_clean_preview_filters' => $identityCryptFlateCleanPreview['image_stream']['filters'] ?? [],
    'identity_crypt_flate_clean_preview_first_sample' => $identityCryptFlateCleanPreview['pixels'][0]['raw_sample'] ?? null,
    'identity_crypt_jpx_post_eoc_payload_excluded_until_real_ei' => in_array('After Identity Crypt JPX Inline', $lines, true)
        && !str_contains($plainText, 'Identity Crypt JPX Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'identity_crypt_jpx_post_eoc_preview_rejected' => $identityCryptJpxPreviewRejected,
    'identity_crypt_jpx_clean_preview_native_prefix_decoded' => ($identityCryptJpxCleanPreview['image_stream']['native_prefix_decoded'] ?? false) === true
        && ($identityCryptJpxCleanPreview['image_stream']['native_prefix_decoded_sha256'] ?? null) === hash('sha256', $identityCryptJpxBytes),
    'identity_crypt_jpx_clean_preview_filters' => $identityCryptJpxCleanPreview['image_stream']['filters'] ?? [],
    'identity_crypt_jpx_clean_preview_stopped_before_filter' => $identityCryptJpxCleanPreview['image_stream']['stopped_before_filter'] ?? null,
    'identity_crypt_jpx_clean_preview_first_pixel' => $identityCryptJpxCleanPreview['pixels'][0]['output_rgba'] ?? null,
    'invalid_lzw_earlychange_decode_failed' => $invalidLzwEarlyChangeDecodeFailed,
    'malformed_inline_decode_source' => $malformedInlineDecodeReview['image_decode']['source'] ?? null,
    'malformed_inline_decode_component_mismatch' => $malformedInlineDecodeReview['image_decode_component_mismatch'] ?? null,
    'malformed_inline_decode_review_only' => $malformedInlineDecodeReview['inline_image_review_only'] ?? null,
    'malformed_inline_decode_native_raster_decode' => $malformedInlineDecodeReview['inline_image']['native_raster_decode'] ?? null,
    'malformed_inline_decode_preview_rejected' => $malformedInlineDecodeRejected,
    'unresolved_inline_decode_review_only' => $unresolvedInlineDecodeReview['inline_image_review_only'] ?? null,
    'unresolved_inline_decode_native_raster_decode' => $unresolvedInlineDecodeReview['inline_image']['native_raster_decode'] ?? null,
    'unresolved_inline_decode_preview_rejected' => $unresolvedInlineDecodeRejected,
    'malformed_inline_imagemask_decode_preview_rejected' => $malformedInlineMaskDecodeRejected,
    'malformed_inline_jpx_colorkey_decode_preview_rejected' => $malformedInlineJpxDecodeRejected,
    'resolves_current_indirect_inline_imagemask_geometry' => ($indirectMaskReview['width'] ?? null) === 4
        && ($indirectMaskReview['height'] ?? null) === 1
        && ($indirectMaskReview['preview_pixel_count'] ?? null) === 4,
    'indirect_inline_imagemask_opacity' => array_column($indirectMaskReview['pixels'] ?? [], 'opacity'),
    'excluded_inline_image_text' => !str_contains($plainText, 'Inline DP Image Noise')
        && !str_contains($plainText, 'raw EI')
        && !str_contains($plainText, 'ASCII85 Inline Noise')
        && !str_contains($plainText, '87cURDc')
        && !str_contains($plainText, 'A85 Post EOD Inline Noise')
        && !str_contains($plainText, 'NUL Filter Boundary Noise')
        && !str_contains($plainText, 'Oversized Flate Inline Noise')
        && !str_contains($plainText, 'X EI')
        && !str_contains($plainText, 'Predictor Short Row Inline Noise')
        && !str_contains($plainText, 'Flate Short Sample Inline Noise')
        && !str_contains($plainText, 'ASCIIHex Surplus Inline Noise')
        && !str_contains($plainText, '414243 EI')
        && !str_contains($plainText, 'ASCIIHex Comment EOD Inline Noise')
        && !str_contains($plainText, 'DeviceN Array Inline Noise')
        && !str_contains($plainText, 'CalRGB Array Inline Noise')
        && !str_contains($plainText, 'WordPress wrapped JPX prefix bytes')
        && !str_contains($plainText, 'Wrapped JPX Prefix Surplus Inline Noise')
        && !str_contains($plainText, 'JPX Post EOC Inline Noise')
        && !str_contains($plainText, 'Flate Wrapped JPX Inline Noise')
        && !str_contains($plainText, 'Stacked Native Inline Noise')
        && !str_contains($plainText, 'Wrapped Terminal Flate Inline Noise')
        && !str_contains($plainText, $directNullFilterSamples)
        && !str_contains($plainText, 'Indirect Geometry Inline Noise')
        && !str_contains($plainText, 'abc EI')
        && !str_contains($plainText, '101 0 R')
        && !str_contains($plainText, 'RunLength Inline Noise')
        && !str_contains($plainText, 'RL EI')
        && !str_contains($plainText, 'RunLength Post EOD Inline Noise')
        && !str_contains($plainText, 'LZW Post EOD Inline Noise')
        && !str_contains($plainText, 'Malformed Filter Inline Noise')
        && !str_contains($plainText, 'Unresolved Filter Inline Noise')
        && !str_contains($plainText, 'Crypt Inline Decode Noise')
        && !str_contains($plainText, 'Identity Crypt Inline Noise')
        && !str_contains($plainText, 'Identity Crypt Flate Inline Noise')
        && !str_contains($plainText, 'Identity Crypt JPX Inline Noise')
        && !str_contains($plainText, 'A85 No Floor Inline Noise')
        && !str_contains($plainText, 'AHx No Floor Inline Noise')
        && !str_contains($plainText, 'Flate No Floor Inline Noise')
        && !str_contains($plainText, 'LZW No Floor Inline Noise')
        && !str_contains($plainText, 'RunLength No Floor Inline Noise')
        && !str_contains($plainText, 'Wrapped No Floor Flate Inline Noise')
        && !str_contains($plainText, 'rawtail'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
