<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceCategoryObjectTailCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode category-object-tail CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceCategoryObjectTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceCategoryObjectTailPdf = static function () use ($pageResourceCategoryObjectTailCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /TailActual BDC <42> Tj EMC ET q /TailForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Category object-tail form leak) Tj ET';
    $cmap = $pageResourceCategoryObjectTailCMap([
        '41' => 'Valid category object-tail base text',
        '42' => 'Physical category object-tail property glyph',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CategoryObjectTailFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "8 0 obj\n<< /ActualText (Category object-tail ActualText leak) >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject 30 0 R /Properties 31 0 R >>\nendobj\n"
        . "30 0 obj\n<< /TailForm 7 0 R >> 99 0 R\nendobj\n"
        . "31 0 obj\n<< /TailActual 8 0 R >> 98 0 R\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed indirect resource category dictionary objects before page-boundary review promotion' => static function (
        TestRunner $t
    ) use ($pageResourceCategoryObjectTailPdf): void {
        $pdf = $pageResourceCategoryObjectTailPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Valid category object-tail base text',
            'Physical category object-tail property glyph',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same('resolved', $resources['status'] ?? null);
        $t->same(true, $resources['resolved'] ?? null);
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(null, $resources['xobject_names'] ?? null);
        $t->same(null, $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Category object-tail form leak'));
        $t->same(false, str_contains($plainText, 'Category object-tail ActualText leak'));
        $t->same(false, str_contains($plainText, 'TailForm'));
        $t->same(false, str_contains($plainText, 'TailActual'));
    },
];
