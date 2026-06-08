<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Current direct fallback page one) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Current direct fallback page two) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 30 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Catalog /Pages 99 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Nums [0 << /P (Current-) /S /D /St 4 >> 1 << /P (Now-) /S /A /St 26 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Nums [0 << /P (stale-catalog-) /S /D /St 99 >> 1 << /P (stale-app-) /S /A /St 26 >>] >>\nendobj\n"
    . "trailer\n<< /Root 7 0 R >>\n%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$textExtractorLabels = $extractor->extractPageLabels($pdf);
$summary = $preview->openPdfSummary($pdf);
$previewLabels = array_column($summary['pages'], 'page_label');
$pageObjectIds = array_column($summary['pages'], 'object_id');
$imagePlan = $preview->getPageImagePlan($pdf, 2);
$staleLabels = ['stale-catalog-99', 'stale-app-Z'];

if ($textExtractorLabels !== []) {
    throw new RuntimeException('Expected broken selected catalog Pages to keep text-extractor labels unavailable.');
}

if ($previewLabels !== ['Current-4', 'Now-Z'] || $pageObjectIds !== [3, 4]) {
    throw new RuntimeException('Expected preview fallback to keep selected trailer Root PageLabels on direct pages.');
}

if (count(array_intersect($staleLabels, $previewLabels)) !== 0 || ($imagePlan['page_label'] ?? null) !== 'Now-Z') {
    throw new RuntimeException('Expected stale catalog PageLabels to stay excluded from preview fallback metadata.');
}

echo '<!-- markerpdf-page-labels-trailer-root-page-fallback-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'selected trailer /Root PageLabels remain authoritative when marker app preview falls back to direct page objects',
    'text_extractor_page_labels_unavailable' => $textExtractorLabels === [],
    'preview_page_labels' => $previewLabels,
    'preview_page_object_ids' => $pageObjectIds,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'selected_root_labels_preserved' => $previewLabels === ['Current-4', 'Now-Z'],
    'stale_catalog_labels_excluded' => count(array_intersect($staleLabels, $previewLabels)) === 0,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['pages'] as $page) {
    echo '<li data-marker-page-index="' . $page['page_index'] . '" data-marker-page-label="'
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">PDF page ' . $page['page_number'] . ': '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
