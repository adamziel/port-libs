<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$jpxOutputFixture = static function (): array {
    $jpxPayload = "\xff\x4fDeviceRGB JPX color samples stay review-only BT /F1 12 Tf (Hidden JPX Output Text) Tj\xff\xd9";
    $maskBytes = "\x00\x80\xff";
    $compressedMask = gzcompress($maskBytes);
    if (!is_string($compressedMask)) {
        throw new RuntimeException('Unable to compress JPEG2000 output soft-mask fixture.');
    }

    $objects = [
        'Resources' => '70 0 R',
        70 => '<< /ColorSpace << /CSrgb /DeviceRGB /CSstale /DeviceCMYK >> >>',
        82 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($compressedMask) . " >>\nstream\n{$compressedMask}\nendstream",
        99 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [0 1] /Length 5 >>\nstream\nSTALE\nendstream",
    ];
    $imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 3 /Height 1 /ColorSpace /CSrgb /BitsPerComponent 8 /Decode [0 1 1 0 0 1] /SMask 82 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";

    return [$imageObject, $objects, $jpxPayload, $maskBytes];
};

return [
    'maps supplied JPEG2000 color samples and current SMask alpha into RGB output rows' => static function (TestRunner $t) use ($jpxOutputFixture): void {
        [$imageObject, $objects, $jpxPayload, $maskBytes] = $jpxOutputFixture();
        $renderer = new PdfImageRenderer();

        $preview = $renderer->jpeg2000ColorSpaceSoftMaskOutputPreviewRows(
            $imageObject,
            [
                [0, 128, 240],
                [255, 64, 180],
                [32, 255, 0],
            ],
            $objects,
            3,
            [
                'pdfa' => [
                    'has_output_intent' => true,
                    'output_condition_identifiers' => ['Current JPEG2000 RGB OutputIntent'],
                    'profile_sha256' => [hash('sha256', 'Current JPEG2000 output profile')],
                ],
            ]
        );

        $t->same('DeviceRGB', $preview['source_color_space']);
        $t->same('CSrgb', $preview['color_space_resource_name']);
        $t->same('Resources.ColorSpace', $preview['color_space_resource_source']);
        $t->same(true, $preview['color_space_resolved_from_resources']);
        $t->same(3, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(3, $preview['components_per_pixel']);
        $t->same(8, $preview['bits_per_component']);
        $t->same(3, $preview['expected_pixel_count']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(false, $preview['native_jpx_raster_decode']);
        $t->same(true, $preview['uses_supplied_jpx_samples']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same([
            'filters' => ['JPXDecode'],
            'preview_only_filters' => ['JPXDecode'],
            'unsupported_filters' => ['JPXDecode'],
            'raw_length' => strlen($jpxPayload),
            'decoded_length' => null,
            'decoded_sha256' => null,
            'decoded_preview_hex' => null,
            'decoded_with_current_filters' => false,
            'decode_failed' => false,
        ], $preview['image_stream']);
        $t->same([
            'filters' => ['FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen(gzcompress($maskBytes) ?: ''),
            'decoded_length' => 3,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => '0080FF',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['soft_mask_stream']);
        $t->same(82, $preview['soft_mask_filter_boundary']['source_object']);
        $t->same([0, 128, 255], $preview['soft_mask_filter_boundary']['decoded_sample_bytes']);
        $t->same(true, $preview['soft_mask_filter_boundary']['uses_current_object_map']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 1.0, 'max' => 0.0],
                ['min' => 0.0, 'max' => 1.0],
            ],
            'component_count' => 3,
            'expected_components' => 3,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [1],
            'source' => 'explicit',
        ], $preview['image_decode']);
        $t->same([
            'present' => true,
            'source' => 'document_metadata_pdfa_output_intents',
            'output_condition_identifiers' => ['Current JPEG2000 RGB OutputIntent'],
            'profile_sha256' => [hash('sha256', 'Current JPEG2000 output profile')],
            'profile_count' => 1,
            'review_only' => true,
            'payload_included' => false,
        ], $preview['pdfa_output_intent']);
        $t->same('pdfa_output_intent', $preview['color_management']['profile_source']);
        $t->same(true, $preview['pdfa_output_intent_applies_before_rgb']);
        $t->same('RGB', $preview['output_color_mode']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);

        $first = $preview['pixels'][0];
        $t->same([0.0, 128.0, 240.0], $first['raw_sample']);
        $t->same([0.0, 0.4980392156862745, 0.9411764705882353], $first['decoded_components']);
        $t->same(0.0, $first['soft_mask_sample']);
        $t->same(1.0, $first['soft_mask_alpha']);
        $t->same(['red' => 0, 'green' => 127, 'blue' => 240, 'alpha' => 1.0], $first['output_rgba']);

        $second = $preview['pixels'][1];
        $t->same([255.0, 64.0, 180.0], $second['raw_sample']);
        $t->same([1.0, 0.7490196078431373, 0.7058823529411765], $second['decoded_components']);
        $t->same(128.0, $second['soft_mask_sample']);
        $t->true(abs((float) $second['soft_mask_alpha'] - (1.0 - (128 / 255))) < 0.000001);
        $t->same(['red' => 255, 'green' => 191, 'blue' => 180, 'alpha' => $second['soft_mask_alpha']], $second['output_rgba']);

        $third = $preview['pixels'][2];
        $t->same([32.0, 255.0, 0.0], $third['raw_sample']);
        $t->same([32 / 255, 0.0, 0.0], $third['decoded_components']);
        $t->same(255.0, $third['soft_mask_sample']);
        $t->same(0.0, $third['soft_mask_alpha']);
        $t->same(['red' => 32, 'green' => 0, 'blue' => 0, 'alpha' => 0.0], $third['output_rgba']);

        $encoded = json_encode($preview, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $jpxPayload));
        $t->true(!str_contains($encoded, $maskBytes));
        $t->true(!str_contains($encoded, 'STALE'));
        $t->same([
            'jpeg2000_image_stream_review_only_before_rgb_conversion',
            'jpeg2000_supplied_colorspace_samples_before_output_preview',
            'soft_mask_stream_filters_decoded_before_output_preview',
        ], $preview['stream_notes']);

        $notes = implode(',', $preview['notes']);
        $t->contains('image_color_space_resolved_from_current_resources', $notes);
        $t->contains('image_decode_inverts_components_before_rgb', $notes);
        $t->contains('soft_mask_decode_inverts_alpha', $notes);
        $t->contains('jpeg2000_colorspace_smask_output_rows_currentbase', $notes);
        $t->contains('jpeg2000_supplied_samples_previewed_without_native_raster_decode', $notes);
        $t->contains('jpeg2000_soft_mask_alpha_composed_to_rgb_output', $notes);
        $t->contains('jpeg2000_output_preserves_pdfa_output_intent_context', $notes);
    },
    'reports incomplete supplied JPEG2000 samples and rejects unsupported output boundaries' => static function (TestRunner $t) use ($jpxOutputFixture): void {
        [$imageObject, $objects] = $jpxOutputFixture();
        $renderer = new PdfImageRenderer();

        $preview = $renderer->jpeg2000ColorSpaceSoftMaskOutputPreviewRows(
            $imageObject,
            [
                [0, 128, 240],
                [255, 64, 180],
            ],
            $objects,
            3
        );

        $t->same(3, $preview['expected_pixel_count']);
        $t->same(2, $preview['preview_pixel_count']);
        $t->same(false, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->contains('jpeg2000_supplied_sample_data_incomplete', implode(',', $preview['stream_notes']));
        $t->contains('jpeg2000_supplied_sample_data_incomplete', implode(',', $preview['notes']));

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->jpeg2000ColorSpaceSoftMaskOutputPreviewRows(
                "<< /Subtype /Image /Filter /JPXDecode /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /SMask 82 0 R /Length 1 >>\nstream\nX\nendstream",
                [[0, 0, 0, 0]],
                $objects
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->jpeg2000ColorSpaceSoftMaskOutputPreviewRows(
                "<< /Subtype /Image /Filter /FlateDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 82 0 R /Length 1 >>\nstream\nX\nendstream",
                [[0, 0, 0]],
                $objects
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->jpeg2000ColorSpaceSoftMaskOutputPreviewRows(
                "<< /Subtype /Image /Filter /JPXDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 1 >>\nstream\nX\nendstream",
                [[0, 0, 0]],
                $objects
            )
        );
    },
];
