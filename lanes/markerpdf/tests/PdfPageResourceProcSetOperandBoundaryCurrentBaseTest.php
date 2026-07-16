<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceProcSetOperandBoundaryPdf = static function (): string {
    $directContent = 'BT /F1 12 Tf 72 720 Td (Direct resource operand boundary text) Tj ET';
    $indirectContent = 'BT /F2 12 Tf 72 720 Td (Indirect resource operand boundary text) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 2 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [4 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($indirectContent) . " >>\nstream\n{$indirectContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /ProcSet [/PDF /Text /ImageB] 99 0 R /Font << /F1 7 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /ProcSet 41 0 R /Font << /F2 7 0 R >> >>\nendobj\n"
        . "41 0 obj\n[/PDF /ImageC /Text] 98 0 R\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed inherited ProcSet operands before page-resource review metadata promotion' => static function (
        TestRunner $t
    ) use ($pageResourceProcSetOperandBoundaryPdf): void {
        $pdf = $pageResourceProcSetOperandBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $firstResources = $boundary[0]['resources'] ?? [];
        $secondResources = $boundary[1]['resources'] ?? [];
        $expectedLines = [
            'Direct resource operand boundary text',
            'Indirect resource operand boundary text',
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
        $t->same(null, $firstResources['procset_names'] ?? null);

        $t->same(true, $secondResources['inherited'] ?? null);
        $t->same(20, $secondResources['resource_owner_object'] ?? null);
        $t->same(40, $secondResources['resource_object'] ?? null);
        $t->same(['ProcSet', 'Font'], $secondResources['categories'] ?? null);
        $t->same(['F2'], $secondResources['font_names'] ?? null);
        $t->same(null, $secondResources['procset_names'] ?? null);

        $t->same(false, str_contains($plainText, 'ProcSet'));
        $t->same(false, str_contains($plainText, 'ImageB'));
        $t->same(false, str_contains($plainText, 'ImageC'));
    },
];
