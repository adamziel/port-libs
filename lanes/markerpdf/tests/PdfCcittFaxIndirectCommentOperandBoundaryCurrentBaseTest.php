<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$ccittFaxIndirectCommentOperandExpectedDetails = static function (): array {
    return [
        [
            'filter' => 'CCF',
            'preview_only' => true,
            'decode_parms' => [
                'type' => 'CCITTFaxDecode',
                'k' => -1,
                'columns' => 16,
                'rows' => 1,
                'black_is_1' => true,
                'encoded_byte_align' => true,
                'end_of_line' => null,
                'end_of_block' => true,
                'damaged_rows_before_error' => null,
            ],
        ],
    ];
};

$ccittFaxIndirectCommentOperandPdf = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before comment indirect CCITT) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After comment indirect CCITT) Tj ET';
    $faxPayload = 'BT /F1 12 Tf 72 700 Td (Comment indirect CCITT payload leak) Tj ET';
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /CommentFax 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter 20 0 R /DecodeParms 21 0 R /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
        . "20 0 obj\n% source filter comment before the real PDF name\n/CCF\nendobj\n"
        . "21 0 obj\n% DecodeParms comment before the dictionary\n<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EncodedByteAlign true /EndOfBlock true >>\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $faxPayload];
};

return [
    'resolves comment-prefixed indirect CCITT Fax filter and DecodeParms operands before renderer review' => static function (
        TestRunner $t
    ) use ($ccittFaxIndirectCommentOperandExpectedDetails): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter 20 0 R /DecodeParms 21 0 R /Decode [1 0] >>',
            [
                20 => "% source filter comment before the real PDF name\n/CCF",
                21 => "% DecodeParms comment before the dictionary\n<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EncodedByteAlign true /EndOfBlock true >>",
            ]
        );

        $t->same(['CCF'], $plan['image_filters']);
        $t->same(['CCF'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->same($ccittFaxIndirectCommentOperandExpectedDetails(), $plan['image_filter_details']);
        $t->same([
            'declared_filter' => 'CCF',
            'canonical_filter' => 'CCITTFaxDecode',
            'alias_used' => true,
            'non_null_filter_index' => 0,
            'filters_before_ccitt' => [],
            'native_prefix_filters' => [],
            'preview_only_filters_before_ccitt' => [],
            'filters_after_ccitt' => [],
            'native_filters_after_ccitt' => [],
            'preview_only_filters_after_ccitt' => [],
            'ccitt_is_terminal_filter' => true,
            'post_ccitt_filters_present' => false,
            'post_ccitt_filters_block_native_decode' => false,
            'source_filter_preserved' => true,
            'review_only' => true,
            'native_raster_decode' => false,
        ], $plan['ccitt_fax_filter_boundary'] ?? null);
        $t->same([
            'k' => -1,
            'columns' => 16,
            'rows' => 1,
            'black_is_1' => true,
            'encoded_byte_align' => true,
            'end_of_line' => false,
            'end_of_block' => true,
            'damaged_rows_before_error' => 0,
        ], $plan['ccitt_fax_decode_boundary']['effective_decode_parms'] ?? null);
        $t->same(false, $plan['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null);
        $t->same(['end_of_line', 'damaged_rows_before_error'], $plan['ccitt_fax_decode_boundary']['defaults_applied'] ?? null);
        $t->same('group4_two_dimensional', $plan['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->same('eofb', $plan['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null);
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
    },
    'resolves comment-prefixed inline CCITT Fax filter operands without promoting payload text' => static function (
        TestRunner $t
    ) use ($ccittFaxIndirectCommentOperandExpectedDetails): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline comment indirect CCITT payload leak) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 1 /IM true /F 20 0 R /DP 21 0 R /D [1 0]',
            $payload,
            [
                20 => "% inline source filter comment\n/CCF",
                21 => "% inline DecodeParms comment\n<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EncodedByteAlign true /EndOfBlock true >>",
            ]
        );

        $t->same(['CCF'], $plan['image_filters']);
        $t->same(['CCF'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same($ccittFaxIndirectCommentOperandExpectedDetails(), $plan['image_filter_details']);
        $t->same(['CCF'], $plan['inline_image']['review_only_filters'] ?? null);
        $t->same(false, $plan['inline_image']['native_raster_decode'] ?? null);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline comment indirect CCITT payload leak'));
    },
    'keeps comment-prefixed indirect CCITT Fax XObject operands review-only for WordPress text import' => static function (
        TestRunner $t
    ) use ($ccittFaxIndirectCommentOperandExpectedDetails, $ccittFaxIndirectCommentOperandPdf): void {
        [$pdf, $faxPayload] = $ccittFaxIndirectCommentOperandPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before comment indirect CCITT', 'After comment indirect CCITT'], $extractor->extractTextLines($pdf));
        $t->same("Before comment indirect CCITT\nAfter comment indirect CCITT", $plainText);
        $t->true(!str_contains($plainText, 'Comment indirect CCITT payload leak'));
        $t->same('CommentFax', $entry['resource_name'] ?? null);
        $t->same(['CCF'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same($ccittFaxIndirectCommentOperandExpectedDetails(), $entry['filter_details'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $entry['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null);
        $t->same('eofb', $entry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null);
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload));
    },
];
