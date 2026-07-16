<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsGeneratedSuffixBoundPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Huge roman label imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Huge alphabetic label imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Huge decimal label imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /P (Roman-) /S /R /St 5000000 >> 1 << /P (Alpha-) /S /A /St 120000 >> 2 << /P (Decimal-) /S /D /St 5000000 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'bounds generated PageLabels roman and alphabetic suffixes before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsGeneratedSuffixBoundPdf): void {
        $pdf = $pageLabelsGeneratedSuffixBoundPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $expected = ['Roman-5000000', 'Alpha-120000', 'Decimal-5000000'];
        $t->same($expected, $labels);
        $t->same($expected, array_column($entries, 'page_label'));
        $t->same($expected, $previewLabels);
        $t->same([
            'Huge roman label imported',
            'Huge alphabetic label imported',
            'Huge decimal label imported',
        ], array_column($entries, 'text'));
        $t->true(max(array_map('strlen', $labels)) < 32, 'Generated PageLabels should stay bounded.');
        $t->true(!str_contains($labels[0], str_repeat('M', 64)), 'Roman fallback should not expand thousands of M glyphs.');
        $t->same('Roman-5000000', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Alpha-120000', $preview->getPageImagePlan($pdf, 2)['page_label']);
        $t->same('Decimal-5000000', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
