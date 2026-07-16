<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$ccittFaxIndirectScalarTailObjects = static function (): array {
    return [
        11 => '-1 /BadK',
        12 => '16 /BadColumns',
        13 => '1 /BadRows',
        14 => 'true /BadBlackIs1',
        15 => 'true /BadEncodedByteAlign',
        16 => 'false /BadEndOfLine',
        17 => 'false /BadEndOfBlock',
        18 => '0 /BadDamagedRowsBeforeError',
    ];
};

$ccittFaxIndirectScalarTailDecodeParms = static function (): array {
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
        'invalid_decode_parms_fields' => [
            'k',
            'columns',
            'rows',
            'black_is_1',
            'encoded_byte_align',
            'end_of_line',
            'end_of_block',
            'damaged_rows_before_error',
        ],
        'decode_parms_review' => 'invalid_ccitt_decodeparms_fail_closed',
    ];
};

$ccittFaxIndirectScalarTailDefaults = static function (): array {
    return [
        'k' => 0,
        'columns' => 1728,
        'rows' => 0,
        'black_is_1' => false,
        'encoded_byte_align' => false,
        'end_of_line' => false,
        'end_of_block' => true,
        'damaged_rows_before_error' => 0,
    ];
};

$ccittFaxIndirectScalarTailInvalidFields = static function (): array {
    return [
        'k',
        'columns',
        'rows',
        'black_is_1',
        'encoded_byte_align',
        'end_of_line',
        'end_of_block',
        'damaged_rows_before_error',
    ];
};

$ccittFaxIndirectScalarTailPdf = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before CCITT scalar tail) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After CCITT scalar tail) Tj ET';
    $faxPayload = 'BT /F1 12 Tf 72 700 Td (CCITT scalar tail payload noise) Tj ET';
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /ScalarTailFax 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 11 0 R /Columns 12 0 R /Rows 13 0 R /BlackIs1 14 0 R /EncodedByteAlign 15 0 R /EndOfLine 16 0 R /EndOfBlock 17 0 R /DamagedRowsBeforeError 18 0 R >> /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "11 0 obj\n-1 /BadK\nendobj\n"
        . "12 0 obj\n16 /BadColumns\nendobj\n"
        . "13 0 obj\n1 /BadRows\nendobj\n"
        . "14 0 obj\ntrue /BadBlackIs1\nendobj\n"
        . "15 0 obj\ntrue /BadEncodedByteAlign\nendobj\n"
        . "16 0 obj\nfalse /BadEndOfLine\nendobj\n"
        . "17 0 obj\nfalse /BadEndOfBlock\nendobj\n"
        . "18 0 obj\n0 /BadDamagedRowsBeforeError\nendobj\n%%EOF";

    return [$pdf, $faxPayload];
};

