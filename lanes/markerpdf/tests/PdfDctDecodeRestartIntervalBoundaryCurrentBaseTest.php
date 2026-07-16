<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeRestartIntervalBoundaryFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before DRI DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After DRI DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (DCT DRI payload leak) Tj ET';
    $segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker)
        . pack('n', strlen($payload) + 2)
        . $payload;
    $appPayload = "APP bytes with marker-looking restart \xff\xd0 and stuffed \xff\x00 payload\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . 'still inside length-coded APP data';
    $sosPayload = "\x01\x01\x00\x00\x3f\x00";
    $jpegPayload = "\xff\xd8"
        . $segment(0xe0, $appPayload)
        . "\xff\xdd\x00\x04\x00\x04"
        . $segment(0xda, $sosPayload)
        . 'scan data without restart markers'
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused DCT DRI fixture must expose a fake endstream inside APP payload.');
    }

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";

    return [
        'page_pdf' => $pagePdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => [
            30 => '[ /ICCBased 31 0 R ]',
            31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
    ];
};

return [
    'records DCTDecode DRI restart intervals without treating APP payload bytes as restart markers' => static function (TestRunner $t) use ($pdfDctDecodeRestartIntervalBoundaryFixture): void {
        $fixture = $pdfDctDecodeRestartIntervalBoundaryFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $expectedLines = ['Before DRI DCT stream', 'After DRI DCT stream'];

        $plainText = $extractor->extractPlainText($fixture['page_pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = is_array($review['entries'][0] ?? null) ? $review['entries'][0] : [];
        $extractorBoundary = is_array($entry['dctdecode_stream_boundary'] ?? null)
            ? $entry['dctdecode_stream_boundary']
            : [];
        $preview = $renderer->iccBasedImageStreamPreviewRows(
            $fixture['renderer_image'],
            $fixture['renderer_objects']
        );
        $imageStream = is_array($preview['image_stream'] ?? null) ? $preview['image_stream'] : [];
        $rendererBoundary = is_array($imageStream['dctdecode_stream_boundary'] ?? null)
            ? $imageStream['dctdecode_stream_boundary']
            : [];

        $t->same($expectedLines, $extractor->extractTextLines($fixture['page_pdf']));
        $t->same($expectedLines, $extractor->extractTextRuns($fixture['page_pdf']));
        $t->same("Before DRI DCT stream\nAfter DRI DCT stream", $plainText);
        $t->true(!str_contains($plainText, 'DCT DRI payload leak'));
        $t->true(!str_contains($plainText, 'APP bytes with marker-looking restart'));
        $t->true(!str_contains($plainText, 'endstream'));

        foreach ([$extractorBoundary, $rendererBoundary] as $boundary) {
            $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
            $t->same(0, $boundary['jpeg_soi_offset'] ?? null);
            $t->same(strlen($fixture['jpeg_payload']), $boundary['jpeg_eoi_end_offset'] ?? null);
            $t->same(strlen($fixture['jpeg_payload']), $boundary['raw_stream_length'] ?? null);
            $t->same(strlen($fixture['jpeg_payload']), $boundary['review_stream_length'] ?? null);
            $t->same(true, $boundary['sos_marker_seen'] ?? null);
            $t->same(true, $boundary['dri_marker_seen'] ?? null);
            $t->same(4, $boundary['jpeg_restart_interval'] ?? null);
            $t->same(false, $boundary['restart_marker_seen'] ?? null);
            $t->same(false, $boundary['byte_stuffed_ff00_seen'] ?? null);
            $t->same(true, $boundary['jpeg_marker_framing_used'] ?? null);
            $t->same(false, $boundary['payload_in_visible_text'] ?? null);
            $t->same(false, $boundary['native_raster_decode'] ?? null);
        }

        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $imageStream['raw_length'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
