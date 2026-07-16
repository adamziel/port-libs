<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$uploadedPdf = "%PDF-1.6\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 200 400] /CropBox 5 0 R /Rotate 90 /UserUnit 5 /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Rotate 45 >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [20 30 180 370] /UserUnit 2 >>\nendobj\n"
    . "5 0 obj\n[10 20 190 380]\nendobj\n"
    . "%%EOF\n";

$preview = new MarkerAppPreview();
$summary = $preview->openPdfSummary($uploadedPdf);
$firstPlan = $preview->getPageImagePlan($uploadedPdf, 1, 72.0);
$secondPlan = $preview->getPageImagePlan($uploadedPdf, 2, 72.0);

echo json_encode([
    'scenario' => 'wordpress-pdf-page-box-inheritance-userunit',
    'page_count' => $summary['page_count'],
    'review_pages' => [
        [
            'page_number' => $firstPlan['page_number'],
            'page_bbox' => $firstPlan['page_bbox'],
            'crop_box_source' => $firstPlan['crop_box_source'],
            'rotation' => $firstPlan['rotation'],
            'rotation_source' => $firstPlan['rotation_source'],
            'user_unit' => $firstPlan['user_unit'],
            'user_unit_source' => $firstPlan['user_unit_source'],
            'rendered_image_size' => $firstPlan['rendered_image_size'],
        ],
        [
            'page_number' => $secondPlan['page_number'],
            'page_bbox' => $secondPlan['page_bbox'],
            'rotation' => $secondPlan['rotation'],
            'user_unit' => $secondPlan['user_unit'],
            'physical_page_size' => $secondPlan['physical_page_size'],
            'rendered_image_size' => $secondPlan['rendered_image_size'],
        ],
    ],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
