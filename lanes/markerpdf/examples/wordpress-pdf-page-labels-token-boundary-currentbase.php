<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Top-level cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Top-level body imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Kids [21 0 R << /Private [22 0 R] /Comment (22 0 R) >>] >>\nendobj\n"
    . "20 1 obj\n<< /Nums [0 << /P (stale-root-) /S /D /St 99 >>] >>\nendobj\n"
    . "21 0 obj\n<< /Nums [0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 4 >> [1 << /P (nested-stale-) /S /D /St 77 >>] (1 0 R)] >>\nendobj\n"
    . "22 0 obj\n<< /Nums [1 << /P (kid-stale-) /S /D /St 66 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 2);
$staleLabels = ['stale-root-99', 'stale-root-100', 'kid-stale-66', 'nested-stale-77'];

if ($labels !== ['Cover-', 'Body 4'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected top-level PageLabels boundaries before WordPress page-break metadata.');
}

$staleExcluded = count(array_intersect($staleLabels, $labels, $previewLabels)) === 0;
if (!$staleExcluded || ($imagePlan['page_label'] ?? null) !== 'Body 4') {
    throw new RuntimeException('Expected stale PageLabels root, kid, and nested Nums decoys to be excluded.');
}

echo '<!-- markerpdf-page-labels-token-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels root references, /Kids arrays, and /Nums arrays stay generation-exact and top-level token bounded',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'stale_root_generation_excluded' => !in_array('stale-root-99', $labels, true)
        && !in_array('stale-root-100', $previewLabels, true),
    'nested_kid_references_excluded' => !in_array('kid-stale-66', $labels, true),
    'nested_nums_entries_excluded' => !in_array('nested-stale-77', $previewLabels, true),
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
