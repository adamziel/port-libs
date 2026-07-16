<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Same lower extension front imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Same lower extension body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Same lower extension continuation imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Same lower extension end imported) Tj ET',
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
    . "21 0 obj\n<< /Limits [0 0] /Nums [0 << /P (Front ) /S /D /St 1 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [0 2] /Nums [1 << /P (stale-same-extend-) /S /D /St 77 >> 2 << /P (stale-same-extend-) /S /D /St 88 >>] >>\nendobj\n"
    . "23 0 obj\n<< /Limits [3 3] /Nums [3 << /P (End-) >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = array_column($summary['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 4);
$expected = ['Front 1', 'Front 2', 'Front 3', 'End-'];
$staleLabels = ['stale-same-extend-77', 'stale-same-extend-88'];

if ($labels !== $expected || $previewLabels !== $expected) {
    throw new RuntimeException('Expected same-lower PageLabels extension to preserve the earlier source-order range.');
}

if (array_intersect($staleLabels, $labels, $previewLabels) !== []) {
    throw new RuntimeException('Expected later same-lower PageLabels extension entries to stay excluded.');
}

echo '<!-- markerpdf-page-labels-same-lower-extension-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PageLabels kid /Limits with the same lower bound cannot extend a prior source-order sibling range',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'same_lower_extension_rejected' => array_intersect($staleLabels, $labels, $previewLabels) === [],
    'earlier_range_continuation_preserved' => ($labels[2] ?? null) === 'Front 3',
    'later_disjoint_kid_preserved' => ($labels[3] ?? null) === 'End-',
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
