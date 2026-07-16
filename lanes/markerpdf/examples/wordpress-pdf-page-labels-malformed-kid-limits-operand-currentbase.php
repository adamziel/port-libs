<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Malformed kid limit cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Malformed kid limit body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Malformed kid limit appendix imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Limits [0 2] /Kids [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [30 0 R 31 0 R] /Nums [0 << /P (Stale-) /S /D /St 77 >> 1 << /P (StaleBody-) /S /D /St 88 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [0 2] /Nums [0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 4 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "30 0 obj\n0 /Private\nendobj\n"
    . "31 0 obj\n1\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = array_column($summary['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 3);
$expected = ['Cover-', 'Body 4', 'App-Z'];
$staleLabels = ['Stale-77', 'StaleBody-88'];

if ($labels !== $expected || $previewLabels !== $expected) {
    throw new RuntimeException('Expected malformed PageLabels kid Limits operands to be rejected before WordPress page-break metadata.');
}

if (array_intersect($staleLabels, $labels, $previewLabels) !== [] || ($imagePlan['page_label'] ?? null) !== 'App-Z') {
    throw new RuntimeException('Expected stale PageLabels child labels to stay excluded from preview metadata.');
}

echo '<!-- markerpdf-page-labels-malformed-kid-limits-operand-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels child nodes with malformed indirect /Limits scalar operands cannot claim stale WordPress page labels',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'malformed_kid_limits_rejected' => !in_array('Stale-77', $labels, true)
        && !in_array('Stale-77', $previewLabels, true),
    'later_valid_kid_preserved' => ($labels[1] ?? null) === 'Body 4',
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
