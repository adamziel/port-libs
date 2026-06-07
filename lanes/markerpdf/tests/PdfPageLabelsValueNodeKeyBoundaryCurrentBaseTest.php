<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsValueNodeKeyBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Node value cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Node value body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Node value appendix imported) Tj ET',
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
        . "20 0 obj\n<< /Nums [0 30 0 R 0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 4 >> 2 31 0 R 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Limits [0 2] /Nums [0 << /P (nested-stale-) /S /D /St 99 >>] /P (node-stale-) /S /D /St 77 >>\nendobj\n"
        . "31 0 obj\n<< /Kids [32 0 R] /P (node-app-) /S /A /St 26 >>\nendobj\n"
        . "32 0 obj\n<< /Nums [2 << /P (nested-kid-) /S /D /St 55 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects PageLabels Nums value dictionaries that are number-tree nodes' => static function (
        TestRunner $t
    ) use ($pageLabelsValueNodeKeyBoundaryPdf): void {
        $pdf = $pageLabelsValueNodeKeyBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Cover-', 'Body 4', 'App-Z'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Node value cover imported',
            'Node value body imported',
            'Node value appendix imported',
        ], array_column($entries, 'text'));
        $t->true(!in_array('node-stale-77', $labels, true));
        $t->true(!in_array('node-app-Z', $labels, true));
        $t->true(!in_array('nested-stale-99', $previewLabels, true));
        $t->true(!in_array('nested-kid-55', $previewLabels, true));
        $t->same('Cover-', $summary['pages'][0]['page_label'] ?? null);
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
