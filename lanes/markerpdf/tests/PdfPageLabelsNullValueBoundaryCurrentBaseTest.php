<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsNullValuePdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Null reset front imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Null reset body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Null reset continuation imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Null reset appendix imported) Tj ET',
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
        . "20 0 obj\n<< /Nums [0 << /P (Front ) /S /r /St 4 >> 1 null 2 << /P (Body ) /S /D /St 8 >> 3 30 0 R] >>\nendobj\n"
        . "30 0 obj\nnull\nendobj\n"
        . "%%EOF\n";
};

return [
    'resets PageLabels ranges on direct and indirect null values before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsNullValuePdf): void {
        $pdf = $pageLabelsNullValuePdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Front iv', '2', 'Body 8', '4'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Null reset front imported',
            'Null reset body imported',
            'Null reset continuation imported',
            'Null reset appendix imported',
        ], array_column($entries, 'text'));
        foreach (['Front v', 'Body 9'] as $leakedLabel) {
            $t->true(!in_array($leakedLabel, $labels, true));
            $t->true(!in_array($leakedLabel, $previewLabels, true));
        }
        $t->same('2', $summary['pages'][1]['page_label'] ?? null);
        $t->same('4', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
];
