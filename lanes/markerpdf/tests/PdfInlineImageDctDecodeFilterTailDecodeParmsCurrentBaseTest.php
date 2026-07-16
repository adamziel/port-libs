<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineDctDecodeFilterTailDecodeParmsCurrentBaseFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before inline DCT filter tail) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After inline DCT filter tail) Tj ET';
    $leak = 'BT /F1 12 Tf 72 700 Td (Inline DCT filter tail payload leak) Tj ET';
    $dictionary = '/W 1 /H 1 /CS /RGB /BPC 8 /F [/DCT] /Crypt /DP << /ColorTransform 1 >>';
    $payload = "\xff\xd8\xff\xe0JFIF\0 inline DCT filter tail bytes EI {$leak} still image bytes \xff\xd9";
    $content = $before . "\n"
        . "BI {$dictionary} ID\n{$payload}\nEI\n"
        . $after;

    return [
        'dictionary' => $dictionary,
        'payload' => $payload,
        'expected_lines' => [
            'Before inline DCT filter tail',
            'After inline DCT filter tail',
        ],
        'pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "%%EOF",
    ];
};

return [
    'preserves inline DCTDecode DecodeParms after malformed filter-array tails' => static function (
        TestRunner $t
    ) use ($inlineDctDecodeFilterTailDecodeParmsCurrentBaseFixture): void {
        $fixture = $inlineDctDecodeFilterTailDecodeParmsCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $plainText = $extractor->extractPlainText($fixture['pdf']);
        $plan = $renderer->inlineImageReviewPlan($fixture['dictionary'], $fixture['payload']);

        $expectedDecodeParms = [
            'type' => 'DCTDecode',
            'color_transform' => 1,
            'valid_color_transform' => true,
        ];
        $expectedBoundary = [
            'declared_filter' => 'DCTDecode',
            'canonical_filter' => 'DCTDecode',
            'alias_used' => false,
            'non_null_filter_index' => 1,
            'filters_before_dctdecode' => ['MalformedFilterOperand'],
            'native_prefix_filters' => ['MalformedFilterOperand'],
            'preview_only_filters_before_dctdecode' => [],
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

        $t->same($fixture['expected_lines'], $extractor->extractTextLines($fixture['pdf']));
        $t->same($fixture['expected_lines'], $extractor->extractTextRuns($fixture['pdf']));
        $t->same(implode("\n", $fixture['expected_lines']), $plainText);
        $t->same(implode("\n", $fixture['expected_lines']) . "\n", $extractor->naiveGetText($fixture['pdf']));
        $t->true(!str_contains($plainText, 'Inline DCT filter tail payload leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
        $t->true(!str_contains($plainText, 'still image bytes'));
        $t->true(!str_contains($plainText, 'Crypt'));

        $t->same(
            '<< /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/DCTDecode] /Crypt /DecodeParms << /ColorTransform 1 >> >>',
            $plan['inline_image']['canonical_dictionary'] ?? null
        );
        $t->same(['MalformedFilterOperand', 'DCTDecode'], $plan['image_filters']);
        $t->same(['DCTDecode'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same('reject_malformed_filter_operands', $plan['image_filter_boundary']['filter_operand_policy'] ?? null);
        $t->same(1, $plan['image_filter_boundary']['malformed_filter_operand_count'] ?? null);
        $t->same(0, $plan['image_filter_boundary']['unresolved_filter_operand_count'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(true, $plan['inline_image_dictionary_operand_invalid']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->same(false, $plan['inline_image']['native_raster_decode'] ?? null);
        $t->same($expectedBoundary, $plan['dctdecode_filter_boundary'] ?? null);
        $t->same($expectedDecodeParms, $plan['image_filter_details'][1]['decode_parms'] ?? null);
        $t->contains('malformed_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->contains('inline_image_dictionary_operand_review_only', implode(',', $plan['notes']));
        $t->contains('inline_dct_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('inline_malformed_image_filter_operand_fail_closed', implode(',', $plan['notes']));
    },
];
