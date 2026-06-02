<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$calibratedJbig2ImageFixture = static function (): array {
    $maskBytes = "\x00\x80\xff";
    $compressedMask = gzcompress($maskBytes);
    if (!is_string($compressedMask)) {
        throw new RuntimeException('Unable to compress calibrated JBIG2 soft-mask fixture.');
    }

    $imagePayload = "\x97JB2\r\n\x1a\nBT /F1 12 Tf 72 690 Td (Calibrated JBIG2 Noise) Tj ET";
    $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
    $globalsBytes = 'GBLS-JB2';
    $objects = [
        81 => '<< /WhitePoint [0.9505 1 1.089] /BlackPoint [0.01 0.02 0.03] /Gamma [2.2 2.1 2.0] /Matrix [0.4 0.3 0.2 0.1 0.8 0.1 0.2 0.1 0.7] >>',
        82 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
        90 => "<< /Length " . strlen($globalsBytes) . " >>\nstream\n{$globalsBytes}\nendstream",
    ];
    $imageObject = "<< /Subtype /Image /Filter /JBIG2Decode /DecodeParms << /JBIG2Globals 90 0 R >> /Width 3 /Height 1 /ColorSpace [/CalRGB 81 0 R] /BitsPerComponent 8 /SMask 82 0 R /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream";

    return [$imageObject, $objects, $imagePayload, $maskBytes, $globalsBytes, $maskHex];
};

return [
    'keeps calibrated JBIG2 image streams review-only while decoding current soft masks before RGB preview' => static function (TestRunner $t) use ($calibratedJbig2ImageFixture): void {
        [$imageObject, $objects, $imagePayload, $maskBytes, $globalsBytes, $maskHex] = $calibratedJbig2ImageFixture();
        $renderer = new PdfImageRenderer();

        $preview = $renderer->calibratedImageStreamPreviewRows($imageObject, $objects, 3);

        $t->same('CalRGB', $preview['source_color_space']);
        $t->same(3, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(3, $preview['components_per_pixel']);
        $t->same(8, $preview['bits_per_component']);
        $t->same(3, $preview['expected_pixel_count']);
        $t->same(0, $preview['preview_pixel_count']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(false, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same([], $preview['pixels']);

        $t->same([
            'preview_only_filters' => ['JBIG2Decode'],
            'jbig2_globals_present' => true,
            'native_raster_decode' => false,
        ], $preview['image_filter_boundary']);
        $t->same([
            [
                'filter' => 'JBIG2Decode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'JBIG2Decode',
                    'jbig2_globals_present' => true,
                    'jbig2_globals_source' => 'object_ref',
                    'jbig2_globals_object' => 90,
                    'jbig2_globals_length' => strlen($globalsBytes),
                    'jbig2_globals_sha256' => hash('sha256', $globalsBytes),
                    'jbig2_globals_preview_hex' => strtoupper(bin2hex($globalsBytes)),
                ],
            ],
        ], $preview['image_filter_details']);
        $t->same([
            'filters' => ['JBIG2Decode'],
            'preview_only_filters' => ['JBIG2Decode'],
            'unsupported_filters' => ['JBIG2Decode'],
            'raw_length' => strlen($imagePayload),
            'decoded_length' => null,
            'decoded_sha256' => null,
            'decoded_preview_hex' => null,
            'decoded_with_current_filters' => false,
            'decode_failed' => false,
        ], $preview['image_stream']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($maskHex),
            'decoded_length' => 3,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => '0080FF',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['soft_mask_stream']);

        $t->same([
            'family' => 'CalRGB',
            'dictionary_source' => 'object_ref',
            'dictionary_object' => 81,
            'white_point' => [0.9505, 1.0, 1.089],
            'black_point' => [0.01, 0.02, 0.03],
            'gamma' => [2.2, 2.1, 2.0],
            'matrix' => [0.4, 0.3, 0.2, 0.1, 0.8, 0.1, 0.2, 0.1, 0.7],
            'range' => null,
            'default_decode' => [0.0, 1.0, 0.0, 1.0, 0.0, 1.0],
        ], $preview['calibrated_color_space']);
        $t->same('default-calibrated', $preview['image_decode']['source']);
        $t->same(true, $preview['soft_mask']['decode_inverted']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same([
            'calibrated_image_stream_preview_only_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $notes = implode(',', $preview['notes']);
        $t->contains('calrgb_calibrated_color_space_review_before_rgb_conversion', $notes);
        $t->contains('jbig2_image_filter_review_only', $notes);
        $t->contains('calibrated_image_stream_preview_only_before_rgb_conversion', $notes);
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', $notes);
        $t->same(1.0, $renderer->softMaskSampleOpacity(0, $preview['soft_mask']));
        $t->true(abs($renderer->softMaskSampleOpacity(128, $preview['soft_mask']) - (1.0 - (128 / 255))) < 0.000001);
    },
    'decodes native calibrated stream samples and matching soft-mask alpha rows' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x00\x80\xff";
        $maskBytes = "\xff\x80\x00";
        $compressedImage = gzcompress($imageBytes);
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress calibrated stream fixtures.');
        }

        $imageObject = "<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/CalGray << /WhitePoint [1 1 1] /Gamma 2.4 >>] /BitsPerComponent 8 /Filter /FlateDecode /SMask 12 0 R /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";
        $objects = [
            12 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($compressedMask) . " >>\nstream\n{$compressedMask}\nendstream",
        ];

        $preview = $renderer->calibratedImageStreamPreviewRows($imageObject, $objects, 3);

        $t->same('CalGray', $preview['source_color_space']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same([
            'calibrated_image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);

        $first = $preview['pixels'][0];
        $t->same([0.0], $first['raw_sample']);
        $t->same([0.0], $first['decoded_components']);
        $t->same(['gray' => 0.0], $first['calibrated_components']);
        $t->same(255.0, $first['soft_mask_sample']);
        $t->same(1.0, $first['soft_mask_alpha']);

        $second = $preview['pixels'][1];
        $t->same([128.0], $second['raw_sample']);
        $t->true(abs($second['decoded_components'][0] - (128 / 255)) < 0.000001);
        $t->true(abs($second['calibrated_components']['gray'] - (128 / 255)) < 0.000001);
        $t->same(128.0, $second['soft_mask_sample']);
        $t->true(abs((float) $second['soft_mask_alpha'] - (128 / 255)) < 0.000001);

        $third = $preview['pixels'][2];
        $t->same([255.0], $third['raw_sample']);
        $t->same([1.0], $third['decoded_components']);
        $t->same(['gray' => 1.0], $third['calibrated_components']);
        $t->same(0.0, $third['soft_mask_sample']);
        $t->same(0.0, $third['soft_mask_alpha']);
        $t->same('RGB', $preview['output_color_mode']);

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->calibratedImageStreamPreviewRows(
                "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 1 >>\nstream\nX\nendstream"
            )
        );
    },
];
