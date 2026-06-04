<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Escaped page imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Named page imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PieceInfo << /PageLabels 40 0 R >> /Page#4Cabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /#4Eums [0 << /#53 /#44 /#50 (Real ) /#53t 7 >> 1 << /#50 (Named-) >>] >>\nendobj\n"
    . "40 0 obj\n<< /Nums [0 << /S /D /P (stale-private-) /St 99 >> 1 << /S /D /P (stale-nested-) /St 100 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$summary = $preview->openPdfSummary($pdf);
$pageLabels = array_column($pages, 'page_label');
$previewLabels = array_column($summary['pages'], 'page_label');

echo '<!-- markerpdf-page-labels-escaped-name-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Page#4Cabels escapes resolve before nested private /PageLabels decoys',
    'page_labels' => $pageLabels,
    'preview_page_labels' => $previewLabels,
    'escaped_catalog_page_labels_resolved' => $pageLabels === ['Real 7', 'Named-'],
    'escaped_page_label_operands_resolved' => $previewLabels === ['Real 7', 'Named-'],
    'nested_private_page_labels_ignored' => !in_array('stale-private-99', $pageLabels, true)
        && !in_array('stale-nested-100', $previewLabels, true),
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
