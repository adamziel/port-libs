<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsInheritedTouchingKidLimitsPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Inherited touching cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Inherited touching body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Inherited touching appendix imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Inherited touching back imported) Tj ET',
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
        . "20 0 obj\n<< /Limits [2 3] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [0 2] /Nums [0 << /P (stale-front-) /S /D /St 90 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [2 3] /Nums [2 << /P (stale-touch-) /S /D /St 70 >> 3 << /P (Back-) /S /D /St 7 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'preserves non-overlapping PageLabels child entries after inherited touching limit clipping' => static function (
        TestRunner $t
    ) use ($pageLabelsInheritedTouchingKidLimitsPdf): void {
        $pdf = $pageLabelsInheritedTouchingKidLimitsPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['1', '2', 'App-Z', 'Back-7'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Inherited touching cover imported',
            'Inherited touching body imported',
            'Inherited touching appendix imported',
            'Inherited touching back imported',
        ], array_column($entries, 'text'));
        foreach (['stale-front-90', 'stale-touch-70', 'App-AA'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('App-Z', $summary['pages'][2]['page_label'] ?? null);
        $t->same('Back-7', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
];
