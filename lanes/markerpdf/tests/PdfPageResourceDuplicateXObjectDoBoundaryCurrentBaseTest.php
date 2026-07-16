<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicateXObjectDoBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate XObject page text) Tj ET '
        . 'q /DupForm Do Q q /ValidForm Do Q';
    $staleForm = 'BT /F1 12 Tf 12 24 Td (Stale duplicate XObject form leak) Tj ET';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Current duplicate XObject form leak) Tj ET';
    $validForm = 'BT /F1 12 Tf 12 24 Td (Valid inherited XObject form text) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($validForm) . " >>\nstream\n{$validForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /DupForm 6 0 R /DupForm 7 0 R /ValidForm 8 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects duplicate inherited XObject Do names before Form expansion leaks stale page text' => static function (
        TestRunner $t
    ) use ($pageResourceDuplicateXObjectDoBoundaryPdf): void {
        $pdf = $pageResourceDuplicateXObjectDoBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Duplicate XObject page text',
            'Valid inherited XObject form text',
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
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['ValidForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale duplicate XObject form leak'));
        $t->same(false, str_contains($plainText, 'Current duplicate XObject form leak'));
        $t->same(false, str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'DupForm'));
    },
];
