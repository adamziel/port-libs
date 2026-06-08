<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeNullSlotMetadataBoundaryCurrentBaseZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused DCTDecode null-slot metadata fixture must fit one deflate stored block.');
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

$pdfDctDecodeNullSlotMetadataBoundaryCurrentBaseFixture = static function () use (
    $pdfDctDecodeNullSlotMetadataBoundaryCurrentBaseZlibStored
): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before null-slot metadata DCT image) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After null-slot metadata DCT image) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0native null-slot review bytes "
        . 'BT /F1 12 Tf 72 700 Td (Null-slot metadata DCT payload leak) Tj ET'
        . "\xff\xd9";
    $encodedPayload = $pdfDctDecodeNullSlotMetadataBoundaryCurrentBaseZlibStored($jpegPayload);
    $imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB '
        . '/BitsPerComponent 8 /Filter [null /Fl /DCTDecode] /DecodeParms [99 0 R null << /ColorTransform 1 >>] '
        . '/Length ' . strlen($encodedPayload) . ' >>';
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $rendererDictionary = str_replace('/ColorSpace /DeviceRGB', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);

    return [
        'expected_lines' => [
            'Before null-slot metadata DCT image',
            'After null-slot metadata DCT image',
        ],
        'jpeg_payload' => $jpegPayload,
        'encoded_payload' => $encodedPayload,
        'image_dictionary' => $imageDictionary,
        'renderer_image' => "{$rendererDictionary}\nstream\n{$encodedPayload}\nendstream",
        'renderer_objects' => [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
        'pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF",
    ];
};

return [
    'reports raw null filter slots before native-prefix DCTDecode image boundaries' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeNullSlotMetadataBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeNullSlotMetadataBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $expectedBoundary = [
            'declared_filter' => 'DCTDecode',
            'canonical_filter' => 'DCTDecode',
            'alias_used' => false,
            'non_null_filter_index' => 1,
            'raw_filter_slot_index' => 2,
            'filter_stack_slot_count' => 3,
            'null_filter_slot_count_before_dctdecode' => 1,
            'filters_before_dctdecode' => ['Fl'],
            'native_prefix_filters' => ['Fl'],
            'canonical_native_prefix_filters' => ['FlateDecode'],
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

        $plainText = $extractor->extractPlainText($fixture['pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['pdf']);
        $entry = $review['entries'][0] ?? null;
        $plan = $renderer->imageColorSpaceSoftMaskPlan($fixture['image_dictionary']);
        $streamPreview = $renderer->iccBasedImageStreamPreviewRows(
            $fixture['renderer_image'],
            $fixture['renderer_objects']
        );

        $t->same($fixture['expected_lines'], $extractor->extractTextLines($fixture['pdf']));
        $t->same($fixture['expected_lines'], $extractor->extractTextRuns($fixture['pdf']));
        $t->same(implode("\n", $fixture['expected_lines']), $plainText);
        $t->same(implode("\n", $fixture['expected_lines']) . "\n", $extractor->naiveGetText($fixture['pdf']));
        $t->true(!str_contains($plainText, 'Null-slot metadata DCT payload leak'));
        $t->true(!str_contains($plainText, 'native null-slot review bytes'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');

        $t->same(['Fl', 'DCTDecode'], $plan['image_filters']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->same($expectedBoundary, $plan['dctdecode_filter_boundary']);
        $t->same($expectedDecodeParms, $plan['image_filter_details'][1]['decode_parms'] ?? null);
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same(['Fl', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same($expectedBoundary, $entry['dctdecode_filter_boundary'] ?? null);
        $t->same($expectedDecodeParms, $entry['filter_details'][1]['decode_parms'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $entry['raw_length'] ?? null);

        $boundary = $entry['dctdecode_stream_boundary'] ?? null;
        $t->true(is_array($boundary), 'DCTDecode stream boundary should be present after native prefix decode.');
        $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
        $t->same(true, $boundary['review_stream_decoded_from_native_prefix'] ?? null);
        $t->same(['Fl'], $boundary['native_prefix_filters'] ?? null);
        $t->same('DCTDecode', $boundary['stopped_before_filter'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['review_stream_length'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $boundary['raw_stream_length'] ?? null);

        $t->same(true, $streamPreview['review_only_image_stream']);
        $t->same(['Fl', 'DCTDecode'], $streamPreview['image_stream']['filters'] ?? null);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['preview_only_filters'] ?? null);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['unsupported_filters'] ?? null);
        $t->same(false, $streamPreview['image_stream']['decoded_with_current_filters'] ?? null);
        $t->same(false, $streamPreview['image_stream']['decode_failed'] ?? null);
        $t->same(true, $streamPreview['image_stream']['native_prefix_decoded'] ?? null);
        $t->same('DCTDecode', $streamPreview['image_stream']['stopped_before_filter'] ?? null);
        $t->same([], $streamPreview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
