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
];
