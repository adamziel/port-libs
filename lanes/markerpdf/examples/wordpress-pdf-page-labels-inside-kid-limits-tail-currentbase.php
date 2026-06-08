<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Inside limits front imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Inside limits body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Inside limits continuation imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Inside limits tail imported) Tj ET',
    14 => 'BT /F1 12 Tf 72 720 Td (Inside limits end imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R] /Count 5 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 13 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 14 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Limits [0 4] /Kids [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [0 2] /Nums [0 << /P (Front ) /S /r /St 4 >> 1 << /P (Body ) /S /D /St 8 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [1 4] /Nums [1 << /P (stale-inside-) /S /D /St 70 >> 2 << /P (stale-inside-) /S /D /St 80 >> 3 << /P (Tail ) /S /D /St 4 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$summaryLabels = array_column($preview->openPdfSummary($pdf)['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 5);
$expected = ['Front iv', 'Body 8', 'Body 9', 'Tail 4', 'Tail 5'];

if ($labels !== $expected || $previewLabels !== $expected || $summaryLabels !== $expected) {
    throw new RuntimeException('Expected inside-overlap PageLabels kid range to preserve only the non-overlapping tail.');
}

$allMetadataLabels = array_merge($labels, $previewLabels, $summaryLabels);
foreach (['stale-inside-70', 'stale-inside-80', 'Tail 3'] as $staleLabel) {
    if (in_array($staleLabel, $allMetadataLabels, true)) {
        throw new RuntimeException('Expected stale inside-overlap PageLabels entries to stay excluded.');
    }
}

echo '<!-- markerpdf-page-labels-inside-kid-limits-tail-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PageLabels kid /Limits that start inside an earlier claim suppress stale overlap while preserving later tail entries',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'summary_page_labels' => $summaryLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'inside_overlap_rejected' => !in_array('stale-inside-70', $labels, true)
        && !in_array('stale-inside-80', $labels, true),
    'tail_after_claim_preserved' => ($labels[3] ?? null) === 'Tail 4'
        && ($imagePlan['page_label'] ?? null) === 'Tail 5',
    'labels_excluded_from_visible_paragraph_text' => !str_contains(strip_tags(implode("\n", array_column($pages, 'text'))), 'Front iv')
        && !str_contains(strip_tags(implode("\n", array_column($pages, 'text'))), 'Tail 5'),
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
