<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerImageMaskPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'closes tight unfiltered inline ImageMask EI after the packed sample floor' => static function (
        TestRunner $t
    ) use ($inlineImageTokenizerImageMaskPdf): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before Tight ImageMask Boundary) Tj ET\n"
            . "BI /W 8 /H 1 /IM true ID\n"
            . "\x80EI\n"
            . "BT /F1 12 Tf 72 704 Td (Visible Tight ImageMask Boundary) Tj ET";
        $pdf = $inlineImageTokenizerImageMaskPdf($content);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Tight ImageMask Boundary',
            'Visible Tight ImageMask Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Tight ImageMask Boundary'));
        $t->true(!str_contains($plainText, "\x80EI"));
        $t->true(!str_contains($plainText, 'BitsPerComponent'));
        $t->true(!str_contains($plainText, 'ColorSpace'));
    },
    'keeps premature tight inline ImageMask EI bytes payload-owned until the packed sample floor' => static function (
        TestRunner $t
    ) use ($inlineImageTokenizerImageMaskPdf): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before Premature ImageMask Boundary) Tj ET\n"
            . "BI /W 9 /H 1 /IM true ID\n"
            . "\x80EI BT /F1 12 Tf 72 660 Td (Premature ImageMask Payload Noise) Tj ET rawtail\n"
            . "EI\n"
            . "BT /F1 12 Tf 72 704 Td (After Premature ImageMask Boundary) Tj ET";
        $pdf = $inlineImageTokenizerImageMaskPdf($content);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Premature ImageMask Boundary',
            'After Premature ImageMask Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'After Premature ImageMask Boundary'));
        $t->true(!str_contains($plainText, 'Premature ImageMask Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, "\x80EI"));
        $t->true(!str_contains($plainText, 'BitsPerComponent'));
        $t->true(!str_contains($plainText, 'ColorSpace'));
    },
];
