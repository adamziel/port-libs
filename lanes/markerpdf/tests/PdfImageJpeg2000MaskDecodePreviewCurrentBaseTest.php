<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$jpxImageMaskObject = static function (string $dictionary, string $payload): string {
    return $dictionary . "\nstream\n{$payload}\nendstream";
};

return [
    'maps JPEG2000 ImageMask Decode samples before RGB preview without native JPX raster decode' => static function (TestRunner $t) use ($jpxImageMaskObject): void {
        $renderer = new PdfImageRenderer();
        $jpxPayload = "\xff\x4fJPX mask bytes BT /F1 12 Tf 72 700 Td (JPX Mask Noise) Tj ET\xff\xd9";
        $imageObject = $jpxImageMaskObject(
            '<< /Type /XObject /Subtype /Image /Width 4 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /JPXDecode /Decode [1 0] /Length ' . strlen($jpxPayload) . ' >>',
            $jpxPayload
        );

        $preview = $renderer->jpeg2000ImageMaskPreviewRows($imageObject, "\x50", [], 4);

        $t->same('ImageMask', $preview['source_color_space']);
        $t->same(4, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(1, $preview['components_per_pixel']);
        $t->same(1, $preview['bits_per_component']);
        $t->same(4, $preview['expected_pixel_count']);
        $t->same(4, $preview['preview_pixel_count']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(false, $preview['native_jpeg2000_decode']);
        $t->same(true, $preview['uses_supplied_jpeg2000_mask_samples']);
        $t->same(true, $preview['complete_mask_sample_data']);
        $t->same([
            'filters' => ['JPXDecode'],
            'preview_only_filters' => ['JPXDecode'],
            'unsupported_filters' => ['JPXDecode'],
            'raw_length' => strlen($jpxPayload),
            'decoded_length' => null,
            'decoded_sha256' => null,
            'decoded_preview_hex' => null,
            'decoded_with_current_filters' => false,
            'decode_failed' => false,
        ], $preview['image_stream']);
        $t->same([
            'preview_only_filters' => ['JPXDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $preview['image_filter_boundary']);
        $t->same([
            'present' => true,
            'width' => 4,
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
        ], $preview['image_mask']);
        $t->same(null, $preview['jpx_soft_mask_in_data']);
        $t->same([
            [
                'pixel_index' => 0,
                'x' => 0,
                'y' => 0,
                'raw_sample' => 0.0,
                'opacity' => 1.0,
            ],
            [
                'pixel_index' => 1,
                'x' => 1,
                'y' => 0,
                'raw_sample' => 1.0,
                'opacity' => 0.0,
            ],
            [
                'pixel_index' => 2,
                'x' => 2,
                'y' => 0,
                'raw_sample' => 0.0,
                'opacity' => 1.0,
            ],
            [
                'pixel_index' => 3,
                'x' => 3,
                'y' => 0,
                'raw_sample' => 1.0,
                'opacity' => 0.0,
            ],
        ], $preview['pixels']);
        $t->same([
            'jpeg2000_image_stream_review_only_before_rgb_conversion',
            'jpeg2000_image_mask_supplied_samples_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);
        $t->same('RGB', $preview['output_color_mode']);
        $t->same('image_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);

        $notes = implode(',', $preview['notes']);
        $t->contains('jpx_image_filter_review_only', $notes);
        $t->contains('image_mask_stencil_applied_before_rgb_conversion', $notes);
        $t->contains('image_mask_decode_inverts_stencil', $notes);
        $t->contains('jpeg2000_image_mask_decode_applied_before_rgb_conversion', $notes);
        $t->contains('jpeg2000_image_mask_supplied_samples_previewed_without_raster_decode', $notes);
    },
    'reports incomplete supplied JPEG2000 mask samples while preserving default Decode opacity' => static function (TestRunner $t) use ($jpxImageMaskObject): void {
        $renderer = new PdfImageRenderer();
        $jpxPayload = "\xff\x4fJPX short mask bytes\xff\xd9";
        $imageObject = $jpxImageMaskObject(
            '<< /Type /XObject /Subtype /Image /Width 10 /Height 1 /ImageMask true /Filter /JPXDecode /Length ' . strlen($jpxPayload) . ' >>',
            $jpxPayload
        );

        $preview = $renderer->jpeg2000ImageMaskPreviewRows($imageObject, "\x80", [], 10);

        $t->same(10, $preview['expected_pixel_count']);
        $t->same(8, $preview['preview_pixel_count']);
        $t->same(false, $preview['complete_mask_sample_data']);
        $t->same('default', $preview['image_mask']['decode']['source']);
        $t->same(1.0, $preview['pixels'][0]['raw_sample']);
        $t->same(1.0, $preview['pixels'][0]['opacity']);
        $t->same(0.0, $preview['pixels'][1]['raw_sample']);
        $t->same(0.0, $preview['pixels'][1]['opacity']);
        $t->contains('jpeg2000_image_mask_sample_data_incomplete', implode(',', $preview['stream_notes']));
        $t->contains('jpeg2000_image_mask_sample_data_incomplete', implode(',', $preview['notes']));
    },
    'rejects non-JPX, non-mask, and non-one-bit JPEG2000 mask preview boundaries' => static function (TestRunner $t) use ($jpxImageMaskObject): void {
        $renderer = new PdfImageRenderer();

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->jpeg2000ImageMaskPreviewRows(
                $jpxImageMaskObject('<< /Subtype /Image /Width 1 /Height 1 /ImageMask true /Filter /FlateDecode /Length 1 >>', 'X'),
                "\x80"
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->jpeg2000ImageMaskPreviewRows(
                $jpxImageMaskObject('<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /Filter /JPXDecode /Length 1 >>', 'X'),
                "\x80"
            )
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->jpeg2000ImageMaskPreviewRows(
                $jpxImageMaskObject('<< /Subtype /Image /Width 1 /Height 1 /ImageMask true /BitsPerComponent 8 /Filter /JPXDecode /Length 1 >>', 'X'),
                "\x80"
            )
        );
    },
];
