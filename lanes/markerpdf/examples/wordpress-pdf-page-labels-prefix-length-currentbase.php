<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Long prefix cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Safe duplicate prefix imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Alphabetic prefix imported) Tj ET',
];
$oversizedPrefix = str_repeat('L', 4097);

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

$pdf .= "20 0 obj\n<< /Nums ["
    . "0 << /P 30 0 R /S /D /St 4 >> "
    . "1 << /P 30 0 R /P (Safe-) /S /D /St 8 >> "
    . "2 << /P (App-) /S /A /St 26 >>"
    . "] >>\nendobj\n"
    . "30 0 obj\n({$oversizedPrefix})\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = array_column($summary['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 2);
$expected = ['4', 'Safe-8', 'App-Z'];

if ($labels !== $expected || $previewLabels !== $expected) {
    throw new RuntimeException('Expected oversized PageLabels prefixes to be skipped before WordPress page metadata.');
}

if (($imagePlan['page_label'] ?? null) !== 'Safe-8') {
    throw new RuntimeException('Expected duplicate safe PageLabels prefix to survive preview image planning.');
}

$visibleText = strip_tags(implode("\n", array_column($pages, 'text')));
$metadata = implode("\n", $labels) . "\n" . implode("\n", $previewLabels);
$oversizedNeedle = str_repeat('L', 64);
if (str_contains($metadata, $oversizedNeedle) || str_contains($visibleText, $oversizedNeedle)) {
    throw new RuntimeException('Expected oversized PageLabels prefix to stay out of WordPress block output.');
}

echo '<!-- markerpdf-page-labels-prefix-length-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels /P prefixes over 4096 bytes are skipped before WordPress metadata',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'oversized_prefix_rejected' => !str_contains($metadata, $oversizedNeedle),
    'duplicate_safe_prefix_preserved' => in_array('Safe-8', $labels, true),
    'labels_excluded_from_visible_paragraph_text' => !str_contains($visibleText, 'Safe-8')
        && !str_contains($visibleText, 'App-Z'),
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
