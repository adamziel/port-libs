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
    'bbox' => ['cx' => 192.0, 'cy' => 190.0, 'width' => 240.0, 'height' => 80.0],
    'rows' => [
        ['row_id' => 10, 'bbox' => ['cx' => 192.0, 'cy' => 166.0, 'width' => 240.0, 'height' => 32.0]],
        ['row_id' => 20, 'center_x' => 192.0, 'center_y' => 205.0, 'width' => 240.0, 'height' => 30.0],
        ['row_id' => 99, 'bbox' => ['x_center' => 192.0, 'y_center' => 260.0, 'width' => 240.0, 'height' => 20.0]],
    ],
    'cols' => [
        ['col_id' => 30, 'bbox' => ['cx' => 122.0, 'cy' => 190.0, 'width' => 100.0, 'height' => 80.0]],
        ['col_id' => 40, 'center_x' => 252.0, 'center_y' => 190.0, 'width' => 120.0, 'height' => 80.0],
        ['col_id' => 99, 'bbox' => ['x_center' => 350.0, 'y_center' => 190.0, 'width' => 20.0, 'height' => 80.0]],
    ],
    'cells' => [
        ['bbox' => ['cx' => 122.0, 'cy' => 162.5, 'width' => 80.0, 'height' => 15.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
        ['bbox' => ['center_x' => 252.0, 'center_y' => 162.5, 'width' => 100.0, 'height' => 15.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
        ['bbox' => ['x_center' => 122.0, 'y_center' => 205.0, 'width' => 80.0, 'height' => 20.0], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
        ['center_x' => 252.0, 'center_y' => 205.0, 'width' => 100.0, 'height' => 20.0, 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
        ['bbox' => ['cx' => 122.0, 'cy' => 259.0, 'width' => 80.0, 'height' => 18.0], 'text' => 'Stale center row', 'row_ids' => [99], 'col_ids' => [30]],
        ['bbox' => ['center_x' => 370.0, 'center_y' => 205.0, 'width' => 22.0, 'height' => 20.0], 'text' => 'Stale center column', 'row_ids' => [20], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Center extent conflict',
        'bbox' => ['cx' => 192.0, 'cy' => 190.0, 'width' => 220.0, 'height' => 60.0],
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            ['cx' => 122.0, 'cy' => 162.5, 'width' => 80.0, 'height' => 15.0],
            ['center_x' => 252.0, 'center_y' => 162.5, 'width' => 100.0, 'height' => 15.0],
            ['x_center' => 122.0, 'y_center' => 205.0, 'width' => 80.0, 'height' => 20.0],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-center-extent-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table center extent boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Center extent table geometry', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale center-extent table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After center extent table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
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
$renderCells = $gridReview['render_cells'] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($directReview['table_bbox_source'] ?? null) !== 'bbox_cx_cy_width_height_fields') {
    throw new RuntimeException('Expected direct table bbox center-size aliases to define the table crop.');
}
if (($rowBand['source_coordinate_source'] ?? null) !== 'bbox_cx_cy_width_height_fields') {
    throw new RuntimeException('Expected row center-size source metadata in supplied table review.');
}
if (($renderCells[1]['source_coordinate_source'] ?? null) !== 'bbox_center_x_center_y_width_height_fields') {
    throw new RuntimeException('Expected cell center_x/center_y source metadata in supplied table review.');
}
if (str_contains($result['text'], 'Stale center-extent table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}
if (in_array('Stale center row', $assignedTexts, true) || in_array('Stale center column', $assignedTexts, true)) {
    throw new RuntimeException('Expected center-size cells outside active row/column bands to be filtered.');
}

echo json_encode([
    'scenario' => 'wordpress-table-center-extent-boundary-currentbase',
    'native_boundary' => 'supplied table rows, columns, cells, and grid-border conflict boxes accept center-plus-size field names before table-crop assignment',
    'source_truth' => [
        'upstream' => 'tabled_pdf SpanTableCell and row/column bands use Bbox endpoint geometry with width, height, and center helpers during assignment',
        'no_gpu_scope' => 'uses supplied table recognition records and does not run Surya, OCR, Python, or external PDF tools',
    ],
    'direct_table_bbox_source' => $directReview['table_bbox_source'] ?? null,
    'supplied_table_bbox_source' => $metadata['table_coordinate_space_reviews'][0]['table_bbox_source'] ?? null,
    'row_source_coordinate_source' => $rowBand['source_coordinate_source'] ?? null,
    'cell_source_coordinate_sources' => array_column($renderCells, 'source_coordinate_source'),
    'cell_source_bboxes' => array_column($renderCells, 'source_cell_bbox'),
    'conflict_candidate_cell_bboxes' => $directConflict['candidate_cell_bboxes'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'center_extent_aliases_translated_to_crop' => ($rowBand['source_coordinate_source'] ?? null) === 'bbox_cx_cy_width_height_fields'
        && ($renderCells[1]['source_coordinate_source'] ?? null) === 'bbox_center_x_center_y_width_height_fields',
    'stale_center_extent_cells_filtered' => !in_array('Stale center row', $assignedTexts, true)
        && !in_array('Stale center column', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale center-extent table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
