<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'records DCTDecode JPEG marker boundaries on nested soft-mask image reviews' => static function (TestRunner $t): void {
        $before = 'BT /F1 12 Tf 72 720 Td (Before soft mask DCT stream boundary) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After soft mask DCT stream boundary) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Soft mask DCT marker payload leak) Tj ET';
        $segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
        $sosPayload = "\x01\x01\x00\x00\x3f\x00";
        $scanPayload = "soft mask scan bytes before stuffed ff \xff\x00\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "restart marker remains soft-mask image bytes \xff\xd0";
        $softMaskJpeg = "\xff\xd8"
            . $segment(0xda, $sosPayload)
            . $scanPayload
            . "\xff\xd9";
        $fakeTerminatorOffset = strpos($softMaskJpeg, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused DCT soft-mask boundary fixture must expose a fake endstream marker.');
        }

        $primaryPayload = "\x00\x7f\xff";
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 6 0 R /Length " . strlen($primaryPayload) . " >>\nstream\n{$primaryPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$softMaskJpeg}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? null;
        $softMask = is_array($entry) ? ($entry['soft_mask_review'] ?? null) : null;
        $boundary = is_array($softMask) ? ($softMask['dctdecode_stream_boundary'] ?? null) : null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);

        $t->same(['Before soft mask DCT stream boundary', 'After soft mask DCT stream boundary'], $extractor->extractTextLines($pdf));
        $t->same(['Before soft mask DCT stream boundary', 'After soft mask DCT stream boundary'], $extractor->extractTextRuns($pdf));
        $t->same("Before soft mask DCT stream boundary\nAfter soft mask DCT stream boundary", $plainText);
        $t->same("Before soft mask DCT stream boundary\nAfter soft mask DCT stream boundary\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Soft mask DCT marker payload leak'));
        $t->true(!str_contains($plainText, 'restart marker remains soft-mask image bytes'));
        $t->true(!str_contains($plainText, 'endstream'));

        $t->true(is_array($entry), 'Primary image review row should be present.');
        $t->true(is_array($softMask), 'Nested DCT soft-mask review row should be present.');
        $t->same('soft_mask_stream', $softMask['type'] ?? null);
        $t->same(['DCTDecode'], $softMask['filters'] ?? null);
        $t->same(['DCTDecode'], $softMask['preview_only_filters'] ?? null);
        $t->same(strlen($softMaskJpeg), $softMask['raw_length'] ?? null);
        $t->true(($softMask['raw_length'] ?? 0) > $fakeTerminatorOffset);
        $t->same(false, $softMask['native_raster_decode'] ?? null);
        $t->same(false, $softMask['decoded_with_current_filters'] ?? null);
        $t->same(false, $softMask['payload_in_visible_text'] ?? null);
        $t->true(is_array($boundary), 'Nested DCT soft-mask stream boundary should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
        $t->same(0, $boundary['jpeg_soi_offset'] ?? null);
        $t->same(strlen($softMaskJpeg), $boundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(strlen($softMaskJpeg), $boundary['raw_stream_length'] ?? null);
        $t->same(strlen($softMaskJpeg), $boundary['review_stream_length'] ?? null);
        $t->same(false, $boundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(true, $boundary['sos_marker_seen'] ?? null);
        $t->same(true, $boundary['byte_stuffed_ff00_seen'] ?? null);
        $t->same(true, $boundary['restart_marker_seen'] ?? null);
        $t->same(true, $boundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $boundary['payload_in_visible_text'] ?? null);
        $t->same(true, $boundary['review_only'] ?? null);
        $t->same(false, $boundary['native_raster_decode'] ?? null);
        $t->true(!str_contains($reviewJson, 'Soft mask DCT marker payload leak'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
