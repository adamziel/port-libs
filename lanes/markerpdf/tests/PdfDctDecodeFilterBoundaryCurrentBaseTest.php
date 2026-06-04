<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'marks DCTDecode image filters review-only before RGB preview metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /DCTDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /DecodeParms << /ColorTransform 1 >> >>'
        );

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'DCTDecode',
                'preview_only' => true,
                'decode_parms' => ['type' => 'DCTDecode'],
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'preview_only_filters' => ['DCTDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same('RGB', $plan['output_color_mode']);
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));
    },
    'keeps DCT alias inline image review metadata out of native raster decode' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "\xff\xd8\xff\xe0JFIF\0 inline DCT payload EI BT /F1 12 Tf (leak) Tj ET \xff\xd9";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS /RGB /BPC 8 /F /DCT',
            $payload
        );

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same([
            'preview_only_filters' => ['DCTDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(['DCTDecode'], $plan['inline_image']['review_only_filters']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_dct_image_filter_review_only', implode(',', $plan['notes']));
    },
];
