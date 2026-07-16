<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'decodes ICCBased image stream samples and SMask Decode alpha before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x00\x80\xff\xff\x00\x40";
        $maskBytes = "\x00\x80";
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress ICCBased image fixtures.');
        }

        $imageHex = strtoupper(bin2hex($compressedImage)) . '>';
        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            10 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0.25 0.75] /Length 11 >>\nstream\nICC-PROFILE\nendstream",
            42 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 2 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0] /Matte [1 0.5 0.25] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
            99 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 8 >>\nstream\nSTALEMSK\nendstream",
        ];
        $imageObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/ICCBased 10 0 R] /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 6 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0 0 1 0.25 0.75] /SMask 42 0 R /Length " . strlen($imageHex) . " >>\nstream\n{$imageHex}\nendstream";

        $preview = $renderer->iccBasedImageStreamPreviewRows($imageObject, $objects, 2);

        $t->same('ICCBased', $preview['source_color_space']);
        $t->same(2, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(3, $preview['components_per_pixel']);
        $t->same(8, $preview['bits_per_component']);
        $t->same(2, $preview['expected_pixel_count']);
        $t->same(2, $preview['preview_pixel_count']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($imageHex),
            'decoded_length' => 6,
            'decoded_sha256' => hash('sha256', $imageBytes),
            'decoded_preview_hex' => '0080FFFF0040',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['image_stream']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($maskHex),
            'decoded_length' => 2,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => '0080',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['soft_mask_stream']);
        $t->same([
            'components' => 3,
            'alternate_color_space' => 'DeviceRGB',
            'range' => [0.0, 1.0, 0.0, 1.0, 0.25, 0.75],
            'length' => 11,
        ], $preview['icc_profile']);
        $t->same(42, $preview['soft_mask_filter_boundary']['source_object']);
        $t->same(true, $preview['soft_mask_filter_boundary']['uses_current_object_map']);
        $t->same([
            'iccbased_image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $first = $preview['pixels'][0];
        $t->same(0, $first['pixel_index']);
        $t->same(0, $first['x']);
        $t->same(0, $first['y']);
        $t->same([0.0, 128.0, 255.0], $first['raw_sample']);
        $t->same(1.0, $first['decoded_components'][0]);
        $t->true(abs($first['decoded_components'][1] - (128 / 255)) < 0.000001);
        $t->same(0.75, $first['decoded_components'][2]);
        $t->same(0.0, $first['soft_mask_sample']);
        $t->same(1.0, $first['soft_mask_alpha']);
        $t->same(1.0, $first['soft_mask_alpha_before_transfer']);
        $t->same(false, $first['soft_mask_transfer_applied']);

        $second = $preview['pixels'][1];
        $t->same(1, $second['pixel_index']);
        $t->same(1, $second['x']);
        $t->same(0, $second['y']);
        $t->same([255.0, 0.0, 64.0], $second['raw_sample']);
        $t->same(0.0, $second['decoded_components'][0]);
        $t->same(0.0, $second['decoded_components'][1]);
        $t->true(abs($second['decoded_components'][2] - (0.25 + (0.5 * (64 / 255)))) < 0.000001);
        $t->same(128.0, $second['soft_mask_sample']);
        $t->true(abs((float) $second['soft_mask_alpha'] - (1.0 - (128 / 255))) < 0.000001);
        $t->same('RGB', $preview['output_color_mode']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);

        $notes = implode(',', $preview['notes']);
        $t->contains('icc_profile_color_space', $notes);
        $t->contains('image_decode_applied_before_rgb_conversion', $notes);
        $t->contains('image_decode_inverts_components_before_rgb', $notes);
        $t->contains('soft_mask_decode_inverts_alpha', $notes);
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', $notes);
        $t->contains('iccbased_image_stream_filters_decoded_before_rgb_conversion', $notes);

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->iccBasedImageStreamPreviewRows(
                "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 1 >>\nstream\nX\nendstream",
                $objects
            )
        );
    },
];
