<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeCommentedFilterOperandBoundaryCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before commented DCT filter operand) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After commented DCT filter operand) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Commented DCT filter operand payload leak) Tj ET';
    $jpegPayload = "\xff\xd8\xff\xe0JFIF\0commented filter operand bytes\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\xff\xd9";
    $fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused commented DCT filter operand fixture must expose a fake endstream marker.');
    }

    $imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 '
        . "/Filter /DCTDecode % malformed filter tail follows a PDF comment\n"
        . "/Crypt null /DecodeParms << /ColorTransform 1 >> /Length {$fakeTerminatorOffset} >>";
    $rendererDictionary = str_replace('/ColorSpace /DeviceRGB', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;

    return [
        'expected_lines' => [
            'Before commented DCT filter operand',
            'After commented DCT filter operand',
        ],
        'jpeg_payload' => $jpegPayload,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'image_dictionary' => $imageDictionary,
        'renderer_image' => "{$rendererDictionary}\nstream\n{$jpegPayload}\nendstream",
        'renderer_objects' => [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
        'pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF",
    ];
};

return [
    'preserves comment-separated extra DCTDecode Filter operands before WordPress image review' => static function (
        TestRunner $t
    ) use ($pdfDctDecodeCommentedFilterOperandBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeCommentedFilterOperandBoundaryCurrentBaseFixture();
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
        $expectedFilters = ['MalformedFilterOperand', 'DCTDecode'];

        $t->same($fixture['expected_lines'], $extractor->extractTextLines($fixture['pdf']));
        $t->same($fixture['expected_lines'], $extractor->extractTextRuns($fixture['pdf']));
        $t->same(implode("\n", $fixture['expected_lines']), $plainText);
        $t->same(implode("\n", $fixture['expected_lines']) . "\n", $extractor->naiveGetText($fixture['pdf']));
        $t->true(!str_contains($plainText, 'Commented DCT filter operand payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($plainText, 'malformed filter tail follows'));
        $t->true(!str_contains($plainText, 'Crypt'));

        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(false, $entry['filters_resolved'] ?? null);
        $t->same($expectedFilters, $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same('reject_malformed_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(1, $entry['malformed_filter_operand_count'] ?? null);
        $t->same(0, $entry['unresolved_filter_operand_count'] ?? null);
        $t->same(true, $entry['extra_filter_operand_after_comment'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(true, $entry['raw_dct_preview_boundary'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);

        $t->same($expectedFilters, $plan['image_filters']);
        $t->same('reject_malformed_filter_operands', $plan['image_filter_boundary']['filter_operand_policy'] ?? null);
        $t->same(1, $plan['image_filter_boundary']['malformed_filter_operand_count'] ?? null);
        $t->same(0, $plan['image_filter_boundary']['unresolved_filter_operand_count'] ?? null);
        $t->same(true, $plan['image_filter_boundary']['extra_filter_operand_after_comment'] ?? null);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->same('DCTDecode', $plan['dctdecode_filter_boundary']['canonical_filter'] ?? null);
        $t->same(['MalformedFilterOperand'], $plan['dctdecode_filter_boundary']['filters_before_dctdecode'] ?? null);
        $t->contains('malformed_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->contains('dctdecode_image_filter_review_only', implode(',', $plan['notes']));

        $t->same(true, $streamPreview['review_only_image_stream']);
        $t->same(true, $streamPreview['image_filter_boundary']['extra_filter_operand_after_comment'] ?? null);
        $t->same($expectedFilters, $streamPreview['image_stream']['filters'] ?? null);
        $t->same(['DCTDecode'], $streamPreview['image_stream']['preview_only_filters'] ?? null);
        $t->same(['MalformedFilterOperand', 'DCTDecode'], $streamPreview['image_stream']['unsupported_filters'] ?? null);
        $t->same(strlen($fixture['jpeg_payload']), $streamPreview['image_stream']['raw_length'] ?? null);
        $t->same(false, $streamPreview['image_stream']['decoded_with_current_filters'] ?? null);
        $t->same(true, $streamPreview['image_stream']['decode_failed'] ?? null);
        $t->same([], $streamPreview['pixels']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
