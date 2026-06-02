<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerBoundaryPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Tokenizer Boundary) Tj ET\n"
        . "BI BT /F1 12 Tf 72 704 Td (Stray BI Text Survives) Tj ET\n"
        . "BT /F1 12 Tf 72 688 Td (After Tokenizer Boundary) Tj ET\n"
        . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
        . "BT /F1 12 Tf 72 660 Td (Inline Image Payload Noise) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 672 Td (After Real Inline Image) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'keeps malformed BI tokenizer boundary from swallowing later WordPress text' => static function (TestRunner $t) use ($inlineImageTokenizerBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Tokenizer Boundary',
            'Stray BI Text Survives',
            'After Tokenizer Boundary',
            'After Real Inline Image',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Stray BI Text Survives'));
        $t->true(!str_contains($plainText, 'Inline Image Payload Noise'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
