<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsEmptyHexPrefixFallbackPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (Empty hex PageLabels fallback first page) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (Empty literal PageLabels fallback second page) Tj ET',
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 99 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums ["
        . "0 << /P <> /S /D /St 4 /P (stale-) >> "
        . "1 << /P () /S /D /St 8 /P (stale-literal-) >>"
        . "] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps empty hex PageLabels prefixes usable in preview fallback before stale duplicates' => static function (
        TestRunner $t
    ) use ($pageLabelsEmptyHexPrefixFallbackPdf): void {
        $pdf = $pageLabelsEmptyHexPrefixFallbackPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $textExtractorLabels = $extractor->extractPageLabels($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');
        $imagePlan = $preview->getPageImagePlan($pdf, 1);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same([], $textExtractorLabels, 'Missing catalog /Pages keeps the text-extractor PageLabels unavailable.');
        $t->same(2, $summary['page_count']);
        $t->same(['4', '8'], $previewLabels);
        $t->same('4', $summary['pages'][0]['page_label'] ?? null);
        $t->same('8', $summary['pages'][1]['page_label'] ?? null);
        $t->same('4', $imagePlan['page_label'] ?? null);
        $t->same([3, 4], array_column($summary['pages'], 'object_id'));

        foreach (['stale-4', 'stale-literal-8'] as $leakedLabel) {
            $t->true(!in_array($leakedLabel, $previewLabels, true));
            $t->true(!str_contains($encodedSummary, $leakedLabel));
        }
    },
];
