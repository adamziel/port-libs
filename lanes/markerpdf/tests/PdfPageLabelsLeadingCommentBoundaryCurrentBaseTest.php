<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsLeadingCommentPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Leading comment cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Leading comment body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Leading comment appendix imported) Tj ET',
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
        . "20 0 obj\n% producer comment before PageLabels dictionary\n<< /Nums [0 30 0 R 1 << /P 31 0 R /S 32 0 R /St 33 0 R >> 2 << /P (End-) >>] >>\nendobj\n"
        . "30 0 obj\n% producer comment before label dictionary\n<< /P (Cover-) >>\nendobj\n"
        . "31 0 obj\n% producer comment before prefix string\n(Body )\nendobj\n"
        . "32 0 obj\n% producer comment before style name\n/D\nendobj\n"
        . "33 0 obj\n% producer comment before start integer\n8\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps PageLabels leading comments as whitespace before indirect dictionaries and style operands' => static function (
        TestRunner $t
    ) use ($pageLabelsLeadingCommentPdf): void {
        $pdf = $pageLabelsLeadingCommentPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Cover-', 'Body 8', 'End-'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Leading comment cover imported',
            'Leading comment body imported',
            'Leading comment appendix imported',
        ], array_column($entries, 'text'));
        foreach (['1', 'Body ', '2'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Body 8', $summary['pages'][1]['page_label'] ?? null);
        $t->same('End-', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
