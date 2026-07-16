<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

return [
    'resolves generation-exact CCITT DecodeParms boolean references before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms 30 1 R /Decode [1 0] >>',
            [
                30 => '<< /K /Bad /Columns 4 /Rows 99 /BlackIs1 false /EncodedByteAlign false /EndOfLine false /EndOfBlock true /DamagedRowsBeforeError 7 >>',
                '30:1' => '<< /K -1 /Columns 16 /Rows 2 /BlackIs1 40 2 R /EncodedByteAlign 42 2 R /EndOfLine 43 2 R /EndOfBlock 41 2 R /DamagedRowsBeforeError 0 >>',
                40 => 'false',
                '40:2' => 'true',
                41 => 'true',
                '41:2' => 'false',
                42 => 'false',
                '42:2' => 'true',
                43 => 'false',
                '43:2' => 'true',
            ]
        );

        $t->same(['CCF'], $plan['image_filters']);
        $t->same(['CCF'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->same([
            [
                'filter' => 'CCF',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 16,
                    'rows' => 2,
                    'black_is_1' => true,
                    'encoded_byte_align' => true,
                    'end_of_line' => true,
                    'end_of_block' => false,
                    'damaged_rows_before_error' => 0,
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same(false, $plan['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null);
        $t->same([], $plan['ccitt_fax_decode_boundary']['invalid_decode_parms_fields'] ?? null);
        $t->same([
            'k' => -1,
            'columns' => 16,
            'rows' => 2,
            'black_is_1' => true,
            'encoded_byte_align' => true,
            'end_of_line' => true,
            'end_of_block' => false,
            'damaged_rows_before_error' => 0,
        ], $plan['ccitt_fax_decode_boundary']['effective_decode_parms'] ?? null);
        $t->same('image_dictionary', $plan['ccitt_fax_decode_boundary']['width_source'] ?? null);
        $t->same('image_dictionary', $plan['ccitt_fax_decode_boundary']['height_source'] ?? null);
        $t->same(true, $plan['ccitt_fax_decode_boundary']['columns_match_width'] ?? null);
        $t->same(true, $plan['ccitt_fax_decode_boundary']['rows_match_height'] ?? null);
        $t->same('group4_two_dimensional', $plan['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->same(false, $plan['ccitt_fax_coding_boundary']['end_of_block'] ?? null);
        $t->same(null, $plan['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null);
        $t->same(true, $plan['ccitt_fax_imagemask_polarity_boundary']['black_is_1'] ?? null);
        $t->same(1, $plan['ccitt_fax_imagemask_polarity_boundary']['black_sample_value'] ?? null);
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('ccitt_fax_imagemask_polarity_review_before_rgb_conversion', implode(',', $plan['notes']));
    },
];
