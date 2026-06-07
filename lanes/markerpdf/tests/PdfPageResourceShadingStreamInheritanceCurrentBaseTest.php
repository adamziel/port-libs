<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceShadingStreamInheritanceCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode focused page Shading resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceShadingStreamInheritanceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceShadingStreamInheritancePdf = static function () use ($pageResourceShadingStreamInheritanceCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /Mesh#20Shade sh Q';
    $shadingPayload = 'BT /F1 12 Tf 1 1 Td (shading stream payload leak) Tj ET';
    $cMap = $pageResourceShadingStreamInheritanceCMap([
        '41' => 'Inherited shading stream text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedShadingFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /Shading << /Mesh#20Shade 12 0 R >> >>\nendobj\n"
        . "12 0 obj\n<< /ShadingType 4 /ColorSpace /DeviceRGB /BitsPerCoordinate 8 /BitsPerComponent 8 /BitsPerFlag 2 /Decode [0 1 0 1 0 1 0 1] /Length " . strlen($shadingPayload) . " >>\nstream\n{$shadingPayload}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'reports inherited Shading stream resource names without extracting stream payload text' => static function (TestRunner $t) use ($pageResourceShadingStreamInheritancePdf): void {
        $pdf = $pageResourceShadingStreamInheritancePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expectedLines = ['Inherited shading stream text'];

        $t->same($expectedLines, $extractor->extractTextLines($pdf));
        $t->same($expectedLines, $extractor->extractTextRuns($pdf));
        $t->same('Inherited shading stream text', $plainText);
        $t->same("Inherited shading stream text\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'Shading'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['Mesh Shade'], $resources['shading_names'] ?? null);
        $t->same(false, isset($resources['xobject_names']));
        $t->same(false, str_contains($plainText, 'Mesh Shade'));
        $t->same(false, str_contains($plainText, 'Shading'));
        $t->same(false, str_contains($plainText, 'shading stream payload leak'));
    },
];
