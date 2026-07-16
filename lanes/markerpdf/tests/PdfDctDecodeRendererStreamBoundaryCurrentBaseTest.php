<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeRendererStreamBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before renderer DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After renderer DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Renderer DCT payload leak) Tj ET';
    $segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
    $sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x03"
        . "\x01\x11\x00"
        . "\x02\x11\x00"
        . "\x03\x11\x00";
    $sosPayload = "\x03"
        . "\x01\x00"
        . "\x02\x11"
        . "\x03\x11"
        . "\x00\x3f\x00";
    $scanPayload = "renderer scan bytes before stuffed ff \xff\x00\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "restart marker remains renderer-owned image bytes \xff\xd0";
    $jpegPayload = "\xff\xd8"
        . $segment(0xc0, $sofPayload)
        . $segment(0xda, $sosPayload)
        . $scanPayload
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused renderer DCT fixture must expose a fake endstream inside scan data.');
    }

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererObjects = [
        30 => '[ /ICCBased 31 0 R ]',
        31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
    ];
    $rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";

    return [
        'page_pdf' => $pagePdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => $rendererObjects,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
    ];
};

return [
    'carries DCTDecode JPEG marker boundary into renderer image stream metadata' => static function (TestRunner $t) use ($pdfDctDecodeRendererStreamBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeRendererStreamBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $expected = [
            'Before renderer DCT stream',
            'After renderer DCT stream',
        ];
        $plainText = $extractor->extractPlainText($fixture['page_pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $extractorBoundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;
        $preview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);
        $imageStream = $preview['image_stream'] ?? null;
        $rendererBoundary = is_array($imageStream) ? ($imageStream['dctdecode_stream_boundary'] ?? null) : null;

        $t->same($expected, $extractor->extractTextLines($fixture['page_pdf']));
        $t->same("Before renderer DCT stream\nAfter renderer DCT stream", $plainText);
        $t->true(!str_contains($plainText, 'Renderer DCT payload leak'));
        $t->true(!str_contains($plainText, 'restart marker remains renderer-owned'));
        $t->true(!str_contains($plainText, 'endstream'));

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->true(is_array($extractorBoundary), 'Extractor DCT marker boundary should be present.');
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(true, $extractorBoundary['sos_marker_seen'] ?? null);
        $t->same(true, $extractorBoundary['byte_stuffed_ff00_seen'] ?? null);
        $t->same(true, $extractorBoundary['restart_marker_seen'] ?? null);

        $t->same(true, $preview['review_only_image_stream']);
        $t->true(is_array($imageStream), 'Renderer image stream metadata should be present.');
        $t->same(['DCTDecode'], $imageStream['filters'] ?? null);
        $t->same(['DCTDecode'], $imageStream['preview_only_filters'] ?? null);
        $t->same(false, $imageStream['decoded_with_current_filters'] ?? null);
        $t->same(false, $imageStream['decode_failed'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $imageStream['raw_length'] ?? null);
        $t->true(is_array($rendererBoundary), 'Renderer DCT marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $rendererBoundary['source'] ?? null);
        $t->same($extractorBoundary['jpeg_soi_offset'] ?? null, $rendererBoundary['jpeg_soi_offset'] ?? null);
        $t->same($extractorBoundary['jpeg_eoi_end_offset'] ?? null, $rendererBoundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['review_stream_length'] ?? null);
        $t->same(false, $rendererBoundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(true, $rendererBoundary['sos_marker_seen'] ?? null);
        $t->same(true, $rendererBoundary['byte_stuffed_ff00_seen'] ?? null);
        $t->same(true, $rendererBoundary['restart_marker_seen'] ?? null);
        $t->same(true, $rendererBoundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $rendererBoundary['payload_in_visible_text'] ?? null);
        $t->same(true, $rendererBoundary['review_only'] ?? null);
        $t->same(false, $rendererBoundary['native_raster_decode'] ?? null);
        $t->same([], $preview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
