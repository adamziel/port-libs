<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsDuplicateScalarOperandPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Duplicate scalar cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Duplicate scalar body imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /P 30 0 R /P (Real-) /S 31 0 R /S /D /St 32 0 R /St 4 >> 1 << /P (Body ) /S /D /St 8 >>] >>\nendobj\n"
        . "30 0 obj\n(Bad-) /Private\nendobj\n"
        . "31 0 obj\n/A /Private\nendobj\n"
        . "32 0 obj\n99 /Private\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps first usable duplicate PageLabels scalar operands before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsDuplicateScalarOperandPdf): void {
        $pdf = $pageLabelsDuplicateScalarOperandPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Real-4', 'Body 8'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Duplicate scalar cover imported',
            'Duplicate scalar body imported',
        ], array_column($entries, 'text'));
        foreach (['1', 'Bad-Z', 'Real-', 'Body 1'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Real-4', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Body 8', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
];
