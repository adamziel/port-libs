<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$uploadedPdf = "%PDF-1.6\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 400 600] /Rotate 8 0 R /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [4 0 R 5 0 R 6 0 R 7 0 R] /TrimBox [10 0 R 11 0 R 12 0 R 13 0 R] /UserUnit 9 0 R >>\nendobj\n"
    . "4 0 obj\n25\nendobj\n"
    . "5 0 obj\n35\nendobj\n"
    . "6 0 obj\n325\nendobj\n"
    . "7 0 obj\n435\nendobj\n"
    . "8 0 obj\n-90\nendobj\n"
    . "9 0 obj\n1.5\nendobj\n"
    . "10 0 obj\n30\nendobj\n"
    . "11 0 obj\n45\nendobj\n"
    . "12 0 obj\n315\nendobj\n"
    . "13 0 obj\n425\nendobj\n"
    . "%%EOF\n";

$preview = new MarkerAppPreview();
$summary = $preview->openPdfSummary($uploadedPdf);
$plan = $preview->getPageImagePlan($uploadedPdf, 1, 72.0);

echo json_encode([
    'scenario' => 'wordpress-pdf-page-box-indirect-operands',
    'page_count' => $summary['page_count'],
    'page_bbox' => $plan['page_bbox'],
    'crop_box' => $plan['crop_box'],
    'trim_box' => $plan['trim_box'],
    'rotation' => $plan['rotation'],
    'rotation_source' => $plan['rotation_source'],
    'user_unit' => $plan['user_unit'],
    'display_page_size' => $plan['display_page_size'],
    'physical_page_size' => $plan['physical_page_size'],
    'rendered_image_size' => $plan['rendered_image_size'],
    'indirect_rectangle_operands_resolved' => $plan['page_bbox'] === [25.0, 35.0, 325.0, 435.0],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
