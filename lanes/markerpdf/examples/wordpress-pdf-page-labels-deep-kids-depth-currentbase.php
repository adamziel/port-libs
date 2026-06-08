<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Deep PageLabels cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Deep PageLabels body imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R /PageLabels 200 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$depth = 105;
for ($index = 0; $index < $depth; $index++) {
    $objectNumber = 20 + $index;
    $kidObjectNumber = $objectNumber + 1;
    $pdf .= "{$objectNumber} 0 obj\n<< /Limits [0 1] /Kids [{$kidObjectNumber} 0 R] >>\nendobj\n";
}

$leafObjectNumber = 20 + $depth;
$pdf .= "{$leafObjectNumber} 0 obj\n<< /Limits [0 1] /Nums [0 << /P (too-deep-) /S /D /St 77 >> 1 << /P (too-deep-body-) /S /D /St 88 >>] >>\nendobj\n"
    . "200 0 obj\n<< /Nums [0 << /P (Cover-) >> 1 << /P (Body ) /S /D /St 4 >>] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$summaryLabels = array_column($summary['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 2);
$expected = ['Cover-', 'Body 4'];
$staleLabels = ['too-deep-77', 'too-deep-body-88', 'too-deep-78', '1', '2'];

if ($labels !== $expected || $summaryLabels !== $expected) {
    throw new RuntimeException('Expected deep PageLabels Kids chains to fail closed before WordPress page labels.');
}

if (count(array_intersect($staleLabels, $labels, $summaryLabels)) !== 0 || ($imagePlan['page_label'] ?? null) !== 'Body 4') {
    throw new RuntimeException('Expected stale too-deep PageLabels labels to stay excluded.');
}

echo '<!-- markerpdf-page-labels-deep-kids-depth-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels /Kids traversal is depth-bounded before WordPress page-break metadata',
    'page_labels' => $labels,
    'summary_page_labels' => $summaryLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'too_deep_kids_chain_rejected' => count(array_intersect($staleLabels, $labels, $summaryLabels)) === 0,
    'later_shallow_page_labels_preserved' => $labels === $expected && $summaryLabels === $expected,
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
