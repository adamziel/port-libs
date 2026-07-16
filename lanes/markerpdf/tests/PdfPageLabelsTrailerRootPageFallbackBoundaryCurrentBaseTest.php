<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsTrailerRootPageFallbackPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Current direct fallback page one) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Current direct fallback page two) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Catalog /Pages 99 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /P (Current-) /S /D /St 4 >> 1 << /P (Now-) /S /A /St 26 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Nums [0 << /P (stale-catalog-) /S /D /St 99 >> 1 << /P (stale-app-) /S /A /St 26 >>] >>\nendobj\n"
        . "trailer\n<< /Root 7 0 R >>\n%%EOF\n";
};

return [
    'keeps selected trailer Root PageLabels when preview falls back to direct pages' => static function (
        TestRunner $t
    ) use ($pageLabelsTrailerRootPageFallbackPdf): void {
        $pdf = $pageLabelsTrailerRootPageFallbackPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $textExtractorLabels = $extractor->extractPageLabels($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');
        $imagePlan = $preview->getPageImagePlan($pdf, 2);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same([], $textExtractorLabels, 'Broken selected catalog /Pages keeps text-extractor labels unavailable.');
        $t->same(2, $summary['page_count']);
        $t->same([3, 4], array_column($summary['pages'], 'object_id'));
        $t->same(['Current-4', 'Now-Z'], $previewLabels);
        $t->same('Current-4', $summary['pages'][0]['page_label'] ?? null);
        $t->same('Now-Z', $imagePlan['page_label'] ?? null);

        foreach (['stale-catalog-99', 'stale-app-Z'] as $staleLabel) {
            $t->true(!in_array($staleLabel, $previewLabels, true));
            $t->true(!str_contains($encodedSummary, $staleLabel));
        }
    },
];
