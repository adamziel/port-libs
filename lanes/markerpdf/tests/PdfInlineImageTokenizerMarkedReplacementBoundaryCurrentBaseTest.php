<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerMarkedReplacementBoundaryPdf = static function (string $propertyName, string $label): string {
    $replacement = "Visible {$label} Replacement Before Stray";
    $after = "Visible After {$label} Replacement Stray";
    $payloadNoise = "{$label} Replacement Payload Noise";
    $content = "BT /F1 12 Tf 72 720 Td (Before {$label} Replacement Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td ({$payloadNoise}) Tj ET rawtail\n"
        . "EI\n"
        . "/Span << /{$propertyName} ({$replacement}) >> BDC EMC\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td ({$after}) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'closes preview-only fallback before replacement-only ActualText marked content followed by stray EI' => static function (TestRunner $t) use ($inlineImageTokenizerMarkedReplacementBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerMarkedReplacementBoundaryPdf('ActualText', 'ActualText');
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before ActualText Replacement Stray',
            'Visible ActualText Replacement Before Stray',
            'Visible After ActualText Replacement Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible ActualText Replacement Before Stray'));
        $t->true(str_contains($plainText, 'Visible After ActualText Replacement Stray'));
        $t->true(!str_contains($plainText, 'ActualText Replacement Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'JBIG2Decode'));
        $t->true(!str_contains($plainText, 'Hidden ActualText Source'));
    },
    'closes preview-only fallback before replacement-only Alt marked content followed by stray EI' => static function (TestRunner $t) use ($inlineImageTokenizerMarkedReplacementBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerMarkedReplacementBoundaryPdf('Alt', 'Alt');
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Alt Replacement Stray',
            'Visible Alt Replacement Before Stray',
            'Visible After Alt Replacement Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Alt Replacement Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Alt Replacement Stray'));
        $t->true(!str_contains($plainText, 'Alt Replacement Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'JBIG2Decode'));
        $t->true(!str_contains($plainText, 'Hidden Alt Source'));
    },
];
