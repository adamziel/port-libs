<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceAnnotationAppearancePropertiesPdf = static function (): string {
    $emptyContent = '';
    $inheritedAppearance = '/Span /SharedActual BDC BT /F1 10 Tf 0 18 Td (Inherited appearance glyph noise) Tj ET EMC';
    $localAppearance = '/Span /SharedActual BDC BT /F1 10 Tf 0 18 Td (Local appearance glyph noise) Tj ET EMC';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [20 0 R 21 0 R] /Contents 5 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($emptyContent) . " >>\nstream\n{$emptyContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /Properties << /SharedActual << /ActualText (Inherited page ActualText) >> >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 660 240 700] /AP << /N 30 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 620 240 650] /AP << /N 31 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($inheritedAppearance) . " >>\nstream\n{$inheritedAppearance}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Resources << /Font << /F1 7 0 R >> /Properties << /SharedActual << /ActualText (Appearance local ActualText) >> >> >> /Length " . strlen($localAppearance) . " >>\nstream\n{$localAppearance}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'scopes annotation appearance Properties before inherited page properties can replace local ActualText' => static function (
        TestRunner $t
    ) use ($pageResourceAnnotationAppearancePropertiesPdf): void {
        $pdf = $pageResourceAnnotationAppearancePropertiesPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Inherited page ActualText',
            'Appearance local ActualText',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'Properties'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['SharedActual'], $resources['properties_names'] ?? null);
        $t->same(1, substr_count($plainText, 'Inherited page ActualText'));
        $t->same(1, substr_count($plainText, 'Appearance local ActualText'));
        $t->same(false, str_contains($plainText, 'glyph noise'));
    },
];
