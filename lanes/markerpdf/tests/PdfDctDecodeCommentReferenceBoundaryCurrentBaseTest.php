<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeCommentReferenceBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before comment DCT reference) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After comment DCT reference) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Comment split DCT payload leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused DCT comment-reference fixture must expose a fake endstream marker.');
    }

    $filterReference = "10 % filter reference comment\n 0 R";
    $decodeParmsReference = "11 % decodeparms reference comment\n 0 R";
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $imageDictionary = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterReference} /DecodeParms {$decodeParmsReference} /Length {$fakeTerminatorOffset} >>";

    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 20 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n/DCTDecode\nendobj\n"
        . "11 0 obj\n<< /ColorTransform 1 >>\nendobj\n"
        . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    $iccImageDictionary = str_replace('/ColorSpace /DeviceRGB', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);
    $iccImageObject = "{$iccImageDictionary}\nstream\n{$jpegPayload}\nendstream";
    $rendererObjects = [
        10 => '/DCTDecode',
        11 => '<< /ColorTransform 1 >>',
        30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
    ];

    return [
        'page_pdf' => $pagePdf,
        'icc_image_object' => $iccImageObject,
        'renderer_objects' => $rendererObjects,
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
    ];
};

return [
    'resolves comment-split DCTDecode filter references before renderer and WordPress review boundaries' => static function (TestRunner $t) use ($pdfDctDecodeCommentReferenceBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeCommentReferenceBoundaryCurrentBaseFixture();
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();

        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter 10 % filter reference comment\n 0 R /DecodeParms 11 % decodeparms reference comment\n 0 R >>",
            [
                10 => '/DCTDecode',
                11 => '<< /ColorTransform 1 >>',
            ]
        );
        $preview = $renderer->iccBasedImageStreamPreviewRows(
            $fixture['icc_image_object'],
            $fixture['renderer_objects']
        );
        $plainText = $extractor->extractPlainText($fixture['page_pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;

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
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same(true, $preview['review_only_image_stream']);
        $t->same(['DCTDecode'], $preview['image_stream']['filters']);
        $t->same(['DCTDecode'], $preview['image_stream']['preview_only_filters']);
        $t->same(['DCTDecode'], $preview['image_stream']['unsupported_filters']);
        $t->same(strlen($fixture['jpeg_payload']), $preview['image_stream']['raw_length']);
        $t->true(($preview['image_stream']['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->same([], $preview['pixels']);

        $t->same(['Before comment DCT reference', 'After comment DCT reference'], $extractor->extractTextLines($fixture['page_pdf']));
        $t->same("Before comment DCT reference\nAfter comment DCT reference", $plainText);
        $t->same("Before comment DCT reference\nAfter comment DCT reference\n", $extractor->naiveGetText($fixture['page_pdf']));
        $t->true(!str_contains($plainText, 'Comment split DCT payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
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
        ], $entry['filter_details'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
