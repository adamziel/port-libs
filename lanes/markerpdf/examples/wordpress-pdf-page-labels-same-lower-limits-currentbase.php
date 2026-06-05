<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Same lower cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Same lower body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Same lower appendix imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Same lower end imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Limits [0 3] /Kids [21 0 R 22 0 R 23 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [0 3] /Nums [0 << /P (Front ) /S /r /St 4 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [0 1] /Nums [0 << /P (stale-same-lower-) /S /D /St 99 >> 1 << /P (stale-same-lower-body-) /S /D /St 100 >>] >>\nendobj\n"
    . "23 0 obj\n<< /Limits [3 3] /Nums [3 << /P (End-) >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 4);
$staleLabels = ['stale-same-lower-99', 'stale-same-lower-body-100'];

if ($labels !== ['Front iv', 'Front v', 'App-Z', 'End-'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected same-lower PageLabels kid limits to preserve source-order page labels.');
}

$staleExcluded = count(array_intersect($staleLabels, $labels, $previewLabels)) === 0;
if (!$staleExcluded || ($imagePlan['page_label'] ?? null) !== 'End-') {
    throw new RuntimeException('Expected stale same-lower PageLabels kid labels to stay excluded from preview metadata.');
}

echo '<!-- markerpdf-page-labels-same-lower-limits-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels sibling kid nodes with identical lower /Limits preserve source order before WordPress page-break metadata',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'same_lower_source_order_preserved' => $labels[0] === 'Front iv' && $labels[1] === 'Front v',
    'stale_same_lower_kid_excluded' => $staleExcluded,
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

echo "<!-- wp:list -->\n<ul>\n";
foreach ($preview->openPdfSummary($pdf)['pages'] as $page) {
    echo '<li data-marker-page-index="' . $page['page_index'] . '" data-marker-page-label="'
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">PDF page ' . $page['page_number'] . ': '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
