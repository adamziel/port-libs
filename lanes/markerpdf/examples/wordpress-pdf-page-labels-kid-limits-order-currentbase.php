<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Out of order front imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Out of order body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Out of order appendix imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Out of order back matter imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R] /Count 4 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Limits [0 3] /Kids [22 0 R 21 0 R 23 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [0 1] /Nums [0 << /P (Front ) /S /r /St 4 >> 1 << /P (Body ) /S /D /St 6 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [1 2] /Nums [1 << /P (stale-late-) /S /D /St 99 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "23 0 obj\n<< /Limits [3 3] /Nums [3 << /P (End-) >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 4);

if ($labels !== ['Front iv', 'Body 6', 'App-Z', 'End-'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected PageLabels kid nodes to merge in /Limits order across import and preview metadata.');
}

if (in_array('stale-late-99', $labels, true) || in_array('stale-late-99', $previewLabels, true)) {
    throw new RuntimeException('Expected stale out-of-order PageLabels kid entry to stay excluded.');
}

echo '<!-- markerpdf-page-labels-kid-limits-order-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels out-of-order /Kids are sorted by effective /Limits before duplicate page-index merge',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'stale_out_of_order_kid_rejected' => !in_array('stale-late-99', $labels, true)
        && !in_array('stale-late-99', $previewLabels, true),
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
