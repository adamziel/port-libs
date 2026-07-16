<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'marks preview-only filters before DCTDecode as unreachable native prefix stages' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/JPXDecode /DCTDecode] /DecodeParms [null << /ColorTransform 1 >>] >>';
        $expectedBoundary = [
            'declared_filter' => 'DCTDecode',
            'canonical_filter' => 'DCTDecode',
            'alias_used' => false,
            'non_null_filter_index' => 1,
            'filters_before_dctdecode' => ['JPXDecode'],
            'native_prefix_filters' => [],
            'preview_only_filters_before_dctdecode' => ['JPXDecode'],
            'pre_dctdecode_preview_filters_block_native_prefix_decode' => true,
            'filters_after_dctdecode' => [],
            'native_filters_after_dctdecode' => [],
            'preview_only_filters_after_dctdecode' => [],
            'dctdecode_is_terminal_filter' => true,
            'post_dctdecode_filters_present' => false,
            'post_dctdecode_filters_block_native_decode' => false,
            'source_filter_preserved' => true,
            'review_only' => true,
            'native_raster_decode' => false,
        ];

        $plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);

        $t->same(['JPXDecode', 'DCTDecode'], $plan['image_filters']);
        $t->same(['JPXDecode', 'DCTDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->same($expectedBoundary, $plan['dctdecode_filter_boundary']);
        $t->contains('jpx_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('dctdecode_pre_preview_filters_block_native_prefix_decode', implode(',', $plan['notes']));

        $before = 'BT /F1 12 Tf 72 720 Td (Before preview-prefix DCT image) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After preview-prefix DCT image) Tj ET';
        $payload = "\x00\x00\x00\x0cjP  \r\n\x87\nJPX bytes before unreachable DCT "
            . 'BT /F1 12 Tf 72 700 Td (Preview prefix DCT payload leak) Tj ET'
            . "\xff\xd9";
        $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/JPXDecode /DCTDecode] /DecodeParms [null << /ColorTransform 1 >>] /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? null;
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);

        $t->same(['Before preview-prefix DCT image', 'After preview-prefix DCT image'], $extractor->extractTextLines($pdf));
        $t->same("Before preview-prefix DCT image\nAfter preview-prefix DCT image", $plainText);
        $t->true(!str_contains($plainText, 'Preview prefix DCT payload leak'));
        $t->true(!str_contains($plainText, 'JPX bytes before unreachable DCT'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['JPXDecode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['JPXDecode', 'DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same($expectedBoundary, $entry['dctdecode_filter_boundary'] ?? null);
        $t->same(strlen($payload), $entry['raw_length'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($reviewJson, 'Preview prefix DCT payload leak'));
        $t->true(!str_contains($reviewJson, 'JPX bytes before unreachable DCT'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
