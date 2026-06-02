<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'bundles named DeviceN JPX color space and soft-mask transfer review before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $jpxPayload = "\xff\x4fNamed DeviceN JPX payload stays preview-only\xff\xd9";
        $groupPayload = "q /ImSpot Do Q\n";
        $objects = [
            'Resources' => '70 0 R',
            70 => '<< /ColorSpace << /CSspot [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /CSstale /DeviceRGB >> >>',
            60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
            91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 36 18] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
            95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.2] /C1 [0.8] /N 2 >>',
            96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.25] /TR 95 0 R >>',
        ];
        $imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace /CSspot /BitsPerComponent 8 /Decode [1 0 0 1] /SMask 96 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";

        $preview = $renderer->imageRenderingColorSpaceSoftMaskTransferBundle($imageObject, $objects, 2);

        $t->same('DeviceN', $preview['source_color_space']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(0, $preview['preview_pixel_count']);
        $t->same(['JPXDecode'], $preview['image_stream']['preview_only_filters']);
        $t->same(null, $preview['soft_mask_stream']);
        $t->same('DeviceCMYK', $preview['alternate_color_space']);
        $t->same(60, $preview['tint_transform_object']);
        $t->same(4, $preview['tint_transform_function_type']);
        $t->same(95, $preview['soft_mask_transfer_function']['object']);
        $t->same(true, $preview['soft_mask_transfer_function_applied_before_rgb']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same([
            'source' => 'marker.pdf.images.render_image_rgb',
            'selected_preview' => 'alternate_colorant',
            'source_color_space' => 'DeviceN',
            'color_space_resource_name' => 'CSspot',
            'color_space_resource_source' => 'Resources.ColorSpace',
            'color_space_resolved_from_resources' => true,
            'image_stream_decoded' => false,
            'image_stream_review_only' => true,
            'soft_mask_present' => true,
            'soft_mask_source_object' => 96,
            'soft_mask_uses_current_object_map' => true,
            'soft_mask_stream_decoded' => false,
            'soft_mask_transfer_present' => true,
            'soft_mask_transfer_applied_before_rgb' => true,
            'soft_mask_transfer_sample_supported' => true,
            'output_color_mode' => 'RGB',
            'alpha_output_mode' => 'soft_mask_composited_to_rgb_preview',
            'executes_python_or_models' => false,
            'executes_pypdfium_or_pil' => false,
            'executes_external_pdf_tools' => false,
        ], $preview['render_bundle']);

        $notes = implode(',', $preview['notes']);
        $t->contains('image_color_space_resolved_from_current_resources', $notes);
        $t->contains('soft_mask_transfer_function_applied_before_rgb_conversion', $notes);
        $t->contains('image_rendering_colorspace_softmask_transfer_bundle_currentbase', $notes);
        $t->contains('image_rendering_bundle_dispatches_alternate_colorant_preview', $notes);
        $t->contains('image_rendering_bundle_keeps_preview_only_image_stream_review_only', $notes);
        $t->contains('image_rendering_bundle_preserves_soft_mask_transfer_function', $notes);
        $t->contains('image_rendering_bundle_applies_soft_mask_transfer_before_rgb_conversion', $notes);
    },
    'bundles current named Separation stream and decoded soft-mask rows before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x00\xff";
        $maskBytes = "\xff\x00";
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress image rendering bundle fixtures.');
        }

        $objects = [
            'Resources' => '70 0 R',
            70 => '<< /ColorSpace << /CSgold [/Separation /Spot#20Gold /DeviceCMYK 81 0 R] /CSstale /DeviceRGB >> >>',
            81 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0] /C1 [0 0.12 0.8 0] /N 1 >>',
            82 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($compressedMask) . " >>\nstream\n{$compressedMask}\nendstream",
        ];
        $imageObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace /CSgold /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /SMask 82 0 R /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";

        $preview = $renderer->imageRenderingColorSpaceSoftMaskTransferBundle($imageObject, $objects, 2);

        $t->same('Separation', $preview['source_color_space']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(2, $preview['preview_pixel_count']);
        $t->same(['FlateDecode'], $preview['image_stream']['filters']);
        $t->same(['FlateDecode'], $preview['soft_mask_stream']['filters']);
        $t->same(82, $preview['soft_mask_filter_boundary']['source_object']);
        $t->same(true, $preview['soft_mask_filter_boundary']['uses_current_object_map']);
        $t->same([
            'image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $first = $preview['pixels'][0];
        $t->same([0.0], $first['raw_sample']);
        $t->same(['Spot Gold' => 1.0], $first['colorant_tints']);
        $t->same(255.0, $first['soft_mask_sample']);
        $t->same(0.0, $first['soft_mask_alpha']);

        $second = $preview['pixels'][1];
        $t->same([255.0], $second['raw_sample']);
        $t->same(['Spot Gold' => 0.0], $second['colorant_tints']);
        $t->same(0.0, $second['soft_mask_sample']);
        $t->same(1.0, $second['soft_mask_alpha']);

        $t->same('alternate_colorant', $preview['render_bundle']['selected_preview']);
        $t->same('CSgold', $preview['render_bundle']['color_space_resource_name']);
        $t->same('Resources.ColorSpace', $preview['render_bundle']['color_space_resource_source']);
        $t->same(true, $preview['render_bundle']['image_stream_decoded']);
        $t->same(true, $preview['render_bundle']['soft_mask_stream_decoded']);
        $t->same(false, $preview['render_bundle']['soft_mask_transfer_present']);
        $t->same(false, $preview['render_bundle']['executes_python_or_models']);
        $t->same(false, $preview['render_bundle']['executes_pypdfium_or_pil']);
        $t->same(false, $preview['render_bundle']['executes_external_pdf_tools']);

        $notes = implode(',', $preview['notes']);
        $t->contains('image_rendering_bundle_resolves_current_color_space_resource', $notes);
        $t->contains('image_rendering_bundle_decodes_image_stream_before_rgb_conversion', $notes);
        $t->contains('image_rendering_bundle_decodes_soft_mask_stream_before_rgb_conversion', $notes);
    },
];
