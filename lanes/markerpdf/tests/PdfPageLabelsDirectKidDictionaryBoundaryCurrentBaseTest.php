<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsDirectKidDictionaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Direct kid front imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Direct kid body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Direct kid appendix imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Direct kid back imported) Tj ET',
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
        . "20 0 obj\n<< /Limits [0 3] /Kids [<< /Limits [0 1] /Nums [0 << /P (Front ) /S /r /St 2 >> 1 << /P (Body ) /S /D /St 7 >>] >> 22 0 R << /Private << /Nums [0 << /P (stale-private-) /S /D /St 99 >>] >> >>] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [2 3] /Nums [2 << /P (App-) /S /A /St 26 >> 3 << /P (Back-) /S /D /St 9 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps direct PageLabels kid dictionaries before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsDirectKidDictionaryPdf): void {
        $pdf = $pageLabelsDirectKidDictionaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Front ii', 'Body 7', 'App-Z', 'Back-9'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Direct kid front imported',
            'Direct kid body imported',
            'Direct kid appendix imported',
            'Direct kid back imported',
        ], array_column($entries, 'text'));
        foreach (['1', '2', 'stale-private-99'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Body 7', $summary['pages'][1]['page_label'] ?? null);
        $t->same('Back-9', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
];
