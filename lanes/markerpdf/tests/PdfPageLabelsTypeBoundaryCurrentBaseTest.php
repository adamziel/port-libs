<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsTypeBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Type boundary cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Type boundary body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Type boundary appendix imported) Tj ET',
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
        . "20 0 obj\n<< /Nums ["
        . "0 << /Type /Pages /P (stale-type-) /S /D /St 77 >> "
        . "0 << /Type /PageLabel /P (Cover-) >> "
        . "1 << /Type 30 0 R /P (Body ) /S /D /St 4 >> "
        . "2 << /Type /PageLabel /P (App-) /S /A /St 26 >>"
        . "] >>\nendobj\n"
        . "30 0 obj\n/PageLabel\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects wrong PageLabels value dictionary Type before stale WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsTypeBoundaryPdf): void {
        $pdf = $pageLabelsTypeBoundaryPdf();
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
            'Type boundary cover imported',
            'Type boundary body imported',
            'Type boundary appendix imported',
        ], array_column($entries, 'text'));
        foreach (['stale-type-77', 'stale-type-78', '1', '2', '3'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Cover-', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Body 4', $summary['pages'][1]['page_label'] ?? null);
        $t->same('App-Z', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
