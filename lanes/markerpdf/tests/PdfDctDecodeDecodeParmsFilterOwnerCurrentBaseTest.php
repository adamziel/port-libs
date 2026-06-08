<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'does not apply DCTDecode ColorTransform DecodeParms when the image filter stack has no DCT owner' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $jpegBytes = "\xff\xd8\xff\xd9";
        $decodeParms = '<< /ColorTransform 1 >>';

        $flatePlan = $renderer->dctDecodeImageColorPlan(
            "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /DecodeParms {$decodeParms} >>",
            $jpegBytes
        );
        $nullFilterPlan = $renderer->dctDecodeImageColorPlan(
            "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter null /DecodeParms {$decodeParms} >>",
            $jpegBytes
        );
        $missingFilterPlan = $renderer->dctDecodeImageColorPlan(
            "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /DecodeParms {$decodeParms} >>",
            $jpegBytes
        );
        $dctPlan = $renderer->dctDecodeImageColorPlan(
            "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms {$decodeParms} >>",
            $jpegBytes
        );

        $t->same('FlateDecode', $flatePlan['filter']);
        foreach ([$flatePlan, $nullFilterPlan, $missingFilterPlan] as $plan) {
            $t->same(null, $plan['decode_parms_color_transform']);
            $t->same(true, $plan['decode_parms_color_transform_valid']);
            $t->same(false, $plan['decode_parms_color_transform_ignored']);
            $t->same(0, $plan['effective_color_transform']);
            $t->same(false, $plan['uses_ycck_transform']);
            $t->same(['render_rgb_preview_from_cmyk'], $plan['notes']);
            $t->same(['red' => 255, 'green' => 0, 'blue' => 0], $renderer->dctDecodeSampleToRgb([0, 255, 255, 0], $plan));
        }

        $t->same('DCTDecode', $dctPlan['filter']);
        $t->same(1, $dctPlan['decode_parms_color_transform']);
        $t->same(true, $dctPlan['decode_parms_color_transform_valid']);
        $t->same(false, $dctPlan['decode_parms_color_transform_ignored']);
        $t->same(1, $dctPlan['effective_color_transform']);
        $t->same(true, $dctPlan['uses_ycck_transform']);
        $t->same(['render_rgb_preview_from_cmyk', 'apply_ycck_to_cmyk_before_rgb'], $dctPlan['notes']);
    },
];
