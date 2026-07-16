<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeDuplicateFilterBoundaryCurrentBaseZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused duplicate DCTDecode filter fixture must fit one deflate stored block.');
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

$pdfDctDecodeDuplicateFilterBoundaryCurrentBaseFixture = static function () use ($pdfDctDecodeDuplicateFilterBoundaryCurrentBaseZlibStored): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before duplicate DCT filter) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After duplicate DCT filter) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0duplicate-filter review bytes\n"
        . "BT /F1 12 Tf 72 700 Td (Duplicate DCT filter payload leak) Tj ET"
        . "\xff\xd9";
    $encodedPayload = $pdfDctDecodeDuplicateFilterBoundaryCurrentBaseZlibStored($jpegPayload);
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Filter /DCTDecode /Length ' . strlen($encodedPayload) . ' >>';

    $rendererDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter /FlateDecode /Filter /DCTDecode /Length ' . strlen($encodedPayload) . ' >>';

    return [
        'expected_lines' => ['Before duplicate DCT filter', 'After duplicate DCT filter'],
        'encoded_payload' => $encodedPayload,
        'jpeg_payload' => $jpegPayload,
        'page_pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF",
        'renderer_dictionary' => $rendererDictionary,
        'renderer_image' => "{$rendererDictionary}\nstream\n{$encodedPayload}\nendstream",
        'renderer_objects' => [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
    ];
};

return [
    'fails closed on duplicate image filter declarations while preserving DCT review metadata' => static function (TestRunner $t) use ($pdfDctDecodeDuplicateFilterBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeDuplicateFilterBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();

        $plainText = $extractor->extractPlainText($fixture['page_pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;

        $t->same($fixture['expected_lines'], $extractor->extractTextLines($fixture['page_pdf']));
        $t->same("Before duplicate DCT filter\nAfter duplicate DCT filter", $plainText);
        $t->true(!str_contains($plainText, 'Duplicate DCT filter payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(false, $entry['filters_resolved'] ?? null);
        $t->same(['FlateDecode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(1, $entry['duplicate_filter_declaration_count'] ?? null);
        $t->same('reject_duplicate_filter_declarations', $entry['filter_operand_policy'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $entry['raw_length'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $boundary = is_array($entry) ? ($entry['dctdecode_filter_boundary'] ?? null) : null;
        $t->true(is_array($boundary), 'DCTDecode filter boundary metadata should be preserved for duplicate filters.');
        $t->same('DCTDecode', $boundary['canonical_filter'] ?? null);
        $t->same(['FlateDecode'], $boundary['filters_before_dctdecode'] ?? null);
        $t->same(['FlateDecode'], $boundary['native_prefix_filters'] ?? null);
        $t->same([], $boundary['filters_after_dctdecode'] ?? null);
        $t->same(true, $boundary['dctdecode_is_terminal_filter'] ?? null);
        $t->same(true, $boundary['review_only'] ?? null);
        $t->same(false, $boundary['native_raster_decode'] ?? null);
    },
    'renders duplicate DCT image filters as review-only instead of native ICC preview samples' => static function (TestRunner $t) use ($pdfDctDecodeDuplicateFilterBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeDuplicateFilterBoundaryCurrentBaseFixture();
        $renderer = new PdfImageRenderer();

        $preview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);
        $plan = $renderer->imageColorSpaceSoftMaskPlan($fixture['renderer_dictionary'], $fixture['renderer_objects']);

        $t->same(true, $preview['review_only_image_stream']);
        $t->same(0, $preview['preview_pixel_count']);
        $t->same([], $preview['pixels']);
        $t->same(['MalformedFilterOperand', 'FlateDecode', 'DCTDecode'], $preview['image_stream']['filters'] ?? null);
        $t->same(['DCTDecode'], $preview['image_stream']['preview_only_filters'] ?? null);
        $t->same(['MalformedFilterOperand', 'DCTDecode'], $preview['image_stream']['unsupported_filters'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $preview['image_stream']['raw_length'] ?? null);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters'] ?? null);
        $t->same(true, $preview['image_stream']['decode_failed'] ?? null);
        $t->same(1, $preview['image_filter_boundary']['duplicate_filter_declaration_count'] ?? null);
        $t->same('reject_duplicate_filter_declarations', $preview['image_filter_boundary']['filter_operand_policy'] ?? null);
        $t->contains('duplicate_image_filter_declarations_fail_closed', implode(',', $preview['notes']));
        $t->contains('malformed_image_filter_operand_fail_closed', implode(',', $preview['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $preview['notes']));
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $preview['notes']));

        $boundary = $plan['dctdecode_filter_boundary'] ?? null;
        $t->true(is_array($boundary), 'Renderer DCTDecode boundary should remain visible when duplicate filters fail closed.');
        $t->same('DCTDecode', $boundary['canonical_filter'] ?? null);
        $t->same(['MalformedFilterOperand', 'FlateDecode'], $boundary['filters_before_dctdecode'] ?? null);
        $t->same(['MalformedFilterOperand', 'FlateDecode'], $boundary['native_prefix_filters'] ?? null);
        $t->same(true, $boundary['dctdecode_is_terminal_filter'] ?? null);
        $t->same(true, $boundary['review_only'] ?? null);
    },
];
