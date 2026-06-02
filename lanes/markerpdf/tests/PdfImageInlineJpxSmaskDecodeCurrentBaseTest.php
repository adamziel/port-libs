<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineJpxPdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$inlineJpxPayload = static function (): string {
    return "\xff\x4f\x00\x00 EI BT /F1 12 Tf 72 690 Td (Inline JPX Noise) Tj ET \xff\xd9";
};

return [
    'keeps inline JPX EI-looking bytes inside image payload before WordPress text import' => static function (TestRunner $t) use ($inlineJpxPdfWithContent, $inlineJpxPayload): void {
        $payload = $inlineJpxPayload();
        $dictionary = '/W 3 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /SMask 38 0 R /D [0 1 1 0 0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Inline JPX) Tj ET\n"
            . 'BI ' . $dictionary . " ID\n" . $payload . "\nEI\n"
            . 'BT /F1 12 Tf 72 704 Td (After Inline JPX) Tj ET';
        $pdf = $inlineJpxPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Before Inline JPX', 'After Inline JPX'], $extractor->extractTextLines($pdf));
        $t->same("Before Inline JPX\nAfter Inline JPX", $text);
        $t->same("Before Inline JPX\nAfter Inline JPX\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($text, 'Inline JPX Noise'));
        $t->true(!str_contains($text, 'JPXDecode'));
        $t->true(!str_contains($text, "\xff\xd9"));
    },
    'maps inline JPX soft-mask Decode from the current object map before RGB preview' => static function (TestRunner $t) use ($inlineJpxPayload): void {
        $renderer = new PdfImageRenderer();
        $maskBytes = "\x00\x80\xff";
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress inline JPX soft-mask fixture.');
        }

        $maskHex = strtoupper(bin2hex($compressedMask)) . '>';
        $objects = [
            38 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
            39 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Length 5 >>\nstream\nSTALE\nendstream",
        ];
        $plan = $renderer->inlineImageReviewPlan(
            '/W 3 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1 1 0 0 1] /SMask 38 0 R',
            $inlineJpxPayload(),
            $objects
        );

        $t->same(true, $plan['inline_image']['present']);
        $t->same('<< /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /Decode [0 1 1 0 0 1] /SMask 38 0 R >>', $plan['inline_image']['canonical_dictionary']);
        $t->same(true, $plan['inline_image']['uses_abbreviations']);
        $t->same(false, $plan['inline_image']['has_object_number']);
        $t->same(true, $plan['inline_image']['excluded_from_visible_text']);
        $t->same(['JPXDecode'], $plan['inline_image']['review_only_filters']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image']['soft_mask_present']);
        $t->same(38, $plan['inline_image']['soft_mask_source_object']);
        $t->same(true, $plan['inline_image']['soft_mask_uses_current_object_map']);
        $t->same(true, $plan['inline_image']['soft_mask_decoded_with_current_filters']);
        $t->same(true, $plan['inline_image']['soft_mask_decode_applied_before_rgb']);
        $t->same([
            'preview_only_filters' => ['JPXDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 1.0, 'max' => 0.0],
                ['min' => 0.0, 'max' => 1.0],
            ],
            'component_count' => 3,
            'expected_components' => 3,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [1],
            'source' => 'explicit',
        ], $plan['image_decode']);
        $t->same([
            'present' => true,
            'subtype' => 'Image',
            'width' => 3,
            'height' => 1,
            'color_space' => 'DeviceGray',
            'components' => 1,
            'bits_per_component' => 8,
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
            'opacity_for_max' => 0.0,
            'decode_inverted' => true,
            'decode_component_mismatch' => false,
            'matte' => null,
            'interpolate' => null,
        ], $plan['soft_mask']);
        $t->same([
            'present' => true,
            'source_object' => 38,
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($maskHex),
            'decoded_length' => 3,
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => '0080FF',
            'decoded_sample_bytes' => [0, 128, 255],
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
            'uses_current_object_map' => true,
        ], $plan['soft_mask_filter_boundary']);
        $t->same(true, $plan['soft_mask_applied_before_rgb']);
        $t->same(true, $plan['soft_mask_decode_applied_before_rgb']);
        $t->same('soft_mask_composited_to_rgb_preview', $plan['alpha_output_mode']);
        $t->same(1.0, $renderer->softMaskSampleOpacity(0, $plan['soft_mask']));
        $t->true(abs($renderer->softMaskSampleOpacity(128, $plan['soft_mask']) - (1.0 - (128 / 255))) < 0.000001);
        $t->same(0.0, $renderer->softMaskSampleOpacity(255, $plan['soft_mask']));
        $t->same(true, $plan['inline_image_review_only']);
        $notes = implode(',', $plan['notes']);
        $t->contains('jpx_image_filter_review_only', $notes);
        $t->contains('inline_jpx_image_filter_review_only', $notes);
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', $notes);
        $t->contains('inline_image_soft_mask_decoded_from_current_object', $notes);
    },
];
