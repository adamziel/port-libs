<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$deviceNIccSmaskTransferFixture = static function (): array {
    $imageBytes = "\x20\xc0\xff\x00";
    $compressedImage = gzcompress($imageBytes);
    if (!is_string($compressedImage)) {
        throw new RuntimeException('Unable to compress DeviceN ICC SMask transfer fixture.');
    }

    $groupPayload = "q /ImSpot Do Q\n";
    $objects = [
        71 => '[/ICCBased 72 0 R]',
        72 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 17 >>\nstream\nICC-DEVICE-N-XFER\nendstream",
        73 => "<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1] /Length 24 >>\nstream\n{ exch dup mul exch }\nendstream",
        91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 40 20] /Group << /S /Transparency /CS 97 0 R /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
        95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.1] /C1 [0.9] /N 2 >>',
        96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.5] /TR 95 0 R >>',
        97 => '[/ICCBased 98 0 R]',
        98 => "<< /N 1 /Alternate /DeviceGray /Range [0 1] /Length 15 >>\nstream\nICC-ALPHA-GROUP\nendstream",
    ];
    $imageObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] 71 0 R 73 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0 0 1] /SMask 96 0 R /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";

    return [
        'image_bytes' => $imageBytes,
        'image_object' => $imageObject,
        'objects' => $objects,
    ];
};

return [
    'maps decoded DeviceN ICCBased streams through supplied soft-mask transfer samples before RGB preview' => static function (TestRunner $t) use ($deviceNIccSmaskTransferFixture): void {
        $renderer = new PdfImageRenderer();
        $fixture = $deviceNIccSmaskTransferFixture();

        $preview = $renderer->alternateColorantStreamPreviewRows(
            $fixture['image_object'],
            $fixture['objects'],
            2,
            [0.25, [0.75]]
        );

        $t->same('DeviceN', $preview['source_color_space']);
        $t->same(2, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(2, $preview['components_per_pixel']);
        $t->same(2, $preview['preview_pixel_count']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same(true, $preview['uses_supplied_soft_mask_samples']);
        $t->same([
            'filters' => ['FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen(gzcompress($fixture['image_bytes']) ?: ''),
            'decoded_length' => 4,
            'decoded_sha256' => hash('sha256', $fixture['image_bytes']),
            'decoded_preview_hex' => '20C0FF00',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['image_stream']);
        $t->same(null, $preview['soft_mask_stream']);
        $t->same([
            'present' => true,
            'source_object' => 96,
            'uses_current_object_map' => true,
            'decoded_with_current_filters' => false,
            'decode_source' => null,
            'opacity_for_zero' => null,
            'opacity_for_max' => null,
            'inverted' => false,
            'component_mismatch' => false,
            'applied_before_rgb' => false,
        ], $preview['soft_mask_decode_review']);
        $t->same([
            'image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_transparency_group_supplied_samples_before_rgb_conversion',
        ], $preview['stream_notes']);

        $t->same('ICCBased', $preview['alternate_color_space']);
        $t->same(3, $preview['alternate_components']);
        $t->same(true, $preview['alternate_uses_icc_profile']);
        $t->same([
            'components' => 3,
            'alternate_color_space' => 'DeviceRGB',
            'range' => [0.0, 1.0, 0.0, 1.0, 0.0, 1.0],
            'length' => 17,
        ], $preview['icc_profile']);
        $t->same(73, $preview['tint_transform_object']);
        $t->same(4, $preview['tint_transform_function_type']);
        $t->same('review_only', $preview['tint_transform_preview_mode']);
        $t->same('Luminosity', $preview['soft_mask_group']['subtype']);
        $t->same('ICCBased', $preview['soft_mask_group']['group_color_space']);
        $t->same(1, $preview['soft_mask_group']['group_components']);
        $t->same([0.5], $preview['soft_mask_group']['backdrop_color']);
        $t->same(95, $preview['soft_mask_transfer_function']['object']);
        $t->same(true, $preview['soft_mask_transfer_function_applied_before_rgb']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);

        $first = $preview['pixels'][0];
        $t->same([32.0, 192.0], $first['raw_sample']);
        $t->true(abs((float) $first['colorant_tints']['Spot Blue'] - (1.0 - (32 / 255))) < 0.000001);
        $t->true(abs((float) $first['colorant_tints']['Spot Varnish'] - (192 / 255)) < 0.000001);
        $t->same(0.25, $first['soft_mask_sample']);
        $t->same(0.25, $first['soft_mask_alpha_before_transfer']);
        $t->same(0.15000000000000002, $first['soft_mask_alpha']);
        $t->same(true, $first['soft_mask_transfer_applied']);

        $second = $preview['pixels'][1];
        $t->same([255.0, 0.0], $second['raw_sample']);
        $t->same(0.0, $second['colorant_tints']['Spot Blue']);
        $t->same(0.0, $second['colorant_tints']['Spot Varnish']);
        $t->same(0.75, $second['soft_mask_sample']);
        $t->same(0.75, $second['soft_mask_alpha_before_transfer']);
        $t->same(0.55, $second['soft_mask_alpha']);
        $t->same(true, $second['soft_mask_transfer_applied']);

        $notes = implode(',', $preview['notes']);
        $t->contains('devicen_tint_transform_review_before_rgb_conversion', $notes);
        $t->contains('alternate_icc_profile_color_space', $notes);
        $t->contains('soft_mask_luminosity_group_review_before_rgb_conversion', $notes);
        $t->contains('soft_mask_transfer_function_applied_before_rgb_conversion', $notes);
        $t->contains('image_stream_filters_decoded_before_rgb_conversion', $notes);
        $t->contains('soft_mask_transparency_group_supplied_samples_before_rgb_conversion', $notes);
    },
    'requires complete supplied soft-mask group samples before decoded DeviceN stream preview' => static function (TestRunner $t) use ($deviceNIccSmaskTransferFixture): void {
        $renderer = new PdfImageRenderer();
        $fixture = $deviceNIccSmaskTransferFixture();

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->alternateColorantStreamPreviewRows(
                $fixture['image_object'],
                $fixture['objects'],
                2
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->alternateColorantStreamPreviewRows(
                $fixture['image_object'],
                $fixture['objects'],
                2,
                [0.25]
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->alternateColorantStreamPreviewRows(
                $fixture['image_object'],
                $fixture['objects'],
                2,
                [[0.25, 0.75], [0.5]]
            )
        );
    },
];
