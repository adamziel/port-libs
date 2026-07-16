<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'fails closed on duplicate DCTDecode ColorTransform DecodeParms before RGB preview planning' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before duplicate DCT DecodeParms) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After duplicate DCT DecodeParms) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0duplicate-decodeparms review bytes "
            . 'BT /F1 12 Tf 72 700 Td (Duplicate DCT DecodeParms payload leak) Tj ET'
            . "\xff\xd9";
        $imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms << /ColorTransform 1 /ColorTransform 0 >> /Length ' . strlen($jpegPayload) . ' >>';
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
            'color_transform' => 1,
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['color_transform'],
            'duplicate_decode_parms_fields' => ['color_transform'],
            'decode_parms_review' => 'duplicate_dctdecode_decodeparms_parameter_fail_closed',
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
        $t->contains('duplicate_dctdecode_decodeparms_parameter_fail_closed', implode(',', $plan['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same('DCTDecode', $colorPlan['filter']);
        $t->same(1, $colorPlan['decode_parms_color_transform']);
        $t->same(false, $colorPlan['decode_parms_color_transform_valid']);
        $t->same(true, $colorPlan['decode_parms_color_transform_ignored']);
        $t->same(0, $colorPlan['effective_color_transform']);
        $t->same(false, $colorPlan['uses_ycck_transform']);
        $t->same([
            'duplicate_dctdecode_decodeparms_parameter_fail_closed',
            'render_rgb_preview_from_cmyk',
        ], $colorPlan['notes']);

        $t->same(['Before duplicate DCT DecodeParms', 'After duplicate DCT DecodeParms'], $extractor->extractTextLines($pdf));
        $t->same("Before duplicate DCT DecodeParms\nAfter duplicate DCT DecodeParms", $plainText);
        $t->true(!str_contains($plainText, 'Duplicate DCT DecodeParms payload leak'));
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
];
