<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodePreSoiBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before pre-SOI DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After pre-SOI DCT stream) Tj ET';
    $preSoiLeak = 'BT /F1 12 Tf 72 700 Td (Pre-SOI DCT garbage leak) Tj ET';
    $appPayload = "JFIF\0pre-soi!";
    $jpegPayload = $preSoiLeak
        . "\n% fake PDF content before JPEG SOI must stay image-owned\n"
        . "\xff\xd8"
        . "\xff\xe0" . pack('n', strlen($appPayload) + 2) . $appPayload
        . "\xff\xd9";
    $jpegSoiOffset = strpos($jpegPayload, "\xff\xd8");
    if ($jpegSoiOffset === false) {
        throw new RuntimeException('Focused DCT pre-SOI fixture must contain a JPEG SOI marker.');
    }

    $streamOnlyPdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegPayload) . " >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegPayload) . " >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    $rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegPayload) . " >>\nstream\n{$jpegPayload}\nendstream";

    return [
        'stream_only_pdf' => $streamOnlyPdf,
        'page_pdf' => $pagePdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => [
            30 => '[ /ICCBased 31 0 R ]',
            31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
        'jpeg_payload' => $jpegPayload,
        'jpeg_soi_offset' => $jpegSoiOffset,
        'pre_soi_sha256' => hash('sha256', substr($jpegPayload, 0, $jpegSoiOffset)),
    ];
};

return [
    'records pre-SOI DCTDecode garbage as image-owned before WordPress media review' => static function (
        TestRunner $t
    ) use ($pdfDctDecodePreSoiBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodePreSoiBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $expected = [
            'Before pre-SOI DCT stream',
            'After pre-SOI DCT stream',
        ];
        $plainPageText = $extractor->extractPlainText($fixture['page_pdf']);
        $plainStreamText = $extractor->extractPlainText($fixture['stream_only_pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;
        $rendererPreview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);
        $rendererBoundary = $rendererPreview['image_stream']['dctdecode_stream_boundary'] ?? null;

        $t->same($expected, $extractor->extractTextLines($fixture['page_pdf']));
        $t->same($expected, $extractor->extractTextLines($fixture['stream_only_pdf']));
        $t->same("Before pre-SOI DCT stream\nAfter pre-SOI DCT stream", $plainPageText);
        $t->same("Before pre-SOI DCT stream\nAfter pre-SOI DCT stream", $plainStreamText);
        $t->true(!str_contains($plainPageText, 'Pre-SOI DCT garbage leak'));
        $t->true(!str_contains($plainStreamText, 'Pre-SOI DCT garbage leak'));
        $t->true(!str_contains($plainPageText, 'fake PDF content before JPEG SOI'));
        $t->true(!str_contains($plainStreamText, 'fake PDF content before JPEG SOI'));

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(is_array($boundary), 'DCT marker boundary should be explicit.');
        $t->same('dctdecode_jpeg_marker_boundary_unverified', $boundary['source'] ?? null);
        $t->same(false, $boundary['valid_jpeg_marker_boundary'] ?? null);
        $t->same('pre_jpeg_soi_non_padding_bytes', $boundary['invalid_reason'] ?? null);
        $t->same($fixture['jpeg_soi_offset'], $boundary['jpeg_soi_offset'] ?? null);
        $t->same($fixture['jpeg_soi_offset'], $boundary['pre_jpeg_soi_byte_count'] ?? null);
        $t->same($fixture['pre_soi_sha256'], $boundary['pre_jpeg_soi_sha256'] ?? null);
        $t->same(false, $boundary['stream_trimmed_to_jpeg_soi'] ?? null);
        $t->same(false, $boundary['pre_jpeg_soi_payload_in_visible_text'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(false, $boundary['jpeg_marker_framing_used'] ?? null);

        $t->same(true, $rendererPreview['review_only_image_stream']);
        $t->same(['DCTDecode'], $rendererPreview['image_stream']['filters']);
        $t->same(['DCTDecode'], $rendererPreview['image_stream']['preview_only_filters']);
        $t->same(strlen($fixture['jpeg_payload']), $rendererPreview['image_stream']['raw_length']);
        $t->same(false, $rendererPreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $rendererPreview['image_stream']['decode_failed']);
        $t->true(is_array($rendererBoundary), 'Renderer DCT marker boundary should be explicit.');
        $t->same('dctdecode_jpeg_marker_boundary_unverified', $rendererBoundary['source'] ?? null);
        $t->same(false, $rendererBoundary['valid_jpeg_marker_boundary'] ?? null);
        $t->same('pre_jpeg_soi_non_padding_bytes', $rendererBoundary['invalid_reason'] ?? null);
        $t->same($fixture['jpeg_soi_offset'], $rendererBoundary['jpeg_soi_offset'] ?? null);
        $t->same($fixture['jpeg_soi_offset'], $rendererBoundary['pre_jpeg_soi_byte_count'] ?? null);
        $t->same($fixture['pre_soi_sha256'], $rendererBoundary['pre_jpeg_soi_sha256'] ?? null);
        $t->same(false, $rendererBoundary['stream_trimmed_to_jpeg_soi'] ?? null);
        $t->same(false, $rendererBoundary['pre_jpeg_soi_payload_in_visible_text'] ?? null);
        $t->same([], $rendererPreview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
