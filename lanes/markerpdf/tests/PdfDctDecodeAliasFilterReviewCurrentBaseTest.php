<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeAliasFilterReviewCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before DCT alias image) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After DCT alias image) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (DCT alias payload leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0abbreviated DCT bytes\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused DCT alias fixture must contain a fake endstream marker.');
    }

    $imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCT /DecodeParms << /ColorTransform 1 >> /Length ' . $fakeTerminatorOffset . ' >>';
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
    $rendererImageDictionary = str_replace('/ColorSpace /DeviceCMYK', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);

    return [
        'expected_lines' => ['Before DCT alias image', 'After DCT alias image'],
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'image_dictionary' => $imageDictionary,
        'renderer_image' => "{$rendererImageDictionary}\nstream\n{$jpegPayload}\nendstream",
        'renderer_objects' => [
            30 => "<< /N 4 /Alternate /DeviceCMYK /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
        'pdf' => $pdf,
    ];
};

return [
    'canonicalizes DCT filter alias in public image review while preserving source boundary metadata' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeAliasFilterReviewCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeAliasFilterReviewCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $plainText = $extractor->extractPlainText($fixture['pdf']);
        $review = $extractor->extractImageXObjectBoundaryReview($fixture['pdf']);
        $entry = $review['entries'][0] ?? null;
        $plan = $renderer->imageColorSpaceSoftMaskPlan($fixture['image_dictionary']);
        $streamPreview = $renderer->iccBasedImageStreamPreviewRows(
            $fixture['renderer_image'],
            $fixture['renderer_objects']
        );
        $colorPlan = $renderer->dctDecodeImageColorPlan($fixture['image_dictionary'], $fixture['jpeg_payload']);

        $t->same($fixture['expected_lines'], $extractor->extractTextLines($fixture['pdf']));
        $t->same($fixture['expected_lines'], $extractor->extractTextRuns($fixture['pdf']));
        $t->same(implode("\n", $fixture['expected_lines']), $plainText);
        $t->same(implode("\n", $fixture['expected_lines']) . "\n", $extractor->naiveGetText($fixture['pdf']));
        $t->true(!str_contains($plainText, 'DCT alias payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');

        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same('DCT', $entry['dctdecode_filter_boundary']['declared_filter'] ?? null);
        $t->same('DCTDecode', $entry['dctdecode_filter_boundary']['canonical_filter'] ?? null);
        $t->same(true, $entry['dctdecode_filter_boundary']['alias_used'] ?? null);

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->same('DCT', $plan['dctdecode_filter_boundary']['declared_filter'] ?? null);
        $t->same('DCTDecode', $plan['dctdecode_filter_boundary']['canonical_filter'] ?? null);
        $t->same(true, $plan['dctdecode_filter_boundary']['alias_used'] ?? null);
        $t->same('DCT', $plan['image_filter_details'][0]['filter'] ?? null);
        $t->same([
            'type' => 'DCTDecode',
            'color_transform' => 1,
            'valid_color_transform' => true,
        ], $plan['image_filter_details'][0]['decode_parms'] ?? null);
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same('DCT', $colorPlan['filter']);
        $t->same(1, $colorPlan['decode_parms_color_transform']);
        $t->same(true, $colorPlan['decode_parms_color_transform_valid']);
        $t->same(1, $colorPlan['effective_color_transform']);
        $t->same(true, $colorPlan['uses_ycck_transform']);

        $t->same(true, $streamPreview['review_only_image_stream']);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['filters']);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['preview_only_filters']);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['unsupported_filters']);
        $t->same(false, $streamPreview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $streamPreview['image_stream']['decode_failed']);
        $t->same([], $streamPreview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
