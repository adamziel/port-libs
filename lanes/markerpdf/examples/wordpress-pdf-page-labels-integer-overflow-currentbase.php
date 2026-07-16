<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hugeInteger = '9' . str_repeat('0', 40);
$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Overflow cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Overflow body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Overflow appendix imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Limits [{$hugeInteger} {$hugeInteger}] /Nums [0 << /P (Cover-) >> {$hugeInteger} << /P (stale-overflow-) /S /D /St 99 >> 1 << /P (Body ) /S /D /St {$hugeInteger} >> 2 << /P (App-) /S /A /St 26 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$summary = $preview->openPdfSummary($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 2);

if ($labels !== ['Cover-', 'Body 1', 'App-Z'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected overlarge PageLabels integer operands to fail closed before page-break metadata.');
}

if (
    in_array('stale-overflow-99', $labels, true)
    || in_array('Body 9223372036854775807', $labels, true)
    || ($imagePlan['page_label'] ?? null) !== 'Body 1'
) {
    throw new RuntimeException('Expected overflow labels and integer-cast labels to stay out of preview metadata.');
}

echo '<!-- markerpdf-page-labels-integer-overflow-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels integer operands are bounded before number-tree ordering and start-number parsing',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'summary_page_labels' => array_column($summary['pages'], 'page_label'),
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'overlarge_nums_key_rejected' => !in_array('stale-overflow-99', $labels, true)
        && $labels[1] === 'Body 1',
    'overlarge_limits_ignored' => $labels[0] === 'Cover-' && $labels[2] === 'App-Z',
    'overlarge_start_defaulted' => $labels[1] === 'Body 1',
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
