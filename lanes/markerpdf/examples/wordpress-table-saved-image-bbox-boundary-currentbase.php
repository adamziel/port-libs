<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$recognizedTable = [
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'coordinate_space' => 'page_image',
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
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale page row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale page edge', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$formatted = (new TableRecognizer())->formatRecognizedTables([$recognizedTable], [[]]);
$review = $formatted['coordinate_space_reviews'][0] ?? [];
$assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');
$markdown = $formatted['markdown_tables'][0] ?? '';

if (($review['image_size_source'] ?? null) !== 'table_crop_bbox_extent') {
    throw new RuntimeException('Expected saved tabled bbox extent to supply the table crop image size.');
}
if (($review['table_crop_size'] ?? null) !== ['width' => 240, 'height' => 80]) {
    throw new RuntimeException('Expected saved table bbox [72,150,312,230] to resolve as a 240x80 crop.');
}
if (in_array('Stale page row', $assignedTexts, true) || in_array('Stale page edge', $assignedTexts, true)) {
    throw new RuntimeException('Expected page-image cells outside the saved table bbox extent to stay out of WordPress output.');
}
if (!str_contains($markdown, '| Feature | Status |') || !str_contains($markdown, '| Images  | Ready  |')) {
    throw new RuntimeException('Expected supplied saved table to produce the WordPress table Markdown.');
}

echo json_encode([
    'scenario' => 'wordpress-table-saved-image-bbox-boundary-currentbase',
    'native_boundary' => 'saved tabled-pdf result JSON can omit a separate crop width/height sidecar when top-level bbox gives the table crop extent',
    'source_truth' => [
        'upstream' => 'tabled.extract.extract_tables records each table bbox plus image_bbox in saved JSON; rows, columns, and cells remain in page-image coordinates for saved high-resolution crops',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [[
        'blockName' => 'core/table',
        'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>',
    ]],
    'coordinate_review' => $review,
    'assigned_table_texts' => $assignedTexts,
    'markdown_table' => $markdown,
    'page_image_bbox_preserved' => $formatted['recognized_tables'][0]['image_bbox'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
