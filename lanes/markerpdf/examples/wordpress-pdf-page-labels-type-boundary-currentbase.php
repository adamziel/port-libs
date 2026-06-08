<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (Type boundary cover imported) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (Type boundary body imported) Tj ET',
    12 => 'BT /F1 12 Tf 72 720 Td (Type boundary appendix imported) Tj ET',
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

$pdf .= "20 0 obj\n<< /Nums ["
    . "0 << /Type /Pages /P (stale-type-) /S /D /St 77 >> "
    . "0 << /Type /PageLabel /P (Cover-) >> "
    . "1 << /Type 30 0 R /P (Body ) /S /D /St 4 >> "
    . "2 << /Type /PageLabel /P (App-) /S /A /St 26 >>"
    . "] >>\nendobj\n"
    . "30 0 obj\n/PageLabel\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$pages = $extractor->extractLabeledPageTexts($pdf);
$labels = array_column($pages, 'page_label');
$summary = $preview->openPdfSummary($pdf);
$previewLabels = $preview->pageLabels($pdf);
$imagePlan = $preview->getPageImagePlan($pdf, 3);

if ($labels !== ['Cover-', 'Body 4', 'App-Z'] || $previewLabels !== $labels) {
    throw new RuntimeException('Expected PageLabels dictionaries with /Type /PageLabel, absent /Type, or indirect /Type /PageLabel to drive WordPress page-break metadata.');
}

if (
    in_array('stale-type-77', $labels, true)
    || in_array('stale-type-78', $labels, true)
    || in_array('stale-type-77', $previewLabels, true)
    || in_array('stale-type-78', $previewLabels, true)
) {
    throw new RuntimeException('Expected PageLabels dictionaries with a wrong /Type to stay excluded from WordPress page metadata.');
}

echo '<!-- markerpdf-page-labels-type-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PageLabels value dictionaries accept absent /Type and /Type /PageLabel, but reject other /Type names',
    'page_labels' => $labels,
    'summary_page_labels' => array_column($summary['pages'], 'page_label'),
    'preview_page_labels' => $previewLabels,
    'selected_preview_page_label' => $imagePlan['page_label'] ?? null,
    'wrong_type_dictionary_rejected' => !in_array('stale-type-77', $labels, true)
        && !in_array('stale-type-78', $previewLabels, true),
    'indirect_page_label_type_preserved' => $labels[1] === 'Body 4'
        && ($summary['pages'][1]['page_label'] ?? null) === 'Body 4',
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
