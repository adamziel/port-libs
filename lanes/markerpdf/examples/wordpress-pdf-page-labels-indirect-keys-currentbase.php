<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Opening fallback imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Indirect front imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Indirect body imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Indirect appendix imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Limits [1 3] /Nums [30 0 R << /S /r /P (Front ) /St 2 >> 31 0 R 34 0 R [2 << /P (nested-stale-) /S /D /St 77 >>] << /P (array-value-stale-) /S /D /St 88 >> 32 0 R << /S /A /P (App-) /St 26 >> 33 0 R << /P (stale-back-) /S /D /St 99 >>] >>\nendobj\n"
    . "30 0 obj\n1\nendobj\n"
    . "30 1 obj\n0\nendobj\n"
    . "31 0 obj\n2\nendobj\n"
    . "31 1 obj\n0\nendobj\n"
    . "32 0 obj\n3\nendobj\n"
    . "33 0 obj\n4\nendobj\n"
    . "34 0 obj\n<< /S /D /P (Body ) /St 8 >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 4);
$staleLabels = ['nested-stale-77', 'array-value-stale-88', 'stale-back-99'];

if ($labels !== ['1', 'Front ii', 'Body 8', 'App-Z'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected indirect PageLabels /Nums keys to resolve before WordPress page-break metadata.');
}

if (count(array_intersect($staleLabels, $labels, $previewLabels)) !== 0 || ($imagePlan['page_label'] ?? null) !== 'App-Z') {
    throw new RuntimeException('Expected stale nested and out-of-page PageLabels decoys to remain excluded.');
}

echo '<!-- markerpdf-page-labels-indirect-keys-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels /Nums keys resolve indirect integer operands by exact generation',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'generation_decoy_rejected' => !in_array('stale-front-1', $labels, true),
    'nested_nums_decoys_excluded' => count(array_intersect($staleLabels, $labels, $previewLabels)) === 0,
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
