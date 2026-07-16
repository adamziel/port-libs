<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceCategoryStreamBoundaryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode focused resource-category CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceCategoryStreamBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourcePropertiesCategoryStreamPdf = static function () use ($pageResourceCategoryStreamBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /BadActual BDC <42> Tj EMC ET q /ValidForm Do Q';
    $formContent = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
    $propertyPayload = 'BT /F1 12 Tf 1 1 Td (stream property payload leak) Tj ET';
    $cmap = $pageResourceCategoryStreamBoundaryCMap([
        '41' => 'Inherited category font text',
        '42' => 'Visible glyph after stream property',
        '43' => 'Valid inherited category form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedCategoryFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ValidForm 6 0 R >> /Properties 30 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($propertyPayload) . " /BadActual << /ActualText (Stream property actual leak) >> >>\nstream\n{$propertyPayload}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageResourceXObjectCategoryStreamPdf = static function () use ($pageResourceCategoryStreamBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /GoodActual BDC <42> Tj EMC ET q /StreamForm Do Q';
    $streamForm = 'BT /F1 12 Tf 12 24 Td (Stream XObject category form leak) Tj ET';
    $xobjectPayload = 'BT /F1 12 Tf 1 1 Td (stream xobject category payload leak) Tj ET';
    $cmap = $pageResourceCategoryStreamBoundaryCMap([
        '41' => 'XObject category base text',
        '42' => 'Direct property category text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedXObjectCategoryFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($streamForm) . " >>\nstream\n{$streamForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject 30 0 R /Properties << /GoodActual << /ActualText (Direct property category text) >> >> >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($xobjectPayload) . " /StreamForm 6 0 R >>\nstream\n{$xobjectPayload}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageResourceFontCategoryStreamPdf = static function () use ($pageResourceCategoryStreamBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /DirectForm Do Q';
    $formContent = 'BT /Fplain 12 Tf 12 24 Td (Valid direct XObject text) Tj ET';
    $fontPayload = 'BT /F1 12 Tf 1 1 Td (stream font category payload leak) Tj ET';
    $cmap = $pageResourceCategoryStreamBoundaryCMap([
        '41' => 'Stream category font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StreamCategoryFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /Fplain 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font 30 0 R /XObject << /DirectForm 6 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($fontPayload) . " /F1 5 0 R >>\nstream\n{$fontPayload}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'ignores inherited Properties category stream objects without losing valid font or form resources' => static function (TestRunner $t) use ($pageResourcePropertiesCategoryStreamPdf): void {
        $pdf = $pageResourcePropertiesCategoryStreamPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'Inherited category font text',
            'Visible glyph after stream property',
            'Valid inherited category form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
        $t->same(['ValidForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(null, $resourceMetadata['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stream property actual leak'));
        $t->same(false, str_contains($plainText, 'stream property payload leak'));
    },
    'ignores inherited XObject category stream objects without losing valid font or property resources' => static function (TestRunner $t) use ($pageResourceXObjectCategoryStreamPdf): void {
        $pdf = $pageResourceXObjectCategoryStreamPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'XObject category base text',
            'Direct property category text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['Font', 'XObject', 'Properties'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
        $t->same(null, $resourceMetadata['xobject_names'] ?? null);
        $t->same(['GoodActual'], $resourceMetadata['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stream XObject category form leak'));
        $t->same(false, str_contains($plainText, 'stream xobject category payload leak'));
    },
    'ignores inherited Font category stream objects without losing valid XObject resources' => static function (TestRunner $t) use ($pageResourceFontCategoryStreamPdf): void {
        $pdf = $pageResourceFontCategoryStreamPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'A',
            'Valid direct XObject text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['Font', 'XObject'], $resourceMetadata['categories'] ?? null);
        $t->same(null, $resourceMetadata['font_names'] ?? null);
        $t->same(['DirectForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stream category font leak'));
        $t->same(false, str_contains($plainText, 'stream font category payload leak'));
    },
];
