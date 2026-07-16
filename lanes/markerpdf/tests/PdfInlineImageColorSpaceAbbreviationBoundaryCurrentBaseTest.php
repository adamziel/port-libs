<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageColorSpaceAbbreviationBoundaryPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves DeviceN colorant names that look like inline abbreviations before text extraction' => static function (
        TestRunner $t
    ) use ($inlineImageColorSpaceAbbreviationBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 1 /H 1 /CS [/DeviceN [/I /RGB] /CMYK 99 0 R << /Subtype /NChannel >>] /BPC 8 /D [0 1 1 0]';
        $payload = "\x01EI BT /F1 12 Tf 72 690 Td (DeviceN Colorant Inline Noise) Tj ET \x02";
        $content = "BT /F1 12 Tf 72 720 Td (Before DeviceN Colorant Inline) Tj ET\n"
            . "BI {$dictionary} ID\n"
            . $payload . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After DeviceN Colorant Inline) Tj ET";
        $pdf = $inlineImageColorSpaceAbbreviationBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, "\x01\x02");

        $t->true(str_contains($payload, 'EI BT'));
        $t->same([
            'Before DeviceN Colorant Inline',
            'After DeviceN Colorant Inline',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Before DeviceN Colorant Inline',
            'After DeviceN Colorant Inline',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Before DeviceN Colorant Inline\nAfter DeviceN Colorant Inline", $plainText);
        $t->same("Before DeviceN Colorant Inline\nAfter DeviceN Colorant Inline\n", $extractor->naiveGetText($pdf));
        foreach (['DeviceN Colorant Inline Noise', 'BT /F1', 'Tj ET', '/Indexed /DeviceRGB'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(
            '<< /Width 1 /Height 1 /ColorSpace [/DeviceN [/I /RGB] /DeviceCMYK 99 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [0 1 1 0] >>',
            $review['inline_image']['canonical_dictionary'] ?? null
        );
        $t->same(true, $review['inline_image']['uses_abbreviations']);
        $t->same('DeviceN', $review['source_color_space']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 2,
            'expected_components' => 2,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [1],
            'source' => 'explicit',
        ], $review['image_decode']);
        $t->same(true, $review['inline_image']['native_raster_decode']);
        $t->same(false, $review['inline_image_review_only']);
        $t->same(true, $review['inline_image_payload_excluded_from_text']);
        $notes = implode(',', $review['notes']);
        $t->contains('devicen_tint_transform_review_before_rgb_conversion', $notes);
        $t->contains('image_decode_inverts_components_before_rgb', $notes);
        $t->contains('inline_image_dictionary_abbreviations_expanded', $notes);
    },
];
