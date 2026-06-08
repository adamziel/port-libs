<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceInlineImageColorSpaceInheritanceCurrentBasePdf = static function (): string {
    $payload = "\x01EI BT /F1 12 Tf 72 660 Td (Inherited Inline ColorSpace Payload Noise) Tj ET \x02\x03";
    $content = "BT /F1 12 Tf 72 720 Td (Before Inherited Inline ColorSpace) Tj ET\n"
        . "BI /W 1 /H 1 /CS /InheritedRGB /BPC 8 ID\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Inherited Inline ColorSpace) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> /ColorSpace << /InheritedRGB /DeviceRGB >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses inherited page ColorSpace resources for inline image sample boundaries before WordPress text import' => static function (
        TestRunner $t
    ) use ($pageResourceInlineImageColorSpaceInheritanceCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $pageResourceInlineImageColorSpaceInheritanceCurrentBasePdf();
        $expected = [
            'Before Inherited Inline ColorSpace',
            'After Inherited Inline ColorSpace',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Inherited Inline ColorSpace Payload Noise'));
        $t->true(!str_contains($plainText, 'InheritedRGB'));
    },
];
