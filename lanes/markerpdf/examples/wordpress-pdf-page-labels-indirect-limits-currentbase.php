<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Opening fallback imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Body indirect limit imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Appendix indirect limit imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Appendix continued imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Limits [30 0 R 31 0 R] /Kids [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Nums [0 << /P (stale-front-) /S /D /St 90 >> 1 << /P (Body ) /S /D /St 4 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Nums [2 << /P (App-) /S /A /St 26 >> 3 << /P (stale-back-) /S /D /St 99 >>] >>\nendobj\n"
    . "30 0 obj\n1\nendobj\n"
    . "31 0 obj\n2\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$pages = $extractor->extractLabeledPageTexts($pdf);
$summary = (new MarkerAppPreview())->openPdfSummary($pdf);
$labels = array_column($pages, 'page_label');

if ($labels !== ['1', 'Body 4', 'App-Z', 'App-AA']) {
    throw new RuntimeException('Expected indirect PageLabels limits to reject stale kid number-tree keys.');
}

if ($labels !== array_column($summary['pages'], 'page_label')) {
    throw new RuntimeException('Expected preview and text extraction page labels to match.');
}

echo '<!-- markerpdf-page-labels-indirect-limits-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels indirect numeric /Limits operands constrain kid /Nums before WordPress page-break metadata',
    'page_labels' => $labels,
    'stale_front_label_excluded' => !in_array('stale-front-90', $labels, true),
    'stale_back_label_excluded' => !in_array('stale-back-99', $labels, true),
    'preview_labels_match_text_extraction' => $labels === array_column($summary['pages'], 'page_label'),
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
