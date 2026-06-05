<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Nested front matter imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Nested front matter continued) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Nested appendix imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Nums 29 0 R >>\nendobj\n"
    . "29 0 obj\n30 0 R\nendobj\n"
    . "30 0 obj\n[0 << /P 31 0 R /S 33 0 R /St 35 0 R >> 2 << /P 37 0 R /S 39 0 R /St 41 0 R >>]\nendobj\n"
    . "31 0 obj\n32 0 R\nendobj\n"
    . "32 0 obj\n(Nested Front )\nendobj\n"
    . "33 0 obj\n34 0 R\nendobj\n"
    . "34 0 obj\n/r\nendobj\n"
    . "35 0 obj\n36 0 R\nendobj\n"
    . "36 0 obj\n4\nendobj\n"
    . "37 0 obj\n38 0 R\nendobj\n"
    . "38 0 obj\n(Nested App-)\nendobj\n"
    . "39 0 obj\n40 0 R\nendobj\n"
    . "40 0 obj\n/A\nendobj\n"
    . "41 0 obj\n42 0 R\nendobj\n"
    . "42 0 obj\n26\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 3);

if ($labels !== ['Nested Front iv', 'Nested Front v', 'Nested App-Z']) {
    throw new RuntimeException('Expected transitive indirect PageLabels operands in native text extraction.');
}

if ($previewLabels !== $labels || ($imagePlan['page_label'] ?? null) !== 'Nested App-Z') {
    throw new RuntimeException('Expected MarkerAppPreview labels to match transitive PageLabels extraction.');
}

echo '<!-- markerpdf-page-labels-transitive-operands-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels transitive indirect /Nums, /P, /S, and /St operands align preview metadata with text extraction',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'fallback_numeric_preview_labels_rejected' => !in_array('1', $previewLabels, true)
        && !in_array('2', $previewLabels, true)
        && !in_array('3', $previewLabels, true),
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
