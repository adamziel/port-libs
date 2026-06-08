<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsTrailerRootAbsentBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Stale absent-root label page) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Current no-label root first page) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Current no-label root second page) Tj ET',
    ];

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R /PageLabels 30 0 R >>',
        2 => '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 10 0 R >>',
        7 => '<< /Type /Catalog /Pages 8 0 R >>',
        8 => '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [9 0 R 11 0 R] /Count 2 >>',
        9 => '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 12 0 R >>',
        11 => '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 13 0 R >>',
        14 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        30 => '<< /Nums [0 << /P (stale-root-) /S /D /St 99 >>] >>',
    ];

    foreach ($contents as $objectNumber => $content) {
        $objects[$objectNumber] = '<< /Length ' . strlen($content) . " >>\nstream\n{$content}\nendstream";
    }

    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $body) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $size = max(array_keys($objects)) + 1;
    $pdf .= "xref\n0 {$size}\n";
    for ($objectNumber = 0; $objectNumber < $size; $objectNumber++) {
        if (!isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }

    return $pdf
        . "trailer\n<< /Size {$size} /Root 7 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "trailer\n<< /Root 1 0 R >>\n";
};

return [
    'uses default PageLabels when current trailer Root omits PageLabels before stale catalog fallback' => static function (
        TestRunner $t
    ) use ($pageLabelsTrailerRootAbsentBoundaryPdf): void {
        $pdf = $pageLabelsTrailerRootAbsentBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['1', '2'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same(['Current no-label root first page', 'Current no-label root second page'], array_column($entries, 'text'));
        $t->same([9, 11], array_column($summary['pages'], 'object_id'));
        foreach (['stale-root-99', 'stale-root-100'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('1', $summary['pages'][0]['page_label'] ?? null);
        $t->same('2', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
];
