<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsDuplicateKidsLocalNumsPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Duplicate local cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Duplicate local body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Duplicate local continuation imported) Tj ET',
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
        . "20 0 obj\n<< /Limits [0 2] /Nums [0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 8 >>] /Kids [21 0 R] /Kids [22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [0 1] /Nums [0 << /P (child-cover-stale-) /S /D /St 55 >> 1 << /P (child-body-stale-) /S /D /St 66 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [2 2] /Nums [2 << /P (stale-late-kids-) /S /D /St 99 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps first usable duplicate PageLabels Kids group when local Nums already own its pages' => static function (
        TestRunner $t
    ) use ($pageLabelsDuplicateKidsLocalNumsPdf): void {
        $pdf = $pageLabelsDuplicateKidsLocalNumsPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Cover-', 'Body 8', 'Body 9'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $preview->pageLabels($pdf));
        $t->same($labels, $previewLabels);
        $t->same([
            'Duplicate local cover imported',
            'Duplicate local body imported',
            'Duplicate local continuation imported',
        ], array_column($entries, 'text'));
        foreach (['child-cover-stale-55', 'child-body-stale-66', 'stale-late-kids-99', '3'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Body 9', $summary['pages'][2]['page_label'] ?? null);
        $t->same('Body 9', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
