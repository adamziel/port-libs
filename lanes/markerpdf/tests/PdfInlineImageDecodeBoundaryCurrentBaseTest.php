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
