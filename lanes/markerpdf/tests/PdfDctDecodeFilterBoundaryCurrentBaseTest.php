<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$pdfDctDecodeFilterBoundaryCurrentBaseZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused DCTDecode Flate-prefix fixture must fit one deflate stored block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$pdfDctDecodeFilterBoundaryCurrentBaseIndirectFilterFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before indirect DCT filter) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After indirect DCT filter) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Indirect DCT filter leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused indirect DCT filter fixture must contain a fake endstream marker.');
    }

    $streamOnlyPdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter 6 0 R /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
        . "6 0 obj\n/DCTDecode\nendobj\n%%EOF";

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter 6 0 R /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "6 0 obj\n/DCTDecode\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [
        'before' => $before,
        'after' => $after,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'stream_only_pdf' => $streamOnlyPdf,
        'page_pdf' => $pagePdf,
    ];
};

$pdfDctDecodeFilterBoundaryCurrentBaseUnsupportedPrefixFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before Crypt DCT filter) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After Crypt DCT filter) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Crypt DCT unsupported leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused unsupported-prefix DCT fixture must contain a fake endstream marker.');
    }

    $filterStack = '[/Crypt /DCTDecode]';
    $streamOnlyPdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [
        'before' => $before,
        'after' => $after,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'stream_only_pdf' => $streamOnlyPdf,
        'page_pdf' => $pagePdf,
    ];
};

$pdfDctDecodeFilterBoundaryCurrentBaseMalformedFilterFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before malformed DCT filter) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After malformed DCT filter) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Malformed nested DCT filter leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused malformed DCT filter fixture must contain a fake endstream marker.');
    }

    $filterStack = '[[/DCTDecode]]';
    $streamOnlyPdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [
        'before' => $before,
        'after' => $after,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'stream_only_pdf' => $streamOnlyPdf,
        'page_pdf' => $pagePdf,
    ];
};

$pdfDctDecodeFilterBoundaryCurrentBaseCryptIdentityFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before Crypt Identity DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After Crypt Identity DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Crypt Identity DCT fake EOI leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0bad segment before false EOI "
        . "\xff\xd9\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "still image bytes before actual boundary \xff\xd9";
    $falseEoiEndOffset = strpos($jpegPayload, "\xff\xd9\nendstream\n");
    if ($falseEoiEndOffset === false) {
        throw new RuntimeException('Focused Crypt Identity DCT fixture must contain a false EOI before fake endstream.');
    }
    $staleLength = $falseEoiEndOffset + 2;
    $filterStack = '[/Crypt /DCTDecode]';
    $decodeParms = '[<< /Name /Identity >> null]';

    $streamOnlyPdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$staleLength} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$staleLength} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [
        'before' => $before,
        'after' => $after,
        'jpeg_payload' => $jpegPayload,
        'stale_length' => $staleLength,
        'stream_only_pdf' => $streamOnlyPdf,
        'page_pdf' => $pagePdf,
    ];
};

