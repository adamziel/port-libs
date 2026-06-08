<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Leading comment cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Leading comment body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Leading comment appendix imported) Tj ET',
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

$pdf .= "20 0 obj\n% producer comment before PageLabels dictionary\n<< /Nums [0 30 0 R 1 << /P 31 0 R /S 32 0 R /St 33 0 R >> 2 << /P (End-) >>] >>\nendobj\n"
    . "30 0 obj\n% producer comment before label dictionary\n<< /P (Cover-) >>\nendobj\n"
    . "31 0 obj\n% producer comment before prefix string\n(Body )\nendobj\n"
    . "32 0 obj\n% producer comment before style name\n/D\nendobj\n"
    . "33 0 obj\n% producer comment before start integer\n8\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summaryLabels = array_column($preview->openPdfSummary($pdf)['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 2);

if ($labels !== ['Cover-', 'Body 8', 'End-'] || $summaryLabels !== $labels || ($imagePlan['page_label'] ?? null) !== 'Body 8') {
    throw new RuntimeException('Expected PageLabels leading comments to behave as whitespace before WordPress page-break metadata.');
}

$staleLabels = ['1', '2', 'Body '];
if (count(array_intersect($staleLabels, $labels, $summaryLabels)) !== 0) {
    throw new RuntimeException('Expected commented PageLabels dictionary and style operands to exclude stale physical labels.');
}

echo '<!-- markerpdf-page-labels-leading-comment-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog PageLabels leading PDF comments before indirect dictionaries and style operands',
    'page_labels' => $labels,
    'summary_page_labels' => $summaryLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'leading_comment_dictionary_resolved' => $labels[0] === 'Cover-',
    'leading_comment_style_resolved' => $labels[1] === 'Body 8',
    'physical_fallback_labels_excluded' => count(array_intersect($staleLabels, $labels, $summaryLabels)) === 0,
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
