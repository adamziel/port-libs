<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeAsciiHexIncompletePrefixBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before incomplete ASCIIHex DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After incomplete ASCIIHex DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Incomplete ASCIIHex DCT payload leak) Tj ET';
    $incompleteJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0incomplete";
    $encodedPayload = strtoupper(bin2hex($incompleteJpeg))
        . ">\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n";
    $fakeTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused ASCIIHex incomplete DCT fixture must expose a fake early endstream marker.');
    }

    $buildStreamOnlyPdf = static function (?int $declaredLength) use ($before, $after, $encodedPayload): string {
        $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

        return "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode]{$lengthOperand} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
    };

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream";

    return [
        'before' => 'Before incomplete ASCIIHex DCT stream',
        'after' => 'After incomplete ASCIIHex DCT stream',
        'fake_object' => $fakeObject,
        'incomplete_jpeg' => $incompleteJpeg,
        'encoded_payload' => $encodedPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'stream_without_length' => $buildStreamOnlyPdf(null),
        'stream_with_stale_length' => $buildStreamOnlyPdf($fakeTerminatorOffset),
        'page_pdf' => $pagePdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
    ];
};

return [
    'keeps incomplete ASCIIHex DCTDecode prefix payload boundaries out of text' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeAsciiHexIncompletePrefixBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeAsciiHexIncompletePrefixBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $expectedText = [$fixture['before'], $fixture['after']];

        foreach ([$fixture['stream_without_length'], $fixture['stream_with_stale_length'], $fixture['page_pdf']] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expectedText, $extractor->extractTextLines($pdf));
            $t->same($expectedText, $extractor->extractTextRuns($pdf));
            $t->same(implode("\n", $expectedText), $plainText);
            $t->same(implode("\n", $expectedText) . "\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Incomplete ASCIIHex DCT payload leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($fixture['encoded_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['ASCIIHexDecode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(true, $entry['native_prefix_decoded'] ?? null);
        $t->same(strlen($fixture['incomplete_jpeg']), $entry['native_prefix_decoded_length'] ?? null);
        $t->same('DCTDecode', $entry['stopped_before_filter'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(is_array($entry['dctdecode_stream_boundary'] ?? null), 'DCT boundary review should describe the incomplete prefix.');
        $t->same('dctdecode_jpeg_marker_boundary_unverified', $entry['dctdecode_stream_boundary']['source'] ?? null);
        $t->same('missing_jpeg_eoi', $entry['dctdecode_stream_boundary']['invalid_reason'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $entry['dctdecode_stream_boundary']['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['incomplete_jpeg']), $entry['dctdecode_stream_boundary']['review_stream_length'] ?? null);
        $t->same(true, $entry['dctdecode_stream_boundary']['review_stream_decoded_from_native_prefix'] ?? null);
        $t->same(false, $entry['dctdecode_stream_boundary']['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($reviewJson, 'Incomplete ASCIIHex DCT payload leak'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $rendererPreview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);
        $rendererStream = $rendererPreview['image_stream'] ?? null;

        $t->true(is_array($rendererStream), 'Renderer image stream review row should be present.');
        $t->same(true, $rendererPreview['review_only_image_stream']);
        $t->same(['ASCIIHexDecode', 'DCTDecode'], $rendererStream['filters'] ?? null);
        $t->same(['DCTDecode'], $rendererStream['preview_only_filters'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $rendererStream['raw_length'] ?? null);
        $t->true(($rendererStream['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(false, $rendererStream['decoded_with_current_filters'] ?? null);
        $t->same(false, $rendererStream['decode_failed'] ?? null);
        $t->same(true, $rendererStream['native_prefix_decoded'] ?? null);
        $t->same(strlen($fixture['incomplete_jpeg']), $rendererStream['native_prefix_decoded_length'] ?? null);
        $t->same('DCTDecode', $rendererStream['stopped_before_filter'] ?? null);
        $t->same(null, $rendererStream['dctdecode_stream_boundary'] ?? null);
        $t->same([], $rendererPreview['pixels']);
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $rendererPreview['notes']));
    },
];
