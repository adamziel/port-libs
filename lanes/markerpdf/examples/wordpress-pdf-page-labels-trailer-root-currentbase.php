<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Stale catalog page imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Current root page one imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Current root page two imported) Tj ET',
];

$objects = [
    1 => '<< /Type /Catalog /Pages 2 0 R /PageLabels 30 0 R >>',
    2 => '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 10 0 R >>',
    7 => '<< /Type /Catalog /Pages 8 0 R /PageLabels 31 0 R >>',
    8 => '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [9 0 R 11 0 R] /Count 2 >>',
    9 => '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 12 0 R >>',
    11 => '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 13 0 R >>',
    14 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    30 => '<< /Nums [0 << /P (stale-root-) /S /D /St 99 >>] >>',
    31 => '<< /Nums [0 << /P (Current-) /S /D /St 4 >> 1 << /P (Appendix-) /S /A /St 26 >>] >>',
];

foreach ($contents as $objectNumber => $content) {
    $objects[$objectNumber] = '<< /Length ' . strlen($content) . " >>\nstream\n{$content}\nendstream";
}

ksort($objects, SORT_NUMERIC);

$pdf = "%PDF-1.7\n";
$offsets = [];
foreach ($objects as $objectNumber => $body) {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
}

$xrefOffset = strlen($pdf);
$size = max(array_keys($objects)) + 1;
$pdf .= "xref\n0 {$size}\n";
for ($objectNumber = 0; $objectNumber < $size; $objectNumber++) {
    if (!isset($offsets[$objectNumber])) {
        $pdf .= "0000000000 65535 f \n";
        continue;
    }

    $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
}

$pdf .= "trailer\n<< /Size {$size} /Root 7 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "trailer\n<< /Root 1 0 R >>\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = array_column($summary['pages'], 'page_label');
$previewPageIds = array_column($summary['pages'], 'object_id');
$imagePlan = $preview->getPageImagePlan($pdf, 2);

if ($labels !== ['Current-4', 'Appendix-Z'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected trailer /Root catalog PageLabels to align text extraction and preview metadata.');
}

if ($previewPageIds !== [9, 11] || in_array('stale-root-99', $labels, true) || in_array('stale-root-99', $previewLabels, true)) {
    throw new RuntimeException('Expected stale scanned catalog PageLabels to stay excluded from WordPress page metadata.');
}

echo '<!-- markerpdf-page-labels-trailer-root-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'classic trailer /Root catalog wins before stale catalog-shaped object scanning',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'preview_page_object_ids' => $previewPageIds,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'trailer_root_catalog_selected' => $previewPageIds === [9, 11],
    'stale_catalog_rejected' => !in_array('stale-root-99', $labels, true) && !in_array('stale-root-99', $previewLabels, true),
    'post_startxref_root_decoy_rejected' => !in_array(3, $previewPageIds, true),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($pages as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
