<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceParentNullCatalogPathCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode parent-null resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceParentNullCatalogPathCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDirectParentNullCatalogPathPdf = static function () use ($pageResourceParentNullCatalogPathCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /ParentNullForm Do Q q /DetachedForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td <42> Tj ET';
    $detachedForm = 'BT /F1 12 Tf 12 24 Td (Detached null-parent form leak) Tj ET';
    $cMap = $pageResourceParentNullCatalogPathCMap([
        '41' => 'Direct parent null catalog font text',
        '42' => 'Direct parent null catalog form text',
    ]);
    $detachedCMap = $pageResourceParentNullCatalogPathCMap([
        '41' => 'Detached null-parent font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent null /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DirectParentNullCatalogFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DetachedNullParentFont /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($detachedForm) . " >>\nstream\n{$detachedForm}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($detachedCMap) . " >>\nstream\n{$detachedCMap}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ParentNullForm 7 0 R >> >>\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /DetachedForm 9 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceIndirectParentNullCatalogPathPdf = static function () use ($pageResourceParentNullCatalogPathCMap): string {
    $content = 'BT /F2 12 Tf 72 720 Td <43> Tj ET q /WrappedParentNullForm Do Q';
    $form = 'BT /F2 12 Tf 12 24 Td <44> Tj ET';
    $cMap = $pageResourceParentNullCatalogPathCMap([
        '43' => 'Indirect parent null catalog font text',
        '44' => 'Indirect parent null catalog form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 12 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectParentNullCatalogFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "12 0 obj\n13 0 R\nendobj\n"
        . "13 0 obj\nnull\nendobj\n"
        . "20 0 obj\n<< /Font << /F2 5 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Font << /F2 5 0 R >> /XObject << /WrappedParentNullForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses selected catalog Kids path resources when a page Parent is direct null' => static function (
        TestRunner $t
    ) use ($pageResourceDirectParentNullCatalogPathPdf): void {
        $pdf = $pageResourceDirectParentNullCatalogPathPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Direct parent null catalog font text',
            'Direct parent null catalog form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(20, $resources['resource_object'] ?? null);
        $t->same([3, 10], $resources['resource_lookup_objects'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['ParentNullForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Detached null-parent font leak'));
        $t->same(false, str_contains($plainText, 'Detached null-parent form leak'));
    },
    'uses selected catalog Kids path resources when a page Parent resolves to indirect null' => static function (
        TestRunner $t
    ) use ($pageResourceIndirectParentNullCatalogPathPdf): void {
        $pdf = $pageResourceIndirectParentNullCatalogPathPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Indirect parent null catalog font text',
            'Indirect parent null catalog form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(30, $resources['resource_object'] ?? null);
        $t->same([3, 10], $resources['resource_lookup_objects'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F2'], $resources['font_names'] ?? null);
        $t->same(['WrappedParentNullForm'], $resources['xobject_names'] ?? null);
    },
];
