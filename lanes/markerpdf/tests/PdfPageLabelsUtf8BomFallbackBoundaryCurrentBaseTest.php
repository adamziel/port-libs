<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageLabelsUtf8BomFallbackPdf = static function (): string {
    $contents = [
        10 => 'BT /F1 12 Tf 72 720 Td (UTF8 BOM preview fallback first page) Tj ET',
        11 => 'BT /F1 12 Tf 72 720 Td (UTF8 BOM preview fallback malformed page) Tj ET',
    ];
    $validPrefixText = "R\u{00E9}sum\u{00E9} ";
    $validPrefix = strtoupper(bin2hex("\xEF\xBB\xBF" . $validPrefixText));
    $malformedPrefix = strtoupper(bin2hex("\xEF\xBB\xBF" . "\xC3" . "Broken "));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 99 0 R /PageLabels 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    foreach ($contents as $objectNumber => $content) {
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Nums [0 << /P <{$validPrefix}> /S /D /St 5 >> 1 << /P <{$malformedPrefix}> /S /D /St 9 >>] >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'decodes UTF-8 BOM PageLabels prefixes in preview fallback before WordPress metadata' => static function (
        TestRunner $t
    ) use ($pageLabelsUtf8BomFallbackPdf): void {
        $pdf = $pageLabelsUtf8BomFallbackPdf();
        $extractor = new PdfTextExtractor();
        $preview = new MarkerAppPreview();

        $textExtractorLabels = $extractor->extractPageLabels($pdf);
        $summary = $preview->openPdfSummary($pdf);
        $previewLabels = array_column($summary['pages'], 'page_label');
        $imagePlan = $preview->getPageImagePlan($pdf, 2);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
        $expectedValidPrefixText = "R\u{00E9}sum\u{00E9} ";

        $t->same([], $textExtractorLabels, 'Missing catalog /Pages keeps text-extractor PageLabels unavailable.');
        $t->same(2, $summary['page_count']);
        $t->same([$expectedValidPrefixText . '5', '9'], $previewLabels);
        $t->same($expectedValidPrefixText . '5', $summary['pages'][0]['page_label'] ?? null);
        $t->same('9', $summary['pages'][1]['page_label'] ?? null);
        $t->same('9', $imagePlan['page_label'] ?? null);
        $t->same([3, 4], array_column($summary['pages'], 'object_id'));
        foreach (
            [
                "\u{00EF}\u{00BB}\u{00BF}R\u{00C3}\u{00A9}sum\u{00C3}\u{00A9} 5",
                "\u{00EF}\u{00BB}\u{00BF}\u{00C3}",
                'Broken 9',
            ] as $leakedLabel
        ) {
            $t->true(!in_array($leakedLabel, $previewLabels, true));
            $t->true(!str_contains($encodedSummary, $leakedLabel));
        }
    },
];
