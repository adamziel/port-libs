<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$uploadedPdf = "%PDF-1.6\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 400 300] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [-50 20 500 260] /Rotate 90 /UserUnit 2.5 >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /CropBox [450 350 600 500] /Rotate 270 /UserUnit 3 >>\nendobj\n"
    . "%%EOF\n";

$preview = new MarkerAppPreview();
$summary = $preview->openPdfSummary($uploadedPdf);
$firstPlan = $preview->getPageImagePlan($uploadedPdf, 1, 72.0);
$secondPlan = $preview->getPageImagePlan($uploadedPdf, 2, 72.0);

echo json_encode([
    'scenario' => 'wordpress-marker-app-preview-crop-boundaries',
    'page_count' => $summary['page_count'],
    'review_pages' => [
        [
            'page_number' => $firstPlan['page_number'],
            'original_crop_box' => $firstPlan['crop_box'],
            'effective_crop_box' => $firstPlan['effective_crop_box'],
            'crop_box_clipped_to_media' => $firstPlan['crop_box_clipped_to_media'],
            'crop_box_intersects_media' => $firstPlan['crop_box_intersects_media'],
            'preview_zero_area' => $firstPlan['preview_zero_area'],
            'rotation' => $firstPlan['rotation'],
            'rotation_swaps_axes' => $firstPlan['rotation_swaps_axes'],
            'user_unit' => $firstPlan['user_unit'],
            'physical_page_size' => $firstPlan['physical_page_size'],
            'rendered_image_size' => $firstPlan['rendered_image_size'],
            'boundary_notes' => $firstPlan['boundary_notes'],
        ],
        [
            'page_number' => $secondPlan['page_number'],
            'original_crop_box' => $secondPlan['crop_box'],
            'effective_crop_box' => $secondPlan['effective_crop_box'],
            'crop_box_clipped_to_media' => $secondPlan['crop_box_clipped_to_media'],
            'crop_box_intersects_media' => $secondPlan['crop_box_intersects_media'],
            'preview_zero_area' => $secondPlan['preview_zero_area'],
            'rendered_image_size' => $secondPlan['rendered_image_size'],
            'wordpress_preview_should_skip_raster' => $secondPlan['preview_zero_area'],
            'boundary_notes' => $secondPlan['boundary_notes'],
        ],
    ],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
