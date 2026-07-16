<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceCatalogParentBoundaryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode catalog parent boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CatalogParentBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDetachedParentCurrentBasePdf = static function () use ($pageResourceCatalogParentBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /DetachedForm Do Q';
    $detachedForm = 'BT /F1 12 Tf 12 24 Td (Detached parent form leak) Tj ET';
    $detachedCMap = $pageResourceCatalogParentBoundaryCMap([
        '41' => 'Detached parent font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 99 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DetachedParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($detachedCMap) . " >>\nstream\n{$detachedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($detachedForm) . " >>\nstream\n{$detachedForm}\nendstream\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /DetachedForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceCatalogParentValidCurrentBasePdf = static function () use ($pageResourceCatalogParentBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CatalogForm Do Q q /DetachedForm Do Q';
    $catalogForm = 'BT /F1 12 Tf 12 24 Td (Catalog parent form text) Tj ET';
    $detachedForm = 'BT /F1 12 Tf 12 24 Td (Detached sibling form leak) Tj ET';
    $catalogCMap = $pageResourceCatalogParentBoundaryCMap([
        '41' => 'Catalog parent font text',
    ]);
    $detachedCMap = $pageResourceCatalogParentBoundaryCMap([
        '41' => 'Detached sibling font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CatalogParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($catalogCMap) . " >>\nstream\n{$catalogCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($catalogForm) . " >>\nstream\n{$catalogForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DetachedSiblingFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($detachedCMap) . " >>\nstream\n{$detachedCMap}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($detachedForm) . " >>\nstream\n{$detachedForm}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CatalogForm 7 0 R >> >>\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /DetachedForm 11 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'excludes detached page Parent resources that are not on the catalog Kids path' => static function (TestRunner $t) use ($pageResourceDetachedParentCurrentBasePdf): void {
        $pdf = $pageResourceDetachedParentCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
        $t->same(false, str_contains($plainText, 'Detached parent font leak'));
        $t->same(false, str_contains($plainText, 'Detached parent form leak'));
        $t->same(false, str_contains($plainText, 'DetachedForm'));
    },
    'keeps inherited resources when the page Parent matches the selected catalog path' => static function (TestRunner $t) use ($pageResourceCatalogParentValidCurrentBasePdf): void {
        $pdf = $pageResourceCatalogParentValidCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Catalog parent font text',
            'Catalog parent form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(20, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CatalogForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Detached sibling font leak'));
        $t->same(false, str_contains($plainText, 'Detached sibling form leak'));
    },
];
