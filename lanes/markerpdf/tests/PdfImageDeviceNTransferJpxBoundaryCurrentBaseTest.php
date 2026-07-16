<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'keeps DeviceN JPX image streams review-only while preserving transfer soft-mask metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $jpxPayload = "\xff\x4fDeviceN JPX colorants and alpha stay inside JPEG2000 bytes\xff\xd9";
        $groupPayload = "q /ImSpot Do Q\n";
        $objects = [
            60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
            91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 36 18] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
            95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.2] /C1 [0.8] /N 2 >>',
            96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.25] /TR 95 0 R >>',
        ];
        $imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [1 0 0 1] /SMask 96 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";

        $preview = $renderer->alternateColorantStreamPreviewRows($imageObject, $objects, 2);

        $t->same('DeviceN', $preview['source_color_space']);
        $t->same(2, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(2, $preview['components_per_pixel']);
        $t->same(8, $preview['bits_per_component']);
        $t->same(2, $preview['expected_pixel_count']);
        $t->same(0, $preview['preview_pixel_count']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(false, $preview['complete_image_sample_data']);
        $t->same(null, $preview['complete_soft_mask_sample_data']);
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
        $t->same(null, $preview['soft_mask_stream']);
        $t->same('DeviceCMYK', $preview['alternate_color_space']);
        $t->same(4, $preview['alternate_components']);
        $t->same(false, $preview['alternate_uses_icc_profile']);
        $t->same(60, $preview['tint_transform_object']);
        $t->same(4, $preview['tint_transform_function_type']);
        $t->same('review_only', $preview['tint_transform_preview_mode']);
        $t->same([
            'present' => true,
            'source' => 'object_ref',
            'object' => 95,
            'name' => null,
            'function_type' => 2,
            'domain' => [0.0, 1.0],
            'range' => [0.0, 1.0],
            'c0' => [0.2],
            'c1' => [0.8],
            'exponent' => 2.0,
            'output_components' => 1,
            'sample_supported' => true,
            'preview_mode' => 'type2_exponential',
        ], $preview['soft_mask_transfer_function']);
        $t->same(true, $preview['soft_mask_transfer_function_applied_before_rgb']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same([
            'alternate_colorant_image_stream_preview_only_before_rgb_conversion',
            'soft_mask_transfer_function_reviewed_without_raster_samples',
        ], $preview['stream_notes']);
        $t->same([], $preview['pixels']);

        $notes = implode(',', $preview['notes']);
        $t->contains('devicen_tint_transform_review_before_rgb_conversion', $notes);
        $t->contains('image_decode_applied_before_rgb_conversion', $notes);
        $t->contains('image_decode_inverts_components_before_rgb', $notes);
        $t->contains('jpx_image_filter_review_only', $notes);
        $t->contains('soft_mask_dictionary_review_before_rgb_conversion', $notes);
        $t->contains('soft_mask_luminosity_group_review_before_rgb_conversion', $notes);
        $t->contains('soft_mask_transfer_function_applied_before_rgb_conversion', $notes);
        $t->contains('alternate_colorant_image_stream_preview_only_before_rgb_conversion', $notes);
        $t->contains('soft_mask_transfer_function_reviewed_without_raster_samples', $notes);
    },
    'keeps JPX SMaskInData authoritative over external DeviceN transfer masks' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
            91 => '<< /Type /XObject /Subtype /Form /BBox [0 0 36 18] /Group << /S /Transparency /CS /DeviceGray >> >>',
            95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [1] /C1 [0] /N 1 >>',
            96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /TR 95 0 R >>',
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /JPXDecode /Width 1 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /SMaskInData 1 /SMask 96 0 R >>',
            $objects
        );

        $t->same([
            'present' => true,
            'value' => 1,
            'valid_value' => true,
            'filter_is_jpx' => true,
            'uses_embedded_soft_mask' => true,
            'encoded_soft_mask_values' => true,
            'preblended_with_matte' => false,
            'external_soft_mask_present' => true,
            'external_soft_mask_ignored' => true,
            'ignored_without_jpx' => false,
            'review_only' => true,
        ], $plan['jpx_soft_mask_in_data']);
        $t->same(null, $plan['soft_mask']);
        $t->same(null, $plan['soft_mask_group']);
        $t->same(null, $plan['soft_mask_transfer_function']);
        $t->same(false, $plan['soft_mask_transfer_function_applied_before_rgb']);
        $t->same('jpx_embedded_soft_mask_review_only_rgb_preview', $plan['alpha_output_mode']);

        $notes = implode(',', $plan['notes']);
        $t->contains('devicen_tint_transform_review_before_rgb_conversion', $notes);
        $t->contains('jpx_embedded_soft_mask_review_before_rgb_conversion', $notes);
        $t->contains('jpx_smaskindata_ignores_external_smask', $notes);
        $t->contains('jpx_image_filter_review_only', $notes);
    },
];
