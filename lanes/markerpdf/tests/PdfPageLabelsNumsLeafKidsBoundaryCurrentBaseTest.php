<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsNumsLeafKidsPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Leaf cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Leaf body imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Leaf appendix imported) Tj ET',
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
        . "20 0 obj\n<< /Nums [0 << /P (Leaf-) /S /D /St 4 >>] /Kids [21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [2 2] /Nums [2 << /P (stale-kid-) /S /D /St 99 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'treats PageLabels Nums nodes as leaves before disjoint stale Kids rows' => static function (
        TestRunner $t
    ) use ($pageLabelsNumsLeafKidsPdf): void {
        $pdf = $pageLabelsNumsLeafKidsPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Leaf-4', 'Leaf-5', 'Leaf-6'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Leaf cover imported',
            'Leaf body imported',
            'Leaf appendix imported',
        ], array_column($entries, 'text'));
        $t->true(!in_array('stale-kid-99', $labels, true));
        $t->true(!in_array('stale-kid-99', $previewLabels, true));
        $t->same('Leaf-6', $summary['pages'][2]['page_label'] ?? null);
        $t->same('Leaf-6', $preview->getPageImagePlan($pdf, 3)['page_label']);
    },
];
