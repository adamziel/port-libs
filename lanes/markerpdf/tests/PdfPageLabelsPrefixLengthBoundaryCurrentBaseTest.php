<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsPrefixLengthPdf = static function (): array {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Long prefix cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Safe duplicate prefix imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Alphabetic prefix imported) Tj ET',
    ];
    $oversizedPrefix = str_repeat('L', 4097);

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

    $pdf .= "20 0 obj\n<< /Nums ["
        . "0 << /P 30 0 R /S /D /St 4 >> "
        . "1 << /P 30 0 R /P (Safe-) /S /D /St 8 >> "
        . "2 << /P (App-) /S /A /St 26 >>"
        . "] >>\nendobj\n"
        . "30 0 obj\n({$oversizedPrefix})\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $oversizedPrefix];
};

return [
    'bounds oversized PageLabels prefixes before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsPrefixLengthPdf): void {
        [$pdf, $oversizedPrefix] = $pageLabelsPrefixLengthPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();
        $expected = ['4', 'Safe-8', 'App-Z'];

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same($expected, $labels);
        $t->same($expected, array_column($entries, 'page_label'));
        $t->same($expected, $previewLabels);
        $t->same([
            'Long prefix cover imported',
            'Safe duplicate prefix imported',
            'Alphabetic prefix imported',
        ], array_column($entries, 'text'));

        foreach ([$labels, $previewLabels, array_column($entries, 'page_label')] as $labelSet) {
            $t->true(!in_array($oversizedPrefix . '4', $labelSet, true));
            $t->true(!in_array($oversizedPrefix . '8', $labelSet, true));
            $t->true(!str_contains(implode("\n", $labelSet), str_repeat('L', 64)));
        }

        $t->same('4', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Safe-8', $summary['pages'][1]['page_label'] ?? null);
        $t->same('Safe-8', $preview->getPageImagePlan($pdf, 2)['page_label']);
        $t->true(strlen(max($labels)) < 16);
    },
];
