<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsUtf16MalformedPrefixPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Malformed UTF16 label imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Valid UTF16 label imported) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /P <FEFF0041D8000042> /S /D /St 4 >> 1 << /P <FEFF00560061006C00690064002D> /S /D /St 8 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'fails closed on malformed UTF-16 PageLabels prefixes before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsUtf16MalformedPrefixPdf): void {
        $pdf = $pageLabelsUtf16MalformedPrefixPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['4', 'Valid-8'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Malformed UTF16 label imported',
            'Valid UTF16 label imported',
        ], array_column($entries, 'text'));
        foreach (['AB4', 'A4', 'B4'] as $leakedLabel) {
            $t->true(!in_array($leakedLabel, $labels, true));
            $t->true(!in_array($leakedLabel, $previewLabels, true));
        }
        $t->same('4', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Valid-8', $preview->getPageImagePlan($pdf, 2)['page_label']);
        $t->same(2, $summary['page_count'] ?? null);
    },
];
