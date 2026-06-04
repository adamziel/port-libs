<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceTopLevelBoundaryCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode focused CMap text.');
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
        . "CMapName currentdict /TopLevelResourceBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceTopLevelBoundaryPdf = static function () use ($pageResourceTopLevelBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET '
        . 'q /CurrentForm Do Q '
        . '/P /ParentActual BDC BT /F1 12 Tf 72 680 Td (Parent actual glyph noise) Tj ET EMC';
    $parentForm = 'BT /F1 12 Tf 12 24 Td (Parent inherited form text) Tj ET';
    $privateForm = 'BT /F1 12 Tf 12 24 Td (Private PieceInfo form leak) Tj ET';
    $parentCMap = $pageResourceTopLevelBoundaryCMap('Parent inherited resources text');
    $privateCMap = $pageResourceTopLevelBoundaryCMap('Private PieceInfo resource leak');

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R "
        . "/PieceInfo << /WPReview << /Private << /Resources << /Font << /F1 8 0 R >> /XObject << /CurrentForm 12 0 R >> /Properties << /ParentActual << /ActualText (Private PieceInfo actual leak) >> >> >> /ReviewOnly true >> >> >> "
        . "/Resources null /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentInherited /Encoding /Identity-H /ToUnicode 13 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PrivatePieceInfo /Encoding /Identity-H /ToUnicode 14 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /CurrentForm 11 0 R >> /Properties << /ParentActual << /ActualText (Parent actual resource text) >> >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
        . "14 0 obj\n<< /Length " . strlen($privateCMap) . " >>\nstream\n{$privateCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'inherits parent page resources when page top-level Resources is null despite nested private Resources' => static function (TestRunner $t) use ($pageResourceTopLevelBoundaryPdf): void {
        $pdf = $pageResourceTopLevelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Parent inherited resources text',
            'Parent inherited form text',
            'Parent actual resource text',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resourceMetadata['inherited'] ?? null);
        $t->same(2, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(10, $resourceMetadata['resource_object'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resourceMetadata['categories'] ?? null);
        $t->same(['F1'], $resourceMetadata['font_names'] ?? null);
        $t->same(['CurrentForm'], $resourceMetadata['xobject_names'] ?? null);
        $t->same(['ParentActual'], $resourceMetadata['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Private PieceInfo'));
        $t->same(false, str_contains($plainText, 'Parent actual glyph noise'));
    },
];
