<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_rows_cols_saved_result_order_xxyy(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_rows_cols_saved_result_order_page(array $lines): array
{
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
}

function markerpdf_rows_cols_saved_result_order_table(): array
{
    return [
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'coordinate_space' => 'page_image',
        'rows_cols' => [[
            'pnum' => 0,
            'tnum' => 0,
            'bbox' => [72.0, 150.0, 312.0, 230.0],
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'rows' => [
                ['row_id' => 10, 'bbox' => markerpdf_rows_cols_saved_result_order_xxyy(72.0, 150.0, 312.0, 182.0)],
                ['row_id' => 11, 'bbox' => markerpdf_rows_cols_saved_result_order_xxyy(72.0, 190.0, 312.0, 220.0)],
                ['row_id' => 99, 'bbox' => markerpdf_rows_cols_saved_result_order_xxyy(72.0, 250.0, 312.0, 268.0)],
            ],
            'cols' => [
                ['col_id' => 20, 'bbox' => markerpdf_rows_cols_saved_result_order_xxyy(72.0, 150.0, 172.0, 230.0)],
                ['col_id' => 21, 'bbox' => markerpdf_rows_cols_saved_result_order_xxyy(192.0, 150.0, 312.0, 230.0)],
                ['col_id' => 99, 'bbox' => markerpdf_rows_cols_saved_result_order_xxyy(342.0, 150.0, 362.0, 230.0)],
            ],
        ]],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale nested saved row', 'row_ids' => [99], 'col_ids' => [20]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale nested saved col', 'row_ids' => [11], 'col_ids' => [99]],
        ],
    ];
}

function markerpdf_rows_cols_saved_result_order_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-table-rows-cols-saved-result-order-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% nested saved tabled rows-cols order boundary fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_rows_cols_saved_result_order_page([
                    ['text' => 'Nested saved rows columns order boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale nested saved table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                    ['text' => 'After nested saved table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
                ]),
            ],
            [
                'metadata' => ['languages' => ['English']],
                'layout_results' => [[
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [72.0, 48.0, 560.0, 68.0]],
                        ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                        ['label' => 'Text', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
                    ],
                ]],
                'recognized_tables' => [markerpdf_rows_cols_saved_result_order_table()],
                'table_text_lines' => [['blocks' => []]],
                'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
            ],
            new MarkerSettings(['EXTRACT_IMAGES' => false])
        );
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

return [
    'defaults nested saved tabled rows_cols row and column bands to x1 x2 y1 y2 order' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_rows_cols_saved_result_order_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');
        $gridReview = $recognizer->spanningGridReview(
            $formatted['assigned_cells'][0] ?? [],
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('translated_to_table_crop', $formatted['coordinate_space_reviews'][0]['status'] ?? null);
        $t->same('rows_cols.0.rows', $localized['rows_source_alias'] ?? null);
        $t->same('rows_cols.0.cols', $localized['cols_source_alias'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
    },
    'surfaces nested saved tabled rows_cols order boundary through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_rows_cols_saved_result_order_convert();
        $metadata = $result['metadata'];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

        $t->contains('# Nested Saved Rows Columns Order Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After nested saved table.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale nested saved table line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Stale nested saved row'));
        $t->true(!str_contains($result['text'], 'Stale nested saved col'));
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same(3, $coordinateReview['translated_row_band_count'] ?? null);
        $t->same(3, $coordinateReview['translated_col_band_count'] ?? null);
        $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $gridReview['geometry_boundary_review']['row_bands'][0]['original_bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $gridReview['geometry_boundary_review']['col_bands'][1]['original_bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['col_bands'][1]['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same(false, $metadata['context']['filetype'] !== 'pdf');
    },
];
