<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerTextPositionBoundaryPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Text Position Boundary) Tj ET\n"
        . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x80 EI BT /F1 12 Tf 72 660 Td (Text Position Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Td Text) Tj ET\n"
        . "BT /F1 12 Tf 1 0 0 1 72 688 Tm (Visible Tm Text) Tj ET\n"
        . "BT /F1 12 Tf 14 TL 72 672 Td (Visible T Star First) Tj T* (Visible T Star Second) Tj ET\n"
        . "BT /F1 12 Tf 72 656 Td (Visible Quote First) Tj T* (Visible Single Quote) ' 2 3 (Visible Double Quote) \" ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 620 Td (After Text Position Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'closes inline image tokenizer boundary before text-positioning and quote-showing text' => static function (
        TestRunner $t
    ) use ($inlineImageTokenizerTextPositionBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerTextPositionBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Text Position Boundary',
            'Visible Td Text',
            'Visible Tm Text',
            'Visible T Star First',
            'Visible T Star Second',
            'Visible Quote First',
            'Visible Single Quote',
            'Visible Double Quote',
            'After Text Position Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);

        foreach ([
            'Text Position Payload Noise',
            'rawtail',
            'JBIG2Decode',
            "\x80 EI",
        ] as $excluded) {
            $t->true(!str_contains($plainText, $excluded), 'excluded inline payload fragment: ' . $excluded);
        }
    },
];
