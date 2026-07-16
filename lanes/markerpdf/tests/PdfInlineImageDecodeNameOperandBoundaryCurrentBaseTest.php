<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageDecodeNameOperandBoundaryPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves inline Decode name operands without expanding filter names before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($inlineImageDecodeNameOperandBoundaryPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $compressedSample = gzcompress('Z', 0);
        if (!is_string($compressedSample)) {
            throw new RuntimeException('Unable to build inline Decode name operand fixture.');
        }

        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /D [0 /Fl] /F /Fl';
        $scalarDictionary = '/W 1 /H 1 /CS /G /BPC 8 /D /Fl /F /Fl';
        $surplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Decode Name Operand Inline Noise) Tj ET rawtail';
        $payload = $compressedSample . $surplus;
        $content = "BT /F1 12 Tf 72 720 Td (Before Decode Name Operand Inline) Tj ET\n"
            . "BI {$dictionary} ID\n{$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Decode Name Operand Inline) Tj ET";
        $pdf = $inlineImageDecodeNameOperandBoundaryPdf($content);

        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, $compressedSample);
        $scalarReview = $renderer->inlineImageReviewPlan($scalarDictionary, $compressedSample);

        $t->true(str_contains($surplus, ' EI '));
        $t->same([
            'Before Decode Name Operand Inline',
            'After Decode Name Operand Inline',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Before Decode Name Operand Inline',
            'After Decode Name Operand Inline',
        ], $extractor->extractTextRuns($pdf));
        $t->same("Before Decode Name Operand Inline\nAfter Decode Name Operand Inline", $plainText);
        $t->same("Before Decode Name Operand Inline\nAfter Decode Name Operand Inline\n", $extractor->naiveGetText($pdf));
        foreach (['Decode Name Operand Inline Noise', 'rawtail', 'ZZ EI', '/FlateDecode]'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(
            '<< /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [0 /Fl] /Filter /FlateDecode >>',
            $review['inline_image']['canonical_dictionary'] ?? null
        );
        $t->same(
            '<< /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode /Fl /Filter /FlateDecode >>',
            $scalarReview['inline_image']['canonical_dictionary'] ?? null
        );
        $t->same(['FlateDecode'], $review['image_filters']);
        $t->same(['FlateDecode'], $scalarReview['image_filters']);
        $t->same(true, $review['inline_image_abbreviations_expanded']);
        $t->same(true, $scalarReview['inline_image_abbreviations_expanded']);

        $t->same(true, $review['image_decode_component_mismatch']);
        $t->same('invalid', $review['image_decode']['source']);
        $t->same(0, $review['image_decode']['component_count']);
        $t->same(1, $review['image_decode']['expected_components']);
        $t->same(false, $review['image_decode']['valid_for_components']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->same(true, $review['inline_image_payload_excluded_from_text']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));

        $t->same(true, $scalarReview['image_decode_component_mismatch']);
        $t->same('invalid', $scalarReview['image_decode']['source']);
        $t->same(true, $scalarReview['inline_image_review_only']);
        $t->same(false, $scalarReview['inline_image']['native_raster_decode']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $scalarReview['notes']));

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedSample, [], 1)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($scalarDictionary, $compressedSample, [], 1)
        );
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'preserves inline ImageMask Decode name operands before stencil preview review' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 1 /H 1 /IM true /D [/I 0]';
        $scalarDictionary = '/W 1 /H 1 /IM true /D /I';
        $review = $renderer->inlineImageReviewPlan($dictionary, "\x80");
        $scalarReview = $renderer->inlineImageReviewPlan($scalarDictionary, "\x80");

        $t->same(
            '<< /Width 1 /Height 1 /ImageMask true /Decode [/I 0] >>',
            $review['inline_image']['canonical_dictionary'] ?? null
        );
        $t->same(
            '<< /Width 1 /Height 1 /ImageMask true /Decode /I >>',
            $scalarReview['inline_image']['canonical_dictionary'] ?? null
        );
        $t->same('ImageMask', $review['source_color_space']);
        $t->same(true, $review['image_decode_component_mismatch']);
        $t->same(true, $review['image_mask']['decode']['valid_for_components'] === false);
        $t->same(0, $review['image_mask']['decode']['component_count']);
        $t->same(1, $review['image_mask']['decode']['expected_components']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $review['notes']));

        $t->same(true, $scalarReview['image_decode_component_mismatch']);
        $t->same(true, $scalarReview['inline_image_review_only']);
        $t->same(false, $scalarReview['inline_image']['native_raster_decode']);
        $t->contains('inline_image_decode_operand_review_only', implode(',', $scalarReview['notes']));

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageMaskPreviewRows($dictionary, "\x80", [], 1)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageMaskPreviewRows($scalarDictionary, "\x80", [], 1)
        );
    },
];
