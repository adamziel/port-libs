<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceInheritanceCurrentBaseCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode focused resource inheritance CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceInheritanceCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

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

$pageResourceNestedCategoryCurrentBasePdf = static function () use ($pageResourceInheritanceCurrentBaseCMap): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q';
    $privateForm = 'BT /F1 12 Tf 12 24 Td (Private nested XObject leak) Tj ET';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Current inherited form text) Tj ET';
    $privateCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Private nested font leak',
    ]);
    $currentCMap = $pageResourceInheritanceCurrentBaseCMap([
        '41' => 'Current inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PrivateNestedFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($privateCMap) . " >>\nstream\n{$privateCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentInheritedFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Properties << /Private << /Font << /F1 5 0 R >> /XObject << /CurrentForm 7 0 R >> >> >> /Font << /F1 8 0 R >> /XObject << /CurrentForm 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
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
    'uses top-level inherited resource categories before nested decoy dictionaries' => static function (TestRunner $t) use ($pageResourceNestedCategoryCurrentBasePdf): void {
        $pdf = $pageResourceNestedCategoryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Current inherited font text',
            'Current inherited form text',
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
        $t->same(false, str_contains($plainText, 'Private nested font leak'));
        $t->same(false, str_contains($plainText, 'Private nested XObject leak'));
    },
];
