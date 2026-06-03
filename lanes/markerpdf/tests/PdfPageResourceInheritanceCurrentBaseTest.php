<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceInheritanceCurrentBasePdf = static function (): string {
    $pageContent = 'q /LegacyOuter Do Q q /ExplicitOuter Do Q';
    $legacyOuter = 'q /LegacyNested Do Q';
    $legacyNested = 'BT /F1 12 Tf 12 24 Td (Legacy nested form inherited resources) Tj ET';
    $explicitOuter = 'q /LegacyNested Do Q BT /F1 12 Tf 12 24 Td (Explicit form local resources) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 80] /Length " . strlen($legacyOuter) . " >>\nstream\n{$legacyOuter}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 80] /Length " . strlen($legacyNested) . " >>\nstream\n{$legacyNested}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 80] /Resources << /Font << /F1 9 0 R >> >> /Length " . strlen($explicitOuter) . " >>\nstream\n{$explicitOuter}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /LegacyOuter 5 0 R /LegacyNested 6 0 R /ExplicitOuter 8 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses inherited page resources for legacy Form XObjects that omit Resources without merging explicit form resources' => static function (TestRunner $t) use ($pageResourceInheritanceCurrentBasePdf): void {
        $pdf = $pageResourceInheritanceCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Legacy nested form inherited resources',
            'Explicit form local resources',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $styledPages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, substr_count($plainText, 'Legacy nested form inherited resources'));
        $t->same(false, str_contains($plainText, 'LegacyNested'));
        $t->same(false, str_contains($plainText, 'LegacyOuter'));
    },
];
