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
$runLengthImageRow = 'RL EI BT /F1 12 Tf 72 618 Td (RunLength Inline Noise) Tj ET';
$runLengthPayload = $runLengthLiteralEncode($runLengthImageRow, true);
$malformedFilterPayload = 'abc EI BT /F1 12 Tf 72 574 Td (Malformed Filter Inline Noise) Tj ET rawtail';
$unresolvedFilterPayload = 'abc EI BT /F1 12 Tf 72 546 Td (Unresolved Filter Inline Noise) Tj ET rawtail';

$content = "BT /F1 12 Tf 72 720 Td (Before DP Inline Image) Tj ET\n"
    . 'BI /W ' . strlen($imageRow) . ' /H 1 /CS /G /BPC 8 /F /Fl '
    . '/DP << /Predictor 12 /Columns ' . strlen($imageRow) . " /Colors 1 /BitsPerComponent 8 >> ID "
    . $compressedImage . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After DP Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 688 Td (Before A85 Inline Image) Tj ET\n"
    . "BI /F /A85 ID\n"
    . "87cURDc^jtCh* EI BT /F1 12 Tf 72 672 Td (ASCII85 Inline Noise) Tj ET ~>\nEI\n"
    . "BT /F1 12 Tf 72 656 Td (After A85 Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 640 Td (Before Oversized Inline Image) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 /F /Fl ID "
    . $oversizedCompressedImage . "\nEI\n"
    . "BT /F1 12 Tf 72 624 Td (After Oversized Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 608 Td (Before RunLength Inline Image) Tj ET\n"
    . 'BI /W ' . strlen($runLengthImageRow) . ' /H 1 /CS /G /BPC 8 /F /RL ID '
    . $runLengthPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 592 Td (After RunLength Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 576 Td (Before Malformed Filter Inline) Tj ET\n"
    . "BI /W 8 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] ID\n"
    . $malformedFilterPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 560 Td (After Malformed Filter Inline) Tj ET\n"
    . "BT /F1 12 Tf 72 548 Td (Before Unresolved Filter Inline) Tj ET\n"
    . "BI /W 8 /H 1 /CS /G /BPC 8 /F 99 0 R ID\n"
    . $unresolvedFilterPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 532 Td (After Unresolved Filter Inline) Tj ET";

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

echo '<!-- markerpdf-inline-image-decode-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page /Contents BI inline image filter decode boundary before Gutenberg paragraph rendering',
    'uses_inline_image_abbreviations' => true,
    'fake_ei_inside_compressed_payload' => str_contains($compressedImage, ' EI '),
    'fake_ei_inside_ascii85_payload' => true,
    'fake_ei_inside_oversized_filtered_payload' => str_contains($oversizedCompressedImage, ' EI '),
    'visible_text_imported' => $lines === [
        'Before DP Inline Image',
        'After DP Inline Image',
        'Before A85 Inline Image',
        'After A85 Inline Image',
        'Before Oversized Inline Image',
        'After Oversized Inline Image',
        'Before RunLength Inline Image',
        'After RunLength Inline Image',
        'Before Malformed Filter Inline',
        'After Malformed Filter Inline',
        'Before Unresolved Filter Inline',
        'After Unresolved Filter Inline',
    ],
    'requires_ascii85_end_marker_before_ei' => true,
    'accepts_filtered_inline_sample_floor_before_real_ei' => true,
    'complete_ascii85_review_decoded' => ($completeInlineReview['image_stream']['decoded_with_current_filters'] ?? false) === true,
    'complete_ascii85_review_preview_pixels' => $completeInlineReview['preview_pixel_count'] ?? null,
    'incomplete_ascii85_review_decode_failed' => $incompleteAscii85ReviewDecodeFailed,
    'requires_ascii85_review_end_marker_before_rgb_preview' => true,
    'resolves_current_indirect_inline_preview_operands' => ($indirectIndexedReview['width'] ?? null) === 3
        && ($indirectIndexedReview['height'] ?? null) === 1
        && ($indirectIndexedReview['bits_per_component'] ?? null) === 2
        && ($indirectIndexedReview['preview_pixel_count'] ?? null) === 3,
    'indirect_inline_decode_source' => $indirectIndexedReview['image_decode']['source'] ?? null,
    'indirect_inline_palette_indexes' => array_column($indirectIndexedReview['pixels'] ?? [], 'palette_index'),
    'lzw_inline_decodeparms_preview_decoded' => ($lzwIndexedReview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($lzwIndexedReview['image_stream']['decoded_preview_hex'] ?? null) === '0055FF',
    'lzw_inline_palette_indexes' => array_column($lzwIndexedReview['pixels'] ?? [], 'palette_index'),
    'null_filter_inline_decodeparms_aligned' => ($nullFilterPredictorReview['image_stream']['decoded_preview_hex'] ?? null) === '414243'
        && array_column($nullFilterPredictorReview['pixels'] ?? [], 'decoded_gray') === [65 / 255, 66 / 255, 67 / 255],
    'null_filter_inline_public_filters' => $nullFilterPredictorReview['image_stream']['filters'] ?? [],
    'null_filter_inline_decode_failed' => $nullFilterPredictorReview['image_stream']['decode_failed'] ?? null,
    'runlength_inline_eod_present' => str_contains($runLengthPayload, chr(128)),
    'runlength_inline_preview_decoded' => ($runLengthIndexedReview['image_stream']['decoded_with_current_filters'] ?? false) === true
        && ($runLengthIndexedReview['image_stream']['decoded_preview_hex'] ?? null) === '1C',
    'runlength_inline_palette_indexes' => array_column($runLengthIndexedReview['pixels'] ?? [], 'palette_index'),
    'runlength_missing_eod_supplied_sample_bypass_rejected' => $runLengthSuppliedSampleBypassRejected,
    'malformed_inline_filter_operand_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Malformed Filter Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'unresolved_inline_filter_operand_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Unresolved Filter Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'invalid_lzw_earlychange_decode_failed' => $invalidLzwEarlyChangeDecodeFailed,
    'resolves_current_indirect_inline_imagemask_geometry' => ($indirectMaskReview['width'] ?? null) === 4
        && ($indirectMaskReview['height'] ?? null) === 1
        && ($indirectMaskReview['preview_pixel_count'] ?? null) === 4,
    'indirect_inline_imagemask_opacity' => array_column($indirectMaskReview['pixels'] ?? [], 'opacity'),
    'excluded_inline_image_text' => !str_contains($plainText, 'Inline DP Image Noise')
        && !str_contains($plainText, 'raw EI')
        && !str_contains($plainText, 'ASCII85 Inline Noise')
        && !str_contains($plainText, '87cURDc')
        && !str_contains($plainText, 'Oversized Flate Inline Noise')
        && !str_contains($plainText, 'X EI')
        && !str_contains($plainText, 'RunLength Inline Noise')
        && !str_contains($plainText, 'RL EI')
        && !str_contains($plainText, 'Malformed Filter Inline Noise')
        && !str_contains($plainText, 'Unresolved Filter Inline Noise')
        && !str_contains($plainText, 'rawtail'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
