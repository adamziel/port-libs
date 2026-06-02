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
