<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceParentCycleCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode parent-cycle resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceParentCycleCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceParentCycleBlockedPdf = static function () use ($pageResourceParentCycleCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CycleForm Do Q q /RootForm Do Q';
    $cycleForm = 'BT /F1 12 Tf 12 24 Td (Parent cycle form leak) Tj ET';
    $rootForm = 'BT /F1 12 Tf 12 24 Td (Root fallback form leak) Tj ET';
    $cycleCMap = $pageResourceParentCycleCMap([
        '41' => 'Parent cycle font leak',
    ]);
    $rootCMap = $pageResourceParentCycleCMap([
        '41' => 'Root fallback font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 /Resources 50 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 11 0 R /Kids [3 0 R 11 0 R] /Count 1 >>\nendobj\n"
        . "11 0 obj\n<< /Type /Pages /Parent 10 0 R /Kids [10 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentCycleFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cycleCMap) . " >>\nstream\n{$cycleCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($cycleForm) . " >>\nstream\n{$cycleForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /RootFallbackFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($rootCMap) . " >>\nstream\n{$rootCMap}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($rootForm) . " >>\nstream\n{$rootForm}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CycleForm 7 0 R >> >>\nendobj\n"
        . "50 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /RootForm 12 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceParentCyclePrefixPdf = static function () use ($pageResourceParentCycleCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /PrefixForm Do Q q /CycleForm Do Q';
    $prefixForm = 'BT /F1 12 Tf 12 24 Td (Parent cycle prefix form text) Tj ET';
    $cycleForm = 'BT /F1 12 Tf 12 24 Td (Parent cycle decoy form leak) Tj ET';
    $prefixCMap = $pageResourceParentCycleCMap([
        '41' => 'Parent cycle prefix font text',
    ]);
    $cycleCMap = $pageResourceParentCycleCMap([
        '41' => 'Parent cycle decoy font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 11 0 R /Kids [3 0 R 11 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Type /Pages /Parent 10 0 R /Kids [10 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentCyclePrefixFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($prefixCMap) . " >>\nstream\n{$prefixCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($prefixForm) . " >>\nstream\n{$prefixForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentCycleDecoyFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($cycleCMap) . " >>\nstream\n{$cycleCMap}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($cycleForm) . " >>\nstream\n{$cycleForm}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /PrefixForm 7 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /CycleForm 12 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'blocks cyclic page Parent resources outside the selected catalog Kids prefix' => static function (
        TestRunner $t
    ) use ($pageResourceParentCycleBlockedPdf): void {
        $pdf = $pageResourceParentCycleBlockedPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
        $t->same(false, str_contains($plainText, 'Parent cycle font leak'));
        $t->same(false, str_contains($plainText, 'Parent cycle form leak'));
        $t->same(false, str_contains($plainText, 'Root fallback font leak'));
        $t->same(false, str_contains($plainText, 'Root fallback form leak'));
        $t->same(false, str_contains($plainText, 'CycleForm'));
        $t->same(false, str_contains($plainText, 'RootForm'));
    },
    'keeps resources on the trusted catalog prefix while excluding cyclic Parent ancestors' => static function (
        TestRunner $t
    ) use ($pageResourceParentCyclePrefixPdf): void {
        $pdf = $pageResourceParentCyclePrefixPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Parent cycle prefix font text',
            'Parent cycle prefix form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same('resolved', $resources['status'] ?? null);
        $t->same(true, $resources['resolved'] ?? null);
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(20, $resources['resource_object'] ?? null);
        $t->same([3, 10], $resources['resource_lookup_objects'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['PrefixForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Parent cycle decoy font leak'));
        $t->same(false, str_contains($plainText, 'Parent cycle decoy form leak'));
        $t->same(false, str_contains($plainText, 'CycleForm'));
    },
];
