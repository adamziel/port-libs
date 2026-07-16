<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeIndirectFilterArrayTailBoundaryCurrentBaseZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused indirect DCT filter-tail fixture must fit one deflate stored block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

return [
    'rejects indirect DCTDecode filter arrays with trailing operands before renderer and WordPress review' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeIndirectFilterArrayTailBoundaryCurrentBaseZlibStored): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $before = 'BT /F1 12 Tf 72 720 Td (Before indirect DCT filter tail) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After indirect DCT filter tail) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0indirect filter tail bytes "
            . 'BT /F1 12 Tf 72 700 Td (Indirect DCT filter tail payload leak) Tj ET'
            . "\xff\xd9";
        $compressedPayload = $pdfDctDecodeIndirectFilterArrayTailBoundaryCurrentBaseZlibStored($jpegPayload);
        $filterObject = '[ /FlateDecode /DCTDecode ] /Crypt';
        $imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter 20 0 R /DecodeParms [null << /ColorTransform 1 >>] /Length ' . strlen($compressedPayload) . ' >>';
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$compressedPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n{$filterObject}\nendobj\n%%EOF";
        $rendererImage = str_replace('/ColorSpace /DeviceRGB', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary)
            . "\nstream\n{$compressedPayload}\nendstream";
        $rendererObjects = [
            20 => $filterObject,
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ];
        $expectedFilters = ['MalformedFilterOperand', 'FlateDecode', 'DCTDecode'];
        $expectedBoundary = [
            'declared_filter' => 'DCTDecode',
            'canonical_filter' => 'DCTDecode',
            'alias_used' => false,
            'non_null_filter_index' => 2,
            'filters_before_dctdecode' => ['MalformedFilterOperand', 'FlateDecode'],
            'native_prefix_filters' => ['MalformedFilterOperand', 'FlateDecode'],
            'preview_only_filters_before_dctdecode' => [],
            'filters_after_dctdecode' => [],
            'native_filters_after_dctdecode' => [],
            'preview_only_filters_after_dctdecode' => [],
            'dctdecode_is_terminal_filter' => true,
            'post_dctdecode_filters_present' => false,
            'post_dctdecode_filters_block_native_decode' => false,
            'source_filter_preserved' => true,
            'review_only' => true,
            'native_raster_decode' => false,
        ];
        $expectedDecodeParms = [
            'type' => 'DCTDecode',
            'color_transform' => 1,
            'valid_color_transform' => true,
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, [20 => $filterObject]);
        $streamPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);

        $t->same(['Before indirect DCT filter tail', 'After indirect DCT filter tail'], $extractor->extractTextLines($pdf));
        $t->same(['Before indirect DCT filter tail', 'After indirect DCT filter tail'], $extractor->extractTextRuns($pdf));
        $t->same("Before indirect DCT filter tail\nAfter indirect DCT filter tail", $plainText);
        $t->same("Before indirect DCT filter tail\nAfter indirect DCT filter tail\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Indirect DCT filter tail payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($plainText, 'Crypt'));

        $t->same($expectedFilters, $plan['image_filters']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->same('reject_malformed_filter_operands', $plan['image_filter_boundary']['filter_operand_policy'] ?? null);
        $t->same(1, $plan['image_filter_boundary']['malformed_filter_operand_count'] ?? null);
        $t->same($expectedBoundary, $plan['dctdecode_filter_boundary']);
        $t->same($expectedDecodeParms, $plan['image_filter_details'][2]['decode_parms'] ?? null);
        $t->contains('malformed_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same($expectedFilters, $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same('reject_malformed_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(1, $entry['malformed_filter_operand_count'] ?? null);
        $t->same($expectedBoundary, $entry['dctdecode_filter_boundary'] ?? null);
        $t->same($expectedDecodeParms, $entry['filter_details'][2]['decode_parms'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($reviewJson, 'Indirect DCT filter tail payload leak'));

        $t->same(true, $streamPreview['review_only_image_stream']);
        $t->same($expectedFilters, $streamPreview['image_stream']['filters']);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['preview_only_filters']);
        $t->same(['MalformedFilterOperand', 'DCTDecode'], $streamPreview['image_stream']['unsupported_filters']);
        $t->same(false, $streamPreview['image_stream']['decoded_with_current_filters']);
        $t->same(true, $streamPreview['image_stream']['decode_failed']);
        $t->same([], $streamPreview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
