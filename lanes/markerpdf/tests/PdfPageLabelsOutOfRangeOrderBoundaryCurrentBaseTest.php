<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsOutOfRangeOrderPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Out of range front imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Out of range body imported) Tj ET',
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
        . "20 0 obj\n<< /Nums [0 << /P (Front ) /S /r /St 4 >> 2 << /P (stale-out-of-range-) /S /D /St 99 >> 1 << /P (stale-late-) /S /D /St 77 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects lower PageLabels Nums keys after out-of-range ordering boundaries' => static function (
        TestRunner $t
    ) use ($pageLabelsOutOfRangeOrderPdf): void {
        $pdf = $pageLabelsOutOfRangeOrderPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Front iv', 'Front v'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Out of range front imported', 'Out of range body imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-out-of-range-99', $labels, true));
        $t->true(!in_array('stale-out-of-range-99', $previewLabels, true));
        $t->true(!in_array('stale-late-77', $labels, true));
        $t->true(!in_array('stale-late-77', $previewLabels, true));
        $t->same('Front v', $summary['pages'][1]['page_label'] ?? null);
        $t->same('Front v', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
];
