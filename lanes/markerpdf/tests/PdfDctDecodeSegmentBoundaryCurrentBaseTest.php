<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeSegmentBoundaryFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before APP DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After APP DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (APP Segment DCT Payload Leak) Tj ET';
    $segmentPayload = "Adobe APP data before fake EOI "
        . "\xff\xd9\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . 'still inside length-coded APP segment';
    $jpegPayload = "\xff\xd8"
        . "\xff\xee" . pack('n', strlen($segmentPayload) + 2) . $segmentPayload
        . "\xff\xd9";
    $fakeEoiOffset = strpos($jpegPayload, "\xff\xd9\nendstream\n");
    if ($fakeEoiOffset === false) {
        throw new RuntimeException('Focused DCT APP-segment fixture must contain a fake EOI before fake endstream.');
    }

    return [
        'before' => $before,
        'after' => $after,
        'jpeg_payload' => $jpegPayload,
        'fake_object' => $fakeObject,
        'stale_length' => $fakeEoiOffset + 2,
    ];
};

return [
    'keeps DCTDecode APP segment EOI decoys inside JPEG payload boundaries' => static function (TestRunner $t) use ($pdfDctDecodeSegmentBoundaryFixture): void {
        $fixture = $pdfDctDecodeSegmentBoundaryFixture();
        $before = $fixture['before'];
        $after = $fixture['after'];
        $jpegPayload = $fixture['jpeg_payload'];
        $staleLength = $fixture['stale_length'];

        $streamOnlyPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$staleLength} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$staleLength} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $streamText = $extractor->extractPlainText($streamOnlyPdf);
        $pageText = $extractor->extractPlainText($pagePdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $expected = ['Before APP DCT stream', 'After APP DCT stream'];
        $t->same($expected, $extractor->extractTextLines($streamOnlyPdf));
        $t->same($expected, $extractor->extractTextRuns($streamOnlyPdf));
        $t->same("Before APP DCT stream\nAfter APP DCT stream", $streamText);
        $t->same("Before APP DCT stream\nAfter APP DCT stream\n", $extractor->naiveGetText($streamOnlyPdf));
        $t->true(!str_contains($streamText, 'APP Segment DCT Payload Leak'));
        $t->true(!str_contains($streamText, 'endstream'));
        $t->true(!str_contains($streamText, 'Adobe APP data'));

        $t->same($expected, $extractor->extractTextLines($pagePdf));
        $t->same("Before APP DCT stream\nAfter APP DCT stream", $pageText);
        $t->true(!str_contains($pageText, 'APP Segment DCT Payload Leak'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($jpegPayload), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $staleLength);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
