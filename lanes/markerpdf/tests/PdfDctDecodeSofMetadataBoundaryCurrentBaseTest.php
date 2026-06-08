<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeSofMetadataBoundaryCurrentBaseSegment = static function (int $marker, string $payload): string {
    return "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
};

$pdfDctDecodeSofMetadataBoundaryCurrentBaseFixture = static function () use ($pdfDctDecodeSofMetadataBoundaryCurrentBaseSegment): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before DCT SOF metadata) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After DCT SOF metadata) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (DCT SOF APP payload leak) Tj ET';
    $fakeSofPayload = "\x08" . pack('n', 99) . pack('n', 99) . "\x01" . "\x01\x11\x00";
    $appPayload = "JFIF\0\1\1\0\0\1\0\1\0\0"
        . "\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . $pdfDctDecodeSofMetadataBoundaryCurrentBaseSegment(0xc0, $fakeSofPayload);
    $sofPayload = "\x08" . pack('n', 23) . pack('n', 37) . "\x03"
        . "\x01\x11\x00"
        . "\x02\x11\x00"
        . "\x03\x11\x00";
    $sosPayload = "\x03"
        . "\x01\x00"
        . "\x02\x11"
        . "\x03\x11"
        . "\x00\x3f\x00";
    $jpegPayload = "\xff\xd8"
        . $pdfDctDecodeSofMetadataBoundaryCurrentBaseSegment(0xe0, $appPayload)
        . $pdfDctDecodeSofMetadataBoundaryCurrentBaseSegment(0xc0, $sofPayload)
        . $pdfDctDecodeSofMetadataBoundaryCurrentBaseSegment(0xda, $sosPayload)
        . "entropy bytes stay image only"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused DCT SOF metadata fixture must expose a fake endstream inside APP metadata.');
    }

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererImageObject = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";

    return [
        'pdf' => $pdf,
        'renderer_image_object' => $rendererImageObject,
        'renderer_objects' => [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
    ];
};

return [
    'records DCTDecode SOF dimensions as review metadata without leaking APP payload text' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeSofMetadataBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeSofMetadataBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $lines = $extractor->extractTextLines($fixture['pdf']);
        $plainText = $extractor->extractPlainText($fixture['pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['pdf']);
        $entry = $review['entries'][0] ?? null;
        $boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;
        $preview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image_object'], $fixture['renderer_objects']);
        $rendererBoundary = $preview['image_stream']['dctdecode_stream_boundary'] ?? null;

        $t->same(['Before DCT SOF metadata', 'After DCT SOF metadata'], $lines);
        $t->same("Before DCT SOF metadata\nAfter DCT SOF metadata", $plainText);
        $t->true(!str_contains($plainText, 'DCT SOF APP payload leak'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->true(is_array($boundary), 'Extractor DCTDecode SOF marker metadata should be present.');
        $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
        $t->same(0, $boundary['jpeg_soi_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(true, $boundary['sos_marker_seen'] ?? null);
        $t->same(true, $boundary['sof_marker_seen'] ?? null);
        $t->same('SOF0', $boundary['jpeg_sof_marker'] ?? null);
        $t->same(8, $boundary['jpeg_precision'] ?? null);
        $t->same(37, $boundary['jpeg_width'] ?? null);
        $t->same(23, $boundary['jpeg_height'] ?? null);
        $t->same(3, $boundary['jpeg_component_count'] ?? null);
        $t->same(false, $boundary['native_raster_decode'] ?? null);
        $t->same(false, $boundary['payload_in_visible_text'] ?? null);
        $t->same(true, $boundary['review_only'] ?? null);
        $t->true(is_array($rendererBoundary), 'Renderer DCTDecode SOF marker metadata should be present.');
        $t->same(true, $rendererBoundary['sof_marker_seen'] ?? null);
        $t->same('SOF0', $rendererBoundary['jpeg_sof_marker'] ?? null);
        $t->same(8, $rendererBoundary['jpeg_precision'] ?? null);
        $t->same(37, $rendererBoundary['jpeg_width'] ?? null);
        $t->same(23, $rendererBoundary['jpeg_height'] ?? null);
        $t->same(3, $rendererBoundary['jpeg_component_count'] ?? null);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
