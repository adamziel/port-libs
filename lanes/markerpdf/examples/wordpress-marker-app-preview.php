<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$uploadedPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 594] >>\nendobj\n"
    . "%%EOF\n";

$preview = new MarkerAppPreview();
$summary = $preview->openPdfSummary($uploadedPdf);
$plan = $preview->getPageImagePlan($uploadedPdf, 2, 96.0);

echo json_encode([
    'scenario' => 'wordpress-marker-app-preview',
    'page_count' => $summary['page_count'],
    'selected_page' => $plan['page_number'],
    'pypdfium_page_indices' => $plan['pypdfium_page_indices'],
    'render_scale' => $plan['scale'],
    'rendered_image_size' => $plan['rendered_image_size'],
    'color_mode' => $plan['color_mode'],
    'annotation_mode' => $plan['annotation_mode'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
