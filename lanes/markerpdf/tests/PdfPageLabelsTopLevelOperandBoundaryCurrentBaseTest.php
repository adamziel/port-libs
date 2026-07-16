<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsTopLevelOperandBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Operand boundary front imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Operand boundary body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Operand boundary appendix imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R 99 /PageLabels 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /P (catalog-stale-) /S /D /St 99 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Limits [0 2] /Kids [31 0 R] 77 /Kids [32 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Limits [0 1] 88 /Nums [0 << /P (kid-stale-) /S /D /St 77 >> 1 << /P (kid-body-) /S /D /St 78 >>] >>\nendobj\n"
        . "32 0 obj\n<< /Limits [0 2] /Nums [0 << /P (nums-stale-) /S /D /St 90 >>] 66 /Nums [0 << /P (bad-front-) 44 /P (Front ) /S /D 55 /S /r /St 99 22 /St 4 >> 1 << /P (Body ) /S /D /St 8 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects PageLabels top-level trailing operands before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsTopLevelOperandBoundaryPdf): void {
        $pdf = $pageLabelsTopLevelOperandBoundaryPdf();
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
            'Operand boundary front imported',
            'Operand boundary body imported',
            'Operand boundary appendix imported',
        ], array_column($entries, 'text'));
        foreach (
            [
                'catalog-stale-99',
                'kid-stale-77',
                'kid-body-78',
                'nums-stale-90',
                'bad-front-99',
                'Front 99',
                '1',
            ] as $staleLabel
        ) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Body 8', $summary['pages'][1]['page_label'] ?? null);
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
