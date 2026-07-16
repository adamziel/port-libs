<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Front scalar imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Body scalar imported) Tj ET',
];
$bodyPrefixHex = strtoupper(bin2hex('Body '));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Nums [0 << /P 30 0 R /S 31 0 R /St 32 0 R >> 1 << /P 33 0 R /S 34 0 R /St 35 0 R >>] >>\nendobj\n"
    . "30 0 obj\n(Front ) % prefix scalar comment\nendobj\n"
    . "31 0 obj\n/r % style scalar comment\nendobj\n"
    . "32 0 obj\n4 % start scalar comment\nendobj\n"
    . "33 0 obj\n<{$bodyPrefixHex}> % hex prefix scalar comment\nendobj\n"
    . "34 0 obj\n/D % decimal style scalar comment\nendobj\n"
    . "35 0 obj\n7 % decimal start scalar comment\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 2);
$staleLabels = ['1', '2', 'Front i', 'Body 1'];

if ($labels !== ['Front iv', 'Body 7'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected comment-bounded PageLabels scalar operands before WordPress page metadata.');
}

if (($imagePlan['page_label'] ?? null) !== 'Body 7') {
    throw new RuntimeException('Expected preview image plan to use the comment-bounded PageLabels section.');
}

echo '<!-- markerpdf-page-labels-scalar-comment-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF comments after indirect PageLabels scalar operands are whitespace before page-break metadata',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'stale_fallback_labels_excluded' => count(array_intersect($staleLabels, $labels, $previewLabels)) === 0,
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

echo "<!-- wp:list -->\n<ul>\n";
foreach ($preview->openPdfSummary($pdf)['pages'] as $page) {
    echo '<li data-marker-page-index="' . $page['page_index'] . '" data-marker-page-label="'
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">PDF page ' . $page['page_number'] . ': '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