return [
    'marks indirect CCITT DecodeParms scalar tails fail closed before RGB preview' => static function (TestRunner $t) use (
        $ccittFaxIndirectScalarTailObjects,
        $ccittFaxIndirectScalarTailDecodeParms,
        $ccittFaxIndirectScalarTailDefaults,
        $ccittFaxIndirectScalarTailInvalidFields
    ): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 11 0 R /Columns 12 0 R /Rows 13 0 R /BlackIs1 14 0 R /EncodedByteAlign 15 0 R /EndOfLine 16 0 R /EndOfBlock 17 0 R /DamagedRowsBeforeError 18 0 R >> /Decode [1 0] >>',
            $ccittFaxIndirectScalarTailObjects()
        );
        $boundary = $plan['ccitt_fax_decode_boundary'] ?? [];

        $t->same(['CCF'], $plan['image_filters']);
        $t->same($ccittFaxIndirectScalarTailDecodeParms(), $plan['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same($ccittFaxIndirectScalarTailInvalidFields(), $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same($ccittFaxIndirectScalarTailDefaults(), $boundary['effective_decode_parms'] ?? null);
        $t->same($ccittFaxIndirectScalarTailInvalidFields(), $boundary['defaults_applied'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
    },
    'keeps indirect CCITT integer values with comment-only tails valid' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 11 0 R /Columns 12 0 R /Rows 13 0 R /BlackIs1 true /EncodedByteAlign true /EndOfLine false /EndOfBlock false /DamagedRowsBeforeError 18 0 R >> /Decode [1 0] >>',
            [
                11 => "-1 % two dimensional mode\n",
                12 => "16 % explicit columns\n",
                13 => "1 % one row\n",
                18 => "0 % no damaged rows allowed\n",
            ]
        );
        $boundary = $plan['ccitt_fax_decode_boundary'] ?? [];

        $t->same([
            'type' => 'CCITTFaxDecode',
            'k' => -1,
            'columns' => 16,
            'rows' => 1,
            'black_is_1' => true,
            'encoded_byte_align' => true,
            'end_of_line' => false,
            'end_of_block' => false,
            'damaged_rows_before_error' => 0,
        ], $plan['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same(false, $boundary['invalid_decode_parms'] ?? null);
        $t->same([], $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same([
            'k' => -1,
            'columns' => 16,
            'rows' => 1,
            'black_is_1' => true,
            'encoded_byte_align' => true,
            'end_of_line' => false,
            'end_of_block' => false,
            'damaged_rows_before_error' => 0,
        ], $boundary['effective_decode_parms'] ?? null);
        $t->same([], $boundary['defaults_applied'] ?? null);
    },
    'marks XObject CCITT DecodeParms scalar tails fail closed before WordPress text import' => static function (TestRunner $t) use (
        $ccittFaxIndirectScalarTailDecodeParms,
        $ccittFaxIndirectScalarTailDefaults,
        $ccittFaxIndirectScalarTailInvalidFields,
        $ccittFaxIndirectScalarTailPdf
    ): void {
        [$pdf, $faxPayload] = $ccittFaxIndirectScalarTailPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $boundary = $entry['ccitt_fax_decode_boundary'] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before CCITT scalar tail', 'After CCITT scalar tail'], $extractor->extractTextLines($pdf));
        $t->same("Before CCITT scalar tail\nAfter CCITT scalar tail", $plainText);
        $t->same('ScalarTailFax', $entry['resource_name'] ?? null);
        $t->same(['CCF'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same($ccittFaxIndirectScalarTailDecodeParms(), $entry['filter_details'][0]['decode_parms'] ?? null);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same($ccittFaxIndirectScalarTailInvalidFields(), $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same($ccittFaxIndirectScalarTailDefaults(), $boundary['effective_decode_parms'] ?? null);
        $t->same($ccittFaxIndirectScalarTailInvalidFields(), $boundary['defaults_applied'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($plainText, 'CCITT scalar tail payload noise'));
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload));
    },
    'marks inline CCITT DecodeParms scalar tails fail closed before preview metadata' => static function (TestRunner $t) use (
        $ccittFaxIndirectScalarTailObjects,
        $ccittFaxIndirectScalarTailDecodeParms,
        $ccittFaxIndirectScalarTailDefaults,
        $ccittFaxIndirectScalarTailInvalidFields
    ): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT scalar tail payload noise) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 1 /IM true /F /CCF /DP << /K 11 0 R /Columns 12 0 R /Rows 13 0 R /BlackIs1 14 0 R /EncodedByteAlign 15 0 R /EndOfLine 16 0 R /EndOfBlock 17 0 R /DamagedRowsBeforeError 18 0 R >> /D [1 0]',
            $payload,
            $ccittFaxIndirectScalarTailObjects()
        );
        $boundary = $plan['ccitt_fax_decode_boundary'] ?? [];

        $t->same(['CCITTFaxDecode'], $plan['image_filters']);
        $t->same($ccittFaxIndirectScalarTailDecodeParms(), $plan['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same($ccittFaxIndirectScalarTailInvalidFields(), $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same($ccittFaxIndirectScalarTailDefaults(), $boundary['effective_decode_parms'] ?? null);
        $t->same($ccittFaxIndirectScalarTailInvalidFields(), $boundary['defaults_applied'] ?? null);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(false, $plan['inline_image']['native_raster_decode'] ?? null);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline CCITT scalar tail payload noise'));
    },
];
