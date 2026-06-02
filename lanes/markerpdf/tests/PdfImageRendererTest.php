<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfImageRenderer;

$dctJpeg = static function (?int $adobeTransform, int $components = 4): string {
    $segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
    $bytes = "\xff\xd8";
    if ($adobeTransform !== null) {
        $bytes .= $segment(0xee, 'Adobe' . pack('n', 100) . pack('n', 0) . pack('n', 0) . chr($adobeTransform));
    }

    $sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . chr($components);
    for ($component = 1; $component <= $components; $component++) {
        $sofPayload .= chr($component) . "\x11\x00";
    }

    return $bytes . $segment(0xc0, $sofPayload) . "\xff\xd9";
};

return [
    'maps upstream render_image dpi scale and RGB/no-annotation output boundary' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();

        $plan = $renderer->renderBboxImagePlan(
            [0.0, 0.0, 600.0, 800.0],
            [60.0, 100.0, 280.0, 220.0],
            (float) (new MarkerSettings())->get('IMAGE_DPI'),
            ['width' => 1200, 'height' => 1600]
        );

        $t->true(abs((96.0 / 72.0) - $plan['scale']) < 0.000001);
        $t->same(false, $plan['draw_annots']);
        $t->same('RGB', $plan['color_mode']);
        $t->same([0.0, 0.0, 1200.0, 1600.0], $plan['rendered_image_bbox']);
    },
    'maps render_bbox_image crop scaling from PDF page space to rendered pixels' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();

        $crop = $renderer->cropBboxForRenderedImage(
            [0.0, 0.0, 600.0, 800.0],
            [1200, 1600],
            [60.0, 100.0, 280.0, 220.0]
        );

        $t->same([120.0, 200.0, 560.0, 440.0], $crop);
    },
    'derives rendered image dimensions from PDF points and dpi without pypdfium' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();

        $t->same(['width' => 1224, 'height' => 1584], $renderer->renderedImageSize([0.0, 0.0, 612.0, 792.0], 144.0));
        $t->same(
            [144.0, 192.0, 672.0, 528.0],
            $renderer->renderBboxImagePlan([0.0, 0.0, 612.0, 792.0], [72.0, 96.0, 336.0, 264.0], 144.0)['crop_bbox']
        );
    },
    'rejects invalid dpi, image sizes, and bbox inputs before raster handoff' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();

        $t->throws(InvalidArgumentException::class, static fn (): float => $renderer->renderScale(0.0));
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->cropBboxForRenderedImage([0.0, 0.0, 600.0, 800.0], [0, 1600], [1.0, 2.0, 3.0, 4.0]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->renderedImageSize([0.0, 0.0, 600.0], 96.0));
    },
    'drives a WordPress media crop review payload for extracted PDF figures' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->renderBboxImagePlan(
            [0.0, 0.0, 600.0, 800.0],
            [60.0, 100.0, 280.0, 220.0],
            96.0,
            ['width' => 1200, 'height' => 1600]
        );

        $html = "<!-- wp:image -->\n";
        $html .= '<figure class="wp-block-image" data-marker-crop="' . implode(',', $plan['crop_bbox']) . '">';
        $html .= '<img src="0_image_0.png" alt="Extracted PDF figure"/></figure>' . "\n";
        $html .= "<!-- /wp:image -->\n";

        $t->contains('data-marker-crop="120,200,560,440"', $html);
        $t->contains('0_image_0.png', $html);
    },
    'plans ICCBased image color profile and soft mask metadata before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            10 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 12 >>\nstream\nICC-PROFILE\nendstream",
            11 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Matte [1 0.5 0] /Interpolate true /Length 4 >>\nstream\nMASK\nendstream",
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace [/ICCBased 10 0 R] /BitsPerComponent 8 /SMask 11 0 R >>',
            $objects
        );

        $t->same('ICCBased', $plan['source_color_space']);
        $t->same(3, $plan['components']);
        $t->same(8, $plan['bits_per_component']);
        $t->same(true, $plan['uses_icc_profile']);
        $t->same([
            'components' => 3,
            'alternate_color_space' => 'DeviceRGB',
            'range' => [0.0, 1.0, 0.0, 1.0, 0.0, 1.0],
            'length' => 12,
        ], $plan['icc_profile']);
        $t->same([
            'present' => true,
            'subtype' => 'Image',
            'width' => 2,
            'height' => 2,
            'color_space' => 'DeviceGray',
            'components' => 1,
            'bits_per_component' => 8,
            'decode' => [
                'ranges' => [
                    ['min' => 0.0, 'max' => 1.0],
                ],
                'component_count' => 1,
                'expected_components' => 1,
                'valid_for_components' => true,
                'identity' => true,
                'inverted_components' => [],
                'source' => 'default',
            ],
            'opacity_for_zero' => 0.0,
            'opacity_for_max' => 1.0,
            'decode_inverted' => false,
            'decode_component_mismatch' => false,
            'matte' => [1.0, 0.5, 0.0],
            'interpolate' => true,
        ], $plan['soft_mask']);
        $t->same(true, $plan['soft_mask_applied_before_rgb']);
        $t->same(true, $plan['matte_unblending_required']);
        $t->same('RGB', $plan['output_color_mode']);
        $t->same('soft_mask_composited_to_rgb_preview', $plan['alpha_output_mode']);
        $t->same([
            'icc_profile_color_space',
            'soft_mask_applied_before_rgb_conversion',
            'soft_mask_decode_applied_before_rgb_conversion',
            'soft_mask_matte_unblend_before_rgb',
        ], $plan['notes']);
    },
    'handles direct ICCBased profile dictionaries and explicit soft-mask none' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 1 /Height 1 /CS [/ICCBased << /N 4 /Alternate /DeviceCMYK /Range [0 1 0 1 0 1 0 1] /Length 9 >>] /BPC 16 /SMask /None >>'
        );

        $t->same('ICCBased', $plan['source_color_space']);
        $t->same(4, $plan['components']);
        $t->same(16, $plan['bits_per_component']);
        $t->same(true, $plan['uses_icc_profile']);
        $t->same([
            'components' => 4,
            'alternate_color_space' => 'DeviceCMYK',
            'range' => [0.0, 1.0, 0.0, 1.0, 0.0, 1.0, 0.0, 1.0],
            'length' => 9,
        ], $plan['icc_profile']);
        $t->same([
            'present' => false,
            'subtype' => null,
            'width' => null,
            'height' => null,
            'color_space' => null,
            'components' => null,
            'bits_per_component' => null,
            'decode' => null,
            'opacity_for_zero' => null,
            'opacity_for_max' => null,
            'decode_inverted' => false,
            'decode_component_mismatch' => false,
            'matte' => null,
            'interpolate' => null,
        ], $plan['soft_mask']);
        $t->same(false, $plan['soft_mask_applied_before_rgb']);
        $t->same(false, $plan['matte_unblending_required']);
        $t->same('opaque_rgb_preview', $plan['alpha_output_mode']);
        $t->same(['icc_profile_color_space', 'soft_mask_none'], $plan['notes']);
    },
    'plans image Decode arrays before RGB preview sample mapping' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Decode [1 0 0 1 0 1] >>'
        );

        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 0.0, 'max' => 1.0],
            ],
            'component_count' => 3,
            'expected_components' => 3,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $plan['image_decode']);
        $t->same(true, $plan['image_decode_applied_before_rgb']);
        $t->same(false, $plan['image_decode_component_mismatch']);

        $decoded = $renderer->imageSampleDecodeValues([0, 128, 255], $plan['image_decode'], $plan['bits_per_component']);
        $t->same(1.0, $decoded[0]);
        $t->true(abs($decoded[1] - (128 / 255)) < 0.000001);
        $t->same(1.0, $decoded[2]);
        $t->same([
            'image_decode_applied_before_rgb_conversion',
            'image_decode_inverts_components_before_rgb',
        ], $plan['notes']);

        $mismatch = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Decode [0 1 0 1] >>'
        );

        $t->same(2, $mismatch['image_decode']['component_count']);
        $t->same(4, $mismatch['image_decode']['expected_components']);
        $t->same(false, $mismatch['image_decode']['valid_for_components']);
        $t->same(false, $mismatch['image_decode_applied_before_rgb']);
        $t->same(true, $mismatch['image_decode_component_mismatch']);
        $t->contains('image_decode_component_mismatch', implode(',', $mismatch['notes']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->imageSampleDecodeValues([0, 1, 2, 3], $mismatch['image_decode']));
    },
    'plans ImageMask stencil Decode opacity before RGB preview compositing' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 2 /Height 1 /ImageMask true /Decode [1 0] >>'
        );

        $t->same([
            'present' => true,
            'width' => 2,
            'height' => 1,
            'bits_per_component' => 1,
            'decode' => [
                'ranges' => [
                    ['min' => 1.0, 'max' => 0.0],
                ],
                'component_count' => 1,
                'expected_components' => 1,
                'valid_for_components' => true,
                'identity' => false,
                'inverted_components' => [0],
                'source' => 'explicit',
            ],
            'opacity_for_zero' => 1.0,
            'opacity_for_one' => 0.0,
            'inverted' => true,
        ], $plan['image_mask']);
        $t->same(true, $plan['image_mask_applied_before_rgb']);
        $t->same('image_mask_composited_to_rgb_preview', $plan['alpha_output_mode']);
        $t->same(1.0, $renderer->imageMaskSampleOpacity(0, $plan['image_mask']));
        $t->same(0.0, $renderer->imageMaskSampleOpacity(1, $plan['image_mask']));
        $t->same([
            'image_decode_applied_before_rgb_conversion',
            'image_decode_inverts_components_before_rgb',
            'image_mask_stencil_applied_before_rgb_conversion',
            'image_mask_decode_inverts_stencil',
        ], $plan['notes']);

        $default = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 1 /Height 1 /ImageMask true >>'
        );

        $t->same('default', $default['image_mask']['decode']['source']);
        $t->same(0.0, $renderer->imageMaskSampleOpacity(0, $default['image_mask']));
        $t->same(1.0, $renderer->imageMaskSampleOpacity(1, $default['image_mask']));
    },
    'plans soft-mask Decode opacity before RGB preview compositing' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            11 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode 12 0 R /Matte [0.2 0.3 0.4] /Length 2 >>\nstream\nMASK\nendstream",
            12 => '[1 0]',
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 11 0 R >>',
            $objects
        );

        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $plan['soft_mask']['decode']);
        $t->same(true, $plan['soft_mask_decode_applied_before_rgb']);
        $t->same(false, $plan['soft_mask_decode_component_mismatch']);
        $t->same(1.0, $plan['soft_mask']['opacity_for_zero']);
        $t->same(0.0, $plan['soft_mask']['opacity_for_max']);
        $t->same(true, $plan['soft_mask']['decode_inverted']);
        $t->same(1.0, $renderer->softMaskSampleOpacity(0, $plan['soft_mask']));
        $t->true(abs($renderer->softMaskSampleOpacity(128, $plan['soft_mask']) - (1.0 - (128 / 255))) < 0.000001);
        $t->same(0.0, $renderer->softMaskSampleOpacity(255, $plan['soft_mask']));
        $t->same([
            'soft_mask_applied_before_rgb_conversion',
            'soft_mask_decode_applied_before_rgb_conversion',
            'soft_mask_decode_inverts_alpha',
            'soft_mask_matte_unblend_before_rgb',
        ], $plan['notes']);

        $mismatch = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 21 0 R >>',
            [
                21 => '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [0 1 0 1] >>',
            ]
        );

        $t->same(false, $mismatch['soft_mask_decode_applied_before_rgb']);
        $t->same(true, $mismatch['soft_mask_decode_component_mismatch']);
        $t->same(false, $mismatch['soft_mask']['decode']['valid_for_components']);
        $t->contains('soft_mask_decode_component_mismatch', implode(',', $mismatch['notes']));
        $t->throws(InvalidArgumentException::class, static fn (): float => $renderer->softMaskSampleOpacity(128, $mismatch['soft_mask']));
    },
    'decodes soft-mask XObject stream filters from the current object map before RGB preview compositing' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $maskBytes = "\x00\x7f\xff";
        $compressed = gzcompress($maskBytes);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress soft-mask fixture.');
        }

        $asciiHex = strtoupper(bin2hex($compressed)) . '>';
        $objects = [
            31 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null 32 0 R] /Decode [0 1] /Length " . strlen($asciiHex) . " >>\nstream\n{$asciiHex}\nendstream",
            32 => '<< /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>',
            99 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 8 >>\nstream\nSTALEMSK\nendstream",
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 31 0 R >>',
            $objects
        );

        $t->same([
            'present' => true,
            'source_object' => 31,
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($asciiHex),
            'decoded_length' => 3,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => '007FFF',
            'decoded_sample_bytes' => [0, 127, 255],
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
            'uses_current_object_map' => true,
        ], $plan['soft_mask_filter_boundary']);
        $t->same(1.0, $renderer->softMaskSampleOpacity(255, $plan['soft_mask']));
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', implode(',', $plan['notes']));

        $unsupported = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 41 0 R >>',
            [
                41 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /JPXDecode /Length 3 >>\nstream\nJPX\nendstream",
            ]
        );

        $t->same(['JPXDecode'], $unsupported['soft_mask_filter_boundary']['preview_only_filters']);
        $t->same(['JPXDecode'], $unsupported['soft_mask_filter_boundary']['unsupported_filters']);
        $t->same(null, $unsupported['soft_mask_filter_boundary']['decoded_length']);
        $t->same(false, $unsupported['soft_mask_filter_boundary']['decoded_with_current_filters']);
        $t->contains('soft_mask_stream_filter_preview_only', implode(',', $unsupported['notes']));
    },
    'plans Indexed ICCBased JBIG2 image palette and soft-mask boundary before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            20 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 11 >>\nstream\nICC-PROFILE\nendstream",
            21 => '[/ICCBased 20 0 R]',
            22 => '<00000080FF40010203>',
            23 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 3 >>\nstream\nMSK\nendstream",
            24 => "<< /Length 4 >>\nstream\nGBLS\nendstream",
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /JBIG2Decode /DecodeParms << /JBIG2Globals 24 0 R >> /Width 3 /Height 1 /ColorSpace [/Indexed 21 0 R 2 22 0 R] /BitsPerComponent 2 /Decode [0 2] /SMask 23 0 R >>',
            $objects
        );

        $t->same('Indexed', $plan['source_color_space']);
        $t->same(1, $plan['components']);
        $t->same(2, $plan['bits_per_component']);
        $t->same(['JBIG2Decode'], $plan['image_filters']);
        $t->same([
            'preview_only_filters' => ['JBIG2Decode'],
            'jbig2_globals_present' => true,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same(true, $plan['uses_indexed_color_space']);
        $t->same(true, $plan['uses_icc_profile']);
        $t->same([
            'base_color_space' => 'ICCBased',
            'base_components' => 3,
            'base_uses_icc_profile' => true,
            'base_icc_profile' => [
                'components' => 3,
                'alternate_color_space' => 'DeviceRGB',
                'range' => [0.0, 1.0, 0.0, 1.0, 0.0, 1.0],
                'length' => 11,
            ],
            'high_value' => 2,
            'lookup_source' => 'hex_string',
            'lookup_length' => 9,
            'expected_lookup_length' => 9,
            'lookup_length_matches' => true,
            'lookup_entry_count' => 3,
            'lookup_preview_hex' => '00000080FF40010203',
            'lookup_bytes' => [0, 0, 0, 128, 255, 64, 1, 2, 3],
        ], $plan['indexed_color_space']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 2.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [],
            'source' => 'explicit',
        ], $plan['image_decode']);
        $t->same([0.0], $renderer->imageSampleDecodeValues([0], $plan['image_decode'], $plan['bits_per_component']));
        $t->same([2.0], $renderer->imageSampleDecodeValues([3], $plan['image_decode'], $plan['bits_per_component']));
        $t->same([128 / 255, 1.0, 64 / 255], $renderer->indexedSampleToBaseComponents(1, $plan['indexed_color_space']));
        $t->same([1 / 255, 2 / 255, 3 / 255], $renderer->indexedSampleToBaseComponents(2, $plan['indexed_color_space']));
        $t->same(true, $plan['soft_mask_applied_before_rgb']);
        $t->same(true, $plan['soft_mask']['decode_inverted']);
        $t->same(1.0, $renderer->softMaskSampleOpacity(0, $plan['soft_mask']));
        $t->same(0.0, $renderer->softMaskSampleOpacity(255, $plan['soft_mask']));
        $t->same([
            'indexed_color_space_palette_before_rgb_conversion',
            'indexed_base_icc_profile_color_space',
            'icc_profile_color_space',
            'image_decode_applied_before_rgb_conversion',
            'jbig2_image_filter_review_only',
            'soft_mask_applied_before_rgb_conversion',
            'soft_mask_decode_applied_before_rgb_conversion',
            'soft_mask_decode_inverts_alpha',
        ], $plan['notes']);

        $mismatch = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /JBIG2Decode /Width 1 /Height 1 /ColorSpace [/Indexed /DeviceRGB 3 <000000FFFFFF>] /BitsPerComponent 2 >>'
        );

        $t->same(false, $mismatch['indexed_color_space']['lookup_length_matches']);
        $t->contains('indexed_lookup_length_mismatch', implode(',', $mismatch['notes']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->indexedSampleToBaseComponents(3, $mismatch['indexed_color_space']));
    },
    'applies Indexed default Decode and soft-mask alpha before RGB palette preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            41 => '<0000002040F0F08010>',
            42 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 2 >>\nstream\nMS\nendstream",
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/Indexed /DeviceRGB 2 41 0 R] /BitsPerComponent 2 /SMask 42 0 R >>',
            $objects
        );

        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 2.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [],
            'source' => 'default-indexed',
        ], $plan['image_decode']);
        $t->same(true, $plan['image_decode_applied_before_rgb']);
        $t->contains('indexed_color_space_palette_before_rgb_conversion', implode(',', $plan['notes']));
        $t->contains('soft_mask_decode_inverts_alpha', implode(',', $plan['notes']));

        $first = $renderer->indexedSamplePreview(0, $plan, 255);
        $last = $renderer->indexedSamplePreview(3, $plan, 0);

        $t->same(0.0, $first['decoded_index']);
        $t->same(0, $first['palette_index']);
        $t->same(false, $first['clamped_to_hival']);
        $t->same([0.0, 0.0, 0.0], $first['base_components']);
        $t->same(0.0, $first['soft_mask_alpha']);

        $t->same(2.0, $last['decoded_index']);
        $t->same(2, $last['palette_index']);
        $t->same(false, $last['clamped_to_hival']);
        $t->same([240 / 255, 128 / 255, 16 / 255], $last['base_components']);
        $t->same(1.0, $last['soft_mask_alpha']);
        $t->same('RGB', $last['output_color_mode']);

        $clamped = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace [/Indexed /DeviceRGB 2 <000000FFFFFF010203>] /BitsPerComponent 2 /Decode [-1 5] >>'
        );

        $clampedLow = $renderer->indexedSamplePreview(0, $clamped);
        $clampedHigh = $renderer->indexedSamplePreview(3, $clamped);

        $t->same(-1.0, $clampedLow['decoded_index']);
        $t->same(0, $clampedLow['palette_index']);
        $t->same(true, $clampedLow['clamped_to_hival']);
        $t->same([0.0, 0.0, 0.0], $clampedLow['base_components']);
        $t->same(5.0, $clampedHigh['decoded_index']);
        $t->same(2, $clampedHigh['palette_index']);
        $t->same(true, $clampedHigh['clamped_to_hival']);
        $t->same([1 / 255, 2 / 255, 3 / 255], $clampedHigh['base_components']);
        $t->same(null, $clampedHigh['soft_mask_alpha']);
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->indexedSamplePreview(1, ['source_color_space' => 'DeviceRGB']));
    },
    'plans Separation and DeviceN alternate color spaces with CCITT preview filters before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            30 => '[/ICCBased 31 0 R]',
            31 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 11 >>\nstream\nICC-PROFILE\nendstream",
            40 => '<< /FunctionType 2 /Domain [0 1] /C0 [0 0 0] /C1 [1 0.2 0] /N 1 >>',
            50 => '<< /K -1 /Columns 1728 /Rows 8 /BlackIs1 true /EncodedByteAlign true /DamagedRowsBeforeError 2 >>',
            60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 18 >>',
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter [/CCITTFaxDecode /ASCIIHexDecode] /DecodeParms [50 0 R null] /Width 1728 /Height 8 /ColorSpace [/Separation /PANTONE#20485#20C 30 0 R 40 0 R] /BitsPerComponent 1 /Decode [1 0] >>',
            $objects
        );

        $t->same('Separation', $plan['source_color_space']);
        $t->same(1, $plan['components']);
        $t->same(true, $plan['uses_alternate_color_space']);
        $t->same([
            'family' => 'Separation',
            'colorant_names' => ['PANTONE 485 C'],
            'alternate_color_space' => 'ICCBased',
            'alternate_components' => 3,
            'alternate_uses_icc_profile' => true,
            'tint_transform_source' => 'object_ref',
            'tint_transform_object' => 40,
            'tint_transform_function_type' => 2,
            'attributes_present' => false,
        ], $plan['alternate_color_space']);
        $t->same(true, $plan['uses_icc_profile']);
        $t->same([
            'components' => 3,
            'alternate_color_space' => 'DeviceRGB',
            'range' => [0.0, 1.0, 0.0, 1.0, 0.0, 1.0],
            'length' => 11,
        ], $plan['icc_profile']);
        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $plan['image_decode']);
        $t->same(['CCITTFaxDecode', 'ASCIIHexDecode'], $plan['image_filters']);
        $t->same([
            'preview_only_filters' => ['CCITTFaxDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 1728,
                    'rows' => 8,
                    'black_is_1' => true,
                    'encoded_byte_align' => true,
                    'end_of_line' => null,
                    'end_of_block' => null,
                    'damaged_rows_before_error' => 2,
                ],
            ],
            [
                'filter' => 'ASCIIHexDecode',
                'preview_only' => false,
                'decode_parms' => null,
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'separation_tint_transform_review_before_rgb_conversion',
            'alternate_icc_profile_color_space',
            'icc_profile_color_space',
            'image_decode_applied_before_rgb_conversion',
            'image_decode_inverts_components_before_rgb',
            'ccitt_fax_image_filter_review_only',
        ], $plan['notes']);
        $t->same(1.0, $renderer->imageSampleDecodeValues([0], $plan['image_decode'], $plan['bits_per_component'])[0]);
        $t->same(0.0, $renderer->imageSampleDecodeValues([1], $plan['image_decode'], $plan['bits_per_component'])[0]);

        $deviceN = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace [/DeviceN [/Cyan /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [0 1 1 0] >>',
            $objects
        );

        $t->same('DeviceN', $deviceN['source_color_space']);
        $t->same(2, $deviceN['components']);
        $t->same([
            'family' => 'DeviceN',
            'colorant_names' => ['Cyan', 'Spot Varnish'],
            'alternate_color_space' => 'DeviceCMYK',
            'alternate_components' => 4,
            'alternate_uses_icc_profile' => false,
            'tint_transform_source' => 'object_ref',
            'tint_transform_object' => 60,
            'tint_transform_function_type' => 4,
            'attributes_present' => true,
        ], $deviceN['alternate_color_space']);
        $t->same(['JPXDecode'], $deviceN['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $deviceN['image_filter_boundary']['native_raster_decode']);
        $t->same([0.0, 1.0], $renderer->imageSampleDecodeValues([0, 0], $deviceN['image_decode'], $deviceN['bits_per_component']));
        $t->same(['devicen_tint_transform_review_before_rgb_conversion', 'image_decode_applied_before_rgb_conversion', 'image_decode_inverts_components_before_rgb', 'jpx_image_filter_review_only'], $deviceN['notes']);
    },
    'plans DCTDecode CMYK Adobe transform before WordPress RGB image preview' => static function (TestRunner $t) use ($dctJpeg): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 /DecodeParms << /ColorTransform 0 >> >>',
            $dctJpeg(2)
        );

        $t->same('DCTDecode', $plan['filter']);
        $t->same('DeviceCMYK', $plan['source_color_space']);
        $t->same(4, $plan['components']);
        $t->same(8, $plan['bits_per_component']);
        $t->same(2, $plan['adobe_app14_transform']);
        $t->same(0, $plan['decode_parms_color_transform']);
        $t->same(2, $plan['effective_color_transform']);
        $t->same(true, $plan['adobe_marker_overrides_decode_parms']);
        $t->same(true, $plan['needs_cmyk_to_rgb']);
        $t->same(true, $plan['uses_ycck_transform']);
        $t->same('RGB', $plan['output_color_mode']);
        $t->same(
            ['adobe_app14_transform_overrides_decodeparms', 'render_rgb_preview_from_cmyk', 'apply_ycck_to_cmyk_before_rgb'],
            $plan['notes']
        );
        $t->same(['red' => 254, 'green' => 0, 'blue' => 0], $renderer->dctDecodeSampleToRgb([76, 85, 255, 0], $plan));

        $invertedPlan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCT /ColorSpace /DeviceCMYK /BitsPerComponent 8 >>',
            $dctJpeg(0)
        );

        $t->same('DCT', $invertedPlan['filter']);
        $t->same(0, $invertedPlan['adobe_app14_transform']);
        $t->same(0, $invertedPlan['effective_color_transform']);
        $t->same(false, $invertedPlan['uses_ycck_transform']);
        $t->same(['red' => 255, 'green' => 0, 'blue' => 0], $renderer->dctDecodeSampleToRgb([255, 0, 0, 255], $invertedPlan));
    },
    'uses DCTDecode DecodeParms and CMYK defaults when Adobe marker is absent' => static function (TestRunner $t) use ($dctJpeg): void {
        $renderer = new PdfImageRenderer();
        $decodeParmsPlan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 /DecodeParms << /ColorTransform 1 >> >>',
            $dctJpeg(null)
        );
        $defaultCmykPlan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 >>',
            $dctJpeg(null)
        );
        $defaultRgbPlan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCTDecode /ColorSpace /DeviceRGB /BitsPerComponent 8 >>',
            $dctJpeg(null, 3)
        );

        $t->same(null, $decodeParmsPlan['adobe_app14_transform']);
        $t->same(1, $decodeParmsPlan['decode_parms_color_transform']);
        $t->same(1, $decodeParmsPlan['effective_color_transform']);
        $t->same(true, $decodeParmsPlan['uses_ycck_transform']);
        $t->same(0, $defaultCmykPlan['effective_color_transform']);
        $t->same(false, $defaultCmykPlan['uses_ycck_transform']);
        $t->same(['red' => 255, 'green' => 0, 'blue' => 0], $renderer->dctDecodeSampleToRgb([0, 255, 255, 0], $defaultCmykPlan));
        $t->same(3, $defaultRgbPlan['components']);
        $t->same(1, $defaultRgbPlan['effective_color_transform']);
        $t->same(false, $defaultRgbPlan['needs_cmyk_to_rgb']);
        $t->throws(InvalidArgumentException::class, static fn (): array => $renderer->dctDecodeSampleToRgb([0, 255, 255], $defaultCmykPlan));
    },
    'applies DCTDecode CMYK Decode arrays before WordPress RGB image preview' => static function (TestRunner $t) use ($dctJpeg): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Decode [1 0 0 1 0 1 0 1] >>',
            $dctJpeg(null)
        );

        $t->true(array_key_exists('image_decode', $plan));
        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 0.0, 'max' => 1.0],
            ],
            'component_count' => 4,
            'expected_components' => 4,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $plan['image_decode']);
        $t->same(true, $plan['image_decode_applied_before_rgb']);
        $t->same(false, $plan['image_decode_component_mismatch']);
        $t->same(['red' => 0, 'green' => 255, 'blue' => 255], $renderer->dctDecodeSampleToRgb([0, 0, 0, 0], $plan));
        $t->same([
            'render_rgb_preview_from_cmyk',
            'image_decode_applied_before_rgb_conversion',
            'image_decode_inverts_components_before_rgb',
        ], $plan['notes']);

        $indirectPlan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Decode 71 0 R /DecodeParms << /ColorTransform 0 >> >>',
            $dctJpeg(2),
            [71 => '[1 0 0 1 0 1 0 1]']
        );

        $t->same(2, $indirectPlan['adobe_app14_transform']);
        $t->same(2, $indirectPlan['effective_color_transform']);
        $t->same(true, $indirectPlan['uses_ycck_transform']);
        $t->same(true, $indirectPlan['image_decode_applied_before_rgb']);
        $t->same(['red' => 1, 'green' => 0, 'blue' => 0], $renderer->dctDecodeSampleToRgb([76, 85, 255, 0], $indirectPlan));

        $mismatch = $renderer->dctDecodeImageColorPlan(
            '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Decode [1 0 0 1] >>',
            $dctJpeg(null)
        );

        $t->same(false, $mismatch['image_decode_applied_before_rgb']);
        $t->same(true, $mismatch['image_decode_component_mismatch']);
        $t->contains('image_decode_component_mismatch', implode(',', $mismatch['notes']));
        $t->same(['red' => 255, 'green' => 255, 'blue' => 255], $renderer->dctDecodeSampleToRgb([0, 0, 0, 0], $mismatch));
    },
];
