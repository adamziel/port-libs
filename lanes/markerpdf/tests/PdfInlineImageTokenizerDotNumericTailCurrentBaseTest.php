<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageDotNumericTailPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'keeps dot-leading numeric inline image dictionary tails closed before WordPress text import' => static function (TestRunner $t) use ($inlineImageDotNumericTailPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $dictionary = '/W 1 /H 1 /CS /G /BPC 8 /D [1 0] .5 /F /MalformedPreview';
        $payload = "\x7f EI BT /F1 12 Tf 72 690 Td (Dot Tail Inline Noise) Tj ET rawtail";
        $content = "BT /F1 12 Tf 72 720 Td (Before Dot Tail Inline) Tj ET\n"
            . "BI {$dictionary} ID\n{$payload}\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After Dot Tail Inline) Tj ET";
        $pdf = $inlineImageDotNumericTailPdf($content);

        $expected = [
            'Before Dot Tail Inline',
            'After Dot Tail Inline',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $reviewPlan = $renderer->inlineImageReviewPlan($dictionary, "\x7f");

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Dot Tail Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, '.5 /F'));
        $t->true(!str_contains($plainText, 'MalformedPreview'));
        $t->true(!str_contains($plainText, "\x7f EI"));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);

        $t->same(true, $reviewPlan['inline_image_dictionary_operand_invalid']);
        $t->same(true, $reviewPlan['inline_image']['dictionary_operand_invalid']);
        $t->same(true, $reviewPlan['inline_image_payload_excluded_from_text']);
        $t->same(false, $reviewPlan['inline_image']['native_raster_decode']);
        $t->same(false, $reviewPlan['image_filter_boundary']['native_raster_decode']);
        $t->contains('inline_image_dictionary_operand_review_only', implode(',', $reviewPlan['notes']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, "\x7f", [], 1)
        );
    },
];
