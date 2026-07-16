<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerIndirectPropertyPdf = static function (string $markedContentOperator): string {
    $label = $markedContentOperator === 'BDC' ? 'BDC' : 'DP';
    $markedContentOpen = $markedContentOperator === 'BDC'
        ? "/Span 6 0 R BDC\nBT /F1 12 Tf 72 704 Td (Visible Indirect BDC Text) Tj ET EMC\n"
        : "/Span 6 0 R DP\nBT /F1 12 Tf 72 704 Td (Visible Indirect DP Text) Tj ET\n";
    $content = "BT /F1 12 Tf 72 720 Td (Before Indirect {$label} Boundary) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Indirect {$label} Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . $markedContentOpen
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (After Indirect {$label} Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /MCID 9 >>\nendobj\n"
        . "%%EOF";
};

return [
    'closes preview-only fallback before indirect BDC property text followed by stray EI operator' => static function (
        TestRunner $t
    ) use ($inlineImageTokenizerIndirectPropertyPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerIndirectPropertyPdf('BDC');
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Indirect BDC Boundary',
            'Visible Indirect BDC Text',
            'After Indirect BDC Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Indirect BDC Text'));
        $t->true(str_contains($plainText, 'After Indirect BDC Boundary'));
        $t->true(!str_contains($plainText, 'Indirect BDC Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'JBIG2Decode'));
        $t->true(!str_contains($plainText, '/Span 6 0 R BDC'));
    },
    'closes preview-only fallback before indirect DP property text followed by stray EI operator' => static function (
        TestRunner $t
    ) use ($inlineImageTokenizerIndirectPropertyPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerIndirectPropertyPdf('DP');
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Indirect DP Boundary',
            'Visible Indirect DP Text',
            'After Indirect DP Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Indirect DP Text'));
        $t->true(str_contains($plainText, 'After Indirect DP Boundary'));
        $t->true(!str_contains($plainText, 'Indirect DP Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'JBIG2Decode'));
        $t->true(!str_contains($plainText, '/Span 6 0 R DP'));
    },
];
