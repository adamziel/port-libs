<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'maps JPX SMaskInData as embedded soft-mask review before ColorKey and external SMask' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            91 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 2 >>\nstream\nMS\nendstream",
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Decode [0 1 1 0 0 1] /Mask [0 0 120 140 200 255] /SMask 91 0 R /SMaskInData 2 >>',
            $objects
        );

        $t->same(['JPXDecode'], $plan['image_filters']);
        $t->same([
            'preview_only_filters' => ['JPXDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same([
            'present' => true,
            'value' => 2,
            'valid_value' => true,
            'filter_is_jpx' => true,
            'uses_embedded_soft_mask' => true,
            'encoded_soft_mask_values' => false,
            'preblended_with_matte' => true,
            'external_soft_mask_present' => true,
            'external_soft_mask_ignored' => true,
            'ignored_without_jpx' => false,
            'review_only' => true,
        ], $plan['jpx_soft_mask_in_data']);
        $t->same([
            'present' => true,
            'ranges' => [
                ['min' => 0, 'max' => 0],
                ['min' => 120, 'max' => 140],
                ['min' => 200, 'max' => 255],
            ],
            'component_count' => 3,
            'expected_components' => 3,
            'valid_for_components' => true,
            'source' => 'explicit',
            'compares_before_decode' => true,
            'transparent_when_all_components_match' => true,
        ], $plan['color_key_mask']);
        $t->same(false, $plan['color_key_mask_applied_before_rgb']);
        $t->same(true, $plan['color_key_mask_suppressed_by_soft_mask']);
        $t->same(false, $plan['color_key_mask_component_mismatch']);
        $t->same(null, $plan['soft_mask']);
        $t->same(null, $plan['soft_mask_filter_boundary']);
        $t->same(null, $plan['soft_mask_is_grayscale']);
        $t->same(false, $plan['soft_mask_applied_before_rgb']);
        $t->same(false, $plan['soft_mask_decode_applied_before_rgb']);
        $t->same('jpx_embedded_soft_mask_review_only_rgb_preview', $plan['alpha_output_mode']);

        $notes = implode(',', $plan['notes']);
        $t->contains('jpx_embedded_soft_mask_review_before_rgb_conversion', $notes);
        $t->contains('jpx_embedded_soft_mask_preblended_matte_review', $notes);
        $t->contains('jpx_smaskindata_ignores_external_smask', $notes);
        $t->contains('color_key_mask_suppressed_by_soft_mask', $notes);
        $t->contains('jpx_embedded_soft_mask_overrides_color_key_mask', $notes);
        $t->contains('jpx_image_filter_review_only', $notes);
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->colorKeyMaskSamplePreview([0, 128, 240], $plan));
    },
    'keeps zero and non-JPX SMaskInData from suppressing ColorKey masks' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();

        $zero = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /JPXDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Mask [0 0 120 140 200 255] /SMaskInData 0 >>'
        );
        $transparent = $renderer->colorKeyMaskSamplePreview([0, 128, 240], $zero);

        $t->same([
            'present' => true,
            'value' => 0,
            'valid_value' => true,
            'filter_is_jpx' => true,
            'uses_embedded_soft_mask' => false,
            'encoded_soft_mask_values' => false,
            'preblended_with_matte' => false,
            'external_soft_mask_present' => false,
            'external_soft_mask_ignored' => false,
            'ignored_without_jpx' => false,
            'review_only' => false,
        ], $zero['jpx_soft_mask_in_data']);
        $t->same(true, $zero['color_key_mask_applied_before_rgb']);
        $t->same(false, $zero['color_key_mask_suppressed_by_soft_mask']);
        $t->same('color_key_mask_composited_to_rgb_preview', $zero['alpha_output_mode']);
        $t->same(true, $transparent['matches_color_key']);
        $t->same(0.0, $transparent['alpha']);
        $t->contains('jpx_smaskindata_zero_ignores_embedded_soft_mask', implode(',', $zero['notes']));

        $nonJpx = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /FlateDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Mask [0 0 0 0 0 0] /SMaskInData 1 >>'
        );
        $opaque = $renderer->colorKeyMaskSamplePreview([1, 0, 0], $nonJpx);

        $t->same(false, $nonJpx['jpx_soft_mask_in_data']['filter_is_jpx']);
        $t->same(false, $nonJpx['jpx_soft_mask_in_data']['uses_embedded_soft_mask']);
        $t->same(true, $nonJpx['jpx_soft_mask_in_data']['ignored_without_jpx']);
        $t->same(true, $nonJpx['color_key_mask_applied_before_rgb']);
        $t->same(false, $nonJpx['color_key_mask_suppressed_by_soft_mask']);
        $t->same('color_key_mask_composited_to_rgb_preview', $nonJpx['alpha_output_mode']);
        $t->same(false, $opaque['matches_color_key']);
        $t->same(1.0, $opaque['alpha']);
        $t->contains('smask_in_data_ignored_without_jpx', implode(',', $nonJpx['notes']));
    },
    'propagates inline JPX SMaskInData review metadata without raster execution' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "\xff\x4fJPX opacity channel stays in image payload\xff\xd9";

        $plan = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /SMaskInData 1 /Mask [0 0 0 0 0 0]',
            $payload
        );

        $t->same(true, $plan['inline_image']['present']);
        $t->same('<< /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /SMaskInData 1 /Mask [0 0 0 0 0 0] >>', $plan['inline_image']['canonical_dictionary']);
        $t->same(false, $plan['inline_image']['has_object_number']);
        $t->same(true, $plan['inline_image']['excluded_from_visible_text']);
        $t->same(['JPXDecode'], $plan['inline_image']['review_only_filters']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image']['jpx_soft_mask_in_data_present']);
        $t->same(true, $plan['inline_image']['jpx_embedded_soft_mask_present']);
        $t->same(true, $plan['inline_image']['jpx_embedded_soft_mask_review_only']);
        $t->same(1, $plan['jpx_soft_mask_in_data']['value']);
        $t->same(true, $plan['jpx_soft_mask_in_data']['encoded_soft_mask_values']);
        $t->same(false, $plan['jpx_soft_mask_in_data']['preblended_with_matte']);
        $t->same(false, $plan['color_key_mask_applied_before_rgb']);
        $t->same(true, $plan['color_key_mask_suppressed_by_soft_mask']);
        $t->same('jpx_embedded_soft_mask_review_only_rgb_preview', $plan['alpha_output_mode']);
        $t->contains('inline_jpx_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('jpx_embedded_soft_mask_overrides_color_key_mask', implode(',', $plan['notes']));
        $t->same(false, $plan['soft_mask_applied_before_rgb']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
    },
];
