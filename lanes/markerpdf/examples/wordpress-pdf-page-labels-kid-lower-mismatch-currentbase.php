<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Lower mismatch cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Lower mismatch body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Lower mismatch appendix imported) Tj ET',
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
    . "21 0 obj\n<< /Limits [0 2] /Nums [2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [0 1] /Nums [0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 4 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$summaryLabels = array_column($preview->openPdfSummary($pdf)['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 3);
$expected = ['Cover-', 'Body 4', 'App-Z'];
$staleLabels = ['1', '2', 'Body 5', 'App-Y'];

if ($labels !== $expected || $previewLabels !== $expected || $summaryLabels !== $expected) {
    throw new RuntimeException('Expected precise later PageLabels kid to label the lower pages.');
}

if (($imagePlan['page_label'] ?? null) !== 'App-Z' || count(array_intersect($staleLabels, $labels, $previewLabels, $summaryLabels)) !== 0) {
    throw new RuntimeException('Expected broad mismatched kid not to claim lower page-label ranges.');
}

echo '<!-- markerpdf-page-labels-kid-lower-mismatch-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PageLabels kids only reserve the range they actually contribute when their first key is above the declared lower Limit',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'summary_page_labels' => $summaryLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'late_precise_lower_kid_preserved' => $labels[0] === 'Cover-' && $labels[1] === 'Body 4',
    'broad_mismatched_kid_did_not_claim_lower_range' => !in_array('1', $labels, true) && !in_array('2', $labels, true),
    'appendix_label_preserved' => ($imagePlan['page_label'] ?? null) === 'App-Z',
    'labels_excluded_from_visible_paragraph_text' => !str_contains(strip_tags(implode("\n", array_column($pages, 'text'))), 'Cover-')
        && !str_contains(strip_tags(implode("\n", array_column($pages, 'text'))), 'Body 4')
        && !str_contains(strip_tags(implode("\n", array_column($pages, 'text'))), 'App-Z'),
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
