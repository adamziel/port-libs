<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsStartValueBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Start value cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Start value body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Start value appendix imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Start value reset imported) Tj ET',
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
        . "20 0 obj\n<< /Nums ["
        . "0 << /P (Front ) /S /D /St 0 /St 4 >> "
        . "1 << /P (Body ) /S /r /St -2 /St 6 >> "
        . "2 << /P (App-) /S /A /St 0 /St 26 >> "
        . "3 << /P (Reset ) /S /D /St 0 >>"
        . "] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'skips non-positive PageLabels St operands before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsStartValueBoundaryPdf): void {
        $pdf = $pageLabelsStartValueBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $expected = ['Front 4', 'Body vi', 'App-Z', 'Reset 1'];
        $t->same($expected, $labels);
        $t->same($expected, array_column($entries, 'page_label'));
        $t->same($expected, $previewLabels);
        $t->same([
            'Start value cover imported',
            'Start value body imported',
            'Start value appendix imported',
            'Start value reset imported',
        ], array_column($entries, 'text'));
        foreach (['Front 1', 'Body i', 'App-A', 'Reset 0'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Body vi', $summary['pages'][1]['page_label'] ?? null);
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
        $t->same(4, $summary['page_count'] ?? null);
    },
];