return [
    'marks DCTDecode image filters review-only before RGB preview metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Filter /DCTDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /DecodeParms << /ColorTransform 1 >> >>'
        );

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'DCTDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'DCTDecode',
                    'color_transform' => 1,
                    'valid_color_transform' => true,
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'preview_only_filters' => ['DCTDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same('RGB', $plan['output_color_mode']);
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));
    },
    'keeps DCT alias inline image review metadata out of native raster decode' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "\xff\xd8\xff\xe0JFIF\0 inline DCT payload EI BT /F1 12 Tf (leak) Tj ET \xff\xd9";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 1 /H 1 /CS /RGB /BPC 8 /F /DCT',
            $payload
        );

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same([
            'preview_only_filters' => ['DCTDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(['DCTDecode'], $plan['inline_image']['review_only_filters']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_dct_image_filter_review_only', implode(',', $plan['notes']));
    },
    'records DCTDecode ColorTransform DecodeParms on image XObject review rows' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before DCT image review) Tj ET\n"
            . "q 24 0 0 24 72 680 cm /Photo Do Q\n"
            . 'BT /F1 12 Tf 72 650 Td (After DCT image review) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0 BT /F1 12 Tf 72 700 Td (DCT DecodeParms JPEG Noise) Tj ET \xff\xd9";
        $encodedPayload = strtoupper(bin2hex($jpegPayload)) . '>';

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode] /DecodeParms [null 6 0 R] /Length " . strlen($encodedPayload) . " >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /ColorTransform 0 >>\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before DCT image review', 'After DCT image review'], $extractor->extractTextLines($pdf));
        $t->same("Before DCT image review\nAfter DCT image review", $plainText);
        $t->true(!str_contains($plainText, 'DCT DecodeParms JPEG Noise'));
        $t->same(['ASCIIHexDecode', 'DCTDecode'], $entry['filters']);
        $t->same(['DCTDecode'], $entry['preview_only_filters']);
        $t->same(false, $entry['native_raster_decode']);
        $t->same(false, $entry['decoded_with_current_filters']);
        $t->same([
            [
                'filter' => 'ASCIIHexDecode',
                'preview_only' => false,
                'decode_parms' => null,
            ],
            [
                'filter' => 'DCTDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'DCTDecode',
                    'color_transform' => 0,
                    'valid_color_transform' => true,
                ],
            ],
        ], $entry['filter_details']);
    },
    'aligns DCTDecode ColorTransform DecodeParms after native prefix filters before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
        $sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x04"
            . "\x01\x11\x00"
            . "\x02\x11\x00"
            . "\x03\x11\x00"
            . "\x04\x11\x00";
        $jpegBytes = "\xff\xd8" . $segment(0xc0, $sofPayload) . "\xff\xd9";

        $plan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter [/FlateDecode /DCTDecode] /ColorSpace /DeviceCMYK /BitsPerComponent 8 /DecodeParms [<< /Predictor 12 /Columns 16 /Colors 1 /BitsPerComponent 8 >> << /ColorTransform 1 >>] >>',
            $jpegBytes
        );
        $compactNullPlan = $renderer->dctDecodeImageColorPlan(
            '<< /Filter [null /DCT] /ColorSpace /DeviceCMYK /BitsPerComponent 8 /DecodeParms [<< /ColorTransform 2 >>] >>',
            $jpegBytes
        );

        $t->same('DCTDecode', $plan['filter']);
        $t->same(null, $plan['adobe_app14_transform']);
        $t->same(1, $plan['decode_parms_color_transform']);
        $t->same(1, $plan['effective_color_transform']);
        $t->same(true, $plan['uses_ycck_transform']);
        $t->same(['render_rgb_preview_from_cmyk', 'apply_ycck_to_cmyk_before_rgb'], $plan['notes']);
        $t->same(['red' => 254, 'green' => 0, 'blue' => 0], $renderer->dctDecodeSampleToRgb([76, 85, 255, 0], $plan));

        $t->same('DCT', $compactNullPlan['filter']);
        $t->same(2, $compactNullPlan['decode_parms_color_transform']);
        $t->same(2, $compactNullPlan['effective_color_transform']);
        $t->same(true, $compactNullPlan['uses_ycck_transform']);
    },
    'keeps direct renderer DCTDecode fake endstream bytes inside image stream previews' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseZlibStored): void {
        $renderer = new PdfImageRenderer();
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Renderer DCT payload leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9";
        $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused renderer DCT fixture must contain a fake endstream marker.');
        }

        $objects = [
            30 => '[ /ICCBased 31 0 R ]',
            31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ];
        $rawImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";
        $compressedPayload = $pdfDctDecodeFilterBoundaryCurrentBaseZlibStored($jpegPayload);
        $fakeCompressedTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
        if ($fakeCompressedTerminatorOffset === false) {
            throw new RuntimeException('Focused renderer Flate-DCT fixture must contain a fake compressed endstream marker.');
        }
        $flateImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode] /Length {$fakeCompressedTerminatorOffset} >>\nstream\n{$compressedPayload}\nendstream";

        $rawPreview = $renderer->iccBasedImageStreamPreviewRows($rawImage, $objects);
        $flatePreview = $renderer->iccBasedImageStreamPreviewRows($flateImage, $objects);

        $t->same(true, $rawPreview['review_only_image_stream']);
        $t->same(0, $rawPreview['preview_pixel_count']);
        $t->same(['DCTDecode'], $rawPreview['image_stream']['filters']);
        $t->same(['DCTDecode'], $rawPreview['image_stream']['preview_only_filters']);
        $t->same(strlen($jpegPayload), $rawPreview['image_stream']['raw_length']);
        $t->true(($rawPreview['image_stream']['raw_length'] ?? 0) > $fakeTerminatorOffset);
        $t->same(false, $rawPreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $rawPreview['image_stream']['decode_failed']);
        $t->same([], $rawPreview['pixels']);
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $rawPreview['notes']));

        $t->same(true, $flatePreview['review_only_image_stream']);
        $t->same(0, $flatePreview['preview_pixel_count']);
        $t->same(['FlateDecode', 'DCTDecode'], $flatePreview['image_stream']['filters']);
        $t->same(['DCTDecode'], $flatePreview['image_stream']['preview_only_filters']);
        $t->same(strlen($compressedPayload), $flatePreview['image_stream']['raw_length']);
        $t->true(($flatePreview['image_stream']['raw_length'] ?? 0) > $fakeCompressedTerminatorOffset);
        $t->same(false, $flatePreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $flatePreview['image_stream']['decode_failed']);
        $t->same([], $flatePreview['pixels']);
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $flatePreview['notes']));
    },
    'keeps direct renderer native-prefix unsupported DCTDecode streams review-only at decoded JPEG boundaries' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseZlibStored): void {
        $renderer = new PdfImageRenderer();
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Renderer native-prefix unsupported DCT payload leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9";
        $compressedPayload = $pdfDctDecodeFilterBoundaryCurrentBaseZlibStored($jpegPayload);
        $fakeCompressedTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
        if ($fakeCompressedTerminatorOffset === false) {
            throw new RuntimeException('Focused renderer native-prefix unsupported DCT fixture must expose a fake compressed endstream marker.');
        }

        $objects = [
            30 => '[ /ICCBased 31 0 R ]',
            31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ];
        $imageObject = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter [/FlateDecode /Crypt /DCTDecode] /DecodeParms [null null null] /Length {$fakeCompressedTerminatorOffset} >>\nstream\n{$compressedPayload}\nendstream";

        $preview = $renderer->iccBasedImageStreamPreviewRows($imageObject, $objects);

        $t->same(true, $preview['review_only_image_stream']);
        $t->same(0, $preview['preview_pixel_count']);
        $t->same(['FlateDecode', 'Crypt', 'DCTDecode'], $preview['image_stream']['filters']);
        $t->same(['DCTDecode'], $preview['image_stream']['preview_only_filters']);
        $t->same(['Crypt', 'DCTDecode'], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($compressedPayload), $preview['image_stream']['raw_length']);
        $t->true(($preview['image_stream']['raw_length'] ?? 0) > $fakeCompressedTerminatorOffset);
        $t->same(null, $preview['image_stream']['decoded_length']);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(true, $preview['image_stream']['decode_failed']);
        $t->same(true, $preview['image_stream']['native_prefix_decoded']);
        $t->same(strlen($jpegPayload), $preview['image_stream']['native_prefix_decoded_length']);
        $t->same('Crypt', $preview['image_stream']['stopped_before_filter']);
        $t->same([], $preview['pixels']);
        $t->contains('dctdecode_image_filter_review_only', implode(',', $preview['notes']));
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $preview['notes']));
    },
    'keeps DCTDecode JPEG endstream decoys inside image payload boundaries' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before DCT stream boundary) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After DCT stream boundary) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Fake DCT stream object leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9";
        $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused DCT fixture must contain a fake endstream terminator.');
        }

        $pdfWithStaleLength = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        $pdfWithoutLength = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        $ascii85WrappedPayload = "<~endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n~>";
        $pdfWithAscii85Prefix = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/A85 /DCTDecode] >>\nstream\n{$ascii85WrappedPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

        foreach ([$pdfWithStaleLength, $pdfWithoutLength, $pdfWithAscii85Prefix] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same(['Before DCT stream boundary', 'After DCT stream boundary'], $extractor->extractTextLines($pdf));
            $t->same("Before DCT stream boundary\nAfter DCT stream boundary", $plainText);
            $t->true(!str_contains($plainText, 'Fake DCT stream object leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }
    },
    'recovers overdeclared DCTDecode lengths at JPEG EOI before later objects' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before overlong DCT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After overlong DCT stream) Tj ET';
        $postStreamDecoy = 'BT /F1 12 Tf 72 700 Td (Overlong DCT poststream leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes before real EOI\xff\xd9";
        $postStreamGarbage = "\nendstream\n{$postStreamDecoy}\nendobj\n";
        $overdeclaredLength = strlen($jpegPayload . $postStreamGarbage) + 24;

        $streamOnlyPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$overdeclaredLength} >>\nstream\n{$jpegPayload}{$postStreamGarbage}"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$overdeclaredLength} >>\nstream\n{$jpegPayload}{$postStreamGarbage}"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $expected = ['Before overlong DCT stream', 'After overlong DCT stream'];

        foreach ([$streamOnlyPdf, $pagePdf] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before overlong DCT stream\nAfter overlong DCT stream", $plainText);
            $t->same("Before overlong DCT stream\nAfter overlong DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Overlong DCT poststream leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($jpegPayload), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) < $overdeclaredLength);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'keeps NUL-padded DCTDecode JPEG EOI boundaries before fake endstream payloads' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before padded DCT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After padded DCT stream) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Padded DCT payload leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9\0\0";
        $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused padded DCT fixture must contain a fake endstream terminator.');
        }

        $buildPdf = static function (?int $declaredLength) use ($before, $after, $jpegPayload): string {
            $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

            return "%PDF-1.4\n"
                . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
                . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode{$lengthOperand} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
                . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        };

        foreach ([$buildPdf(null), $buildPdf($fakeTerminatorOffset)] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same(['Before padded DCT stream', 'After padded DCT stream'], $extractor->extractTextLines($pdf));
            $t->same(['Before padded DCT stream', 'After padded DCT stream'], $extractor->extractTextRuns($pdf));
            $t->same("Before padded DCT stream\nAfter padded DCT stream", $plainText);
            $t->same("Before padded DCT stream\nAfter padded DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Padded DCT payload leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }
    },
    'keeps malformed DCTDecode lenient EOI decoys inside image payload boundaries' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before malformed DCT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After malformed DCT stream) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Malformed DCT lenient EOI leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0bad segment before false EOI "
            . "\xff\xd9\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "still image bytes before actual boundary \xff\xd9";
        $falseEoiEndOffset = strpos($jpegPayload, "\xff\xd9\nendstream\n");
        if ($falseEoiEndOffset === false) {
            throw new RuntimeException('Focused malformed DCT fixture must contain a false EOI before fake endstream.');
        }
        $staleLength = $falseEoiEndOffset + 2;

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

        $expected = [
            'Before malformed DCT stream',
            'After malformed DCT stream',
        ];
        foreach ([$streamOnlyPdf, $pagePdf] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before malformed DCT stream\nAfter malformed DCT stream", $plainText);
            $t->same("Before malformed DCT stream\nAfter malformed DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Malformed DCT lenient EOI leak'));
            $t->true(!str_contains($plainText, 'bad segment before false EOI'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

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
    'keeps missing-Length malformed DCTDecode false EOI decoys before final JPEG boundary' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before missing length malformed DCT) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After missing length malformed DCT) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Missing length malformed DCT leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0bad segment before false EOI "
            . "\xff\xd9\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "still image bytes before actual boundary \xff\xd9";
        $falseEoiEndOffset = strpos($jpegPayload, "\xff\xd9\nendstream\n");
        if ($falseEoiEndOffset === false) {
            throw new RuntimeException('Focused missing-Length malformed DCT fixture must contain a false EOI before fake endstream.');
        }

        $streamOnlyPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $expected = [
            'Before missing length malformed DCT',
            'After missing length malformed DCT',
        ];
        foreach ([$streamOnlyPdf, $pagePdf] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before missing length malformed DCT\nAfter missing length malformed DCT", $plainText);
            $t->same("Before missing length malformed DCT\nAfter missing length malformed DCT\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Missing length malformed DCT leak'));
            $t->true(!str_contains($plainText, 'bad segment before false EOI'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($jpegPayload), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $falseEoiEndOffset + 2);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'keeps Flate-wrapped DCTDecode JPEG endstream decoys inside image payload boundaries' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseZlibStored): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before Flate DCT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After Flate DCT stream) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Fake Flate DCT prefix leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9";
        $compressedPayload = $pdfDctDecodeFilterBoundaryCurrentBaseZlibStored($jpegPayload);
        $fakeTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused Flate-wrapped DCT fixture must expose a raw fake endstream marker.');
        }

        $buildPdf = static function (?int $declaredLength) use ($before, $after, $compressedPayload): string {
            $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

            return "%PDF-1.4\n"
                . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
                . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode]{$lengthOperand} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
                . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        };

        foreach ([$buildPdf(null), $buildPdf($fakeTerminatorOffset)] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same(['Before Flate DCT stream', 'After Flate DCT stream'], $extractor->extractTextLines($pdf));
            $t->same("Before Flate DCT stream\nAfter Flate DCT stream", $plainText);
            $t->true(!str_contains($plainText, 'Fake Flate DCT prefix leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }
    },
    'ignores null-filter DecodeParms slots before Flate-wrapped DCTDecode JPEG boundaries' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseZlibStored): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before null DCT prefix) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After null DCT prefix) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Null DCT prefix leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9";
        $compressedPayload = $pdfDctDecodeFilterBoundaryCurrentBaseZlibStored($jpegPayload);
        $fakeTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused null-slot DCT fixture must expose a raw fake endstream marker.');
        }

        $filterStack = '[ null /FlateDecode null /DCTDecode ]';
        $decodeParms = '[ 99 0 R null 100 0 R << /ColorTransform 1 >> ]';
        $buildStreamOnlyPdf = static function (?int $declaredLength) use ($before, $after, $compressedPayload, $filterStack, $decodeParms): string {
            $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

            return "%PDF-1.4\n"
                . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
                . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms}{$lengthOperand} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
                . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        };
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$fakeTerminatorOffset} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        foreach ([$buildStreamOnlyPdf(null), $buildStreamOnlyPdf($fakeTerminatorOffset)] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same(['Before null DCT prefix', 'After null DCT prefix'], $extractor->extractTextLines($pdf));
            $t->same(['Before null DCT prefix', 'After null DCT prefix'], $extractor->extractTextRuns($pdf));
            $t->same("Before null DCT prefix\nAfter null DCT prefix", $plainText);
            $t->same("Before null DCT prefix\nAfter null DCT prefix\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Null DCT prefix leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;
        $pageText = $extractor->extractPlainText($pagePdf);

        $t->same(['Before null DCT prefix', 'After null DCT prefix'], $extractor->extractTextLines($pagePdf));
        $t->same("Before null DCT prefix\nAfter null DCT prefix", $pageText);
        $t->true(!str_contains($pageText, 'Null DCT prefix leak'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($compressedPayload), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fakeTerminatorOffset);
        $t->same(['FlateDecode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'preserves DCTDecode DecodeParms when trailing null filter slots are unresolved' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before trailing null DCT filter) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After trailing null DCT filter) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Trailing null DCT payload leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9";
        $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused trailing-null DCT fixture must expose a fake endstream marker.');
        }

        $filterStack = '[/DCTDecode null]';
        $decodeParms = '[<< /ColorTransform 2 >> 99 0 R]';
        $streamOnlyPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pagePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $expected = [
            'Before trailing null DCT filter',
            'After trailing null DCT filter',
        ];

        foreach ([$streamOnlyPdf, $pagePdf] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before trailing null DCT filter\nAfter trailing null DCT filter", $plainText);
            $t->same("Before trailing null DCT filter\nAfter trailing null DCT filter\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Trailing null DCT payload leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($jpegPayload), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fakeTerminatorOffset);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same([
            [
                'filter' => 'DCTDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'DCTDecode',
                    'color_transform' => 2,
                    'valid_color_transform' => true,
                ],
            ],
        ], $entry['filter_details'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'keeps prefix-decoded NUL-padded DCTDecode JPEG boundaries before fake endstream payloads' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseZlibStored): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before padded Flate DCT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After padded Flate DCT stream) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Padded Flate DCT payload leak) Tj ET';
        $jpegPayload = "\0\0\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9\0\0";
        $compressedPayload = $pdfDctDecodeFilterBoundaryCurrentBaseZlibStored($jpegPayload);
        $fakeTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused padded Flate-wrapped DCT fixture must expose a raw fake endstream marker.');
        }

        $buildPdf = static function (?int $declaredLength) use ($before, $after, $compressedPayload): string {
            $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

            return "%PDF-1.4\n"
                . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
                . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode]{$lengthOperand} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
                . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        };

        foreach ([$buildPdf(null), $buildPdf($fakeTerminatorOffset)] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same(['Before padded Flate DCT stream', 'After padded Flate DCT stream'], $extractor->extractTextLines($pdf));
            $t->same(['Before padded Flate DCT stream', 'After padded Flate DCT stream'], $extractor->extractTextRuns($pdf));
            $t->same("Before padded Flate DCT stream\nAfter padded Flate DCT stream", $plainText);
            $t->same("Before padded Flate DCT stream\nAfter padded Flate DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Padded Flate DCT payload leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }
    },
    'keeps ASCIIHex DCTDecode early EOD decoys inside image payload boundaries' => static function (TestRunner $t): void {
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before ASCIIHex DCT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After ASCIIHex DCT stream) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (ASCIIHex DCT early EOD leak) Tj ET';
        $prefix = strtoupper(bin2hex("\xff\xd8\xff\xe0\x00\x10JFIF\0incomplete"));
        $jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0complete!\xff\xd9";
        $encodedPayload = $prefix
            . ">\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . strtoupper(bin2hex($jpegPayload))
            . '>';
        $fakeTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused ASCIIHex DCT fixture must expose a fake early EOD endstream marker.');
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

        foreach ([$buildStreamOnlyPdf(null), $buildStreamOnlyPdf($fakeTerminatorOffset)] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same(['Before ASCIIHex DCT stream', 'After ASCIIHex DCT stream'], $extractor->extractTextLines($pdf));
            $t->same(['Before ASCIIHex DCT stream', 'After ASCIIHex DCT stream'], $extractor->extractTextRuns($pdf));
            $t->same("Before ASCIIHex DCT stream\nAfter ASCIIHex DCT stream", $plainText);
            $t->same("Before ASCIIHex DCT stream\nAfter ASCIIHex DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'ASCIIHex DCT early EOD leak'));
            $t->true(!str_contains($plainText, 'incomplete'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;
        $pageText = $extractor->extractPlainText($pagePdf);

        $t->same(['Before ASCIIHex DCT stream', 'After ASCIIHex DCT stream'], $extractor->extractTextLines($pagePdf));
        $t->same("Before ASCIIHex DCT stream\nAfter ASCIIHex DCT stream", $pageText);
        $t->true(!str_contains($pageText, 'ASCIIHex DCT early EOD leak'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($encodedPayload), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fakeTerminatorOffset);
        $t->same(['ASCIIHexDecode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'keeps indirect DCTDecode filter owner boundaries before fake JPEG payload objects' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseIndirectFilterFixture): void {
        $fixture = $pdfDctDecodeFilterBoundaryCurrentBaseIndirectFilterFixture();
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $streamOnlyPdf = $fixture['stream_only_pdf'];
        $pagePdf = $fixture['page_pdf'];
        $expected = [
            'Before indirect DCT filter',
            'After indirect DCT filter',
        ];

        $streamText = $extractor->extractPlainText($streamOnlyPdf);
        $pageText = $extractor->extractPlainText($pagePdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $t->same($expected, $extractor->extractTextLines($streamOnlyPdf));
        $t->same($expected, $extractor->extractTextRuns($streamOnlyPdf));
        $t->same("Before indirect DCT filter\nAfter indirect DCT filter", $streamText);
        $t->same("Before indirect DCT filter\nAfter indirect DCT filter\n", $extractor->naiveGetText($streamOnlyPdf));
        $t->true(!str_contains($streamText, 'Indirect DCT filter leak'));
        $t->true(!str_contains($streamText, 'JFIF'));
        $t->true(!str_contains($streamText, 'endstream'));

        $t->same($expected, $extractor->extractTextLines($pagePdf));
        $t->same($expected, $extractor->extractTextRuns($pagePdf));
        $t->same("Before indirect DCT filter\nAfter indirect DCT filter", $pageText);
        $t->same("Before indirect DCT filter\nAfter indirect DCT filter\n", $extractor->naiveGetText($pagePdf));
        $t->true(!str_contains($pageText, 'Indirect DCT filter leak'));
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
    },
    'keeps unsupported DCTDecode prefix filters closed around visible JPEG boundaries' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseUnsupportedPrefixFixture): void {
        $fixture = $pdfDctDecodeFilterBoundaryCurrentBaseUnsupportedPrefixFixture();
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $streamOnlyPdf = $fixture['stream_only_pdf'];
        $pagePdf = $fixture['page_pdf'];
        $expected = [
            'Before Crypt DCT filter',
            'After Crypt DCT filter',
        ];

        $streamText = $extractor->extractPlainText($streamOnlyPdf);
        $pageText = $extractor->extractPlainText($pagePdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $t->same($expected, $extractor->extractTextLines($streamOnlyPdf));
        $t->same($expected, $extractor->extractTextRuns($streamOnlyPdf));
        $t->same("Before Crypt DCT filter\nAfter Crypt DCT filter", $streamText);
        $t->same("Before Crypt DCT filter\nAfter Crypt DCT filter\n", $extractor->naiveGetText($streamOnlyPdf));
        $t->true(!str_contains($streamText, 'Crypt DCT unsupported leak'));
        $t->true(!str_contains($streamText, 'JFIF'));
        $t->true(!str_contains($streamText, 'endstream'));

        $t->same($expected, $extractor->extractTextLines($pagePdf));
        $t->same($expected, $extractor->extractTextRuns($pagePdf));
        $t->same("Before Crypt DCT filter\nAfter Crypt DCT filter", $pageText);
        $t->same("Before Crypt DCT filter\nAfter Crypt DCT filter\n", $extractor->naiveGetText($pagePdf));
        $t->true(!str_contains($pageText, 'Crypt DCT unsupported leak'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['Crypt', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'keeps malformed DCTDecode filter operands review-only without native raster decode claims' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseMalformedFilterFixture): void {
        $fixture = $pdfDctDecodeFilterBoundaryCurrentBaseMalformedFilterFixture();
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $streamOnlyPdf = $fixture['stream_only_pdf'];
        $pagePdf = $fixture['page_pdf'];
        $expected = [
            'Before malformed DCT filter',
            'After malformed DCT filter',
        ];

        foreach ([$streamOnlyPdf, $pagePdf] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before malformed DCT filter\nAfter malformed DCT filter", $plainText);
            $t->same("Before malformed DCT filter\nAfter malformed DCT filter\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Malformed nested DCT filter leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(false, $entry['filters_resolved'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same([], $entry['preview_only_filters'] ?? null);
        $t->same([], $entry['filter_details'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'keeps direct renderer malformed DCTDecode operands review-only at raw JPEG boundaries' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Renderer malformed DCT payload leak) Tj ET';
        $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\xff\xd9";
        $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused renderer malformed DCT fixture must expose a fake endstream marker.');
        }

        $objects = [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ];
        $imageObject = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [ /ICCBased 30 0 R ] /BitsPerComponent 8 /Filter [[/DCTDecode]] /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";

        $preview = $renderer->iccBasedImageStreamPreviewRows($imageObject, $objects);

        $t->same(true, $preview['review_only_image_stream']);
        $t->same(0, $preview['preview_pixel_count']);
        $t->same(['MalformedFilterOperand'], $preview['image_stream']['filters']);
        $t->same([], $preview['image_stream']['preview_only_filters']);
        $t->same(['MalformedFilterOperand'], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($jpegPayload), $preview['image_stream']['raw_length']);
        $t->true(($preview['image_stream']['raw_length'] ?? 0) > $fakeTerminatorOffset);
        $t->same(null, $preview['image_stream']['decoded_length']);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(true, $preview['image_stream']['decode_failed']);
        $t->same(true, $preview['image_stream']['raw_dct_preview_boundary']);
        $t->same([], $preview['pixels']);
        $t->contains('malformed_image_filter_operand_fail_closed', implode(',', $preview['notes']));
        $t->contains('iccbased_image_stream_preview_only_before_rgb_conversion', implode(',', $preview['notes']));
    },
    'keeps Crypt Identity DCTDecode stale EOI boundaries before fake JPEG payload objects' => static function (TestRunner $t) use ($pdfDctDecodeFilterBoundaryCurrentBaseCryptIdentityFixture): void {
        $fixture = $pdfDctDecodeFilterBoundaryCurrentBaseCryptIdentityFixture();
        $extractor = new PortLibs\MarkerPDF\PdfTextExtractor();
        $streamOnlyPdf = $fixture['stream_only_pdf'];
        $pagePdf = $fixture['page_pdf'];
        $expected = [
            'Before Crypt Identity DCT stream',
            'After Crypt Identity DCT stream',
        ];

        foreach ([$streamOnlyPdf, $pagePdf] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before Crypt Identity DCT stream\nAfter Crypt Identity DCT stream", $plainText);
            $t->same("Before Crypt Identity DCT stream\nAfter Crypt Identity DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'Crypt Identity DCT fake EOI leak'));
            $t->true(!str_contains($plainText, 'bad segment before false EOI'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($pagePdf);
        $entry = $review['entries'][0] ?? null;

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['stale_length']);
        $t->same(['Crypt', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
