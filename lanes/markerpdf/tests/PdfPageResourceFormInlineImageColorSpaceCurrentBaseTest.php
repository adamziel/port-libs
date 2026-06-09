<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceFormInlineImageColorSpaceCurrentBasePdf = static function (): string {
    $payload = "\x01EI BT /F1 12 Tf 4 24 Td (Form Inline ColorSpace Payload Noise) Tj ET \x02\x03";
    $formStream = "BT /F1 12 Tf 4 36 Td (Before Form Inline ColorSpace) Tj ET\n"
        . "BI /W 1 /H 1 /CS /FormRGB /BPC 8 ID\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 4 18 Td (After Form Inline ColorSpace) Tj ET";
    $pageStream = "q 1 0 0 1 72 650 cm /Inline#20Form Do Q";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /XObject << /Inline#20Form 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n{$pageStream}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 60] /Resources << /Font << /F1 6 0 R >> /ColorSpace << /FormRGB /DeviceRGB >> >> /Length " . strlen($formStream) . " >>\nstream\n{$formStream}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses Form XObject local ColorSpace resources for inline image boundaries before WordPress text import' => static function (
        TestRunner $t
    ) use ($pageResourceFormInlineImageColorSpaceCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $pageResourceFormInlineImageColorSpaceCurrentBasePdf();
        $expected = [
            'Before Form Inline ColorSpace',
            'After Form Inline ColorSpace',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Form Inline ColorSpace Payload Noise'));
        $t->true(!str_contains($plainText, 'FormRGB'));
    },
];
