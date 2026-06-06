<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsNegativeKidLimitsPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Negative limits front imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Negative limits body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Negative limits appendix imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Negative limits end imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R] /Count 4 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Limits [0 3] /Kids [21 0 R 22 0 R 23 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [-1 2] /Nums [0 << /P (stale-underflow-) /S /D /St 77 >> 2 << /P (stale-app-) /S /A /St 26 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [0 2] /Nums [0 << /P (Front ) /S /r /St 4 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
        . "23 0 obj\n<< /Limits [3 3] /Nums [3 << /P (End-) >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects negative PageLabels kid Limits before stale range claims' => static function (
        TestRunner $t
    ) use ($pageLabelsNegativeKidLimitsPdf): void {
        $pdf = $pageLabelsNegativeKidLimitsPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Front iv', 'Front v', 'App-Z', 'End-'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Negative limits front imported',
            'Negative limits body imported',
            'Negative limits appendix imported',
            'Negative limits end imported',
        ], array_column($entries, 'text'));
        foreach (['stale-underflow-77', 'stale-underflow-78', 'stale-app-Z', '2'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Front v', $summary['pages'][1]['page_label'] ?? null);
        $t->same('End-', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
];
