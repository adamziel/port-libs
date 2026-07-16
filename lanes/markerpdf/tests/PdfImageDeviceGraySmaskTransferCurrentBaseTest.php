<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'applies DeviceGray image Decode and soft-mask transfer functions before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $groupPayload = "q /ImGray Do Q\n";
        $objects = [
            91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
            95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.1] /C1 [0.9] /N 2 >>',
            96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.5] /TR 95 0 R >>',
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /SMask 96 0 R >>',
            $objects
        );
        $preview = $renderer->deviceGraySamplePreview(64, $plan, 0.5);

        $t->same('DeviceGray', $preview['source_color_space']);
        $t->same(64.0, $preview['raw_sample']);
        $t->true(abs($preview['decoded_gray'] - (1.0 - (64 / 255))) < 0.000001);
        $t->same([$preview['decoded_gray'], $preview['decoded_gray'], $preview['decoded_gray']], $preview['rgb_components']);
        $t->same(0.5, $preview['soft_mask_alpha_before_transfer']);
        $t->true(abs($preview['soft_mask_alpha'] - 0.3) < 0.000001);
        $t->same(true, $preview['soft_mask_transfer_applied']);
        $t->same([
            'present' => true,
            'source' => 'object_ref',
            'object' => 95,
            'name' => null,
            'function_type' => 2,
            'domain' => [0.0, 1.0],
            'range' => [0.0, 1.0],
            'c0' => [0.1],
            'c1' => [0.9],
            'exponent' => 2.0,
            'output_components' => 1,
            'sample_supported' => true,
            'preview_mode' => 'type2_exponential',
        ], $preview['soft_mask_transfer_function']);
        $t->same(true, $plan['soft_mask_transfer_function_applied_before_rgb']);
        $t->contains('soft_mask_transfer_function_applied_before_rgb_conversion', implode(',', $plan['notes']));
        $t->same('RGB', $preview['output_color_mode']);
    },
    'decodes current DeviceGray image and soft-mask streams into preview rows' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x00\x80\xff";
        $maskBytes = "\xff\x80\x00";
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress DeviceGray image fixtures.');
        }

        $imageHex = strtoupper(bin2hex($compressedImage)) . '>';
        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            42 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
            99 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 9 >>\nstream\nSTALEMASK\nendstream",
        ];
        $imageObject = "<< /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0] /SMask 42 0 R /Length " . strlen($imageHex) . " >>\nstream\n{$imageHex}\nendstream";

        $preview = $renderer->deviceGrayImageStreamPreviewRows($imageObject, $objects, 3);

        $t->same('DeviceGray', $preview['source_color_space']);
        $t->same(3, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(1, $preview['components_per_pixel']);
        $t->same(8, $preview['bits_per_component']);
        $t->same(3, $preview['expected_pixel_count']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($imageHex),
            'decoded_length' => 3,
            'decoded_sha256' => hash('sha256', $imageBytes),
            'decoded_preview_hex' => '0080FF',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['image_stream']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($maskHex),
            'decoded_length' => 3,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => 'FF8000',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['soft_mask_stream']);
        $t->same(42, $preview['soft_mask_filter_boundary']['source_object']);
        $t->same(true, $preview['soft_mask_filter_boundary']['uses_current_object_map']);
        $t->same([
            'devicegray_image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $first = $preview['pixels'][0];
        $t->same(0, $first['pixel_index']);
        $t->same(0, $first['x']);
        $t->same(0, $first['y']);
        $t->same(0.0, $first['raw_sample']);
        $t->same(1.0, $first['decoded_gray']);
        $t->same([1.0, 1.0, 1.0], $first['rgb_components']);
        $t->same(255.0, $first['soft_mask_sample']);
        $t->same(0.0, $first['soft_mask_alpha']);

        $second = $preview['pixels'][1];
        $t->same(128.0, $second['raw_sample']);
        $t->true(abs($second['decoded_gray'] - (1.0 - (128 / 255))) < 0.000001);
        $t->same(128.0, $second['soft_mask_sample']);
        $t->true(abs($second['soft_mask_alpha'] - (1.0 - (128 / 255))) < 0.000001);

        $third = $preview['pixels'][2];
        $t->same(255.0, $third['raw_sample']);
        $t->same(0.0, $third['decoded_gray']);
        $t->same([0.0, 0.0, 0.0], $third['rgb_components']);
        $t->same(0.0, $third['soft_mask_sample']);
        $t->same(1.0, $third['soft_mask_alpha']);
        $t->same('RGB', $preview['output_color_mode']);

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->deviceGrayImageStreamPreviewRows(
                "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 1 >>\nstream\nX\nendstream",
                $objects
            )
        );
    },
];
