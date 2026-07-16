<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Empty hex PageLabels fallback first page) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Empty literal PageLabels fallback second page) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 99 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Nums ["
    . "0 << /P <> /S /D /St 4 /P (stale-) >> "
    . "1 << /P () /S /D /St 8 /P (stale-literal-) >>"
    . "] >>\nendobj\n"
    . "%%EOF\n";

$preview = new MarkerAppPreview();
$extractor = new PdfTextExtractor();
$summary = $preview->openPdfSummary($pdf);
$labels = array_column($summary['pages'], 'page_label');
$expectedLabels = ['4', '8'];
$encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$textExtractorLabels = $extractor->extractPageLabels($pdf);

if ($labels !== $expectedLabels) {
    throw new RuntimeException('Expected MarkerAppPreview fallback to keep empty hex PageLabels prefixes usable before stale duplicates.');
}

if ($textExtractorLabels !== []) {
    throw new RuntimeException('Expected text-extractor PageLabels to stay unavailable when the selected catalog /Pages reference is missing.');
}

foreach (['stale-4', 'stale-literal-8'] as $leakedLabel) {
    if (str_contains($encodedSummary, $leakedLabel)) {
        throw new RuntimeException('Expected WordPress preview metadata to exclude stale duplicate PageLabels prefixes.');
    }
}

echo '<!-- markerpdf-page-labels-empty-hex-prefix-fallback-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'marker_app preview fallback accepts an empty hex PDF text string PageLabels prefix before stale duplicate prefixes',
    'text_extractor_page_labels_unavailable' => $textExtractorLabels === [],
    'preview_page_labels' => $labels,
    'empty_hex_prefix_kept' => $labels[0] === '4',
    'empty_literal_prefix_kept' => $labels[1] === '8',
    'stale_duplicate_prefixes_excluded' => !str_contains($encodedSummary, 'stale-4')
        && !str_contains($encodedSummary, 'stale-literal-8'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($summary['pages'] as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>Preview fallback page ' . $page['page_number'] . ': '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
