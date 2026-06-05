<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceProcSetInheritanceCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode inherited ProcSet CMap fixture text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceProcSetInheritanceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceProcSetInheritancePdf = static function () use ($pageResourceProcSetInheritanceCMap): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $pageTwoContent = 'BT /F2 12 Tf 72 720 Td <42> Tj ET';
    $cMap = $pageResourceProcSetInheritanceCMap([
        '41' => 'Direct inherited font text',
        '42' => 'Indirect inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 2 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [4 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ProcSetInherited /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "30 0 obj\n<< /ProcSet [/PDF /Text /ImageB /Image#43 /Text] /Font << /F1 7 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /ProcSet 41 0 R /Font << /F2 7 0 R >> >>\nendobj\n"
        . "41 0 obj\n42 0 R\nendobj\n"
        . "42 0 obj\n[/PDF /ImageI /Text]\nendobj\n"
        . "%%EOF";
};

return [
    'reports inherited page ProcSet arrays without leaking resource names into WordPress text' => static function (TestRunner $t) use ($pageResourceProcSetInheritancePdf): void {
        $pdf = $pageResourceProcSetInheritancePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $firstResources = $boundary[0]['resources'] ?? [];
        $secondResources = $boundary[1]['resources'] ?? [];
        $expectedLines = [
            'Direct inherited font text',
            'Indirect inherited font text',
        ];

        $t->same($expectedLines, $extractor->extractTextLines($pdf));
        $t->same($expectedLines, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expectedLines), $plainText);
        $t->same(implode("\n", $expectedLines) . "\n", $extractor->naiveGetText($pdf));
        $t->same(2, count($boundary));

        $t->same(true, $firstResources['inherited'] ?? null);
        $t->same(10, $firstResources['resource_owner_object'] ?? null);
        $t->same(30, $firstResources['resource_object'] ?? null);
        $t->same(['ProcSet', 'Font'], $firstResources['categories'] ?? null);
        $t->same(['F1'], $firstResources['font_names'] ?? null);
        $t->same(['PDF', 'Text', 'ImageB', 'ImageC'], $firstResources['procset_names'] ?? null);

        $t->same(true, $secondResources['inherited'] ?? null);
        $t->same(20, $secondResources['resource_owner_object'] ?? null);
        $t->same(40, $secondResources['resource_object'] ?? null);
        $t->same(['ProcSet', 'Font'], $secondResources['categories'] ?? null);
        $t->same(['F2'], $secondResources['font_names'] ?? null);
        $t->same(['PDF', 'ImageI', 'Text'], $secondResources['procset_names'] ?? null);

        $t->same(false, str_contains($plainText, 'ProcSet'));
        $t->same(false, str_contains($plainText, 'ImageB'));
        $t->same(false, str_contains($plainText, 'ImageI'));
    },
];
