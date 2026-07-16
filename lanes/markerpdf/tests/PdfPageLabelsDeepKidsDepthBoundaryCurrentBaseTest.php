<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsDeepKidsDepthPdf = static function (bool $missingPagesTree = false): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Deep PageLabels cover imported) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Deep PageLabels body imported) Tj ET',
    ];

    $pagesReference = $missingPagesTree ? '99 0 R' : '2 0 R';
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages {$pagesReference} /PageLabels 20 0 R /PageLabels 200 0 R >>\nendobj\n";

    if (!$missingPagesTree) {
        $pdf .= "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n";
    } else {
        $pdf .= "3 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 10 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 11 0 R >>\nendobj\n";
    }

    $pdf .= "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    $depth = 105;
    for ($index = 0; $index < $depth; $index++) {
        $objectNumber = 20 + $index;
        $kidObjectNumber = $objectNumber + 1;
        $pdf .= "{$objectNumber} 0 obj\n<< /Limits [0 1] /Kids [{$kidObjectNumber} 0 R] >>\nendobj\n";
    }

    $leafObjectNumber = 20 + $depth;
    return $pdf
        . "{$leafObjectNumber} 0 obj\n<< /Limits [0 1] /Nums [0 << /P (too-deep-) /S /D /St 77 >> 1 << /P (too-deep-body-) /S /D /St 88 >>] >>\nendobj\n"
        . "200 0 obj\n<< /Nums [0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 4 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects too-deep PageLabels Kids chains before stale WordPress page metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsDeepKidsDepthPdf): void {
        $pdf = $pageLabelsDeepKidsDepthPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same(['Cover-', 'Body 4'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $previewLabels);
        $t->same([
            'Deep PageLabels cover imported',
            'Deep PageLabels body imported',
        ], array_column($entries, 'text'));
        foreach (['too-deep-77', 'too-deep-body-88', 'too-deep-78', '1', '2'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Cover-', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Body 4', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
    'rejects too-deep PageLabels Kids chains in preview fallback metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsDeepKidsDepthPdf): void {
        $pdf = $pageLabelsDeepKidsDepthPdf(true);
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $textExtractorLabels = $extractor->extractPageLabels($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');

        $t->same([], $textExtractorLabels, 'Missing catalog /Pages keeps text-extractor PageLabels unavailable.');
        $t->same(2, $summary['page_count']);
        $t->same(['Cover-', 'Body 4'], $previewLabels);
        $t->same([3, 4], array_column($summary['pages'], 'object_id'));
        foreach (['too-deep-77', 'too-deep-body-88', 'too-deep-78', '1', '2'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
        $t->same('Cover-', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Body 4', $preview->getPageImagePlan($pdf, 2)['page_label']);
    },
];
