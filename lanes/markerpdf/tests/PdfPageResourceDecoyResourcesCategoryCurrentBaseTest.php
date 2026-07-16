<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDecoyResourcesCategoryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Valid resource dict font text) Tj ET '
        . 'q /CurrentForm Do Q q /StaleForm Do Q';
    $currentForm = 'BT /F1 12 Tf 12 24 Td (Valid resource dict form text) Tj ET';
    $staleForm = 'BT /F1 12 Tf 12 24 Td (Top-level Resources decoy form leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CurrentForm 6 0 R >> "
        . "/Resources << /Font << /F1 7 0 R >> /XObject << /StaleForm 8 0 R >> >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'ignores decoy Resources keys inside inherited resource dictionaries before page review' => static function (
        TestRunner $t
    ) use ($pageResourceDecoyResourcesCategoryPdf): void {
        $pdf = $pageResourceDecoyResourcesCategoryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Valid resource dict font text',
            'Valid resource dict form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CurrentForm'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Top-level Resources decoy form leak'));
        $t->same(false, str_contains($plainText, 'StaleForm'));
        $t->same(false, in_array('Resources', $resources['categories'] ?? [], true));
    },
];
