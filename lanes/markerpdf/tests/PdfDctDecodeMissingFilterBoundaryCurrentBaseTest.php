<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeMissingFilterBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before missing DCT filter) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After missing DCT filter) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Missing DCT filter payload leak) Tj ET';
    $appPayload = "Raw JPEG image stream omits /Filter before fake stream boundaries\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . 'still inside the length-coded APP segment';
    $jpegPayload = "\xff\xd8"
        . "\xff\xe0" . pack('n', strlen($appPayload) + 2) . $appPayload
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused missing-filter DCT fixture must expose a fake endstream marker.');
    }

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [ /ICCBased 30 0 R ] /BitsPerComponent 8 /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";
    $rendererObjects = [
        30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
    ];

    return [
        'pdf' => $pdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => $rendererObjects,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
    ];
};

return [
    'infers omitted DCTDecode filters from complete raw JPEG image streams without leaking fake PDF tokens' => static function (TestRunner $t) use ($pdfDctDecodeMissingFilterBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeMissingFilterBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $expected = [
            'Before missing DCT filter',
            'After missing DCT filter',
        ];

        $plainText = $extractor->extractPlainText($fixture['pdf']);
        $t->same($expected, $extractor->extractTextLines($fixture['pdf']));
        $t->same($expected, $extractor->extractTextRuns($fixture['pdf']));
        $t->same("Before missing DCT filter\nAfter missing DCT filter", $plainText);
        $t->same("Before missing DCT filter\nAfter missing DCT filter\n", $extractor->naiveGetText($fixture['pdf']));
        $t->true(!str_contains($plainText, 'Missing DCT filter payload leak'));
        $t->true(!str_contains($plainText, 'Raw JPEG image stream omits'));
        $t->true(!str_contains($plainText, 'endstream'));

        $review = $extractor->extractImageXObjectBoundaryReview($fixture['pdf']);
        $entry = $review['entries'][0] ?? null;
        $extractorBoundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;
        $rendererPreview = $renderer->iccBasedImageStreamPreviewRows(
            $fixture['renderer_image'],
            $fixture['renderer_objects']
        );
        $imageStream = is_array($rendererPreview['image_stream'] ?? null) ? $rendererPreview['image_stream'] : null;
        $rendererBoundary = is_array($imageStream) ? ($imageStream['dctdecode_stream_boundary'] ?? null) : null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same([], $entry['filters'] ?? null);
        $t->same([], $entry['preview_only_filters'] ?? null);
        $t->same(true, $entry['raw_dct_preview_boundary'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $t->true(is_array($extractorBoundary), 'Extractor inferred DCT marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $extractorBoundary['source'] ?? null);
        $t->same(true, $extractorBoundary['inferred_from_raw_image_stream'] ?? null);
        $t->same(true, $extractorBoundary['declared_filter_missing'] ?? null);
        $t->same(0, $extractorBoundary['jpeg_soi_offset'] ?? null);
        $t->same(0, $extractorBoundary['jpeg_marker_fill_byte_count'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $extractorBoundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $extractorBoundary['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $extractorBoundary['review_stream_length'] ?? null);
        $t->same(false, $extractorBoundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(false, $extractorBoundary['sos_marker_seen'] ?? null);
        $t->same(false, $extractorBoundary['byte_stuffed_ff00_seen'] ?? null);
        $t->same(false, $extractorBoundary['restart_marker_seen'] ?? null);
        $t->same(true, $extractorBoundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $extractorBoundary['payload_in_visible_text'] ?? null);
        $t->same(true, $extractorBoundary['review_only'] ?? null);
        $t->same(false, $extractorBoundary['native_raster_decode'] ?? null);

        $t->same(true, $rendererPreview['review_only_image_stream']);
        $t->true(is_array($imageStream), 'Renderer image stream metadata should be present.');
        $t->same([], $imageStream['filters'] ?? null);
        $t->same([], $imageStream['preview_only_filters'] ?? null);
        $t->same([], $imageStream['unsupported_filters'] ?? null);
        $t->same(true, $imageStream['raw_dct_preview_boundary'] ?? null);
        $t->same(false, $imageStream['decoded_with_current_filters'] ?? null);
        $t->same(false, $imageStream['decode_failed'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $imageStream['raw_length'] ?? null);
        $t->true(($imageStream['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(null, $imageStream['decoded_length'] ?? null);
        $t->true(is_array($rendererBoundary), 'Renderer inferred DCT marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $rendererBoundary['source'] ?? null);
        $t->same(true, $rendererBoundary['inferred_from_raw_image_stream'] ?? null);
        $t->same(true, $rendererBoundary['declared_filter_missing'] ?? null);
        $t->same(0, $rendererBoundary['jpeg_soi_offset'] ?? null);
        $t->same(0, $rendererBoundary['jpeg_marker_fill_byte_count'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['review_stream_length'] ?? null);
        $t->same(false, $rendererBoundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(false, $rendererBoundary['sos_marker_seen'] ?? null);
        $t->same(false, $rendererBoundary['byte_stuffed_ff00_seen'] ?? null);
        $t->same(false, $rendererBoundary['restart_marker_seen'] ?? null);
        $t->same(true, $rendererBoundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $rendererBoundary['payload_in_visible_text'] ?? null);
        $t->same(true, $rendererBoundary['review_only'] ?? null);
        $t->same(false, $rendererBoundary['native_raster_decode'] ?? null);
        $t->same([], $rendererPreview['pixels']);
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $rendererPreview['notes']));
    },
];
