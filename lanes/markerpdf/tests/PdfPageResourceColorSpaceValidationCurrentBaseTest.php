<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceColorSpaceValidationCurrentBasePdf = static function (): string {
    $firstPayload = "\x01EI BT /F1 12 Tf 72 660 Td (ColorSpace first inline payload leak) Tj ET \x02";
    $secondPayload = "\x03EI BT /F1 12 Tf 72 640 Td (ColorSpace second inline payload leak) Tj ET \x04";
    $content = "BT /F1 12 Tf 72 720 Td (Before ColorSpace review) Tj ET\n"
        . "BI /W 1 /H 1 /CS /GoodIndirectName /BPC 8 ID\n"
        . $firstPayload . "\nEI\n"
        . "BI /W 1 /H 1 /CS /GoodIndirectArray /BPC 8 ID\n"
        . $secondPayload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After ColorSpace review) Tj ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /ColorSpace << "
        . "/GoodName /DeviceRGB "
        . "/GoodArray [/Indexed /DeviceRGB 0 <00>] "
        . "/GoodIndirectName 11 0 R "
        . "/GoodIndirectArray 12 0 R "
        . "/BadString (ColorSpace review string leak) "
        . "/BadNumber 99 "
        . "/BadDictionary << /Private (ColorSpace review dictionary leak) >> "
        . "/BadNull null "
        . ">> >>\nendobj\n"
        . "11 0 obj\n/DeviceRGB\nendobj\n"
        . "12 0 obj\n[/CalRGB << /WhitePoint [1 1 1] >>]\nendobj\n"
        . "%%EOF";
};

return [
    'validates inherited page ColorSpace resource operands before WordPress review metadata' => static function (
        TestRunner $t
    ) use ($pageResourceColorSpaceValidationCurrentBasePdf): void {
        $pdf = $pageResourceColorSpaceValidationCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Before ColorSpace review',
            'After ColorSpace review',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'ColorSpace'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(
            ['GoodName', 'GoodArray', 'GoodIndirectName', 'GoodIndirectArray'],
            $resources['color_space_names'] ?? null
        );
        $t->same(false, in_array('BadString', $resources['color_space_names'] ?? [], true));
        $t->same(false, in_array('BadNumber', $resources['color_space_names'] ?? [], true));
        $t->same(false, in_array('BadDictionary', $resources['color_space_names'] ?? [], true));
        $t->same(false, in_array('BadNull', $resources['color_space_names'] ?? [], true));
        $t->same(false, str_contains($plainText, 'ColorSpace first inline payload leak'));
        $t->same(false, str_contains($plainText, 'ColorSpace second inline payload leak'));
        $t->same(false, str_contains($plainText, 'ColorSpace review string leak'));
        $t->same(false, str_contains($plainText, 'ColorSpace review dictionary leak'));
        $t->same(false, str_contains($plainText, 'GoodIndirectName'));
        $t->same(false, str_contains($plainText, 'GoodIndirectArray'));
    },
];
