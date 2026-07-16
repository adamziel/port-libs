<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceTreeWrapperCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode page-resource tree-wrapper CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceTreeWrapperCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceTreeWrapperCurrentBasePdf = static function () use ($pageResourceTreeWrapperCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /WrappedForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Wrapped page-tree inherited form text) Tj ET';
    $cmap = $pageResourceTreeWrapperCMap([
        '41' => 'Wrapped page-tree inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 12 0 R >>\nendobj\n"
        . "12 0 obj\n2 0 R\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [13 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "13 0 obj\n3 0 R\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WrappedPageTreeInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /WrappedForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$pageResourceTreeWrapperStaleGenerationPdf = static function () use ($pageResourceTreeWrapperCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cmap = $pageResourceTreeWrapperCMap([
        '41' => 'Stale wrapper generation font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 12 1 R >>\nendobj\n"
        . "12 0 obj\n2 0 R\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [13 1 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "13 0 obj\n3 0 R\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleWrapperGeneration /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'resolves catalog Pages and Kids wrapper references before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceTreeWrapperCurrentBasePdf): void {
        $pdf = $pageResourceTreeWrapperCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Wrapped page-tree inherited font text',
            'Wrapped page-tree inherited form text',
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
        $t->same(['WrappedForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale wrapper generation font leak'));
    },
    'fails closed when page-tree wrapper references use stale generations' => static function (TestRunner $t) use ($pageResourceTreeWrapperStaleGenerationPdf): void {
        $pdf = $pageResourceTreeWrapperStaleGenerationPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $plainText);
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
        $t->same(false, str_contains($plainText, 'Stale wrapper generation font leak'));
    },
];
