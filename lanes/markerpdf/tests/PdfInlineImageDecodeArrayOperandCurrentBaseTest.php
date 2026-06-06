<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageDecodeArrayOperandPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'resolves direct inline Decode array indirect numeric operands before RGB preview' => static function (TestRunner $t) use ($inlineImageDecodeArrayOperandPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $palette = '<000000FF000000FF000000FF>';
        $objects = [
            91 => $palette,
            101 => '0',
            102 => '3',
            103 => '1',
        ];

        $indexedDictionary = '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [101 0 R 102 0 R]';
        $indexedPreview = $renderer->inlineIndexedImageStreamPreviewRows(
            $indexedDictionary,
            "\x00\x80\xff",
            $objects,
            3
        );

        $grayDictionary = '/W 2 /H 1 /CS /G /BPC 8 /D [103 0 R 101 0 R]';
        $grayPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            $grayDictionary,
            "\x00\xff",
            $objects,
            2
        );

        $content = "BT /F1 12 Tf 72 720 Td (Before Indirect Decode Array Inline) Tj ET\n"
            . "BI /W 32 /H 1 /CS /G /BPC 8 /D [103 0 R 101 0 R] ID\n"
            . "abc EI BT /F1 12 Tf 72 690 Td (Indirect Decode Array Payload Noise) Tj ET rawtail\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Indirect Decode Array Inline) Tj ET";
        $plainText = $extractor->extractPlainText($inlineImageDecodeArrayOperandPdf($content));

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
        ], $indexedPreview['image_decode']);
        $t->same([0.0, 128.0, 255.0], array_column($indexedPreview['pixels'], 'raw_sample'));
        $t->same([0, 2, 3], array_column($indexedPreview['pixels'], 'palette_index'));
        $t->same([0.0, 0.0, 0.0], $indexedPreview['pixels'][0]['base_components']);
        $t->same([0.0, 1.0, 0.0], $indexedPreview['pixels'][1]['base_components']);
        $t->same([0.0, 0.0, 1.0], $indexedPreview['pixels'][2]['base_components']);
        $t->true(abs($indexedPreview['pixels'][1]['decoded_index'] - (3 * (128 / 255))) < 0.000001);
        $t->same(true, $indexedPreview['inline_image']['native_raster_decode']);
        $t->same(false, $indexedPreview['review_only_image_stream']);
        $t->contains('image_decode_applied_before_rgb_conversion', implode(',', $indexedPreview['notes']));

        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $grayPreview['image_decode']);
        $t->same([[0.0], [255.0]], array_column($grayPreview['pixels'], 'raw_sample'));
        $t->same([1.0, 0.0], array_column($grayPreview['pixels'], 'decoded_gray'));
        $t->same(['red' => 255, 'green' => 255, 'blue' => 255, 'alpha' => 1.0], $grayPreview['pixels'][0]['output_rgba']);
        $t->same(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 1.0], $grayPreview['pixels'][1]['output_rgba']);
        $t->contains('image_decode_inverts_components_before_rgb', implode(',', $grayPreview['notes']));

        $t->same("Before Indirect Decode Array Inline\nAfter Indirect Decode Array Inline", $plainText);
        $t->true(!str_contains($plainText, 'Indirect Decode Array Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'resolves direct inline ImageMask Decode array indirect numeric operands before stencil preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $objects = [
            101 => '1',
            102 => '0',
        ];

        $preview = $renderer->inlineImageMaskPreviewRows(
            '/W 4 /H 1 /IM true /BPC 1 /D [101 0 R 102 0 R]',
            "\xa0",
            $objects,
            4
        );
        $cycleReview = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /IM true /D [101 0 R 102 0 R]',
            "\x80",
            [
                101 => '102 0 R',
                102 => '101 0 R',
            ]
        );

        $t->same('ImageMask', $preview['source_color_space']);
        $t->same(4, $preview['expected_pixel_count']);
        $t->same(4, $preview['preview_pixel_count']);
        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $preview['image_mask']['decode']);
        $t->same([1.0, 0.0, 1.0, 0.0], array_column($preview['pixels'], 'raw_sample'));
        $t->same([0.0, 1.0, 0.0, 1.0], array_column($preview['pixels'], 'opacity'));
        $t->contains('image_mask_decode_inverts_stencil', implode(',', $preview['notes']));

        $t->same(true, $cycleReview['image_mask']['decode']['valid_for_components'] === false);
        $t->same(true, $cycleReview['inline_image_review_only']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $cycleReview['notes']));
    },
];
