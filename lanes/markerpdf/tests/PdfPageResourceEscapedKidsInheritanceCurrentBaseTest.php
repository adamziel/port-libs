<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceEscapedKidsCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode focused escaped-Kids CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceEscapedKidsCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceEscapedKidsNestedDecoyPdf = static function () use ($pageResourceEscapedKidsCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedKidsForm Do Q';
    $formContent = 'BT /F1 12 Tf 12 24 Td (Escaped Kids inherited form text) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET';
    $cmap = $pageResourceEscapedKidsCMap([
        '41' => 'Escaped Kids inherited font text',
        '42' => 'Nested decoy kid resource leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /PieceInfo << /WPReview << /Private << /Kids [99 0 R] /ReviewOnly true >> >> >> /Type /Pages /Ki#64s [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EscapedKidsInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /InheritedKidsForm 7 0 R >> >>\nendobj\n"
        . "99 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 100 0 R >>\nendobj\n"
        . "100 0 obj\n<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageResourceEscapedKidsIndirectArrayPdf = static function () use ($pageResourceEscapedKidsCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cmap = $pageResourceEscapedKidsCMap([
        '41' => 'Escaped indirect Kids inherited text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Ki#64s 20 0 R /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EscapedIndirectKidsInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> >>\nendobj\n"
        . "20 0 obj\n[3 0 R]\nendobj\n"
        . "%%EOF";
};

return [
    'uses escaped top-level Kids instead of nested decoy Kids before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceEscapedKidsNestedDecoyPdf): void {
        $pdf = $pageResourceEscapedKidsNestedDecoyPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];
        $expected = [
            'Escaped Kids inherited font text',
            'Escaped Kids inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
        $t->same(['InheritedKidsForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Nested decoy kid resource leak'));
    },
    'resolves escaped indirect Kids arrays before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceEscapedKidsIndirectArrayPdf): void {
        $pdf = $pageResourceEscapedKidsIndirectArrayPdf();
        $extractor = new PdfTextExtractor();
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same(['Escaped indirect Kids inherited text'], $extractor->extractTextLines($pdf));
        $t->same(['Escaped indirect Kids inherited text'], $extractor->extractTextRuns($pdf));
        $t->same('Escaped indirect Kids inherited text', $extractor->extractPlainText($pdf));
        $t->same("Escaped indirect Kids inherited text\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
    },
];
