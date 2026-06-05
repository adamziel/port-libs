<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceEntryGenerationBoundaryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode focused resource-entry CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /ResourceEntryGenerationBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceEntryGenerationBoundaryPdf = static function () use ($pageResourceEntryGenerationBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* '
        . '/P /Actual BDC <42> Tj EMC ET q /StaleForm Do Q q /ValidForm Do Q';
    $staleForm = 'BT /F1 12 Tf 12 24 Td (Stale generation form leak) Tj ET';
    $validForm = 'BT /F1 12 Tf 12 24 Td (Valid generation form text) Tj ET';
    $staleCMap = $pageResourceEntryGenerationBoundaryCMap([
        '41' => 'Stale generation font leak',
        '42' => 'Stale generation property glyph leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleGenerationFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "7 0 obj\n<< /ActualText (Stale generation ActualText leak) >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($validForm) . " >>\nstream\n{$validForm}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 1 R >> /XObject << /StaleForm 6 1 R /ValidForm 8 0 R >> /Properties << /Actual 7 1 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'filters generation-mismatched inherited resource entries before stale font form or ActualText reuse' => static function (TestRunner $t) use ($pageResourceEntryGenerationBoundaryPdf): void {
        $pdf = $pageResourceEntryGenerationBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'A',
            'B',
            'Valid generation form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resourceMetadata['categories'] ?? null);
        $t->same(null, $resourceMetadata['font_names'] ?? null);
        $t->same(['ValidForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(null, $resourceMetadata['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale generation font leak'));
        $t->same(false, str_contains($plainText, 'Stale generation property glyph leak'));
        $t->same(false, str_contains($plainText, 'Stale generation ActualText leak'));
        $t->same(false, str_contains($plainText, 'Stale generation form leak'));
    },
];
