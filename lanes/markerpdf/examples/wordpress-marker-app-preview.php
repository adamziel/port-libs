<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$uploadedPdf = "%PDF-1.6\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /CropBox [36 36 576 756] /Rotate 0 /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 500] /CropBox [10 20 260 420] /Rotate 90 /UserUnit 2 >>\nendobj\n"
    . "20 0 obj\n<< /Kids [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [0 0] /Nums [0 << /P (front-) /S /r /St 2 >> 1 << /P (stale-) /S /D /St 99 >>] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [1 1] /Nums [1 << /P (Body ) /S /D /St 7 >>] >>\nendobj\n"
    . "%%EOF\n";

$preview = new MarkerAppPreview();
$summary = $preview->openPdfSummary($uploadedPdf);
$plan = $preview->getPageImagePlan($uploadedPdf, 2, 96.0);
$pageLabels = array_column($summary['pages'], 'page_label');

if ($pageLabels !== ['front-ii', 'Body 7']) {
    throw new RuntimeException('Expected page-label number-tree /Limits to bound WordPress preview labels.');
}

if (($plan['page_label'] ?? null) !== 'Body 7') {
    throw new RuntimeException('Expected selected page image plan to carry the bounded page label.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-app-preview',
    'native_boundary' => 'marker app preview honors catalog /PageLabels number-tree /Limits before page-image metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_count' => $summary['page_count'],
    'page_labels' => $pageLabels,
    'selected_page' => $plan['page_number'],
    'selected_page_label' => $plan['page_label'] ?? null,
    'pypdfium_page_indices' => $plan['pypdfium_page_indices'],
    'render_scale' => $plan['scale'],
    'page_bbox' => $plan['page_bbox'],
    'media_box' => $plan['media_box'],
    'crop_box' => $plan['crop_box'],
    'rotation' => $plan['rotation'],
    'user_unit' => $plan['user_unit'],
    'display_page_size' => $plan['display_page_size'],
    'physical_page_size' => $plan['physical_page_size'],
    'rendered_image_size' => $plan['rendered_image_size'],
    'color_mode' => $plan['color_mode'],
    'annotation_mode' => $plan['annotation_mode'],
    'ignored_stale_page_label_key' => !in_array('stale-99', $pageLabels, true),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
