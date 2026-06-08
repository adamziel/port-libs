<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageDecodeParmsTailPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$pngSubPredictorEncode = static function (string $rowBytes, int $columns): string {
    if (strlen($rowBytes) !== $columns) {
        throw new InvalidArgumentException('Focused PNG Sub predictor fixture expects one complete row.');
    }

    $encoded = "\x01";
    for ($index = 0; $index < $columns; $index++) {
        $left = $index === 0 ? 0 : ord($rowBytes[$index - 1]);
        $encoded .= chr((ord($rowBytes[$index]) - $left + 256) % 256);
    }

    return $encoded;
};

return [
    'preserves inline Filter and Decode metadata after malformed DecodeParms tail before WordPress extraction' => static function (
        TestRunner $t
    ) use ($inlineImageDecodeParmsTailPdf, $pngSubPredictorEncode): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $decodedSamples = 'ABC';
        $payload = gzcompress($pngSubPredictorEncode($decodedSamples, 3), 0);
        if (!is_string($payload)) {
            throw new RuntimeException('Unable to build inline DecodeParms tail filter fixture.');
        }

        $dictionary = '/W 3 /H 1 /CS /G /BPC 8 '
            . '/DP << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >> 99 0 R '
            . '/F /Fl /D [1 0]';
        $surplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (DecodeParms Tail Inline Noise) Tj ET rawtail';
        $content = "BT /F1 12 Tf 72 720 Td (Before DecodeParms Tail Inline) Tj ET\n"
            . "BI {$dictionary} ID {$payload}{$surplus}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After DecodeParms Tail Inline) Tj ET";
        $pdf = $inlineImageDecodeParmsTailPdf($content);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $renderer->inlineImageReviewPlan($dictionary, $payload . $surplus);
        $decodeParms = $review['image_filter_details'][0]['decode_parms'] ?? [];

        $t->same([
            'Before DecodeParms Tail Inline',
            'After DecodeParms Tail Inline',
        ], $extractor->extractTextLines($pdf));
        $t->same("Before DecodeParms Tail Inline\nAfter DecodeParms Tail Inline", $plainText);
        foreach (['DecodeParms Tail Inline Noise', 'rawtail', 'ZZ EI', '99 0 R'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }

        $t->same(
            '<< /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /DecodeParms << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >> 99 0 R /Filter /FlateDecode /Decode [1 0] >>',
            $review['inline_image']['canonical_dictionary']
        );
        $t->same(['FlateDecode'], $review['image_filters']);
        $t->same([
            'ranges' => [['min' => 1.0, 'max' => 0.0]],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $review['image_decode']);
        $t->same('FlateDecode', $decodeParms['type'] ?? null);
        $t->same(12, $decodeParms['predictor'] ?? null);
        $t->same(3, $decodeParms['columns'] ?? null);
        $t->same(1, $decodeParms['colors'] ?? null);
        $t->same(8, $decodeParms['bits_per_component'] ?? null);
        $t->same(false, $decodeParms['valid_decode_parms'] ?? null);
        $t->same(['decode_parms_operand'], $decodeParms['invalid_decode_parms_fields'] ?? null);
        $t->same(true, $review['inline_image_dictionary_operand_invalid']);
        $t->same(true, $review['inline_image_review_only']);
        $t->same(false, $review['inline_image']['native_raster_decode']);
        $t->same(['FlateDecode'], $review['inline_image']['unsupported_filters']);
        $t->contains('inline_image_dictionary_operand_review_only', implode(',', $review['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 3)
        );
    },
];
