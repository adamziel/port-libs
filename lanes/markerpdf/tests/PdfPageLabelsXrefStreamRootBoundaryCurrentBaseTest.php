<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsXrefStreamRootPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref-stream catalog page) Tj ET';
    $currentFirstContent = 'BT /F1 12 Tf 72 720 Td (Current xref-stream root first page) Tj ET';
    $currentSecondContent = 'BT /F1 12 Tf 72 720 Td (Current xref-stream root appendix page) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /PageLabels 30 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $addObject(30, 0, '<< /Nums [0 << /P (stale-xref-root-) /S /D /St 99 >>] >>');

    $addObject(7, 0, '<< /Type /Catalog /Pages 8 0 R /PageLabels 14 0 R >>');
    $addObject(8, 0, '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [9 0 R 11 0 R] /Count 2 >>');
    $addObject(9, 0, '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 13 0 R >> >> /Contents 10 0 R >>');
    $addObject(10, 0, "<< /Length " . strlen($currentFirstContent) . " >>\nstream\n{$currentFirstContent}\nendstream");
    $addObject(11, 0, '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 13 0 R >> >> /Contents 12 0 R >>');
    $addObject(12, 0, "<< /Length " . strlen($currentSecondContent) . " >>\nstream\n{$currentSecondContent}\nendstream");
    $addObject(13, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $addObject(14, 0, '<< /Nums [0 << /P (Current-) /S /D /St 4 >> 1 << /P (Appendix-) /S /A /St 26 >>] >>');

    $currentRows = '';
    for ($objectNumber = 7; $objectNumber <= 14; $objectNumber++) {
        $currentRows .= $xrefRow(1, $offsets[$objectNumber . ':0']);
    }

    $currentCompressed = gzcompress($currentRows);
    if (!is_string($currentCompressed)) {
        throw new RuntimeException('Unable to compress PageLabels xref-stream root fixture.');
    }

    $currentXrefOffset = $addObject(
        40,
        0,
        '<< /Type /XRef /Size 41 /Root 7 0 R /Index [7 8] /W [1 4 1] /Filter /FlateDecode /Length '
            . strlen($currentCompressed)
            . " >>\nstream\n{$currentCompressed}\nendstream"
    );

    return $pdf . "startxref\n{$currentXrefOffset}\n%%EOF\n";
};

return [
    'uses xref-stream trailer Root before stale catalog PageLabels in WordPress preview metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsXrefStreamRootPdf): void {
        $pdf = $pageLabelsXrefStreamRootPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $labels = $extractor->extractPageLabels($pdf);
        $entries = $extractor->extractLabeledPageTexts($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $summaryLabels = array_column($summary['pages'], 'page_label');
        $summaryPageObjects = array_column($summary['pages'], 'object_id');
        $previewLabels = $preview->pageLabels($pdf);

        $t->same(['Current-4', 'Appendix-Z'], $labels);
        $t->same($labels, array_column($entries, 'page_label'));
        $t->same($labels, $summaryLabels);
        $t->same($labels, $previewLabels);
        $t->same([9, 11], $summaryPageObjects);
        $t->same([
            'Current xref-stream root first page',
            'Current xref-stream root appendix page',
        ], array_column($entries, 'text'));
        $t->same('Appendix-Z', $preview->getPageImagePlan($pdf, 2)['page_label']);
        foreach (['stale-xref-root-99', '1'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $labels, true));
            $t->true(!in_array($staleLabel, $summaryLabels, true));
            $t->true(!in_array($staleLabel, $previewLabels, true));
        }
    },
];
