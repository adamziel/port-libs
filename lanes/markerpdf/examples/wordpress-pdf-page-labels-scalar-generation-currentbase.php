<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Generated cover fallback imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Generated roman prefix imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Generated appendix imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Limits [40 0 R 41 0 R] /Nums [0 << /P (stale-cover-) /S /D /St 9 >> 1 << /P 30 0 R /S 31 0 R /St 32 0 R >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "30 0 obj\n(Real-)\nendobj\n"
    . "31 0 obj\n/r\nendobj\n"
    . "32 0 obj\n4\nendobj\n"
    . "40 0 obj\n1\nendobj\n"
    . "41 0 obj\n2\nendobj\n"
    . "30 1 obj\n(stale-prefix-)\nendobj\n"
    . "31 1 obj\n/D\nendobj\n"
    . "32 1 obj\n99\nendobj\n"
    . "40 1 obj\n0\nendobj\n"
    . "41 1 obj\n99\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 2);
$staleLabels = ['stale-cover-9', 'stale-prefix-99', 'stale-prefix-100', 'Real-4'];

if ($labels !== ['1', 'Real-iv', 'App-Z'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected generation-exact PageLabels scalar operands and Limits to align import and preview metadata.');
}

if (count(array_intersect($staleLabels, $labels, $previewLabels)) !== 0 || ($imagePlan['page_label'] ?? null) !== 'Real-iv') {
    throw new RuntimeException('Expected stale PageLabels scalar and limit operands to stay excluded.');
}

echo '<!-- markerpdf-page-labels-scalar-generation-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels scalar operands and Limits resolve by exact object generation',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'scalar_generation_decoys_rejected' => !in_array('stale-prefix-99', $labels, true)
        && !in_array('Real-4', $previewLabels, true),
    'limits_generation_decoys_rejected' => !in_array('stale-cover-9', $labels, true),
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
