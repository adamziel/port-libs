<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$ccittFaxIndirectFilterArrayTailExpectedRendererPlan = static function (): array {
    return [
        'image_filters' => ['MalformedFilterOperand', 'CCF'],
        'image_filter_boundary' => [
            'preview_only_filters' => ['CCF'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
            'filter_operand_policy' => 'reject_malformed_filter_operands',
            'malformed_filter_operand_count' => 1,
            'unresolved_filter_operand_count' => 0,
        ],
        'ccitt_fax_filter_boundary' => [
            'declared_filter' => 'CCF',
            'canonical_filter' => 'CCITTFaxDecode',
            'alias_used' => true,
            'non_null_filter_index' => 1,
            'filters_before_ccitt' => ['MalformedFilterOperand'],
            'native_prefix_filters' => ['MalformedFilterOperand'],
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
        ],
        'image_filter_details' => [
            [
                'filter' => 'MalformedFilterOperand',
                'preview_only' => false,
                'decode_parms' => null,
            ],
            [
                'filter' => 'CCF',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => 0,
                    'columns' => 16,
                    'rows' => 1,
                    'black_is_1' => true,
                    'encoded_byte_align' => null,
                    'end_of_line' => null,
                    'end_of_block' => true,
                    'damaged_rows_before_error' => null,
                ],
            ],
        ],
        'ccitt_fax_decode_boundary' => [
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => 0,
                'columns' => 16,
                'rows' => 1,
                'black_is_1' => true,
                'encoded_byte_align' => false,
                'end_of_line' => false,
                'end_of_block' => true,
                'damaged_rows_before_error' => 0,
            ],
            'defaults_applied' => [
                'encoded_byte_align',
                'end_of_line',
                'damaged_rows_before_error',
            ],
            'dictionary_width' => 16,
            'dictionary_height' => 1,
            'effective_width' => 16,
            'effective_height' => 1,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => true,
            'rows_match_height' => true,
            'dimension_mismatch' => false,
        ],
        'ccitt_fax_imagemask_polarity_boundary' => [
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => true,
            'black_sample_value' => 1,
            'white_sample_value' => 0,
            'image_mask_decode_source' => 'explicit',
            'decode_inverts_stencil' => true,
            'black_sample_opacity' => 0.0,
            'white_sample_opacity' => 1.0,
            'black_sample_is_visible' => false,
            'white_sample_is_visible' => true,
        ],
    ];
};

$ccittFaxIndirectFilterArrayTailPdf = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before indirect filter tail CCITT) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After indirect filter tail CCITT) Tj ET';
    $faxPayload = 'BT /F1 12 Tf 72 700 Td (Indirect filter tail CCITT payload noise) Tj ET';
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /TailFilterFax 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [20 0 R] /DecodeParms [<< /K 0 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
        . "20 0 obj\n/CCF /DCTDecode\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $faxPayload];
};

return [
    'marks indirect CCITT Fax filter array entries with trailing operands fail closed before renderer preview' => static function (TestRunner $t) use ($ccittFaxIndirectFilterArrayTailExpectedRendererPlan): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [20 0 R] /DecodeParms [<< /K 0 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] /Decode [1 0] >>',
            [
                20 => '/CCF /DCTDecode',
            ]
        );
        $expected = $ccittFaxIndirectFilterArrayTailExpectedRendererPlan();

        foreach ($expected as $key => $value) {
            $t->same($value, $plan[$key] ?? null);
        }
        $t->contains('malformed_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('ccitt_fax_imagemask_polarity_review_before_rgb_conversion', implode(',', $plan['notes']));
    },
    'keeps indirect CCITT Fax filter array tail metadata aligned for XObject WordPress import' => static function (TestRunner $t) use (
        $ccittFaxIndirectFilterArrayTailPdf,
        $ccittFaxIndirectFilterArrayTailExpectedRendererPlan
    ): void {
        [$pdf, $faxPayload] = $ccittFaxIndirectFilterArrayTailPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);
        $expected = $ccittFaxIndirectFilterArrayTailExpectedRendererPlan();

        $t->same(['Before indirect filter tail CCITT', 'After indirect filter tail CCITT'], $extractor->extractTextLines($pdf));
        $t->same("Before indirect filter tail CCITT\nAfter indirect filter tail CCITT", $plainText);
        $t->same('TailFilterFax', $entry['resource_name'] ?? null);
        $t->same($expected['image_filters'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['filters_resolved'] ?? null);
        $t->same('reject_malformed_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(1, $entry['malformed_filter_operand_count'] ?? null);
        $t->same(0, $entry['unresolved_filter_operand_count'] ?? null);
        $t->same($expected['image_filter_details'], $entry['filter_details'] ?? null);
        $t->same($expected['ccitt_fax_filter_boundary'], $entry['ccitt_fax_filter_boundary'] ?? null);
        $t->same($expected['ccitt_fax_decode_boundary'], $entry['ccitt_fax_decode_boundary'] ?? null);
        $t->same($expected['ccitt_fax_imagemask_polarity_boundary'], $entry['ccitt_fax_imagemask_polarity_boundary'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($plainText, 'Indirect filter tail CCITT payload noise'));
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload));
    },
];
