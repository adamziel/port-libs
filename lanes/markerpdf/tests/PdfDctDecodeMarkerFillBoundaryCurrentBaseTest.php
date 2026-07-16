<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeMarkerFillBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before marker-fill DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After marker-fill DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Marker-fill DCT payload leak) Tj ET';
    $appPayload = "JPEG APP segment uses a fill-prefixed SOI before fake stream boundaries\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . 'still inside the length-coded APP segment';
    $jpegPayload = "\xff\xff\xd8"
        . "\xff\xe0" . pack('n', strlen($appPayload) + 2) . $appPayload
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused DCT marker-fill fixture must expose a fake endstream marker.');
    }

    $streamOnlyPdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";
    $rendererObjects = [
        30 => '[ /ICCBased 31 0 R ]',
        31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
    ];

    return [
        'stream_only_pdf' => $streamOnlyPdf,
        'page_pdf' => $pagePdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => $rendererObjects,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
    ];
};

return [
    'accepts DCTDecode JPEG marker fill before SOI while rejecting fake endstream boundaries' => static function (TestRunner $t) use ($pdfDctDecodeMarkerFillBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeMarkerFillBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $expected = [
            'Before marker-fill DCT stream',
            'After marker-fill DCT stream',
        ];

        foreach ([$fixture['stream_only_pdf'], $fixture['page_pdf']] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);
            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before marker-fill DCT stream\nAfter marker-fill DCT stream", $plainText);
            $t->same("Before marker-fill DCT stream\nAfter marker-fill DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Marker-fill DCT payload leak'));
            $t->true(!str_contains($plainText, 'JPEG APP segment uses'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $extractorBoundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;
        $preview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);
        $imageStream = is_array($preview['image_stream'] ?? null) ? $preview['image_stream'] : null;
        $rendererBoundary = is_array($imageStream) ? ($imageStream['dctdecode_stream_boundary'] ?? null) : null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $t->true(is_array($extractorBoundary), 'Extractor DCT marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $extractorBoundary['source'] ?? null);
        $t->same(0, $extractorBoundary['jpeg_soi_offset'] ?? null);
        $t->same(1, $extractorBoundary['jpeg_marker_fill_byte_count'] ?? null);
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

        $t->same(true, $preview['review_only_image_stream']);
        $t->true(is_array($imageStream), 'Renderer image stream metadata should be present.');
        $t->same(['DCTDecode'], $imageStream['filters'] ?? null);
        $t->same(['DCTDecode'], $imageStream['preview_only_filters'] ?? null);
        $t->same(false, $imageStream['decoded_with_current_filters'] ?? null);
        $t->same(false, $imageStream['decode_failed'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $imageStream['raw_length'] ?? null);
        $t->true(($imageStream['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->true(is_array($rendererBoundary), 'Renderer DCT marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $rendererBoundary['source'] ?? null);
        $t->same(0, $rendererBoundary['jpeg_soi_offset'] ?? null);
        $t->same(1, $rendererBoundary['jpeg_marker_fill_byte_count'] ?? null);
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
        $t->same([], $preview['pixels']);
    },
];
