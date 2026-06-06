<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsReversedKidLimitsPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Reversed limits cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Reversed limits body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Reversed limits appendix imported) Tj ET',
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
        . "20 0 obj\n<< /Limits [0 2] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [2 1] /Nums [0 << /P (stale-reversed-) /S /D /St 99 >> 1 << /P (stale-reversed-body-) /S /D /St 100 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [0 2] /Nums [0 << /P (Front ) /S /r /St 4 >> 1 << /P (Body ) /S /D /St 8 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects reversed PageLabels kid Limits before stale child labels' => static function (
        TestRunner $t
    ) use ($pageLabelsReversedKidLimitsPdf): void {
        $pdf = $pageLabelsReversedKidLimitsPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Front iv', 'Body 8', 'App-Z'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Reversed limits cover imported',
            'Reversed limits body imported',
            'Reversed limits appendix imported',
        ], array_column($entries, 'text'));
        foreach (['stale-reversed-99', 'stale-reversed-body-100', '1', '2'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Body 8', $summary['pages'][1]['page_label'] ?? null);
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
