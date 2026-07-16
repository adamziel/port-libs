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
    'bbox' => ['xmin' => 72.0, 'ymin' => 150.0, 'xmax' => 312.0, 'ymax' => 230.0],
    'rows' => [
        ['row_id' => 10, 'bbox' => ['xmin' => 72.0, 'ymin' => 150.0, 'xmax' => 312.0, 'ymax' => 182.0]],
        ['row_id' => 20, 'x0' => 72.0, 'y0' => 190.0, 'x1' => 312.0, 'y1' => 220.0],
        ['row_id' => 99, 'bbox' => ['x_min' => 72.0, 'y_min' => 250.0, 'x_max' => 312.0, 'y_max' => 270.0]],
    ],
    'cols' => [
        ['col_id' => 30, 'bbox' => ['x_min' => 72.0, 'y_min' => 150.0, 'x_max' => 172.0, 'y_max' => 230.0]],
        ['col_id' => 40, 'xmin' => 192.0, 'ymin' => 150.0, 'xmax' => 312.0, 'ymax' => 230.0],
        ['col_id' => 99, 'bbox' => ['x0' => 340.0, 'y0' => 150.0, 'x1' => 360.0, 'y1' => 230.0]],
    ],
    'cells' => [
        ['bbox' => ['xmin' => 82.0, 'ymin' => 155.0, 'xmax' => 162.0, 'ymax' => 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
        ['bbox' => ['x_min' => 202.0, 'y_min' => 155.0, 'x_max' => 302.0, 'y_max' => 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
        ['bbox' => ['x0' => 82.0, 'y0' => 195.0, 'x1' => 162.0, 'y1' => 215.0], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
        ['x0' => 202.0, 'y0' => 195.0, 'x1' => 302.0, 'y1' => 215.0, 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
        ['bbox' => ['xmin' => 82.0, 'ymin' => 250.0, 'xmax' => 162.0, 'ymax' => 268.0], 'text' => 'Stale alias row', 'row_ids' => [99], 'col_ids' => [30]],
        ['bbox' => ['x_min' => 360.0, 'y_min' => 195.0, 'x_max' => 382.0, 'y_max' => 215.0], 'text' => 'Stale alias column', 'row_ids' => [20], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Endpoint alias conflict',
        'bbox' => ['xmin' => 82.0, 'ymin' => 155.0, 'xmax' => 302.0, 'ymax' => 215.0],
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            ['xmin' => 82.0, 'ymin' => 155.0, 'xmax' => 162.0, 'ymax' => 170.0],
            ['x_min' => 202.0, 'y_min' => 155.0, 'x_max' => 302.0, 'y_max' => 170.0],
            ['x0' => 82.0, 'y0' => 195.0, 'x1' => 162.0, 'y1' => 215.0],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-endpoint-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table endpoint alias boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Endpoint alias table geometry', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale endpoint-alias table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After endpoint alias table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
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
$renderCell = $gridReview['render_cells'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($directReview['table_bbox_source'] ?? null) !== 'bbox_xmin_ymin_xmax_ymax_fields') {
    throw new RuntimeException('Expected direct table bbox endpoint aliases to define the table crop.');
}
if (($rowBand['source_coordinate_source'] ?? null) !== 'bbox_xmin_ymin_xmax_ymax_fields') {
    throw new RuntimeException('Expected row endpoint alias source metadata in supplied table review.');
}
if (($colBand['source_coordinate_source'] ?? null) !== 'bbox_x_min_y_min_x_max_y_max_fields') {
    throw new RuntimeException('Expected column endpoint alias source metadata in supplied table review.');
}
if (($renderCell['source_coordinate_source'] ?? null) !== 'bbox_xmin_ymin_xmax_ymax_fields') {
    throw new RuntimeException('Expected cell endpoint alias source metadata in supplied table review.');
}
if (str_contains($result['text'], 'Stale endpoint-alias table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}
if (in_array('Stale alias row', $assignedTexts, true) || in_array('Stale alias column', $assignedTexts, true)) {
    throw new RuntimeException('Expected endpoint-alias cells outside active row/column bands to be filtered.');
}

echo json_encode([
    'scenario' => 'wordpress-table-endpoint-alias-boundary-currentbase',
    'native_boundary' => 'supplied table rows, columns, cells, and grid-border conflict boxes accept endpoint alias field names before table-crop assignment',
    'source_truth' => [
        'upstream' => 'tabled_pdf stores table, row, column, and SpanTableCell geometry as Bbox endpoints; assignment consumes those row and column boxes before Markdown formatting',
        'no_gpu_scope' => 'uses supplied table recognition records and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'direct_table_bbox_source' => $directReview['table_bbox_source'] ?? null,
    'supplied_table_bbox_source' => $metadata['table_coordinate_space_reviews'][0]['table_bbox_source'] ?? null,
    'row_source_coordinate_source' => $rowBand['source_coordinate_source'] ?? null,
    'col_source_coordinate_source' => $colBand['source_coordinate_source'] ?? null,
    'cell_source_coordinate_source' => $renderCell['source_coordinate_source'] ?? null,
    'cell_source_bbox' => $renderCell['source_cell_bbox'] ?? null,
    'conflict_candidate_cell_bboxes' => $directConflict['candidate_cell_bboxes'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'endpoint_aliases_translated_to_crop' => ($rowBand['source_coordinate_source'] ?? null) === 'bbox_xmin_ymin_xmax_ymax_fields'
        && ($renderCell['source_coordinate_source'] ?? null) === 'bbox_xmin_ymin_xmax_ymax_fields',
    'stale_endpoint_alias_cells_filtered' => !in_array('Stale alias row', $assignedTexts, true)
        && !in_array('Stale alias column', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale endpoint-alias table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
