<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceKidsTokenBoundaryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode page resource Kids token-boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceKidsTokenBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceKidsNestedReferencePdf = static function () use ($pageResourceKidsTokenBoundaryCMap): string {
    $decoyContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET q /DecoyForm Do Q';
    $currentContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q';
    $decoyForm = 'BT /F1 12 Tf 12 24 Td (Nested Kids decoy form leak) Tj ET';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Top-level Kids inherited form text) Tj ET';
    $cmap = $pageResourceKidsTokenBoundaryCMap([
        '41' => 'Top-level Kids inherited font text',
        '42' => 'Nested Kids decoy font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [<< /Private [3 0 R] >> [99 0 R] 5 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidsTokenInherited /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($decoyForm) . " >>\nstream\n{$decoyForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /CurrentForm 9 0 R /DecoyForm 11 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceParentKidsNestedReferencePdf = static function () use ($pageResourceKidsTokenBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /WrongParentForm Do Q';
    $wrongParentForm = 'BT /F1 12 Tf 12 24 Td (Nested parent Kids form leak) Tj ET';
    $cmap = $pageResourceKidsTokenBoundaryCMap([
        '41' => 'Nested parent Kids font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 9 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NestedParentKidsFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($wrongParentForm) . " >>\nstream\n{$wrongParentForm}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Pages /Kids [<< /Private [3 0 R] >>] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /WrongParentForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'ignores nested page-tree Kids references before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceKidsNestedReferencePdf): void {
        $pdf = $pageResourceKidsNestedReferencePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Top-level Kids inherited font text',
            'Top-level Kids inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CurrentForm', 'DecoyForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Nested Kids decoy font leak'));
        $t->same(false, str_contains($plainText, 'Nested Kids decoy form leak'));
    },
    'fails closed when page Parent lists the child only through nested Kids payload references' => static function (TestRunner $t) use ($pageResourceParentKidsNestedReferencePdf): void {
        $pdf = $pageResourceParentKidsNestedReferencePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
        $t->same(false, str_contains($plainText, 'Nested parent Kids font leak'));
        $t->same(false, str_contains($plainText, 'Nested parent Kids form leak'));
        $t->same(false, str_contains($plainText, 'WrongParentForm'));
    },
];
