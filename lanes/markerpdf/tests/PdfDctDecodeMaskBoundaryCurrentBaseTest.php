<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'clips nested DCTDecode soft-mask and explicit-mask review bytes at JPEG EOI boundaries' => static function (TestRunner $t): void {
        $before = 'BT /F1 12 Tf 72 720 Td (Before DCT mask boundary) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After DCT mask boundary) Tj ET';
        $softMaskJpeg = "\xff\xd8\xff\xe0JFIF\0soft mask jpeg\xff\xd9";
        $explicitMaskJpeg = "\xff\xd8\xff\xe0JFIF\0explicit mask jpeg\xff\xd9";
        $softMaskSurplus = "\nBT /F1 12 Tf 72 700 Td (DCT soft mask surplus leak) Tj ET\n";
        $explicitMaskSurplus = "\nBT /F1 12 Tf 72 690 Td (DCT explicit mask surplus leak) Tj ET\n";
        $softMaskPayload = $softMaskJpeg . $softMaskSurplus;
        $explicitMaskPayload = $explicitMaskJpeg . $explicitMaskSurplus;
        $primaryPayload = "\x00\x7f\xff";
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 6 0 R /Mask 7 0 R /Length " . strlen($primaryPayload) . " >>\nstream\n{$primaryPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($softMaskPayload) . " >>\nstream\n{$softMaskPayload}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /DCTDecode /Length " . strlen($explicitMaskPayload) . " >>\nstream\n{$explicitMaskPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? null;
        $softMask = is_array($entry) ? ($entry['soft_mask_review'] ?? null) : null;
        $explicitMask = is_array($entry) ? ($entry['mask_review'] ?? null) : null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);

        $t->same(['Before DCT mask boundary', 'After DCT mask boundary'], $extractor->extractTextLines($pdf));
        $t->same(['Before DCT mask boundary', 'After DCT mask boundary'], $extractor->extractTextRuns($pdf));
        $t->same("Before DCT mask boundary\nAfter DCT mask boundary", $plainText);
        $t->same("Before DCT mask boundary\nAfter DCT mask boundary\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'DCT soft mask surplus leak'));
        $t->true(!str_contains($plainText, 'DCT explicit mask surplus leak'));
        $t->true(!str_contains($plainText, 'JFIF'));

        $t->true(is_array($entry), 'Primary image review row should be present.');
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(is_array($softMask), 'DCT soft-mask review row should be present.');
        $t->same('soft_mask_stream', $softMask['type'] ?? null);
        $t->same(['DCTDecode'], $softMask['filters'] ?? null);
        $t->same(['DCTDecode'], $softMask['preview_only_filters'] ?? null);
        $t->same(strlen($softMaskJpeg), $softMask['raw_length'] ?? null);
        $t->true(($softMask['raw_length'] ?? 0) < strlen($softMaskPayload));
        $t->same(false, $softMask['native_raster_decode'] ?? null);
        $t->same(false, $softMask['decoded_with_current_filters'] ?? null);
        $t->same(false, $softMask['payload_in_visible_text'] ?? null);
        $t->same('DCTDecode', $softMask['dctdecode_filter_boundary']['canonical_filter'] ?? null);
        $t->same(true, $softMask['dctdecode_filter_boundary']['review_only'] ?? null);

        $t->true(is_array($explicitMask), 'DCT explicit-mask review row should be present.');
        $t->same('image_mask_stream', $explicitMask['type'] ?? null);
        $t->same(['DCTDecode'], $explicitMask['filters'] ?? null);
        $t->same(['DCTDecode'], $explicitMask['preview_only_filters'] ?? null);
        $t->same(strlen($explicitMaskJpeg), $explicitMask['raw_length'] ?? null);
        $t->true(($explicitMask['raw_length'] ?? 0) < strlen($explicitMaskPayload));
        $t->same(false, $explicitMask['native_raster_decode'] ?? null);
        $t->same(false, $explicitMask['decoded_with_current_filters'] ?? null);
        $t->same(false, $explicitMask['payload_in_visible_text'] ?? null);
        $t->same('DCTDecode', $explicitMask['dctdecode_filter_boundary']['canonical_filter'] ?? null);
        $t->same(true, $explicitMask['dctdecode_filter_boundary']['review_only'] ?? null);

        $t->true(!str_contains($reviewJson, 'DCT soft mask surplus leak'));
        $t->true(!str_contains($reviewJson, 'DCT explicit mask surplus leak'));
        $t->true(!str_contains($reviewJson, 'JFIF'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
