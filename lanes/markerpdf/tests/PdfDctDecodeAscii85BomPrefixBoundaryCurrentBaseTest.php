<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeAscii85BomPrefixBoundaryCurrentBaseAscii85Encode = static function (string $bytes): string {
    $encoded = '<~';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $padding = 4 - strlen($chunk);
        $padded = $chunk . str_repeat("\0", $padding);
        $value = 0;
        for ($index = 0; $index < 4; $index++) {
            $value = ($value << 8) | ord($padded[$index]);
        }

        if ($value === 0 && $padding === 0) {
            $encoded .= 'z';
            continue;
        }

        $digits = '';
        for ($index = 0; $index < 5; $index++) {
            $digits = chr(($value % 85) + 33) . $digits;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($digits, 0, 5 - $padding);
    }

    return $encoded . '~>';
};

$pdfDctDecodeAscii85BomPrefixBoundaryCurrentBaseFixture = static function () use ($pdfDctDecodeAscii85BomPrefixBoundaryCurrentBaseAscii85Encode): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before ASCII85 BOM DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After ASCII85 BOM DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (ASCII85 BOM DCT payload leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0complete!\xff\xd9";
    $encodedPayload = "\xef\xbb\xbf" . $pdfDctDecodeAscii85BomPrefixBoundaryCurrentBaseAscii85Encode($jpegPayload);
    $streamPayload = $encodedPayload
        . "\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n";
    $fakeTerminatorOffset = strpos($streamPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused ASCII85 BOM DCT fixture must expose a fake endstream marker.');
    }

    $imageDictionary = '/Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB '
        . '/BitsPerComponent 8 /Filter [/ASCII85Decode /DCTDecode] /DecodeParms [null null] '
        . '/Length ' . $fakeTerminatorOffset;
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$imageDictionary} >>\nstream\n{$streamPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    $rendererImageDictionary = str_replace('/ColorSpace /DeviceRGB', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);
    $rendererImage = "<< {$rendererImageDictionary} >>\nstream\n{$streamPayload}\nendstream";

    return [
        'before' => 'Before ASCII85 BOM DCT stream',
        'after' => 'After ASCII85 BOM DCT stream',
        'fake_object' => $fakeObject,
        'jpeg_payload' => $jpegPayload,
        'encoded_payload' => $encodedPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'page_pdf' => $pagePdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
    ];
};

return [
    'keeps ASCII85 BOM prefix DCTDecode streams review-only and out of text' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeAscii85BomPrefixBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeAscii85BomPrefixBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $expectedText = [$fixture['before'], $fixture['after']];

        $plainText = $extractor->extractPlainText($fixture['page_pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $rendererPreview = $renderer->iccBasedImageStreamPreviewRows($fixture['renderer_image'], $fixture['renderer_objects']);
        $rendererStream = $rendererPreview['image_stream'] ?? null;
        $rendererBoundary = is_array($rendererStream) ? ($rendererStream['dctdecode_stream_boundary'] ?? null) : null;

        $t->same($expectedText, $extractor->extractTextLines($fixture['page_pdf']));
        $t->same($expectedText, $extractor->extractTextRuns($fixture['page_pdf']));
        $t->same(implode("\n", $expectedText), $plainText);
        $t->same(implode("\n", $expectedText) . "\n", $extractor->naiveGetText($fixture['page_pdf']));
        $t->true(!str_contains($plainText, 'ASCII85 BOM DCT payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($plainText, '<~'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');

        $t->same(['ASCII85Decode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $entry['raw_length'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($reviewJson, 'ASCII85 BOM DCT payload leak'));
        $t->true(!str_contains($reviewJson, 'JFIF'));
        $t->true(!str_contains($reviewJson, '<~'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $t->true(is_array($rendererStream), 'Renderer image stream review row should be present.');
        $t->same(true, $rendererPreview['review_only_image_stream']);
        $t->same(['ASCII85Decode', 'DCTDecode'], $rendererStream['filters']);
        $t->same(['DCTDecode'], $rendererStream['preview_only_filters']);
        $t->same(strlen($fixture['encoded_payload']), $rendererStream['raw_length']);
        $t->same(false, $rendererStream['decoded_with_current_filters']);
        $t->same(false, $rendererStream['decode_failed']);
        $t->same(true, $rendererStream['native_prefix_decoded']);
        $t->same(strlen($fixture['jpeg_payload']), $rendererStream['native_prefix_decoded_length']);
        $t->same(strtoupper(bin2hex(substr($fixture['jpeg_payload'], 0, 16))), $rendererStream['native_prefix_decoded_preview_hex']);
        $t->same('DCTDecode', $rendererStream['stopped_before_filter']);
        $t->true(is_array($rendererBoundary), 'Renderer decoded-prefix DCT boundary should be explicit.');
        $t->same('dctdecode_jpeg_marker_boundary', $rendererBoundary['source'] ?? null);
        $t->same(0, $rendererBoundary['jpeg_soi_offset'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(strlen($fixture['encoded_payload']), $rendererBoundary['raw_stream_length'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $rendererBoundary['review_stream_length'] ?? null);
        $t->same(true, $rendererBoundary['stream_trimmed_to_jpeg_eoi'] ?? null);
        $t->same(true, $rendererBoundary['review_stream_decoded_from_native_prefix'] ?? null);
        $t->same(['ASCII85Decode'], $rendererBoundary['native_prefix_filters'] ?? null);
        $t->same('DCTDecode', $rendererBoundary['stopped_before_filter'] ?? null);
        $t->same(true, $rendererBoundary['jpeg_marker_framing_used'] ?? null);
        $t->same(false, $rendererBoundary['payload_in_visible_text'] ?? null);
        $t->same(false, $rendererBoundary['native_raster_decode'] ?? null);
        $t->same([], $rendererPreview['pixels']);
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $rendererPreview['notes']));
    },
];
