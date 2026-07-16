<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'decodes inline ImageMask packed samples before WordPress RGB preview metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "\xa0";

        $preview = $renderer->inlineImageMaskPreviewRows(
            '/W 4 /H 1 /IM true /D [1 0]',
            $payload,
            [],
            4
        );

        $t->same('ImageMask', $preview['source_color_space']);
        $t->same(4, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(1, $preview['bits_per_component']);
        $t->same(4, $preview['expected_pixel_count']);
        $t->same(4, $preview['preview_pixel_count']);
        $t->same(false, $preview['review_only_image_stream']);
        $t->same(true, $preview['complete_image_sample_data']);
        $t->same(true, $preview['inline_image']['excluded_from_visible_text']);
        $t->same(true, $preview['inline_image_abbreviations_expanded']);
        $t->same('image_mask_composited_to_rgb_preview', $preview['alpha_output_mode']);
        $t->same([
            [
                'pixel_index' => 0,
                'x' => 0,
                'y' => 0,
                'raw_sample' => 1.0,
                'opacity' => 0.0,
            ],
            [
                'pixel_index' => 1,
                'x' => 1,
                'y' => 0,
                'raw_sample' => 0.0,
                'opacity' => 1.0,
            ],
            [
                'pixel_index' => 2,
                'x' => 2,
                'y' => 0,
                'raw_sample' => 1.0,
                'opacity' => 0.0,
            ],
            [
                'pixel_index' => 3,
                'x' => 3,
                'y' => 0,
                'raw_sample' => 0.0,
                'opacity' => 1.0,
            ],
        ], $preview['pixels']);
        $t->same([
            'image_decode_applied_before_rgb_conversion',
            'image_decode_inverts_components_before_rgb',
            'image_mask_stencil_applied_before_rgb_conversion',
            'image_mask_decode_inverts_stencil',
            'inline_image_dictionary_abbreviations_expanded',
            'inline_image_payload_excluded_from_visible_text',
            'inline_image_mask_samples_decoded_before_rgb_conversion',
        ], $preview['notes']);
    },
    'keeps inline ImageMask payload text excluded while exposing incomplete sample review boundary' => static function (TestRunner $t) use ($pdfWithContent): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $payload = "\x80BT /F1 12 Tf 72 690 Td (Inline Mask Payload Noise) Tj ET";
        $dictionary = '/W 9 /H 1 /IM true /D [0 1]';
        $content = "BT /F1 12 Tf 72 720 Td (Before Inline Mask) Tj ET\n"
            . 'BI ' . $dictionary . " ID\n" . $payload . "\nEI\n"
            . 'BT /F1 12 Tf 72 704 Td (After Inline Mask) Tj ET';
        $text = $extractor->extractPlainText($pdfWithContent($content));

        $preview = $renderer->inlineImageMaskPreviewRows($dictionary, "\x80", [], 8);

        $t->same("Before Inline Mask\nAfter Inline Mask", $text);
        $t->true(!str_contains($text, 'Inline Mask Payload Noise'));
        $t->same(false, $preview['complete_image_sample_data']);
        $t->same(8, $preview['preview_pixel_count']);
        $t->same(9, $preview['expected_pixel_count']);
        $t->same(1.0, $preview['pixels'][0]['raw_sample']);
        $t->same(1.0, $preview['pixels'][0]['opacity']);
        $t->same('inline_image_mask_sample_data_incomplete', $preview['stream_notes'][1]);
    },
];
