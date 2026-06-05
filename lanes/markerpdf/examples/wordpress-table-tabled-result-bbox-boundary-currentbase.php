<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'rows' => [
        ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
        ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
        ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
        ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
        ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
    ],
    'cells' => [
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale page edge', 'row_ids' => [1], 'col_ids' => [99]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale page row', 'row_ids' => [99], 'col_ids' => [0]],
    ],
];

$recognizer = new TableRecognizer();
$formatted = $recognizer->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);
$coordinateReview = $formatted['coordinate_space_reviews'][0] ?? [];
$localized = $formatted['recognized_tables'][0] ?? [];
$assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');
$markdown = $formatted['markdown_tables'][0] ?? '';

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected saved tabled result bbox to localize page-image table geometry.');
}
if (in_array('Stale page edge', $assignedTexts, true) || in_array('Stale page row', $assignedTexts, true)) {
    throw new RuntimeException('Expected off-crop saved tabled cells to be filtered before WordPress output.');
}
if (str_contains($markdown, 'Stale page edge') || str_contains($markdown, 'Stale page row')) {
    throw new RuntimeException('Expected stale page-image table cells to stay out of Markdown.');
}

echo json_encode([
    'scenario' => 'wordpress-table-tabled-result-bbox-boundary-currentbase',
    'native_boundary' => 'saved tabled-pdf result bbox is accepted as the page-image table crop before assigned SpanTableCell geometry reaches WordPress table output',
    'source_truth' => [
        'upstream_marker' => 'sddai/markerPDF marker/tables/table.py crops high-resolution page images before tabled assignment and Markdown formatting',
        'upstream_tabled' => 'tabled-pdf 0.1.4 extract.py saves each table result with cells, rows, cols, bbox, image_bbox, pnum, and tnum',
        'no_gpu_scope' => 'uses supplied saved table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
    ],
    'coordinate_review' => [
        'status' => $coordinateReview['status'] ?? null,
        'table_bbox' => $coordinateReview['table_bbox'] ?? null,
        'translation' => $coordinateReview['translation'] ?? null,
        'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    ],
    'localized_first_cell_bbox' => $localized['cells'][0]['bbox'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'saved_tabled_bbox_translated' => ($coordinateReview['status'] ?? null) === 'translated_to_table_crop',
    'offcrop_saved_result_cells_filtered_from_assignment' => !in_array('Stale page edge', $assignedTexts, true)
        && !in_array('Stale page row', $assignedTexts, true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
