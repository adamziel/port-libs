<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsInsideKidLimitsTailPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Inside limits front imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Inside limits body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Inside limits continuation imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Inside limits tail imported) Tj ET',
        14 => 'BT /F1 12 Tf 72 720 Td (Inside limits end imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R] /Count 5 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 14 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Limits [0 4] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [0 2] /Nums [0 << /P (Front ) /S /r /St 4 >> 1 << /P (Body ) /S /D /St 8 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [1 4] /Nums [1 << /P (stale-inside-) /S /D /St 70 >> 2 << /P (stale-inside-) /S /D /St 80 >> 3 << /P (Tail ) /S /D /St 4 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'preserves PageLabels kid tail after suppressing stale inside overlapping child ranges' => static function (
        TestRunner $t
    ) use ($pageLabelsInsideKidLimitsTailPdf): void {
        $pdf = $pageLabelsInsideKidLimitsTailPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Front iv', 'Body 8', 'Body 9', 'Tail 4', 'Tail 5'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $preview->pageLabels($pdf));
        $t->same($labels, $previewLabels);
        $t->same([
            'Inside limits front imported',
            'Inside limits body imported',
            'Inside limits continuation imported',
            'Inside limits tail imported',
            'Inside limits end imported',
        ], array_column($entries, 'text'));
        foreach (['stale-inside-70', 'stale-inside-80', 'Tail 3'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Body 9', $summary['pages'][2]['page_label'] ?? null);
        $t->same('Tail 4', $summary['pages'][3]['page_label'] ?? null);
        $t->same('Tail 5', $preview->getPageImagePlan($pdf, 5)['page_label']);
    },
];
