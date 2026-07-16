<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$ccittFaxDecodeParmsTrailingOperandExpected = static function (): array {
    return [
        'type' => 'CCITTFaxDecode',
        'k' => null,
        'columns' => null,
        'rows' => null,
        'black_is_1' => null,
        'encoded_byte_align' => null,
        'end_of_line' => null,
        'end_of_block' => null,
        'damaged_rows_before_error' => null,
        'valid_decode_parms' => false,
        'invalid_decode_parms_fields' => ['decode_parms_operand'],
        'decode_parms_review' => 'malformed_ccitt_decodeparms_fail_closed',
        'decode_parms_operand' => 'malformed_operand',
        'decode_parms_operand_detail' => 'dictionary_with_trailing_operands',
        'decode_parms_dictionary_policy' => 'reject_top_level_decodeparms_dictionary_tail',
    ];
};

$ccittFaxDecodeParmsTrailingOperandPdf = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before CCITT DecodeParms tail) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After CCITT DecodeParms tail) Tj ET';
    $faxPayload = 'BT /F1 12 Tf 72 700 Td (CCITT DecodeParms tail payload noise) Tj ET';
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /TailFax 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 0 /Columns 16 /Rows 1 /EndOfBlock true >> << /K /Bad /Columns 1 >> /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $faxPayload];
};

return [
    'marks direct CCITT DecodeParms dictionaries with trailing operands fail closed before RGB preview' => static function (TestRunner $t) use ($ccittFaxDecodeParmsTrailingOperandExpected): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 0 /Columns 16 /Rows 1 /EndOfBlock true >> << /K /Bad /Columns 1 >> /Decode [1 0] >>'
        );
        $expected = $ccittFaxDecodeParmsTrailingOperandExpected();
        $boundary = $plan['ccitt_fax_decode_boundary'] ?? [];

        $t->same(['CCF'], $plan['image_filters']);
        $t->same($expected, $plan['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_operand'], $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same([
            'k' => 0,
            'columns' => 1728,
            'rows' => 0,
            'black_is_1' => false,
            'encoded_byte_align' => false,
            'end_of_line' => false,
            'end_of_block' => true,
            'damaged_rows_before_error' => 0,
        ], $boundary['effective_decode_parms'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
    },
    'marks XObject CCITT DecodeParms dictionaries with trailing operands fail closed before WordPress text import' => static function (TestRunner $t) use (
        $ccittFaxDecodeParmsTrailingOperandExpected,
        $ccittFaxDecodeParmsTrailingOperandPdf
    ): void {
        [$pdf, $faxPayload] = $ccittFaxDecodeParmsTrailingOperandPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $boundary = $entry['ccitt_fax_decode_boundary'] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before CCITT DecodeParms tail', 'After CCITT DecodeParms tail'], $extractor->extractTextLines($pdf));
        $t->same("Before CCITT DecodeParms tail\nAfter CCITT DecodeParms tail", $plainText);
        $t->same('TailFax', $entry['resource_name'] ?? null);
        $t->same(['CCF'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same($ccittFaxDecodeParmsTrailingOperandExpected(), $entry['filter_details'][0]['decode_parms'] ?? null);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_operand'], $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($plainText, 'CCITT DecodeParms tail payload noise'));
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload));
    },
    'marks inline CCITT DecodeParms dictionaries with trailing operands fail closed before preview metadata' => static function (TestRunner $t) use ($ccittFaxDecodeParmsTrailingOperandExpected): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT DecodeParms tail payload noise) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 1 /IM true /F /CCF /DP << /K 0 /Columns 16 /Rows 1 /EndOfBlock true >> << /K /Bad /Columns 1 >> /D [1 0]',
            $payload
        );
        $boundary = $plan['ccitt_fax_decode_boundary'] ?? [];

        $t->same(['CCITTFaxDecode'], $plan['image_filters']);
        $t->same($ccittFaxDecodeParmsTrailingOperandExpected(), $plan['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_operand'], $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(false, $plan['inline_image']['native_raster_decode'] ?? null);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline CCITT DecodeParms tail payload noise'));
    },
];
