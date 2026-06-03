<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageDecodeBoundaryPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payload = '87cURDc^jtCh* EI BT /F1 12 Tf 72 700 Td (ASCII85 inline image leak) Tj ET ~>';
        $content = "BT /F1 12 Tf 72 720 Td (Before A85 Inline Image) Tj ET\n"
            . "BI /F /A85 ID\n{$payload}\nEI\n"
            . "BT /F1 12 Tf 72 680 Td (After A85 Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before A85 Inline Image',
            'After A85 Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'ASCII85 inline image leak'));
        $t->true(!str_contains($plainText, '87cURDc'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'decodes Flate DecodeParms inline image payload before accepting EI boundaries' => static function (TestRunner $t) use ($inlineImageDecodeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $payloadText = 'raw EI BT /F1 12 Tf 72 690 Td (Inline DP Image Noise) Tj ET';
        $compressedImage = gzcompress("\0" . $payloadText, 0);
        if (!is_string($compressedImage)) {
            throw new RuntimeException('Unable to build inline image DecodeParms fixture.');
        }

        $content = "BT /F1 12 Tf 72 720 Td (Before DP Inline Image) Tj ET\n"
            . 'BI /W ' . strlen($payloadText) . ' /H 1 /CS /G /BPC 8 /F /Fl '
            . '/DP << /Predictor 12 /Columns ' . strlen($payloadText) . " /Colors 1 /BitsPerComponent 8 >> ID "
            . $compressedImage . "\nEI\n"
            . "BT /F1 12 Tf 72 704 Td (After DP Inline Image) Tj ET";
        $pdf = $inlineImageDecodeBoundaryPdf($content);

        $expected = [
            'Before DP Inline Image',
            'After DP Inline Image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->true(str_contains($compressedImage, ' EI '));
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Inline DP Image Noise'));
        $t->true(!str_contains($plainText, 'raw EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
];
