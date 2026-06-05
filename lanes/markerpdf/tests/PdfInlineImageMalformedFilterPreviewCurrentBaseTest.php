<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineMalformedFilterPreviewPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed on malformed inline image filter operands before output previews' => static function (TestRunner $t) use ($inlineMalformedFilterPreviewPdf): void {
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 3 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] /D [0 1]';
        $payload = 'ABC';
        $plan = $renderer->inlineImageReviewPlan($dictionary, $payload);

        $t->same(['MalformedFilterOperand'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'MalformedFilterOperand',
                'preview_only' => false,
                'decode_parms' => null,
            ],
        ], $plan['image_filter_details']);
        $t->same([], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('malformed_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->contains('inline_malformed_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 3)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageMaskPreviewRows(
                '/W 4 /H 1 /IM true /BPC 1 /F [ << /Bad true >> ] /D [0 1]',
                "\xa0",
                [],
                4
            )
        );

        $content = "BT /F1 12 Tf 72 720 Td (Before Malformed Preview Boundary) Tj ET\n"
            . "BI {$dictionary} ID\nABC EI BT /F1 12 Tf 72 700 Td (Malformed Preview Leak) Tj ET rawtail\nEI\n"
            . "BT /F1 12 Tf 72 680 Td (After Malformed Preview Boundary) Tj ET";
        $pdf = $inlineMalformedFilterPreviewPdf($content);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Before Malformed Preview Boundary',
            'After Malformed Preview Boundary',
        ], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Malformed Preview Leak'));
        $t->true(!str_contains($plainText, 'ABC EI'));
    },
    'fails closed on unresolved inline image filter references before indexed previews' => static function (TestRunner $t) use ($inlineMalformedFilterPreviewPdf): void {
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 2 /H 1 /CS [/I /RGB 1 91 0 R] /BPC 1 /F 99 0 R /D [0 1]';
        $payload = "\x80";
        $objects = [
            91 => '<000000FFFFFF>',
        ];
        $plan = $renderer->inlineImageReviewPlan($dictionary, $payload, $objects);

        $t->same(['UnresolvedFilterOperand'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'UnresolvedFilterOperand',
                'preview_only' => false,
                'decode_parms' => null,
            ],
        ], $plan['image_filter_details']);
        $t->same([], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('unresolved_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->contains('inline_unresolved_image_filter_operand_fail_closed', implode(',', $plan['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineIndexedImageStreamPreviewRows($dictionary, $payload, $objects, 2)
        );

        $content = "BT /F1 12 Tf 72 720 Td (Before Unresolved Preview Boundary) Tj ET\n"
            . "BI {$dictionary} ID\nXY EI BT /F1 12 Tf 72 700 Td (Unresolved Preview Leak) Tj ET rawtail\nEI\n"
            . "BT /F1 12 Tf 72 680 Td (After Unresolved Preview Boundary) Tj ET";
        $pdf = $inlineMalformedFilterPreviewPdf($content);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Before Unresolved Preview Boundary',
            'After Unresolved Preview Boundary',
        ], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Unresolved Preview Leak'));
        $t->true(!str_contains($plainText, 'XY EI'));
    },
];
