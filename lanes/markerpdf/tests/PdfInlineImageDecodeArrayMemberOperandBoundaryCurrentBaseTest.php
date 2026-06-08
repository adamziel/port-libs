<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageDecodeArrayMemberOperandPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed on nonnumeric inline Decode array members before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($inlineImageDecodeArrayMemberOperandPdf): void {
        $renderer = new PdfImageRenderer();
        $extractor = new PdfTextExtractor();
        $compressedSample = gzcompress('Z', 0);
        if (!is_string($compressedSample)) {
            throw new RuntimeException('Unable to build inline Decode array member fixture.');
        }

        $cases = [
            'null' => [
                '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 null]',
                'Before Null Decode Array Member Inline',
                'After Null Decode Array Member Inline',
                'Null Decode Array Member Inline Noise',
            ],
            'dictionary' => [
                '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 << /Bad true >>]',
                'Before Dictionary Decode Array Member Inline',
                'After Dictionary Decode Array Member Inline',
                'Dictionary Decode Array Member Inline Noise',
            ],
            'unresolved reference' => [
                '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 99 0 R]',
                'Before Unresolved Decode Array Member Inline',
                'After Unresolved Decode Array Member Inline',
                'Unresolved Decode Array Member Inline Noise',
            ],
        ];

        $content = '';
        $expectedLines = [];
        foreach ($cases as [$dictionary, $before, $after, $noise]) {
            $payload = $compressedSample . "ZZ EI BT /F1 12 Tf 72 690 Td ({$noise}) Tj ET rawtail";
            $content .= "BT /F1 12 Tf 72 720 Td ({$before}) Tj ET\n"
                . "BI {$dictionary} ID\n{$payload}\nEI\n"
                . "BT /F1 12 Tf 72 704 Td ({$after}) Tj ET\n";
            $expectedLines[] = $before;
            $expectedLines[] = $after;
            $t->true(str_contains($payload, ' EI '));

            $review = $renderer->inlineImageReviewPlan($dictionary, $compressedSample);
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
        }

        $pdf = $inlineImageDecodeArrayMemberOperandPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expectedLines, $extractor->extractTextLines($pdf));
        $t->same($expectedLines, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expectedLines), $plainText);
        $t->same(implode("\n", $expectedLines) . "\n", $extractor->naiveGetText($pdf));
        foreach ($cases as [, , , $noise]) {
            $t->true(!str_contains($plainText, $noise));
        }
        foreach (['ZZ EI', 'rawtail', '99 0 R', '<< /Bad true >>'] as $excludedText) {
            $t->true(!str_contains($plainText, $excludedText));
        }
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'keeps numeric inline Decode array members valid with PDF comments between operands' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();

        $preview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
            "/W 1 /H 1 /CS /G /BPC 8 /D [1 % comment with fake 0 null EI\n 0]",
            "\xff",
            [],
            1
        );

        $t->same('explicit', $preview['image_decode']['source']);
        $t->same(true, $preview['image_decode']['valid_for_components']);
        $t->same([0], $preview['image_decode']['inverted_components']);
        $t->same([255.0], $preview['pixels'][0]['raw_sample']);
        $t->same(0.0, $preview['pixels'][0]['decoded_gray']);
        $t->same(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 1.0], $preview['pixels'][0]['output_rgba']);
        $t->same(true, $preview['inline_image']['native_raster_decode']);
        $t->same(false, $preview['review_only_image_stream']);
    },
];
