<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$ccittFaxInlineSourceAliasPayload = static function (): array {
    $leak = 'BT /F1 12 Tf 72 700 Td (Inline source CCF alias payload leak) Tj ET';
    $nativeBytes = "\x11\x22\x33 EI {$leak} \x00\x10\x01";

    return [
        'native_bytes' => $nativeBytes,
        'encoded' => strtoupper(bin2hex($nativeBytes)) . '>',
        'leak' => 'Inline source CCF alias payload leak',
    ];
};

$ccittFaxInlineSourceAliasDictionary = static function (): string {
    return '/W 16 /H 1 /IM true /F [/AHx /CCF] '
        . '/DP [null << /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] '
        . '/D [1 0]';
};

$ccittFaxInlineSourceAliasPdf = static function (string $payload): string {
    $before = 'BT /F1 12 Tf 72 720 Td (Before inline CCF alias import) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After inline CCF alias import) Tj ET';
    $content = $before . "\n"
        . 'BI /W 16 /H 1 /IM true /F [/AHx /CCF] '
        . '/DP [null << /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] '
        . "/D [1 0] ID\n{$payload}\nEI\n"
        . $after;

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves inline CCF source aliases separately from canonical CCITT Fax review metadata' => static function (
        TestRunner $t
    ) use ($ccittFaxInlineSourceAliasPayload, $ccittFaxInlineSourceAliasDictionary): void {
        $case = $ccittFaxInlineSourceAliasPayload();
        $plan = (new PdfImageRenderer())->inlineImageReviewPlan(
            $ccittFaxInlineSourceAliasDictionary(),
            $case['encoded']
        );
        $inline = $plan['inline_image'];

        $t->same(['ASCIIHexDecode', 'CCITTFaxDecode'], $plan['image_filters']);
        $t->same(['CCITTFaxDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(['AHx', 'CCF'], $inline['source_filters']);
        $t->same(['CCF'], $inline['source_preview_only_filters']);
        $t->same([
            [
                'index' => 0,
                'source' => 'AHx',
                'canonical' => 'ASCIIHexDecode',
            ],
            [
                'index' => 1,
                'source' => 'CCF',
                'canonical' => 'CCITTFaxDecode',
            ],
        ], $inline['source_filter_aliases']);
        $t->same(true, $inline['source_ccitt_alias_used']);
        $t->same(['CCITTFaxDecode'], $inline['review_only_filters']);
        $t->same(false, $inline['native_raster_decode']);
        $t->same(
            '<< /Width 16 /Height 1 /ImageMask true /Filter [/ASCIIHexDecode /CCITTFaxDecode] /DecodeParms [null << /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] /Decode [1 0] >>',
            $inline['canonical_dictionary']
        );

        $t->same('CCITTFaxDecode', $plan['ccitt_fax_filter_boundary']['declared_filter'] ?? null);
        $t->same('CCITTFaxDecode', $plan['ccitt_fax_filter_boundary']['canonical_filter'] ?? null);
        $t->same(false, $plan['ccitt_fax_filter_boundary']['alias_used'] ?? null);
        $t->same(['ASCIIHexDecode'], $plan['ccitt_fax_filter_boundary']['native_prefix_filters'] ?? null);
        $t->same(true, $plan['ccitt_fax_filter_boundary']['ccitt_is_terminal_filter'] ?? null);
        $t->same(false, $plan['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null);
        $t->same([
            'k' => -1,
            'columns' => 16,
            'rows' => 1,
            'black_is_1' => true,
            'encoded_byte_align' => false,
            'end_of_line' => false,
            'end_of_block' => true,
            'damaged_rows_before_error' => 0,
        ], $plan['ccitt_fax_decode_boundary']['effective_decode_parms'] ?? null);
        $t->contains('inline_ccitt_fax_source_alias_preserved', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', $case['leak']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', $case['native_bytes']));
    },
    'keeps inline source-alias CCITT Fax payload excluded from WordPress text extraction' => static function (
        TestRunner $t
    ) use ($ccittFaxInlineSourceAliasPayload, $ccittFaxInlineSourceAliasPdf): void {
        $case = $ccittFaxInlineSourceAliasPayload();
        $pdf = $ccittFaxInlineSourceAliasPdf($case['encoded']);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before inline CCF alias import',
            'After inline CCF alias import',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, $case['leak']));
        $t->true(!str_contains($plainText, '/CCF'));
        $t->true(!str_contains($plainText, '/AHx'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
    },
];
