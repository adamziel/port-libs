<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Preface imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Chapter starts) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Appendix imported) Tj ET',
];

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}
$pdf .= "20 0 obj\n<< /Nums [0 << /S /r /P (front-) /St 2 >> 1 << /S /D /P (Body ) /St 1 >> 2 << /S /A /P (App-) /St 27 >>] >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$pages = $extractor->extractLabeledPageTexts($pdf);
$summary = (new MarkerAppPreview())->openPdfSummary($pdf);
$labels = array_column($summary['pages'], 'page_label');

if ($labels !== array_column($pages, 'page_label')) {
    throw new RuntimeException('Expected preview and text extraction page labels to match.');
}

echo '<!-- markerpdf-page-labels-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels number-tree labels aligned to page /Contents extraction and marker_app preview pages',
    'page_count' => $summary['page_count'],
    'page_labels' => $labels,
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
foreach ($summary['pages'] as $page) {
    $label = htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<li data-marker-page-index="' . $page['page_index'] . '" data-marker-page-label="' . $label . '">'
        . 'PDF page ' . $page['page_number'] . ': ' . $label
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
