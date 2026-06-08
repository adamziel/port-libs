<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Start value cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Start value body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Start value appendix imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Start value reset imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Nums ["
    . "0 << /P (Front ) /S /D /St 0 /St 4 >> "
    . "1 << /P (Body ) /S /r /St -2 /St 6 >> "
    . "2 << /P (App-) /S /A /St 0 /St 26 >> "
    . "3 << /P (Reset ) /S /D /St 0 >>"
    . "] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 3);
$expected = ['Front 4', 'Body vi', 'App-Z', 'Reset 1'];

if ($labels !== $expected || $previewLabels !== $expected || array_column($summary['pages'], 'page_label') !== $expected) {
    throw new RuntimeException('Expected PageLabels /St values below 1 to be skipped before later valid duplicate starts.');
}

$allMetadataLabels = array_merge($labels, $previewLabels, array_column($summary['pages'], 'page_label'));
foreach (['Front 1', 'Body i', 'App-A', 'Reset 0'] as $staleLabel) {
    if (in_array($staleLabel, $allMetadataLabels, true)) {
        throw new RuntimeException('Expected non-positive PageLabels /St operands to stay out of WordPress page-break metadata.');
    }
}

echo '<!-- markerpdf-page-labels-start-value-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels value dictionaries skip non-positive /St operands before later valid duplicate starts',
    'page_labels' => $labels,
    'summary_page_labels' => array_column($summary['pages'], 'page_label'),
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'non_positive_st_rejected' => !in_array('Front 1', $allMetadataLabels, true)
        && !in_array('Body i', $allMetadataLabels, true)
        && !in_array('App-A', $allMetadataLabels, true),
    'no_positive_st_defaults_to_one' => ($labels[3] ?? null) === 'Reset 1',
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
