<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsScalarCommentBoundaryPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Front scalar imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Body scalar imported) Tj ET',
    ];
    $bodyPrefixHex = strtoupper(bin2hex('Body '));

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
        . "20 0 obj\n<< /Nums [0 << /P 30 0 R /S 31 0 R /St 32 0 R >> 1 << /P 33 0 R /S 34 0 R /St 35 0 R >>] >>\nendobj\n"
        . "30 0 obj\n(Front ) % prefix scalar comment\nendobj\n"
        . "31 0 obj\n/r % style scalar comment\nendobj\n"
        . "32 0 obj\n4 % start scalar comment\nendobj\n"
        . "33 0 obj\n<{$bodyPrefixHex}> % hex prefix scalar comment\nendobj\n"
        . "34 0 obj\n/D % decimal style scalar comment\nendobj\n"
        . "35 0 obj\n7 % decimal start scalar comment\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps PageLabels indirect scalar comments as whitespace before WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsScalarCommentBoundaryPdf): void {
        $pdf = $pageLabelsScalarCommentBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $previewLabels = $preview->pageLabels($pdf);
        $summaryLabels = array_column($preview->openPdfSummary($pdf)['pages'], 'page_label');

        $t->same(['Front iv', 'Body 7'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same($labels, $summaryLabels);
        $t->same(['Front scalar imported', 'Body scalar imported'], array_column($entries, 'text'));
        $t->same('Body 7', $preview->getPageImagePlan($pdf, 2)['page_label']);
        foreach (['1', '2', 'Front i', 'Body 1'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
    },
];
