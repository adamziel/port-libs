<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageIdTokenBoundaryCurrentBasePdf = static function (string $content, bool $declareLength = true): string {
    $streamDictionary = $declareLength ? '<< /Length ' . strlen($content) . ' >>' : '<< >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n{$streamDictionary}\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects ID-prefixed inline image dictionary tokens before WordPress text import' => static function (
        TestRunner $t
    ) use ($inlineImageIdTokenBoundaryCurrentBasePdf): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before ID Token Boundary) Tj ET\n"
            . "BI /W 1 /H 1 /CS /RGB /BPC 8 IDENTITY\n"
            . "BT /F1 12 Tf 72 704 Td (Recovered ID Prefix Text) Tj ET\n"
            . "EI\n"
            . "BI /W 18 /H 1 /CS /DeviceGray /BPC 8 ID\n"
            . "raw EI BT /F1 12 Tf 72 690 Td (Valid Inline Payload Noise) Tj ET tail\n"
            . "EI\n"
            . "BT /F1 12 Tf 72 688 Td (After ID Token Boundary) Tj ET";
        $pdf = $inlineImageIdTokenBoundaryCurrentBasePdf($content);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before ID Token Boundary',
            'Recovered ID Prefix Text',
            'After ID Token Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Valid Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'IDENTITY'));
        $t->true(!str_contains($plainText, 'raw EI'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
    'rejects ID-prefixed inline image tokens while scanning missing-Length content streams' => static function (
        TestRunner $t
    ) use ($inlineImageIdTokenBoundaryCurrentBasePdf): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before Missing Length Boundary) Tj ET\n"
            . "BI /W 1 /H 1 /CS /RGB /BPC 8 IDENTITY\n"
            . "BT /F1 12 Tf 72 704 Td (Recovered Missing Length Text) Tj ET\n"
            . "EI\n"
            . "BT /F1 12 Tf 72 688 Td (After Missing Length Boundary) Tj ET";
        $pdf = $inlineImageIdTokenBoundaryCurrentBasePdf($content, false);
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Missing Length Boundary',
            'Recovered Missing Length Text',
            'After Missing Length Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'IDENTITY'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
];
