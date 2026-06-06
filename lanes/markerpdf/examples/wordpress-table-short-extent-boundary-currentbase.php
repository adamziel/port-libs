<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = static function (array $lines): array {
    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => [
                            'name' => $line['font'] ?? 'Times-Roman',
                            'flags' => 0,
                            'weight' => $line['weight'] ?? 400,
                            'size' => $line['size'] ?? 12,
                        ],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'bbox' => ['x' => 72.0, 'y' => 150.0, 'w' => 240.0, 'h' => 80.0],
    'rows' => [
        ['row_id' => 10, 'bbox' => ['x' => 72.0, 'y' => 150.0, 'w' => 240.0, 'h' => 32.0]],
        ['row_id' => 20, 'x0' => 72.0, 'y0' => 190.0, 'w' => 240.0, 'h' => 30.0],
        ['row_id' => 99, 'bbox' => ['left' => 72.0, 'top' => 250.0, 'w' => 240.0, 'h' => 20.0]],
    ],
    'cols' => [
        ['col_id' => 30, 'bbox' => ['left' => 72.0, 'top' => 150.0, 'w' => 100.0, 'h' => 80.0]],
        ['col_id' => 40, 'x' => 192.0, 'y' => 150.0, 'w' => 120.0, 'h' => 80.0],
        ['col_id' => 99, 'bbox' => ['left' => 340.0, 'top' => 150.0, 'w' => 20.0, 'h' => 80.0]],
    ],
    'cells' => [
        ['bbox' => ['x' => 82.0, 'y' => 155.0, 'w' => 80.0, 'h' => 15.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
        ['bbox' => ['left' => 202.0, 'top' => 155.0, 'w' => 100.0, 'h' => 15.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
        ['bbox' => ['x0' => 82.0, 'y0' => 195.0, 'w' => 80.0, 'h' => 20.0], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
        ['cx' => 252.0, 'cy' => 205.0, 'w' => 100.0, 'h' => 20.0, 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
        ['bbox' => ['center_x' => 122.0, 'center_y' => 259.0, 'w' => 80.0, 'h' => 18.0], 'text' => 'Stale compact row', 'row_ids' => [99], 'col_ids' => [30]],
        ['bbox' => ['x_center' => 370.0, 'y_center' => 205.0, 'w' => 22.0, 'h' => 20.0], 'text' => 'Stale compact column', 'row_ids' => [20], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Compact extent conflict',
        'bbox' => ['x' => 82.0, 'y' => 155.0, 'w' => 220.0, 'h' => 60.0],
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            ['x' => 82.0, 'y' => 155.0, 'w' => 80.0, 'h' => 15.0],
            ['left' => 202.0, 'top' => 155.0, 'w' => 100.0, 'h' => 15.0],
            ['cx' => 122.0, 'cy' => 205.0, 'w' => 80.0, 'h' => 20.0],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-short-extent-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table compact extent boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Compact extent table geometry', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale compact-extent table line should be replaced.', 'bbox' => [82.0, 176.0, 330.0, 196.0]],
                ['text' => 'After compact extent table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => ['x' => 72.0, 'y' => 150.0, 'w' => 240.0, 'h' => 80.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$directReview = $direct['coordinate_space_reviews'][0] ?? [];
$directConflict = $direct['recognized_tables'][0]['ocr_grid_border_conflicts'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$rowBand = $gridReview['geometry_boundary_review']['row_bands'][0] ?? [];
$colBand = $gridReview['geometry_boundary_review']['col_bands'][0] ?? [];
$renderCells = $gridReview['render_cells'] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($directReview['table_bbox_source'] ?? null) !== 'bbox_xy_w_h_fields') {
    throw new RuntimeException('Expected direct compact x/y/w/h table bbox to define the table crop.');
}
if (($rowBand['source_coordinate_source'] ?? null) !== 'bbox_xy_w_h_fields') {
    throw new RuntimeException('Expected compact row source metadata in supplied table review.');
}
if (($colBand['source_coordinate_source'] ?? null) !== 'bbox_left_top_w_h_fields') {
    throw new RuntimeException('Expected compact column source metadata in supplied table review.');
}
if (($renderCells[3]['source_coordinate_source'] ?? null) !== 'bbox_cx_cy_w_h_fields') {
    throw new RuntimeException('Expected compact center cell source metadata in supplied table review.');
}
if (str_contains($result['text'], 'Stale compact-extent table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}
if (in_array('Stale compact row', $assignedTexts, true) || in_array('Stale compact column', $assignedTexts, true)) {
    throw new RuntimeException('Expected compact-extent cells outside active row/column bands to be filtered.');
}

echo json_encode([
    'scenario' => 'wordpress-table-short-extent-boundary-currentbase',
    'native_boundary' => 'supplied layout and table recognition geometry accept compact w/h extent field names before table-crop assignment',
    'source_truth' => [
        'marker' => 'marker.tables.table.get_table_boxes crops supplied Table layout bboxes before tabled recognition',
        'tabled' => 'tabled.get_cells and assign_rows_columns hand off Bbox-shaped table cells, rows, and columns for intersection assignment',
        'no_gpu_scope' => 'uses supplied table recognition records and does not run Surya, OCR, Python, or external PDF tools',
    ],
    'direct_table_bbox_source' => $directReview['table_bbox_source'] ?? null,
    'supplied_table_bbox_source' => $metadata['table_coordinate_space_reviews'][0]['table_bbox_source'] ?? null,
    'row_source_coordinate_source' => $rowBand['source_coordinate_source'] ?? null,
    'col_source_coordinate_source' => $colBand['source_coordinate_source'] ?? null,
    'cell_source_coordinate_sources' => array_column($renderCells, 'source_coordinate_source'),
    'cell_source_bboxes' => array_column($renderCells, 'source_cell_bbox'),
    'conflict_candidate_cell_bboxes' => $directConflict['candidate_cell_bboxes'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'short_extent_aliases_translated_to_crop' => ($rowBand['source_coordinate_source'] ?? null) === 'bbox_xy_w_h_fields'
        && ($colBand['source_coordinate_source'] ?? null) === 'bbox_left_top_w_h_fields'
        && ($renderCells[3]['source_coordinate_source'] ?? null) === 'bbox_cx_cy_w_h_fields',
    'stale_short_extent_cells_filtered' => !in_array('Stale compact row', $assignedTexts, true)
        && !in_array('Stale compact column', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale compact-extent table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
