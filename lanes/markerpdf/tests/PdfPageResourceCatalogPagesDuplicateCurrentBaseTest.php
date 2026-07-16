<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceCatalogPagesDuplicateCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode duplicate catalog Pages CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceCatalogPagesDuplicateCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceCatalogPagesDuplicatePdf = static function () use ($pageResourceCatalogPagesDuplicateCMap): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET q /StaleForm Do Q';
    $currentContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q';
    $staleForm = 'BT /F1 12 Tf 12 24 Td (Stale duplicate catalog Pages form leak) Tj ET';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Current duplicate catalog Pages form text) Tj ET';
    $staleCMap = $pageResourceCatalogPagesDuplicateCMap([
        '42' => 'Stale duplicate catalog Pages font leak',
    ]);
    $currentCMap = $pageResourceCatalogPagesDuplicateCMap([
        '41' => 'Current duplicate catalog Pages font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Pages 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentCatalogPagesFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleCatalogPagesFont /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Font << /F1 11 0 R >> /XObject << /StaleForm 13 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /CurrentForm 9 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last duplicate catalog Pages key before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceCatalogPagesDuplicatePdf): void {
        $pdf = $pageResourceCatalogPagesDuplicatePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Current duplicate catalog Pages font text',
            'Current duplicate catalog Pages form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(20, $resources['resource_owner_object'] ?? null);
        $t->same(40, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CurrentForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale duplicate catalog Pages font leak'));
        $t->same(false, str_contains($plainText, 'Stale duplicate catalog Pages form leak'));
        $t->same(false, str_contains($plainText, 'StaleForm'));
    },
];
