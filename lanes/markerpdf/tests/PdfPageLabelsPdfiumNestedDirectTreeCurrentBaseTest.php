<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfiumNestedDirectPageLabelPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Nested label cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Nested label second imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Nested label appendix imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Nested label repeated imported) Tj ET',
        14 => 'BT /F1 12 Tf 72 720 Td (Nested label roman tail imported) Tj ET',
        15 => 'BT /F1 12 Tf 72 720 Td (Nested label lowercase tail imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R 8 0 R] /Count 6 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 13 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 14 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 15 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Kids ["
        . "<< /Limits [0 5] /Kids ["
        . "<< /Limits [0 4] /Kids ["
        . "<< /Limits [0 3] /Nums [0 << /S /R >> 2 << /P (abc) /S /A /St 26 >>] >> "
        . "<< /Limits [4 4] /Nums [4 << /S /r >>] >>"
        . "] >> "
        . "<< /Limits [5 5] /Nums [5 << /S /a /St 26 >>] >>"
        . "] >>"
        . "] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps PDFium nested direct PageLabels kids aligned with preview metadata' => static function (TestRunner $t) use ($pdfiumNestedDirectPageLabelPdf): void {
        $pdf = $pdfiumNestedDirectPageLabelPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();
        $expectedLabels = ['I', 'II', 'abcZ', 'abcAA', 'i', 'z'];
        $expectedTexts = [
            'Nested label cover imported',
            'Nested label second imported',
            'Nested label appendix imported',
            'Nested label repeated imported',
            'Nested label roman tail imported',
            'Nested label lowercase tail imported',
        ];

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $previewLabels = $preview->pageLabels($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $summaryLabels = array_column($summary['pages'], 'page_label');

        $t->same($expectedLabels, $labels);
        $t->same($expectedLabels, array_column($entries, 'page_label'));
        $t->same($expectedLabels, $previewLabels);
        $t->same($expectedLabels, $summaryLabels);
        $t->same($expectedTexts, array_column($entries, 'text'));
        $t->same('abcAA', $preview->getPageImagePlan($pdf, 4)['page_label']);
        $t->same('z', $preview->getPageImagePlan($pdf, 6)['page_label']);
        $t->same('abcAA', $summary['pages'][3]['page_label'] ?? null);
        $t->same('z', $summary['pages'][5]['page_label'] ?? null);
        $t->true(!in_array('3', $labels, true));
        $t->true(!in_array('4', $previewLabels, true));
        $t->true(!in_array('abcA', $summaryLabels, true));
        $t->true(!in_array('abcB', $summaryLabels, true));
    },
];
