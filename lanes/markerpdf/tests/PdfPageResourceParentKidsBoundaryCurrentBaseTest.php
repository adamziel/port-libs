<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceParentKidsBoundaryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode page resource parent Kids boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceParentKidsBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceParentKidsBoundaryPdf = static function () use ($pageResourceParentKidsBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /SiblingForm Do Q';
    $siblingForm = 'BT /F1 12 Tf 12 24 Td (Sibling parent form leak) Tj ET';
    $siblingCMap = $pageResourceParentKidsBoundaryCMap([
        '41' => 'Sibling parent font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [] /Count 0 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SiblingParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($siblingCMap) . " >>\nstream\n{$siblingCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($siblingForm) . " >>\nstream\n{$siblingForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /Font << /F1 8 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /SiblingForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceParentKidsValidPdf = static function () use ($pageResourceParentKidsBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /ListedForm Do Q';
    $listedForm = 'BT /F1 12 Tf 12 24 Td (Listed parent form text) Tj ET';
    $listedCMap = $pageResourceParentKidsBoundaryCMap([
        '41' => 'Listed parent font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ListedParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($listedCMap) . " >>\nstream\n{$listedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($listedForm) . " >>\nstream\n{$listedForm}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ListedForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed when page Parent does not list the page in Kids before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceParentKidsBoundaryPdf): void {
        $pdf = $pageResourceParentKidsBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
        $t->same(false, str_contains($plainText, 'Sibling parent font leak'));
        $t->same(false, str_contains($plainText, 'Sibling parent form leak'));
    },
    'keeps inherited resources when page Parent lists the exact child reference' => static function (TestRunner $t) use ($pageResourceParentKidsValidPdf): void {
        $pdf = $pageResourceParentKidsValidPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Listed parent font text',
            'Listed parent form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(10, $resources['resource_owner_object'] ?? null);
        $t->same(30, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['ListedForm'], $resources['xobject_names'] ?? null);
    },
];
