<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Opening page imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Body page imported) Tj ET',
        12 => 'BT /F1 12 Tf 72 720 Td (Chapter page imported) Tj ET',
        13 => 'BT /F1 12 Tf 72 720 Td (Continued page imported) Tj ET',
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
        . "20 0 obj\n<< /Limits [1 2] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Nums [0 << /S /r /P (stale-front-) /St 6 >> 1 << /S /D /P (Body ) /St 5 >>] >>\nendobj\n"
        . "22 0 obj\n<< /Nums [2 << /S /D /P (Chapter ) /St 9 >> 3 << /S /D /P (stale-back-) /St 99 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps parent PageLabels Limits across indirect kid number-tree boundaries' => static function (TestRunner $t) use ($pageLabelBoundaryPdf): void {
        $pdf = $pageLabelBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);

        $t->same(['1', 'Body 5', 'Chapter 9', 'Chapter 10'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, array_column($summary['pages'], 'page_label'));
        $t->same(['Opening page imported', 'Body page imported', 'Chapter page imported', 'Continued page imported'], array_column($entries, 'text'));
        $t->true(!in_array('stale-front-vi', $labels, true));
        $t->true(!in_array('stale-back-99', $labels, true));
        $t->same('Chapter 10', $preview->getPageImagePlan($pdf, 4)['page_label']);
    },
];
