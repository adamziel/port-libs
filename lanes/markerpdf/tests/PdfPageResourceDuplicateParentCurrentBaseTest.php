<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicateParentCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode duplicate Parent resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDuplicateParentCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDuplicateParentPdf = static function () use ($pageResourceDuplicateParentCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q q /DetachedForm Do Q';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Duplicate Parent current form text) Tj ET';
    $detachedForm = 'BT /F1 12 Tf 12 24 Td (Duplicate Parent detached form leak) Tj ET';
    $currentCMap = $pageResourceDuplicateParentCMap([
        '41' => 'Duplicate Parent current font text',
    ]);
    $detachedCMap = $pageResourceDuplicateParentCMap([
        '41' => 'Duplicate Parent detached font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 99 0 R /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DuplicateParentCurrentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DuplicateParentDetachedFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($detachedCMap) . " >>\nstream\n{$detachedCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CurrentForm 7 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($detachedForm) . " >>\nstream\n{$detachedForm}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /DetachedForm 11 0 R >> >>\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last duplicate page Parent key before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceDuplicateParentPdf): void {
        $pdf = $pageResourceDuplicateParentPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Duplicate Parent current font text',
            'Duplicate Parent current form text',
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
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CurrentForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Duplicate Parent detached font leak'));
        $t->same(false, str_contains($plainText, 'Duplicate Parent detached form leak'));
        $t->same(false, str_contains($plainText, 'DetachedForm'));
        $t->same(false, str_contains($plainText, 'PageResourceDuplicateParentCurrentBaseCMap'));
    },
];
