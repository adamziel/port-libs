<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineJpxPdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$inlineJpxPayload = static function (): string {
    return "\xff\x4fJPX ColorKey bytes EI BT /F1 12 Tf 72 690 Td (Inline ColorKey Noise) Tj ET\xff\xd9";
};

return [
    'maps inline JPX ColorKey supplied output samples without claiming native raster decode' => static function (TestRunner $t) use ($inlineJpxPayload): void {
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1 1 0 0 1] /Mask [0 0 120 140 200 255]';
        $preview = $renderer->inlineJpxColorKeyOutputPreviewRows(
            $dictionary,
            $inlineJpxPayload(),
            [
                [0, 128, 240],
                [40, 64, 180],
            ],
            [],
            2
        );

        $t->same('DeviceRGB', $preview['source_color_space']);
        $t->same(2, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(3, $preview['components_per_pixel']);
        $t->same(8, $preview['bits_per_component']);
        $t->same(2, $preview['expected_pixel_count']);
        $t->same(2, $preview['preview_pixel_count']);
        $t->same(true, $preview['review_only_image_stream']);
        $t->same(false, $preview['native_jpx_raster_decode']);
        $t->same(true, $preview['uses_supplied_jpx_samples']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same([
            'filters' => ['JPXDecode'],
            'preview_only_filters' => ['JPXDecode'],
            'unsupported_filters' => ['JPXDecode'],
            'raw_length' => strlen($inlineJpxPayload()),
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
            'ranges' => [
                ['min' => 0, 'max' => 0],
                ['min' => 120, 'max' => 140],
                ['min' => 200, 'max' => 255],
            ],
            'component_count' => 3,
            'expected_components' => 3,
            'valid_for_components' => true,
            'source' => 'explicit',
            'compares_before_decode' => true,
            'transparent_when_all_components_match' => true,
        ], $preview['color_key_mask']);
        $t->same('color_key_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same('RGB', $preview['output_color_mode']);

        $transparent = $preview['pixels'][0];
        $t->same(0, $transparent['pixel_index']);
        $t->same([0.0, 128.0, 240.0], $transparent['raw_sample']);
        $t->same(true, $transparent['matches_color_key']);
        $t->same(0.0, $transparent['color_key_alpha']);
        $t->same([0.0, 0.4980392156862745, 0.9411764705882353], $transparent['decoded_components']);
        $t->same(['red' => 0, 'green' => 127, 'blue' => 240, 'alpha' => 0.0], $transparent['output_rgba']);
        $t->same(true, $transparent['decode_applied_after_color_key']);

        $opaque = $preview['pixels'][1];
        $t->same(1, $opaque['pixel_index']);
        $t->same([40.0, 64.0, 180.0], $opaque['raw_sample']);
        $t->same(false, $opaque['matches_color_key']);
        $t->same(1.0, $opaque['color_key_alpha']);
        $t->same([0.1568627450980392, 0.7490196078431373, 0.7058823529411765], $opaque['decoded_components']);
        $t->same(['red' => 40, 'green' => 191, 'blue' => 180, 'alpha' => 1.0], $opaque['output_rgba']);

        $defaultDecode = $renderer->inlineJpxColorKeyOutputPreviewRows(
            '/W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /Mask [255 255 0 0 128 128]',
            $inlineJpxPayload(),
            [[255, 0, 128]],
            [],
            1
        );
        $defaultPixel = $defaultDecode['pixels'][0];
        $t->same(false, $defaultPixel['decode_applied_after_color_key']);
        $t->same([1.0, 0.0, 0.5019607843137255], $defaultPixel['decoded_components']);
        $t->same(['red' => 255, 'green' => 0, 'blue' => 128, 'alpha' => 0.0], $defaultPixel['output_rgba']);

        $t->same([
            'inline_jpx_image_stream_review_only_before_rgb_conversion',
            'inline_jpx_colorkey_supplied_samples_before_output_preview',
        ], $preview['stream_notes']);
        $notes = implode(',', $preview['notes']);
        $t->contains('inline_jpx_image_filter_review_only', $notes);
        $t->contains('color_key_mask_applied_before_rgb_conversion', $notes);
        $t->contains('inline_jpx_colorkey_supplied_samples_previewed_without_raster_decode', $notes);
    },
    'keeps inline JPX ColorKey payload bytes out of WordPress text import' => static function (TestRunner $t) use ($inlineJpxPdfWithContent, $inlineJpxPayload): void {
        $dictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /Mask [0 0 120 140 200 255]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Inline ColorKey) Tj ET\n"
            . 'BI ' . $dictionary . " ID\n" . $inlineJpxPayload() . "\nEI\n"
            . 'BT /F1 12 Tf 72 704 Td (After Inline ColorKey) Tj ET';
        $pdf = $inlineJpxPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Before Inline ColorKey', 'After Inline ColorKey'], $extractor->extractTextLines($pdf));
        $t->same("Before Inline ColorKey\nAfter Inline ColorKey", $text);
        $t->true(!str_contains($text, 'Inline ColorKey Noise'));
        $t->true(!str_contains($text, 'JPXDecode'));
        $t->true(!str_contains($text, "\xff\xd9"));
    },
];
