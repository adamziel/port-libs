<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Direct kid front imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Direct kid body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Direct kid appendix imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Direct kid back imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Limits [0 3] /Kids [<< /Limits [0 1] /Nums [0 << /P (Front ) /S /r /St 2 >> 1 << /P (Body ) /S /D /St 7 >>] >> 22 0 R << /Private << /Nums [0 << /P (stale-private-) /S /D /St 99 >>] >> >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [2 3] /Nums [2 << /P (App-) /S /A /St 26 >> 3 << /P (Back-) /S /D /St 9 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$summaryLabels = array_column($preview->openPdfSummary($pdf)['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 4);
$staleLabels = ['1', '2', 'stale-private-99'];

if ($labels !== ['Front ii', 'Body 7', 'App-Z', 'Back-9'] || $previewLabels !== $labels || $summaryLabels !== $labels) {
    throw new RuntimeException('Expected direct PageLabels kid dictionaries to align text import and preview metadata.');
}

if (count(array_intersect($staleLabels, $labels, $previewLabels, $summaryLabels)) !== 0 || ($imagePlan['page_label'] ?? null) !== 'Back-9') {
    throw new RuntimeException('Expected fallback and nested private PageLabels labels to stay excluded.');
}

echo '<!-- markerpdf-page-labels-direct-kid-dictionary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels direct child dictionaries are accepted alongside indirect kids before WordPress page-break metadata',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'summary_page_labels' => $summaryLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'direct_kid_labels_applied' => $labels[0] === 'Front ii' && $labels[1] === 'Body 7',
    'stale_nested_private_labels_excluded' => count(array_intersect($staleLabels, $labels, $previewLabels, $summaryLabels)) === 0,
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
