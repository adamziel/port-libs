<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfImageRenderer;

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
];
