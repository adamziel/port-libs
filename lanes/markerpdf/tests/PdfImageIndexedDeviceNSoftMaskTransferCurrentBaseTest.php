<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$indexedDeviceNTransferFixture = static function (): array {
    $objects = [
        60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
        91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 42 21] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length 14 >>\nstream\nq /ImSpot Do Q\nendstream",
        95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [1] /C1 [0] /N 1 >>',
        96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.25] /TR 95 0 R >>',
    ];
    $imageDictionary = '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/Indexed [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] 2 <0040FF0000FF>] /BitsPerComponent 2 /Decode [0 2] /SMask 96 0 R >>';

    return [$imageDictionary, $objects];
};

return [
    'maps Indexed DeviceN palette tints and soft-mask transfer alpha before RGB preview' => static function (TestRunner $t) use ($indexedDeviceNTransferFixture): void {
        [$imageDictionary, $objects] = $indexedDeviceNTransferFixture();
        $renderer = new PdfImageRenderer();

        $plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
        $preview = $renderer->indexedAlternateColorantSamplePreview(3, $plan, 0.25);

        $t->same('Indexed', $plan['source_color_space']);
        $t->same(true, $plan['uses_indexed_color_space']);
        $t->same('DeviceN', $plan['indexed_color_space']['base_color_space']);
        $t->same(true, $plan['indexed_color_space']['base_uses_alternate_color_space']);
        $t->same([
            'family' => 'DeviceN',
            'colorant_names' => ['Spot Blue', 'Spot Varnish'],
            'alternate_color_space' => 'DeviceCMYK',
            'alternate_components' => 4,
            'alternate_uses_icc_profile' => false,
            'tint_transform_source' => 'object_ref',
            'tint_transform_object' => 60,
            'tint_transform_function_type' => 4,
            'attributes_present' => true,
        ], $plan['indexed_color_space']['base_alternate_color_space']);
        $t->same([
            'present' => true,
            'subtype' => 'Luminosity',
            'source_object' => 96,
            'group_object' => 91,
            'group_subtype' => 'Form',
            'group_bbox' => [0.0, 0.0, 42.0, 21.0],
            'group_color_space' => 'DeviceGray',
            'group_components' => 1,
            'group_is_isolated' => true,
            'group_is_knockout' => false,
            'uses_indexed_color_space' => false,
            'indexed_color_space' => null,
            'backdrop_color' => [0.25],
            'backdrop_component_count' => 1,
            'backdrop_matches_group_components' => true,
            'transfer_function' => [
                'present' => true,
                'source' => 'object_ref',
                'object' => 95,
                'name' => null,
                'function_type' => 2,
                'domain' => [0.0, 1.0],
                'range' => [0.0, 1.0],
                'c0' => [1.0],
                'c1' => [0.0],
                'exponent' => 1.0,
                'output_components' => 1,
                'sample_supported' => true,
                'preview_mode' => 'type2_exponential',
            ],
            'review_only' => true,
        ], $plan['soft_mask_group']);

        $t->same('Indexed', $preview['source_color_space']);
        $t->same('DeviceN', $preview['base_color_space']);
        $t->same(2.0, $preview['decoded_index']);
        $t->same(2, $preview['palette_index']);
        $t->same(['Spot Blue', 'Spot Varnish'], array_keys($preview['colorant_tints']));
        $t->same(0.0, $preview['colorant_tints']['Spot Blue']);
        $t->same(1.0, $preview['colorant_tints']['Spot Varnish']);
        $t->same([0.0, 1.0], $preview['tint_values']);
        $t->same('DeviceCMYK', $preview['alternate_color_space']);
        $t->same(4, $preview['alternate_components']);
        $t->same(60, $preview['tint_transform_object']);
        $t->same(4, $preview['tint_transform_function_type']);
        $t->same('review_only', $preview['tint_transform_preview_mode']);
        $t->same(0.25, $preview['soft_mask_alpha_before_transfer']);
        $t->same(0.75, $preview['soft_mask_alpha']);
        $t->same(true, $preview['soft_mask_transfer_applied']);
        $t->same($plan['soft_mask_transfer_function'], $preview['soft_mask_transfer_function']);
        $t->same(true, $plan['soft_mask_transfer_function_applied_before_rgb']);
        $t->same('RGB', $preview['output_color_mode']);
        $t->contains('indexed_base_devicen_tint_transform_review_before_rgb_conversion', implode(',', $plan['notes']));
        $t->contains('soft_mask_transfer_function_applied_before_rgb_conversion', implode(',', $plan['notes']));
    },
    'carries DeviceN palette colorant names through decoded Indexed stream rows' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x1c";
        $maskBytes = "\x00\x80\xff";
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress Indexed DeviceN stream fixtures.');
        }

        $imageHex = strtoupper(bin2hex($compressedImage)) . '>';
        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
            82 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
        ];
        $imageObject = "<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/Indexed [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] 2 <0040FF0000FF>] /BitsPerComponent 2 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [0 2] /SMask 82 0 R /Length " . strlen($imageHex) . " >>\nstream\n{$imageHex}\nendstream";

        $preview = $renderer->indexedImageStreamPreviewRows($imageObject, $objects, 3);

        $t->same('Indexed', $preview['source_color_space']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same([
            'base_color_space' => 'DeviceN',
            'colorant_names' => ['Spot Blue', 'Spot Varnish'],
            'alternate_color_space' => 'DeviceCMYK',
            'alternate_components' => 4,
            'alternate_uses_icc_profile' => false,
            'icc_profile' => null,
            'tint_transform_object' => 60,
            'tint_transform_function_type' => 4,
            'tint_transform_preview_mode' => 'review_only',
        ], $preview['indexed_alternate_color_space']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $preview['image_stream']['filters']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $preview['soft_mask_stream']['filters']);
        $t->same([
            'indexed_image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $first = $preview['pixels'][0];
        $t->same(0.0, $first['raw_sample']);
        $t->same(0, $first['palette_index']);
        $t->same(['Spot Blue' => 0.0, 'Spot Varnish' => 64 / 255], $first['colorant_tints']);
        $t->same([0.0, 64 / 255], $first['tint_values']);
        $t->same(0.0, $first['soft_mask_sample']);
        $t->same(1.0, $first['soft_mask_alpha']);
        $t->same(1.0, $first['soft_mask_alpha_before_transfer']);
        $t->same(false, $first['soft_mask_transfer_applied']);

        $second = $preview['pixels'][1];
        $t->same(1.0, $second['raw_sample']);
        $t->same(1, $second['palette_index']);
        $t->same(['Spot Blue' => 1.0, 'Spot Varnish' => 0.0], $second['colorant_tints']);
        $t->same(128.0, $second['soft_mask_sample']);
        $t->true(abs((float) $second['soft_mask_alpha'] - (1.0 - (128 / 255))) < 0.000001);

        $third = $preview['pixels'][2];
        $t->same(3.0, $third['raw_sample']);
        $t->same(2.0, $third['decoded_index']);
        $t->same(2, $third['palette_index']);
        $t->same(['Spot Blue' => 0.0, 'Spot Varnish' => 1.0], $third['colorant_tints']);
        $t->same(255.0, $third['soft_mask_sample']);
        $t->same(0.0, $third['soft_mask_alpha']);
        $t->same('RGB', $preview['output_color_mode']);
    },
];
