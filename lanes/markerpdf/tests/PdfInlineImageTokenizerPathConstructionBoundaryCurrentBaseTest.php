<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerPathConstructionBoundaryPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Curve Path Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Curve Path Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "10 10 m\n"
        . "20 20 30 20 40 10 c\n"
        . "50 20 60 10 v\n"
        . "80 20 100 10 y\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Curve Path Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Curve Path Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'closes preview-only fallback before Bezier path-construction text followed by stray EI operator' => static function (
        TestRunner $t
    ) use ($inlineImageTokenizerPathConstructionBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerPathConstructionBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Curve Path Stray',
            'Visible Curve Path Before Stray',
            'Visible After Curve Path Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Curve Path Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Curve Path Stray'));
        $t->true(!str_contains($plainText, 'Curve Path Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'JBIG2Decode'));
        $t->true(!str_contains($plainText, '20 20 30 20 40 10 c'));
        $t->true(!str_contains($plainText, '50 20 60 10 v'));
        $t->true(!str_contains($plainText, '80 20 100 10 y'));
    },
];
