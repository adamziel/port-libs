<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Overlapping kid front imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Overlapping kid body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Overlapping kid appendix imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Overlapping kid end imported) Tj ET',
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
    . "21 0 obj\n<< /Limits [0 2] /Nums [0 << /P (Front ) /S /r /St 4 >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [1 1] /Nums [1 << /P (stale-overlap-) /S /D /St 77 >>] >>\nendobj\n"
    . "23 0 obj\n<< /Limits [3 3] /Nums [3 << /P (End-) >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = array_column($summary['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 4);
$expected = ['Front iv', 'Front v', 'App-Z', 'End-'];

if ($labels !== $expected || $previewLabels !== $expected) {
    throw new RuntimeException('Expected overlapping PageLabels kid Limits to preserve the earlier contributing range.');
}

if (in_array('stale-overlap-77', $labels, true) || in_array('stale-overlap-77', $previewLabels, true)) {
    throw new RuntimeException('Expected overlapping stale PageLabels kid range to stay excluded.');
}

echo '<!-- markerpdf-page-labels-overlapping-kid-limits-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'overlapping PageLabels kid /Limits cannot relabel inside an earlier contributing kid range',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'overlapping_kid_limits_rejected' => !in_array('stale-overlap-77', $labels, true),
    'earlier_kid_range_preserved' => ($labels[1] ?? null) === 'Front v',
    'later_non_overlapping_kid_preserved' => ($labels[3] ?? null) === 'End-',
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
