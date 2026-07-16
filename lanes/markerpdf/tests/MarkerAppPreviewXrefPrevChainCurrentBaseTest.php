<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

$markerAppPreviewXrefPrevChainCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale preview base page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current preview inherited root page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $baseXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . "trailer\n<< /Size 16 /Root 1 0 R >>\n"
        . "startxref\n{$baseXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(12, 0, '<< /Type /Catalog /Pages 13 0 R >>');
    $currentPagesOffset = $addObject(13, 0, '<< /Type /Pages /Kids [14 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(14, 0, '<< /Type /Page /Parent 13 0 R /MediaBox [0 0 612 792] /CropBox [36 72 576 720] /Rotate 90 /UserUnit 2 /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R >>');
    $currentContentOffset = $addObject(15, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $middleRows = ''
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $currentCatalogOffset, 0)
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0);
    $compressedMiddleRows = gzcompress($middleRows);
    if (!is_string($compressedMiddleRows)) {
        throw new RuntimeException('Unable to compress marker app preview xref middle rows.');
    }

    $middleXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 12 0 R /Prev ' . $baseXrefOffset . ' /Index [5 1 12 4] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedMiddleRows) . " >>\n"
        . "stream\n{$compressedMiddleRows}\nendstream\nendobj\n"
        . "startxref\n{$middleXrefOffset}\n%%EOF\n";

    $latestRows = $xrefStreamRow(1, strlen($pdf), 0);
    $compressedLatestRows = gzcompress($latestRows);
    if (!is_string($compressedLatestRows)) {
        throw new RuntimeException('Unable to compress marker app preview xref latest rows.');
    }

    $latestXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Prev ' . $middleXrefOffset . ' /Index [30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedLatestRows) . " >>\n"
        . "stream\n{$compressedLatestRows}\nendstream\nendobj\n"
        . "startxref\n{$latestXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'inherits marker app preview root through sparse xref-stream Prev chain' => static function (
        TestRunner $t
    ) use ($markerAppPreviewXrefPrevChainCurrentBasePdf): void {
        $pdf = $markerAppPreviewXrefPrevChainCurrentBasePdf();
        $preview = new MarkerAppPreview();
        $summary = $preview->openPdfSummary($pdf);
        $plan = $preview->getPageImagePlan($pdf, 1);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Current preview inherited root page', $text);
        $t->same(1, $summary['page_count']);
        $t->same(14, $summary['pages'][0]['object_id'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $summary['pages'][0]['media_box'] ?? null);
        $t->same([36.0, 72.0, 576.0, 720.0], $summary['pages'][0]['crop_box'] ?? null);
        $t->same(90, $summary['pages'][0]['rotation'] ?? null);
        $t->same(2.0, $summary['pages'][0]['user_unit'] ?? null);
        $t->same(14, $plan['object_id']);
        $t->same([36.0, 72.0, 576.0, 720.0], $plan['page_bbox']);
        $t->same(90, $plan['rotation']);
        $t->same(2.0, $plan['user_unit']);
        $t->true(str_contains($pdf, '/Root 12 0 R'));
        $t->true(str_contains($pdf, "/Index [30 1] /W [1 4 1]"));
        $t->true(!str_contains($text, 'Stale preview base page'));
    },
];
