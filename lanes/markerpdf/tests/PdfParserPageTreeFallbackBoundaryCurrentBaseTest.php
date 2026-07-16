<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageTreeFallbackBoundaryPdf = static function (string $pagesDictionary, string $label): string {
    $visible = "BT /F1 12 Tf 72 720 Td ({$label}) Tj ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n{$pagesDictionary}\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$assertNoStreamOnlyFallback = static function (TestRunner $t, string $pdf, string $label): void {
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);

    $t->same('', $plainText);
    $t->same([], $extractor->extractTextLines($pdf));
    $t->same([], $extractor->extractTextRuns($pdf));
    $t->same('', $extractor->naiveGetText($pdf));
    $t->same([], $extractor->extractPageLabels($pdf));
    $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->true(!str_contains($plainText, $label));
};

return [
    'blocks stream-only fallback when an empty Kids array declares a nonzero Count' => static function (
        TestRunner $t
    ) use ($pageTreeFallbackBoundaryPdf, $assertNoStreamOnlyFallback): void {
        $label = 'Empty Kids Nonzero Count Fallback Leak';
        $pdf = $pageTreeFallbackBoundaryPdf(
            '<< /Type /Pages /Kids [] /Count 1 >>',
            $label
        );

        $assertNoStreamOnlyFallback($t, $pdf, $label);
    },
    'blocks stream-only fallback when a zero Count tree has null Kids entries' => static function (
        TestRunner $t
    ) use ($pageTreeFallbackBoundaryPdf, $assertNoStreamOnlyFallback): void {
        $label = 'Null Kid Zero Count Fallback Leak';
        $pdf = $pageTreeFallbackBoundaryPdf(
            '<< /Type /Pages /Kids [ null ] /Count 0 >>',
            $label
        );

        $assertNoStreamOnlyFallback($t, $pdf, $label);
    },
    'blocks stream-only fallback when zero Count omits Kids entirely' => static function (
        TestRunner $t
    ) use ($pageTreeFallbackBoundaryPdf, $assertNoStreamOnlyFallback): void {
        $label = 'Missing Kids Zero Count Fallback Leak';
        $pdf = $pageTreeFallbackBoundaryPdf(
            '<< /Type /Pages /Count 0 >>',
            $label
        );

        $assertNoStreamOnlyFallback($t, $pdf, $label);
    },
    'blocks stream-only fallback when empty Kids omit Count entirely' => static function (
        TestRunner $t
    ) use ($pageTreeFallbackBoundaryPdf, $assertNoStreamOnlyFallback): void {
        $label = 'Empty Kids Missing Count Fallback Leak';
        $pdf = $pageTreeFallbackBoundaryPdf(
            '<< /Type /Pages /Kids [] >>',
            $label
        );

        $assertNoStreamOnlyFallback($t, $pdf, $label);
    },
];
