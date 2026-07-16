<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Duplicate kids cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Duplicate kids body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Duplicate kids appendix imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Limits [0 2] /Kids [99 0 R] /Kids [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [0 1] /Nums [0 << /P (Front ) /S /r /St 4 >> 1 << /P (Body ) /S /D /St 8 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [2 2] /Nums [2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "40 0 obj\n<< /Limits [0 2] /Nums [0 << /P (stale-kids-) /S /D /St 99 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = array_column($summary['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 3);
$expected = ['Front iv', 'Body 8', 'App-Z'];

if ($labels !== $expected || $previewLabels !== $expected) {
    throw new RuntimeException('Expected duplicate PageLabels Kids keys to keep the first usable child array.');
}

if (in_array('stale-kids-99', $labels, true) || in_array('stale-kids-99', $previewLabels, true)) {
    throw new RuntimeException('Expected stale duplicate PageLabels Kids fallback labels to stay excluded.');
}

echo '<!-- markerpdf-page-labels-duplicate-kids-key-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels duplicate /Kids keys skip unusable child arrays before WordPress page-break metadata',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'first_usable_duplicate_kids_key_preserved' => $labels === $expected,
    'unusable_first_kids_array_skipped' => !in_array('1', $labels, true),
    'stale_duplicate_kids_labels_excluded' => !in_array('stale-kids-99', $labels, true),
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
