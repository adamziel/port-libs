<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Huge roman label imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Huge alphabetic label imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Huge decimal label imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Nums [0 << /P (Roman-) /S /R /St 5000000 >> 1 << /P (Alpha-) /S /A /St 120000 >> 2 << /P (Decimal-) /S /D /St 5000000 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$summary = $preview->openPdfSummary($pdf);
$secondPlan = $preview->getPageImagePlan($pdf, 2);
$thirdPlan = $preview->getPageImagePlan($pdf, 3);
$expected = ['Roman-5000000', 'Alpha-120000', 'Decimal-5000000'];

if ($labels !== $expected || $previewLabels !== $expected || array_column($summary['pages'], 'page_label') !== $expected) {
    throw new RuntimeException('Expected generated PageLabels suffixes to stay bounded across extraction and preview metadata.');
}

if (
    max(array_map('strlen', $labels)) >= 32
    || str_contains($labels[0], str_repeat('M', 64))
    || str_contains($labels[1], str_repeat('J', 64))
    || ($secondPlan['page_label'] ?? null) !== 'Alpha-120000'
    || ($thirdPlan['page_label'] ?? null) !== 'Decimal-5000000'
) {
    throw new RuntimeException('Expected huge roman/alphabetic labels to avoid unbounded generated suffix strings.');
}

echo '<!-- markerpdf-page-labels-generated-suffix-bound-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels generated roman and alphabetic suffixes are bounded before WordPress page-break metadata',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'summary_page_labels' => array_column($summary['pages'], 'page_label'),
    'roman_suffix_decimal_fallback' => $labels[0] === 'Roman-5000000',
    'alphabetic_suffix_decimal_fallback' => $labels[1] === 'Alpha-120000',
    'decimal_start_preserved' => $labels[2] === 'Decimal-5000000',
    'generated_suffixes_bounded' => max(array_map('strlen', $labels)) < 32,
    'selected_preview_page_label' => $secondPlan['page_label'] ?? null,
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
