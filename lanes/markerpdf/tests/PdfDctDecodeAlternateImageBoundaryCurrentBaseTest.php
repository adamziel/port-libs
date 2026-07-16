<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'DCTDecode alternate image review is filter-aware and clips post-EOI surplus bytes' => static function (TestRunner $test): void {
        $visibleContent = "BT /F1 12 Tf 72 720 Td (Before DCT alternate image) Tj T* (After DCT alternate image) Tj ET";
        $primaryImage = "primary image review placeholder";
        $jpegPayload = "\xff\xd8\xff\xe0\0\x10JFIF\0\x01\x02\0\0\0\0\0\0\0\xff\xd9";
        $surplus = "\nBT /F1 12 Tf 72 680 Td (DCT alternate surplus leak) Tj ET\n";
        $declaredAlternatePayload = $jpegPayload . $surplus;

        $pdf = "%PDF-1.4\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            . "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> /XObject << /Photo 5 0 R >> >> /Contents 4 0 R >> endobj\n"
            . "4 0 obj << /Length " . strlen($visibleContent) . " >>\nstream\n" . $visibleContent . "\nendstream\nendobj\n"
            . "5 0 obj << /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Alternates [<< /Image 6 0 R /DefaultForPrinting true >>] /Length " . strlen($primaryImage) . " >>\nstream\n" . $primaryImage . "\nendstream\nendobj\n"
            . "6 0 obj << /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms << /ColorTransform 0 >> /Length " . strlen($declaredAlternatePayload) . " >>\nstream\n" . $declaredAlternatePayload . "\nendstream\nendobj\n"
            . "7 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
            . "%%EOF\n";

        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);

        $test->same(
            "Before DCT alternate image\nAfter DCT alternate image",
            trim($text),
            'Visible text extraction ignores DCT alternate image bytes.'
        );
        $test->true(!str_contains($text, 'DCT alternate surplus leak'), 'Post-EOI alternate image surplus is not leaked as text.');

        $entry = $review['entries'][0] ?? null;
        $test->true(is_array($entry), 'Primary image boundary entry is present.');
        $test->same(1, $entry['alternate_image_count'] ?? null, 'Primary image reports one alternate image.');
        $test->true($entry['alternates_review_only'] ?? false, 'Alternate DCT image is review-only.');

        $alternate = $entry['alternate_images'][0] ?? null;
        $test->true(is_array($alternate), 'Alternate image review entry is present.');
        $test->same(['DCTDecode'], $alternate['filters'] ?? null, 'Alternate image keeps the DCTDecode filter name.');
        $test->same(['DCTDecode'], $alternate['preview_only_filters'] ?? null, 'Alternate image is marked as preview-only.');
        $test->true(!($alternate['native_raster_decode'] ?? true), 'DCT alternate image is not decoded natively.');
        $test->same(strlen($jpegPayload), $alternate['raw_length'] ?? null, 'Alternate review clips raw bytes at the final JPEG EOI marker.');
        $test->true(
            ($alternate['raw_length'] ?? 0) < strlen($declaredAlternatePayload),
            'Alternate DCT review clips raw bytes below the declared stream boundary.'
        );
        $test->same(0, $alternate['filter_details'][0]['decode_parms']['color_transform'] ?? null, 'DCT DecodeParms are preserved for the alternate image.');
        $test->true($alternate['filter_details'][0]['decode_parms']['valid_color_transform'] ?? false, 'DCT DecodeParms validity is preserved for the alternate image.');
        $test->true(!($alternate['decoded_with_current_filters'] ?? true), 'DCT alternate payload is not decoded as a text stream.');
        $test->true(!($alternate['payload_in_visible_text'] ?? true), 'DCT alternate payload is not exposed in visible text.');
    },
];
