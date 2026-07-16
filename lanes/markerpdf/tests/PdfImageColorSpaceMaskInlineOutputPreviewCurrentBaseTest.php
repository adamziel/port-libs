<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'maps inline Indexed color-space and soft-mask samples into RGB output preview rows' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x1c";
        $maskBytes = "\x00\x80\xff";
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress inline image output preview fixtures.');
        }

        $imageHex = strtoupper(bin2hex($compressedImage)) . '>';
        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            91 => '<000000FF000000FF000000FF>',
            92 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
        ];

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F [/AHx /Fl] /D [0 3] /Mask [1 1] /SMask 92 0 R',
            $imageHex,
            $objects,
            3
        );

        $t->same('Indexed', $preview['source_color_space']);
        $t->same(3, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(1, $preview['components_per_pixel']);
        $t->same(2, $preview['bits_per_component']);
        $t->same(3, $preview['expected_pixel_count']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['native_raster_decode']);
        $t->same(false, $preview['uses_supplied_samples']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same(true, $preview['color_key_mask_suppressed_by_soft_mask']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same('RGB', $preview['output_color_mode']);
        $t->same([
            'inline_image_stream_filters_decoded_before_output_preview',
            'inline_soft_mask_stream_filters_decoded_before_output_preview',
        ], $preview['stream_notes']);

        $black = $preview['pixels'][0];
        $t->same(0, $black['pixel_index']);
        $t->same([0.0], $black['raw_sample']);
        $t->same(0.0, $black['decoded_index']);
        $t->same(0, $black['palette_index']);
        $t->same([0.0, 0.0, 0.0], $black['base_components']);
        $t->same([0.0, 0.0, 0.0], $black['rgb_components']);
        $t->same(0.0, $black['soft_mask_sample']);
        $t->same(1.0, $black['alpha']);
        $t->same('soft_mask', $black['alpha_source']);
        $t->same(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 1.0], $black['output_rgba']);

        $red = $preview['pixels'][1];
        $t->same(1.0, $red['raw_sample'][0]);
        $t->same(1, $red['palette_index']);
        $t->same([1.0, 0.0, 0.0], $red['rgb_components']);
        $t->same(128.0, $red['soft_mask_sample']);
        $t->true(abs((float) $red['alpha'] - (1.0 - (128 / 255))) < 0.000001);
        $t->same(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => $red['alpha']], $red['output_rgba']);

        $blue = $preview['pixels'][2];
        $t->same(3, $blue['palette_index']);
        $t->same([0.0, 0.0, 1.0], $blue['rgb_components']);
        $t->same(255.0, $blue['soft_mask_sample']);
        $t->same(0.0, $blue['alpha']);
        $t->same(['red' => 0, 'green' => 0, 'blue' => 255, 'alpha' => 0.0], $blue['output_rgba']);

        $notes = implode(',', $preview['notes']);
        $t->contains('inline_image_colorspace_mask_output_preview_currentbase', $notes);
        $t->contains('inline_image_output_preview_expands_indexed_palette_to_rgb', $notes);
        $t->contains('inline_image_output_preview_applies_soft_mask_alpha', $notes);
        $t->contains('inline_image_output_preview_soft_mask_suppresses_color_key', $notes);
        $t->contains('inline_image_payload_excluded_from_visible_text', $notes);
    },
    'uses supplied inline JPX samples with current soft-mask decode for output preview rows' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "\xff\x4fJPX SMask bytes EI BT /F1 12 Tf (Hidden output text) Tj ET\xff\xd9";
        $maskBytes = "\x00\xff";
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress inline JPX soft-mask fixture.');
        }

        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            38 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
        ];

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            '/W 2 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1 1 0 0 1] /SMask 38 0 R',
            $payload,
            $objects,
            2,
            [
                [0, 128, 240],
                [40, 64, 180],
            ]
        );

        $t->same('DeviceRGB', $preview['source_color_space']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(false, $preview['native_raster_decode']);
        $t->same(true, $preview['uses_supplied_samples']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same([
            'filters' => ['JPXDecode'],
            'preview_only_filters' => ['JPXDecode'],
            'unsupported_filters' => ['JPXDecode'],
            'raw_length' => strlen($payload),
            'decoded_length' => null,
            'decoded_sha256' => null,
            'decoded_preview_hex' => null,
            'decoded_with_current_filters' => false,
            'decode_failed' => false,
        ], $preview['image_stream']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($maskHex),
            'decoded_length' => 2,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => '00FF',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['soft_mask_stream']);
        $t->same([
            'inline_image_stream_review_only_before_output_preview',
            'inline_image_supplied_samples_before_output_preview',
            'inline_soft_mask_stream_filters_decoded_before_output_preview',
        ], $preview['stream_notes']);

        $visible = $preview['pixels'][0];
        $t->same([0.0, 128.0, 240.0], $visible['raw_sample']);
        $t->same([0.0, 0.4980392156862745, 0.9411764705882353], $visible['decoded_components']);
        $t->same(0.0, $visible['soft_mask_sample']);
        $t->same(1.0, $visible['alpha']);
        $t->same('soft_mask', $visible['alpha_source']);
        $t->same(['red' => 0, 'green' => 127, 'blue' => 240, 'alpha' => 1.0], $visible['output_rgba']);

        $transparent = $preview['pixels'][1];
        $t->same([40.0, 64.0, 180.0], $transparent['raw_sample']);
        $t->same([0.1568627450980392, 0.7490196078431373, 0.7058823529411765], $transparent['decoded_components']);
        $t->same(255.0, $transparent['soft_mask_sample']);
        $t->same(0.0, $transparent['alpha']);
        $t->same(['red' => 40, 'green' => 191, 'blue' => 180, 'alpha' => 0.0], $transparent['output_rgba']);

        $notes = implode(',', $preview['notes']);
        $t->contains('inline_jpx_image_filter_review_only', $notes);
        $t->contains('inline_image_output_preview_preserves_review_only_filter_boundary', $notes);
        $t->contains('inline_image_output_preview_uses_supplied_samples_without_raster_decode', $notes);
        $t->contains('inline_image_output_preview_uses_devicergb_samples', $notes);
        $t->contains('inline_image_output_preview_applies_soft_mask_alpha', $notes);
    },
];
