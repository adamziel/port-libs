<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'maps Indexed ColorKey masks against raw indexes before Decode palette transfer' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/Indexed /DeviceRGB 3 <000000FF000000FF000000FF>] /BitsPerComponent 2 /Decode [3 0] /Mask [1 1] >>'
        );

        $transparent = $renderer->indexedColorKeyMaskSamplePreview(1, $plan);
        $opaque = $renderer->indexedColorKeyMaskSamplePreview(3, $plan);

        $t->same('Indexed', $plan['source_color_space']);
        $t->same(true, $plan['uses_indexed_color_space']);
        $t->same([
            'present' => true,
            'ranges' => [
                ['min' => 1, 'max' => 1],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'source' => 'explicit',
            'compares_before_decode' => true,
            'transparent_when_all_components_match' => true,
        ], $plan['color_key_mask']);
        $t->same(true, $plan['color_key_mask_applied_before_rgb']);
        $t->same(false, $plan['color_key_mask_component_mismatch']);
        $t->same('color_key_mask_composited_to_rgb_preview', $plan['alpha_output_mode']);
        $t->same([
            'indexed_color_space_palette_before_rgb_conversion',
            'image_decode_applied_before_rgb_conversion',
            'image_decode_inverts_components_before_rgb',
            'color_key_mask_applied_before_rgb_conversion',
            'color_key_mask_compares_raw_samples_before_decode',
        ], $plan['notes']);

        $t->same([1.0], $transparent['raw_sample']);
        $t->same(true, $transparent['matches_color_key']);
        $t->same(0.0, $transparent['alpha']);
        $t->same(2.0, $transparent['decoded_index']);
        $t->same(2, $transparent['palette_index']);
        $t->same([0.0, 1.0, 0.0], $transparent['base_components']);
        $t->same(true, $transparent['decode_applied_after_color_key']);
        $t->same(true, $transparent['palette_transfer_applied_after_color_key']);
        $t->same('RGB', $transparent['output_color_mode']);

        $t->same(false, $opaque['matches_color_key']);
        $t->same(1.0, $opaque['alpha']);
        $t->same(0.0, $opaque['decoded_index']);
        $t->same(0, $opaque['palette_index']);
        $t->same([0.0, 0.0, 0.0], $opaque['base_components']);
    },
    'carries Indexed ColorKey alpha through decoded stream rows before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x1c";
        $compressedImage = gzcompress($imageBytes);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to compress Indexed ColorKey stream fixture.');
        }

        $imageObject = "<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/Indexed /DeviceRGB 3 <000000FF000000FF000000FF>] /BitsPerComponent 2 /Filter /FlateDecode /Decode [3 0] /Mask [1 1] /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";
        $preview = $renderer->indexedImageStreamPreviewRows($imageObject, [], 3);

        $t->same('Indexed', $preview['source_color_space']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same('color_key_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same([
            'indexed_image_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $first = $preview['pixels'][0];
        $t->same(0.0, $first['raw_sample']);
        $t->same(3.0, $first['decoded_index']);
        $t->same(3, $first['palette_index']);
        $t->same([0.0, 0.0, 1.0], $first['base_components']);
        $t->same(false, $first['matches_color_key']);
        $t->same(1.0, $first['color_key_alpha']);
        $t->same(true, $first['palette_transfer_applied_after_color_key']);

        $second = $preview['pixels'][1];
        $t->same(1.0, $second['raw_sample']);
        $t->same(2.0, $second['decoded_index']);
        $t->same(2, $second['palette_index']);
        $t->same([0.0, 1.0, 0.0], $second['base_components']);
        $t->same(true, $second['matches_color_key']);
        $t->same(0.0, $second['color_key_alpha']);
        $t->same(true, $second['decode_applied_after_color_key']);

        $third = $preview['pixels'][2];
        $t->same(3.0, $third['raw_sample']);
        $t->same(0.0, $third['decoded_index']);
        $t->same(0, $third['palette_index']);
        $t->same([0.0, 0.0, 0.0], $third['base_components']);
        $t->same(false, $third['matches_color_key']);
        $t->same(1.0, $third['color_key_alpha']);
    },
];
