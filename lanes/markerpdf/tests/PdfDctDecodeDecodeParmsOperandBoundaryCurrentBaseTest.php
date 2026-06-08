<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'fails closed on unresolved DCTDecode DecodeParms indirect operand before RGB preview planning' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before unresolved DCT DecodeParms operand) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After unresolved DCT DecodeParms operand) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0unresolved-decodeparms operand bytes "
            . 'BT /F1 12 Tf 72 700 Td (Unresolved DCT DecodeParms operand payload leak) Tj ET'
            . "\xff\xd9";
        $imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms 99 0 R /Length ' . strlen($jpegPayload) . ' >>';
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $expectedDecodeParms = [
            'type' => 'DCTDecode',
            'color_transform' => null,
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['decode_parms_operand'],
            'decode_parms_review' => 'unresolved_dctdecode_decodeparms_fail_closed',
            'decode_parms_operand' => 'unresolved_reference',
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
        $colorPlan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegPayload);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? null;

        $t->same([
            [
                'filter' => 'DCTDecode',
                'preview_only' => true,
                'decode_parms' => $expectedDecodeParms,
            ],
        ], $plan['image_filter_details']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->contains('unresolved_dctdecode_decodeparms_fail_closed', implode(',', $plan['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same('DCTDecode', $colorPlan['filter']);
        $t->same(null, $colorPlan['decode_parms_color_transform']);
        $t->same(false, $colorPlan['decode_parms_color_transform_valid']);
        $t->same(true, $colorPlan['decode_parms_color_transform_ignored']);
        $t->same(0, $colorPlan['effective_color_transform']);
        $t->same(false, $colorPlan['uses_ycck_transform']);
        $t->same([
            'unresolved_dctdecode_decodeparms_fail_closed',
            'render_rgb_preview_from_cmyk',
        ], $colorPlan['notes']);

        $t->same(['Before unresolved DCT DecodeParms operand', 'After unresolved DCT DecodeParms operand'], $extractor->extractTextLines($pdf));
        $t->same("Before unresolved DCT DecodeParms operand\nAfter unresolved DCT DecodeParms operand", $plainText);
        $t->true(!str_contains($plainText, 'Unresolved DCT DecodeParms operand payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same($expectedDecodeParms, $entry['filter_details'][0]['decode_parms'] ?? null);
        $t->same(strlen($jpegPayload), $entry['raw_length'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },

    'fails closed on malformed indirect DCTDecode DecodeParms operand before RGB preview planning' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $objects = [11 => '/NotADictionary'];
        $before = 'BT /F1 12 Tf 72 720 Td (Before malformed DCT DecodeParms operand) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After malformed DCT DecodeParms operand) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0malformed-decodeparms operand bytes "
            . 'BT /F1 12 Tf 72 700 Td (Malformed DCT DecodeParms operand payload leak) Tj ET'
            . "\xff\xd9";
        $imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms 11 0 R /Length ' . strlen($jpegPayload) . ' >>';
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "11 0 obj\n/NotADictionary\nendobj\n%%EOF";
        $expectedDecodeParms = [
            'type' => 'DCTDecode',
            'color_transform' => null,
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['decode_parms_operand'],
            'decode_parms_review' => 'malformed_dctdecode_decodeparms_fail_closed',
            'decode_parms_operand' => 'malformed_operand',
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
        $colorPlan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegPayload, $objects);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? null;

        $t->same([
            [
                'filter' => 'DCTDecode',
                'preview_only' => true,
                'decode_parms' => $expectedDecodeParms,
            ],
        ], $plan['image_filter_details']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->contains('malformed_dctdecode_decodeparms_fail_closed', implode(',', $plan['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same('DCTDecode', $colorPlan['filter']);
        $t->same(null, $colorPlan['decode_parms_color_transform']);
        $t->same(false, $colorPlan['decode_parms_color_transform_valid']);
        $t->same(true, $colorPlan['decode_parms_color_transform_ignored']);
        $t->same(0, $colorPlan['effective_color_transform']);
        $t->same(false, $colorPlan['uses_ycck_transform']);
        $t->same([
            'malformed_dctdecode_decodeparms_fail_closed',
            'render_rgb_preview_from_cmyk',
        ], $colorPlan['notes']);

        $t->same(['Before malformed DCT DecodeParms operand', 'After malformed DCT DecodeParms operand'], $extractor->extractTextLines($pdf));
        $t->same("Before malformed DCT DecodeParms operand\nAfter malformed DCT DecodeParms operand", $plainText);
        $t->true(!str_contains($plainText, 'Malformed DCT DecodeParms operand payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same($expectedDecodeParms, $entry['filter_details'][0]['decode_parms'] ?? null);
        $t->same(strlen($jpegPayload), $entry['raw_length'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },

    'reports indirect DCTDecode DecodeParms arrays with trailing operands as array-tail boundaries' => static function (
        TestRunner $t
    ): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $decodeParmsObject = '[<< /ColorTransform 1 >>] << /ColorTransform 0 >>';
        $objects = [20 => $decodeParmsObject];
        $before = 'BT /F1 12 Tf 72 720 Td (Before indirect DCT DecodeParms array tail) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After indirect DCT DecodeParms array tail) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0array-tail-decodeparms operand bytes "
            . 'BT /F1 12 Tf 72 700 Td (Array-tail DCT DecodeParms operand payload leak) Tj ET'
            . "\xff\xd9";
        $imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms 20 0 R /Length ' . strlen($jpegPayload) . ' >>';
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n{$decodeParmsObject}\nendobj\n%%EOF";
        $expectedDecodeParms = [
            'type' => 'DCTDecode',
            'color_transform' => null,
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['decode_parms_operand'],
            'decode_parms_review' => 'malformed_dctdecode_decodeparms_fail_closed',
            'decode_parms_operand' => 'malformed_operand',
            'decode_parms_operand_detail' => 'array_with_trailing_operands',
            'decode_parms_array_policy' => 'reject_top_level_decodeparms_array_tail',
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
        $colorPlan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegPayload, $objects);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);

        $t->same([
            [
                'filter' => 'DCTDecode',
                'preview_only' => true,
                'decode_parms' => $expectedDecodeParms,
            ],
        ], $plan['image_filter_details']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->contains('malformed_dctdecode_decodeparms_fail_closed', implode(',', $plan['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same('DCTDecode', $colorPlan['filter']);
        $t->same(null, $colorPlan['decode_parms_color_transform']);
        $t->same(false, $colorPlan['decode_parms_color_transform_valid']);
        $t->same(true, $colorPlan['decode_parms_color_transform_ignored']);
        $t->same(0, $colorPlan['effective_color_transform']);
        $t->same(false, $colorPlan['uses_ycck_transform']);
        $t->same([
            'malformed_dctdecode_decodeparms_fail_closed',
            'render_rgb_preview_from_cmyk',
        ], $colorPlan['notes']);

        $t->same(['Before indirect DCT DecodeParms array tail', 'After indirect DCT DecodeParms array tail'], $extractor->extractTextLines($pdf));
        $t->same("Before indirect DCT DecodeParms array tail\nAfter indirect DCT DecodeParms array tail", $plainText);
        $t->true(!str_contains($plainText, 'Array-tail DCT DecodeParms operand payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($reviewJson, 'Array-tail DCT DecodeParms operand payload leak'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same($expectedDecodeParms, $entry['filter_details'][0]['decode_parms'] ?? null);
        $t->same(strlen($jpegPayload), $entry['raw_length'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
