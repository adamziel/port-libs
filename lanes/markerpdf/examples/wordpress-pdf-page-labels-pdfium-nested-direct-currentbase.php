<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Nested label cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Nested label second imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Nested label appendix imported) Tj ET',
    13 => 'BT /F1 12 Tf 72 720 Td (Nested label repeated imported) Tj ET',
    14 => 'BT /F1 12 Tf 72 720 Td (Nested label roman tail imported) Tj ET',
    15 => 'BT /F1 12 Tf 72 720 Td (Nested label lowercase tail imported) Tj ET',
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R 5 0 R 6 0 R 7 0 R 8 0 R] /Count 6 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 13 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 14 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 15 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Kids ["
    . "<< /Limits [0 5] /Kids ["
    . "<< /Limits [0 4] /Kids ["
    . "<< /Limits [0 3] /Nums [0 << /S /R >> 2 << /P (abc) /S /A /St 26 >>] >> "
    . "<< /Limits [4 4] /Nums [4 << /S /r >>] >>"
    . "] >> "
    . "<< /Limits [5 5] /Nums [5 << /S /a /St 26 >>] >>"
    . "] >>"
    . "] >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$previewLabels = $preview->pageLabels($pdf);
$summaryLabels = array_column($preview->openPdfSummary($pdf)['pages'], 'page_label');
$imagePlan = $preview->getPageImagePlan($pdf, 4);
$expectedLabels = ['I', 'II', 'abcZ', 'abcAA', 'i', 'z'];
$staleLabels = ['3', '4', 'abcA', 'abcB', 'abcAB'];

if ($labels !== $expectedLabels || $previewLabels !== $expectedLabels || $summaryLabels !== $expectedLabels) {
    throw new RuntimeException('Expected PDFium-style nested direct PageLabels trees to align import and preview metadata.');
}

if (count(array_intersect($staleLabels, $labels, $previewLabels, $summaryLabels)) !== 0 || ($imagePlan['page_label'] ?? null) !== 'abcAA') {
    throw new RuntimeException('Expected fallback and stale alphabetic PageLabels labels to stay excluded.');
}

echo '<!-- markerpdf-page-labels-pdfium-nested-direct ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDFium-style nested direct /PageLabels /Kids trees preserve /Limits, roman labels, and repeated-letter alphabetic labels before WordPress page-break metadata',
    'page_labels' => $labels,
    'preview_page_labels' => $previewLabels,
    'summary_page_labels' => $summaryLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'nested_direct_kids_preserved' => $labels[2] === 'abcZ' && $labels[3] === 'abcAA',
    'repeated_alphabetic_label_preserved' => $labels[3] === 'abcAA' && $labels[5] === 'z',
    'roman_label_sections_preserved' => $labels[0] === 'I' && $labels[1] === 'II' && $labels[4] === 'i',
    'stale_fallback_labels_excluded' => count(array_intersect($staleLabels, $labels, $previewLabels, $summaryLabels)) === 0,
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
