<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeEscapedFilterBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before escaped DCT filter) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After escaped DCT filter) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Escaped DCT payload leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0escaped DCT bytes\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused escaped DCT fixture must expose a fake endstream marker.');
    }

    $imageDictionary = '/Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK '
        . '/BitsPerComponent 8 /Fil#74er /DCT#44ecode '
        . '/Decode#50arms << /Color#54ransform 1 >> /Len#67th ' . $fakeTerminatorOffset;
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /EscapedDct Do Q\n" . $after;
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /EscapedDct 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$imageDictionary} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererImageDictionary = str_replace('/ColorSpace /DeviceCMYK', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);
    $rendererImage = "<< {$rendererImageDictionary} >>\nstream\n{$jpegPayload}\nendstream";

    return [
        'before' => $before,
        'after' => $after,
        'fake_object' => $fakeObject,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'image_dictionary' => "<< {$imageDictionary} >>",
        'renderer_image' => $rendererImage,
        'renderer_objects' => [
            30 => "<< /N 4 /Alternate /DeviceCMYK /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
        'pdf' => $pdf,
    ];
};

return [
    'normalizes escaped DCTDecode image filter names before stream-boundary review' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeEscapedFilterBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeEscapedFilterBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan($fixture['image_dictionary']);
        $colorPlan = $renderer->dctDecodeImageColorPlan($fixture['image_dictionary'], $fixture['jpeg_payload']);
        $streamPreview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);

        $plainText = $extractor->extractPlainText($fixture['pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['pdf']);
        $entry = $review['entries'][0] ?? null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $expectedText = ['Before escaped DCT filter', 'After escaped DCT filter'];
        $expectedFilterBoundary = [
            'declared_filter' => 'DCTDecode',
            'canonical_filter' => 'DCTDecode',
            'alias_used' => false,
            'non_null_filter_index' => 0,
            'filters_before_dctdecode' => [],
            'native_prefix_filters' => [],
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

        $t->same($expectedText, $extractor->extractTextLines($fixture['pdf']));
        $t->same($expectedText, $extractor->extractTextRuns($fixture['pdf']));
        $t->same(implode("\n", $expectedText), $plainText);
        $t->same(implode("\n", $expectedText) . "\n", $extractor->naiveGetText($fixture['pdf']));
        $t->true(!str_contains($plainText, 'Escaped DCT payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($plainText, 'Fil#74er'));
        $t->true(!str_contains($plainText, 'DCT#44ecode'));
        $t->true(!str_contains($plainText, 'Color#54ransform'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->same($expectedFilterBoundary, $plan['dctdecode_filter_boundary']);
        $t->same($expectedDecodeParms, $plan['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same('DCTDecode', $colorPlan['filter']);
        $t->same(1, $colorPlan['decode_parms_color_transform']);
        $t->same(true, $colorPlan['decode_parms_color_transform_valid']);
        $t->same(1, $colorPlan['effective_color_transform']);
        $t->same(true, $colorPlan['uses_ycck_transform']);
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same($expectedFilterBoundary, $entry['dctdecode_filter_boundary'] ?? null);
        $t->same($expectedDecodeParms, $entry['filter_details'][0]['decode_parms'] ?? null);
        $t->true(!str_contains($reviewJson, 'Escaped DCT payload leak'));
        $t->true(!str_contains($reviewJson, 'JFIF'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $t->same(true, $streamPreview['review_only_image_stream']);
        $t->same(strlen($fixture['jpeg_payload']), $streamPreview['image_stream']['raw_length'] ?? null);
        $t->true(($streamPreview['image_stream']['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['filters']);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['preview_only_filters']);
        $t->same(false, $streamPreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $streamPreview['image_stream']['decode_failed']);
        $t->same([], $streamPreview['pixels']);
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $streamPreview['notes']));
    },
];
