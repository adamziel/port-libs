<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'decodes inline Indexed filter payloads with palette and current soft-mask alpha before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageBytes = "\x1c";
        $compressedImage = gzcompress($imageBytes);
        $maskBytes = "\x00\x80\xff";
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedImage) || !is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress inline Indexed image fixtures.');
        }

        $imageHex = strtoupper(bin2hex($compressedImage)) . '>';
        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            91 => '<000000FF000000FF000000FF>',
            92 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
        ];

        $preview = $renderer->inlineIndexedImageStreamPreviewRows(
            '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F [/AHx /Fl] /D [0 3] /SMask 92 0 R',
            $imageHex,
            $objects,
            3
        );

        $t->same('Indexed', $preview['source_color_space']);
        $t->same(3, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(1, $preview['components_per_pixel']);
        $t->same(2, $preview['bits_per_component']);
        $t->same(3, $preview['expected_pixel_count']);
        $t->same(3, $preview['preview_pixel_count']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same(true, $preview['inline_image']['present']);
        $t->same(true, $preview['inline_image']['uses_abbreviations']);
        $t->same(true, $preview['inline_image']['excluded_from_visible_text']);
        $t->same(false, $preview['inline_image']['has_object_number']);
        $t->same('<< /Width 3 /Height 1 /ColorSpace [/Indexed /DeviceRGB 3 91 0 R] /BitsPerComponent 2 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [0 3] /SMask 92 0 R >>', $preview['inline_image']['canonical_dictionary']);
        $t->same([
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($imageHex),
            'decoded_length' => 1,
            'decoded_sha256' => hash('sha256', $imageBytes),
            'decoded_preview_hex' => '1C',
            'decoded_with_current_filters' => true,
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
            'inline_indexed_image_stream_filters_decoded_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);
        $t->same([
            'base_color_space' => 'DeviceRGB',
            'base_components' => 3,
            'base_uses_icc_profile' => false,
            'base_icc_profile' => null,
            'base_uses_alternate_color_space' => false,
            'base_alternate_color_space' => null,
            'high_value' => 3,
            'lookup_source' => 'hex_string',
            'lookup_length' => 12,
            'expected_lookup_length' => 12,
            'lookup_length_matches' => true,
            'lookup_entry_count' => 4,
            'lookup_preview_hex' => '000000FF000000FF000000FF',
            'lookup_bytes' => [0, 0, 0, 255, 0, 0, 0, 255, 0, 0, 0, 255],
        ], $preview['indexed_color_space']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 3.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [],
            'source' => 'explicit',
        ], $preview['image_decode']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same('RGB', $preview['output_color_mode']);

        $first = $preview['pixels'][0];
        $t->same(0.0, $first['raw_sample']);
        $t->same(0.0, $first['decoded_index']);
        $t->same(0, $first['palette_index']);
        $t->same([0.0, 0.0, 0.0], $first['base_components']);
        $t->same(0.0, $first['soft_mask_sample']);
        $t->same(1.0, $first['soft_mask_alpha']);
        $t->same(1.0, $first['soft_mask_alpha_before_transfer']);
        $t->same(false, $first['soft_mask_transfer_applied']);

        $second = $preview['pixels'][1];
        $t->same(1.0, $second['raw_sample']);
        $t->same(1.0, $second['decoded_index']);
        $t->same(1, $second['palette_index']);
        $t->same([1.0, 0.0, 0.0], $second['base_components']);
        $t->same(128.0, $second['soft_mask_sample']);
        $t->true(abs((float) $second['soft_mask_alpha'] - (1.0 - (128 / 255))) < 0.000001);

        $third = $preview['pixels'][2];
        $t->same(3.0, $third['raw_sample']);
        $t->same(3.0, $third['decoded_index']);
        $t->same(3, $third['palette_index']);
        $t->same([0.0, 0.0, 1.0], $third['base_components']);
        $t->same(255.0, $third['soft_mask_sample']);
        $t->same(0.0, $third['soft_mask_alpha']);

        $notes = implode(',', $preview['notes']);
        $t->contains('inline_image_dictionary_abbreviations_expanded', $notes);
        $t->contains('inline_image_payload_excluded_from_visible_text', $notes);
        $t->contains('inline_image_soft_mask_decoded_from_current_object', $notes);
        $t->contains('inline_indexed_image_stream_filters_decoded_before_rgb_conversion', $notes);
    },
    'keeps preview-only inline Indexed filters review-only while preserving palette alpha metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $maskBytes = "\x40\xff";
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress inline Indexed mask fixture.');
        }

        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            93 => '<000000FFFFFF>',
            94 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
        ];
        $payload = "\xff\x4fINLINE JPX PALETTE BYTES EI BT /F1 12 Tf (Hidden inline palette text) Tj ET\xff\xd9";

        $preview = $renderer->inlineIndexedImageStreamPreviewRows(
            '/W 2 /H 1 /CS [/I /RGB 1 93 0 R] /BPC 1 /F /JPXDecode /D [0 1] /SMask 94 0 R',
            $payload,
            $objects,
            2
        );

        $t->same('Indexed', $preview['source_color_space']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(false, $preview['complete_image_sample_data']);
        $t->same(true, $preview['complete_soft_mask_sample_data']);
        $t->same(0, $preview['preview_pixel_count']);
        $t->same([], $preview['pixels']);
        $t->same(['JPXDecode'], $preview['inline_image']['review_only_filters']);
        $t->same(false, $preview['inline_image']['native_raster_decode']);
        $t->same(hash('sha256', $payload), $preview['inline_image']['payload_sha256']);
        $t->same([
            'filters' => ['JPXDecode'],
            'preview_only_filters' => ['JPXDecode'],
            'unsupported_filters' => ['JPXDecode'],
            'raw_length' => strlen($payload),
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
            'decoded_length' => 2,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => '40FF',
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
        ], $preview['soft_mask_stream']);
        $t->same([
            'inline_indexed_image_stream_preview_only_before_rgb_conversion',
            'soft_mask_stream_filters_decoded_before_rgb_conversion',
        ], $preview['stream_notes']);
        $t->same('soft_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same(1, $preview['indexed_color_space']['high_value']);
        $t->same([0, 0, 0, 255, 255, 255], $preview['indexed_color_space']['lookup_bytes']);
        $t->same('explicit', $preview['image_decode']['source']);
        $notes = implode(',', $preview['notes']);
        $t->contains('jpx_image_filter_review_only', $notes);
        $t->contains('inline_jpx_image_filter_review_only', $notes);
        $t->contains('inline_indexed_image_stream_preview_only_before_rgb_conversion', $notes);
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', $notes);
    },
];
