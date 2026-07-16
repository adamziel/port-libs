<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicateKidsParentCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode duplicate Kids parent-chain resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /DuplicateKidsParentResourceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDuplicateKidsParentPdf = static function () use ($pageResourceDuplicateKidsParentCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q q /DecoyForm Do Q';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Current duplicate parent form text) Tj ET';
    $decoyForm = 'BT /F1 12 Tf 12 24 Td (First duplicate parent form leak) Tj ET';
    $currentCMap = $pageResourceDuplicateKidsParentCMap([
        '41' => 'Current duplicate parent font text',
    ]);
    $decoyCMap = $pageResourceDuplicateKidsParentCMap([
        '41' => 'First duplicate parent font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FirstDuplicateParentFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($decoyCMap) . " >>\nstream\n{$decoyCMap}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($decoyForm) . " >>\nstream\n{$decoyForm}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /DecoyForm 11 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CurrentForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'prefers explicit page Parent lineage when duplicate catalog Kids reach the same page object' => static function (TestRunner $t) use ($pageResourceDuplicateKidsParentPdf): void {
        $pdf = $pageResourceDuplicateKidsParentPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Current duplicate parent font text',
            'Current duplicate parent form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(20, $resources['resource_owner_object'] ?? null);
        $t->same(40, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CurrentForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'First duplicate parent font leak'));
        $t->same(false, str_contains($plainText, 'First duplicate parent form leak'));
        $t->same(false, str_contains($plainText, 'DecoyForm'));
    },
];
