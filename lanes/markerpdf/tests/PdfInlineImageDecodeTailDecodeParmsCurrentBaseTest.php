<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageDecodeTailDecodeParmsPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves inline DecodeParms after malformed Decode tail operands before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($inlineImageDecodeTailDecodeParmsPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $sample = 'Z';
        $compressedSample = gzcompress($sample, 0);
        if (!is_string($compressedSample)) {
            throw new RuntimeException('Unable to build inline Decode tail DecodeParms fixture.');
        }

        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [1 0] 99 0 R /DP << /Predictor 1 >>';
        $surplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Decode Tail DecodeParms Inline Noise) Tj ET rawtail';
        $payload = $compressedSample . $surplus;
        $content = "BT /F1 12 Tf 72 720 Td (Before Decode Tail Inline Image) Tj ET\n"
            . "BI {$dictionary} ID\n{$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Decode Tail Inline Image) Tj ET";
        $pdf = $inlineImageDecodeTailDecodeParmsPdf($content);

        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, $compressedSample);
        $expectedDecodeParms = [
            'type' => 'FlateDecode',
            'predictor' => 1,
            'columns' => null,
            'colors' => null,
            'bits_per_component' => null,
            'early_change' => null,
            'valid_decode_parms' => true,
        ];

        $t->true(str_contains($surplus, ' EI '));
        $t->same([
            'Before Decode Tail Inline Image',
            'After Decode Tail Inline Image',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Before Decode Tail Inline Image',
            'After Decode Tail Inline Image',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Before Decode Tail Inline Image\nAfter Decode Tail Inline Image", $plainText);
        $t->same("Before Decode Tail Inline Image\nAfter Decode Tail Inline Image\n", $extractor->naiveGetText($pdf));
        foreach (['Decode Tail DecodeParms Inline Noise', 'rawtail', 'ZZ EI', '99 0 R'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(
            '<< /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /DecodeParms << /Predictor 1 >> >>',
            $review['inline_image']['canonical_dictionary'] ?? null
        );
        $t->same(['FlateDecode'], $review['image_filters']);
        $t->same($expectedDecodeParms, $review['image_filter_details'][0]['decode_parms'] ?? null);
        $t->same([], $review['inline_image']['unsupported_filters']);
        $t->same(true, $review['image_decode_component_mismatch']);
        $t->same('invalid', $review['image_decode']['source']);
        $t->same(0, $review['image_decode']['component_count']);
        $t->same(1, $review['image_decode']['expected_components']);
        $t->same(false, $review['image_decode']['valid_for_components']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->same(true, $review['inline_image_payload_excluded_from_text']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedSample, [], 1)
        );
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
];
