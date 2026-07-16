<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeSosMarkerBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before SOS DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After SOS DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (SOS marker DCT payload leak) Tj ET';
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
    $scanPayload = "entropy before stuffed ff byte \xff\x00\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "restart marker follows before true JPEG EOI \xff\xd0";
    $jpegPayload = "\xff\xd8"
        . $segment(0xc0, $sofPayload)
        . $segment(0xda, $sosPayload)
        . $scanPayload
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused DCT SOS fixture must expose a fake endstream inside scan data.');
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

    return [
        'stream_only_pdf' => $streamOnlyPdf,
        'page_pdf' => $pagePdf,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
    ];
};

return [
    'records DCTDecode SOS marker boundaries while excluding stuffed scan-data payload text' => static function (TestRunner $t) use ($pdfDctDecodeSosMarkerBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeSosMarkerBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Before SOS DCT stream',
            'After SOS DCT stream',
        ];

        foreach ([$fixture['stream_only_pdf'], $fixture['page_pdf']] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before SOS DCT stream\nAfter SOS DCT stream", $plainText);
            $t->same("Before SOS DCT stream\nAfter SOS DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'SOS marker DCT payload leak'));
            $t->true(!str_contains($plainText, 'restart marker follows'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same('DCTDecode', $entry['dctdecode_filter_boundary']['canonical_filter'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(is_array($boundary), 'DCT stream marker boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
        $t->same(0, $boundary['jpeg_soi_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['review_stream_length'] ?? null);
        $t->same(false, $boundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(true, $boundary['sos_marker_seen'] ?? null);
        $t->same(true, $boundary['byte_stuffed_ff00_seen'] ?? null);
        $t->same(true, $boundary['restart_marker_seen'] ?? null);
        $t->same(true, $boundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $boundary['payload_in_visible_text'] ?? null);
        $t->same(true, $boundary['review_only'] ?? null);
        $t->same(false, $boundary['native_raster_decode'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
