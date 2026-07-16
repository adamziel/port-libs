<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeBomPrefixBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before BOM DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After BOM DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (BOM DCT fake object leak) Tj ET';
    $jpegPayload = "\xef\xbb\xbf\xff\xd8BOM-prefixed JPEG bytes before fake boundary\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "more BOM-prefixed image bytes before real EOI\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused BOM-prefixed DCT fixture must expose a fake endstream marker.');
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
    'recovers BOM-prefixed DCTDecode image stream boundaries before WordPress text handoff' => static function (TestRunner $t) use ($pdfDctDecodeBomPrefixBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeBomPrefixBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $expected = [
            'Before BOM DCT stream',
            'After BOM DCT stream',
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
        $t->same("Before BOM DCT stream\nAfter BOM DCT stream", $plainPageText);
        $t->same("Before BOM DCT stream\nAfter BOM DCT stream", $plainStreamText);
        $t->true(!str_contains($plainPageText, 'BOM DCT fake object leak'));
        $t->true(!str_contains($plainStreamText, 'BOM DCT fake object leak'));
        $t->true(!str_contains($plainPageText, 'BOM-prefixed JPEG bytes'));
        $t->true(!str_contains($plainStreamText, 'BOM-prefixed JPEG bytes'));
        $t->true(!str_contains($plainPageText, 'endstream'));
        $t->true(!str_contains($plainStreamText, 'endstream'));

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(is_array($boundary), 'BOM-prefixed DCT marker boundary should be explicit.');
        $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
        $t->same(3, $boundary['jpeg_soi_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $boundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(true, $boundary['jpeg_marker_framing_used'] ?? null);

        $t->same(true, $rendererPreview['review_only_image_stream']);
        $t->same(['DCTDecode'], $rendererPreview['image_stream']['filters']);
        $t->same(['DCTDecode'], $rendererPreview['image_stream']['preview_only_filters']);
        $t->same(strlen($fixture['jpeg_payload']), $rendererPreview['image_stream']['raw_length']);
        $t->same(false, $rendererPreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $rendererPreview['image_stream']['decode_failed']);
        $t->true(is_array($rendererBoundary), 'Renderer BOM-prefixed DCT boundary should be explicit.');
        $t->same('dctdecode_jpeg_marker_boundary', $rendererBoundary['source'] ?? null);
        $t->same(3, $rendererBoundary['jpeg_soi_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(true, $rendererBoundary['jpeg_marker_framing_used'] ?? null);
        $t->same([], $rendererPreview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
