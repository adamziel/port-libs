<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'decodes current Separation image and inverted soft-mask streams before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x00\xff";
        $maskBytes = "\x00\x80";
        $staleMaskBytes = 'STALE-SEPARATION-SMASK';
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress Separation SMask fixture.');
        }

        $imageHex = strtoupper(bin2hex($compressedImage)) . '>';
        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            40 => '<< /FunctionType 4 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
            42 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /DecodeParms [null << /Predictor 1 /Columns 2 /Colors 1 /BitsPerComponent 8 >>] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
            99 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [0 1] /Length " . strlen($staleMaskBytes) . " >>\nstream\n{$staleMaskBytes}\nendstream",
        ];
        $imageObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/Separation /Spot#20Red /DeviceCMYK 40 0 R] /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /DecodeParms [null << /Predictor 1 /Columns 2 /Colors 1 /BitsPerComponent 8 >>] /SMask 42 0 R /Length " . strlen($imageHex) . " >>\nstream\n{$imageHex}\nendstream";

        $preview = $renderer->alternateColorantStreamPreviewRows($imageObject, $objects, 2);

        $t->same('Separation', $preview['source_color_space']);
        $t->same(2, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(1, $preview['components_per_pixel']);
        $t->same(2, $preview['preview_pixel_count']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($imageHex),
            'decoded_length' => 2,
            'decoded_sha256' => hash('sha256', $imageBytes),
            'decoded_preview_hex' => '00FF',
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
            'present' => true,
            'source_object' => 42,
            'uses_current_object_map' => true,
            'decoded_with_current_filters' => true,
            'decode_source' => 'explicit',
            'opacity_for_zero' => 1.0,
            'opacity_for_max' => 0.0,
            'inverted' => true,
            'component_mismatch' => false,
            'applied_before_rgb' => true,
        ], $preview['soft_mask_decode_review']);
        $t->same([
            'image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);
        $t->same(42, $preview['soft_mask_filter_boundary']['source_object']);
        $t->same([0, 128], $preview['soft_mask_filter_boundary']['decoded_sample_bytes']);
        $t->same(true, $preview['soft_mask_filter_boundary']['uses_current_object_map']);

        $first = $preview['pixels'][0];
        $t->same([0.0], $first['raw_sample']);
        $t->same(['Spot Red' => 1.0], $first['colorant_tints']);
        $t->same([1.0], $first['tint_values']);
        $t->same(0.0, $first['soft_mask_sample']);
        $t->same(1.0, $first['soft_mask_alpha_before_transfer']);
        $t->same(1.0, $first['soft_mask_alpha']);

        $second = $preview['pixels'][1];
        $t->same([255.0], $second['raw_sample']);
        $t->same(['Spot Red' => 0.0], $second['colorant_tints']);
        $t->same([0.0], $second['tint_values']);
        $t->same(128.0, $second['soft_mask_sample']);
        $t->true(abs((float) $second['soft_mask_alpha'] - (1.0 - (128 / 255))) < 0.000001);

        $encoded = json_encode($preview, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $staleMaskBytes));
        $t->same('RGB', $preview['output_color_mode']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);

        $notes = implode(',', $preview['notes']);
        $t->contains('separation_tint_transform_review_before_rgb_conversion', $notes);
        $t->contains('image_decode_inverts_components_before_rgb', $notes);
        $t->contains('soft_mask_decode_inverts_alpha', $notes);
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', $notes);
    },
    'decodes current DeviceN stream rows and non-identity soft-mask Decode alpha' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x00\x40\xff\x80";
        $maskBytes = "\xff\x00";
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress DeviceN SMask fixture.');
        }

        $objects = [
            60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
            77 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0.25 0.75] /Length " . strlen($compressedMask) . " >>\nstream\n{$compressedMask}\nendstream",
        ];
        $imageObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1 1 0] /SMask 77 0 R /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";

        $preview = $renderer->alternateColorantStreamPreviewRows($imageObject, $objects, 2);

        $t->same('DeviceN', $preview['source_color_space']);
        $t->same(2, $preview['components_per_pixel']);
        $t->same('DeviceCMYK', $preview['alternate_color_space']);
        $t->same(4, $preview['alternate_components']);
        $t->same(60, $preview['tint_transform_object']);
        $t->same(4, $preview['tint_transform_function_type']);
        $t->same('review_only', $preview['tint_transform_preview_mode']);
        $t->same([
            'present' => true,
            'source_object' => 77,
            'uses_current_object_map' => true,
            'decoded_with_current_filters' => true,
            'decode_source' => 'explicit',
            'opacity_for_zero' => 0.25,
            'opacity_for_max' => 0.75,
            'inverted' => false,
            'component_mismatch' => false,
            'applied_before_rgb' => true,
        ], $preview['soft_mask_decode_review']);
        $t->same([
            'image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $first = $preview['pixels'][0];
        $t->same([0.0, 64.0], $first['raw_sample']);
        $t->same(0.0, $first['colorant_tints']['Spot Blue']);
        $t->true(abs((float) $first['colorant_tints']['Spot Varnish'] - (1.0 - (64 / 255))) < 0.000001);
        $t->same(255.0, $first['soft_mask_sample']);
        $t->same(0.75, $first['soft_mask_alpha']);

        $second = $preview['pixels'][1];
        $t->same([255.0, 128.0], $second['raw_sample']);
        $t->same(1.0, $second['colorant_tints']['Spot Blue']);
        $t->true(abs((float) $second['colorant_tints']['Spot Varnish'] - (1.0 - (128 / 255))) < 0.000001);
        $t->same(0.0, $second['soft_mask_sample']);
        $t->same(0.25, $second['soft_mask_alpha']);

        $notes = implode(',', $preview['notes']);
        $t->contains('devicen_tint_transform_review_before_rgb_conversion', $notes);
        $t->contains('image_decode_inverts_components_before_rgb', $notes);
        $t->contains('soft_mask_decode_applied_before_rgb_conversion', $notes);
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', $notes);
    },
];
