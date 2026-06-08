<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused DCTDecode native-prefix post-EOI fixture must fit one deflate stored block.');
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

$pdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseFixture = static function () use (
    $pdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseZlibStored
): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before prefix post EOI DCT) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After prefix post EOI DCT) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0prefix-post-eoi\xff\xd9";
    $postEoiSurplus = "\nBT /F1 12 Tf 72 700 Td (Prefix post EOI DCT surplus leak) Tj ET\n";
    $decodedPayload = $jpegPayload . $postEoiSurplus;
    $encodedPayload = $pdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseZlibStored($decodedPayload);
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode] /Length ' . strlen($encodedPayload) . ' >>';
    $rendererDictionary = str_replace('/DeviceRGB', '30 0 R', $imageDictionary);

    return [
        'expected_lines' => [
            'Before prefix post EOI DCT',
            'After prefix post EOI DCT',
        ],
        'jpeg_payload' => $jpegPayload,
        'post_eoi_surplus' => $postEoiSurplus,
        'decoded_payload' => $decodedPayload,
        'encoded_payload' => $encodedPayload,
        'page_pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF",
        'renderer_image' => "{$rendererDictionary}\nstream\n{$encodedPayload}\nendstream",
        'renderer_objects' => [
            30 => '[ /ICCBased 31 0 R ]',
            31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
    ];
};

return [
    'clips native-prefix decoded DCTDecode post-EOI surplus before image review handoff' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $plainText = $extractor->extractPlainText($fixture['page_pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;
        $rendererPreview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);
        $rendererStream = $rendererPreview['image_stream'] ?? null;
        $rendererBoundary = is_array($rendererStream) ? ($rendererStream['dctdecode_stream_boundary'] ?? null) : null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $rendererJson = json_encode($rendererPreview, JSON_THROW_ON_ERROR);

        $t->same($fixture['expected_lines'], $extractor->extractTextLines($fixture['page_pdf']));
        $t->same($fixture['expected_lines'], $extractor->extractTextRuns($fixture['page_pdf']));
        $t->same(implode("\n", $fixture['expected_lines']), $plainText);
        $t->same(implode("\n", $fixture['expected_lines']) . "\n", $extractor->naiveGetText($fixture['page_pdf']));
        $t->true(!str_contains($plainText, 'Prefix post EOI DCT surplus leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($reviewJson, 'Prefix post EOI DCT surplus leak'));
        $t->true(!str_contains($rendererJson, 'Prefix post EOI DCT surplus leak'));

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['FlateDecode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $entry['raw_length'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(true, $entry['native_prefix_decoded'] ?? null);
        $t->same(strlen($fixture['decoded_payload']), $entry['native_prefix_decoded_length'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(is_array($boundary), 'Decoded-prefix DCT marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
        $t->same(0, $boundary['jpeg_soi_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $boundary['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['review_stream_length'] ?? null);
        $t->same(true, $boundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(true, $boundary['review_stream_decoded_from_native_prefix'] ?? null);
        $t->same(['FlateDecode'], $boundary['native_prefix_filters'] ?? null);
        $t->same('DCTDecode', $boundary['stopped_before_filter'] ?? null);
        $t->same(true, $boundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $boundary['payload_in_visible_text'] ?? null);
        $t->same(false, $boundary['native_raster_decode'] ?? null);

        $t->same(true, $rendererPreview['review_only_image_stream']);
        $t->true(is_array($rendererStream), 'Renderer image stream metadata should be present.');
        $t->same(['FlateDecode', 'DCTDecode'], $rendererStream['filters'] ?? null);
        $t->same(['DCTDecode'], $rendererStream['preview_only_filters'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $rendererStream['raw_length'] ?? null);
        $t->same(false, $rendererStream['decoded_with_current_filters'] ?? null);
        $t->same(false, $rendererStream['decode_failed'] ?? null);
        $t->same(true, $rendererStream['native_prefix_decoded'] ?? null);
        $t->same(strlen($fixture['decoded_payload']), $rendererStream['native_prefix_decoded_length'] ?? null);
        $t->true(is_array($rendererBoundary), 'Renderer decoded-prefix DCT marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $rendererBoundary['source'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $rendererBoundary['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['review_stream_length'] ?? null);
        $t->same(true, $rendererBoundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(true, $rendererBoundary['review_stream_decoded_from_native_prefix'] ?? null);
        $t->same(['FlateDecode'], $rendererBoundary['native_prefix_filters'] ?? null);
        $t->same('DCTDecode', $rendererBoundary['stopped_before_filter'] ?? null);
        $t->same(true, $rendererBoundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $rendererBoundary['payload_in_visible_text'] ?? null);
        $t->same(false, $rendererBoundary['native_raster_decode'] ?? null);
        $t->same([], $rendererPreview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
