<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

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
];
