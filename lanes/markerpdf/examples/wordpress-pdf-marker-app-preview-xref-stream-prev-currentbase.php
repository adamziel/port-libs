<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
    throw new RuntimeException('Unable to compress marker app preview middle xref-stream rows.');
}

$middleXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 12 0 R /Prev ' . $baseXrefOffset . ' /Index [5 1 12 4] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedMiddleRows) . " >>\n"
    . "stream\n{$compressedMiddleRows}\nendstream\nendobj\n"
    . "startxref\n{$middleXrefOffset}\n%%EOF\n";

$latestRows = $xrefStreamRow(1, strlen($pdf), 0);
$compressedLatestRows = gzcompress($latestRows);
if (!is_string($compressedLatestRows)) {
    throw new RuntimeException('Unable to compress marker app preview sparse latest xref-stream row.');
}

$latestXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Prev ' . $middleXrefOffset . ' /Index [30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedLatestRows) . " >>\n"
    . "stream\n{$compressedLatestRows}\nendstream\nendobj\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF";

$preview = new MarkerAppPreview();
$summary = $preview->openPdfSummary($pdf);
$plan = $preview->getPageImagePlan($pdf, 1);
$text = (new PdfTextExtractor())->extractPlainText($pdf);

$currentSelected = $text === 'Current preview inherited root page'
    && ($summary['page_count'] ?? null) === 1
    && ($summary['pages'][0]['object_id'] ?? null) === 14
    && ($plan['object_id'] ?? null) === 14
    && ($plan['crop_box'] ?? null) === [36.0, 72.0, 576.0, 720.0]
    && ($plan['rotation'] ?? null) === 90
    && ($plan['user_unit'] ?? null) === 2.0;
$staleExcluded = !str_contains($text, 'Stale preview base page');

if (!$currentSelected || !$staleExcluded) {
    throw new RuntimeException('Expected marker app preview to inherit Root through xref-stream Prev before stale base trailer fallback.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-marker-app-preview-xref-stream-prev-currentbase ' . $htmlJson([
    'source' => 'native-marker-app-preview-xref-stream-prev-chain',
    'support_component' => 'native-pdf-xref-section-root-inheritance',
    'native_boundary' => 'latest sparse xref-stream without Root follows Prev to current xref-stream trailer Root before stale classic base trailer fallback',
    'page_count' => $summary['page_count'],
    'preview_page_object' => $plan['object_id'],
    'preview_media_box' => $plan['media_box'],
    'preview_crop_box' => $plan['crop_box'],
    'preview_rotation' => $plan['rotation'],
    'preview_user_unit' => $plan['user_unit'],
    'current_text_selected' => $currentSelected,
    'stale_base_root_excluded' => $staleExcluded,
    'latest_xref_omits_root' => true,
    'base_xref_offset' => $baseXrefOffset,
    'middle_xref_offset' => $middleXrefOffset,
    'latest_xref_offset' => $latestXrefOffset,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
