<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceEntryStreamBoundaryCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode focused resource-entry stream CMap text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceEntryStreamBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceEntryStreamBoundaryPdf = static function () use ($pageResourceEntryStreamBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /P1 BDC (Property glyph text) Tj EMC ET q /ValidForm Do Q';
    $formContent = 'BT /Fplain 12 Tf 12 24 Td (Valid stream-boundary form text) Tj ET';
    $fontPayload = 'BT /F1 12 Tf 1 1 Td (stream font payload leak) Tj ET';
    $propertyPayload = 'BT /F1 12 Tf 1 1 Td (stream property payload leak) Tj ET';
    $cmap = $pageResourceEntryStreamBoundaryCMap('Stream font entry leak');

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StreamFontEntry /Encoding /Identity-H /ToUnicode 6 0 R /Length " . strlen($fontPayload) . " >>\nstream\n{$fontPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /ActualText (Stream property ActualText leak) /Length " . strlen($propertyPayload) . " >>\nstream\n{$propertyPayload}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /Fplain 9 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /Properties << /P1 7 0 R >> /XObject << /ValidForm 8 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects inherited Font and Properties resource entries that resolve to streams while preserving valid XObject streams' => static function (TestRunner $t) use ($pageResourceEntryStreamBoundaryPdf): void {
        $pdf = $pageResourceEntryStreamBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'A',
            'Property glyph text',
            'Valid stream-boundary form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font', 'Properties', 'XObject'], $resourceMetadata['categories'] ?? null);
        $t->same(null, $resourceMetadata['font_names'] ?? null);
        $t->same(null, $resourceMetadata['properties_names'] ?? null);
        $t->same(['ValidForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stream font entry leak'));
        $t->same(false, str_contains($plainText, 'stream font payload leak'));
        $t->same(false, str_contains($plainText, 'Stream property ActualText leak'));
        $t->same(false, str_contains($plainText, 'stream property payload leak'));
    },
];
