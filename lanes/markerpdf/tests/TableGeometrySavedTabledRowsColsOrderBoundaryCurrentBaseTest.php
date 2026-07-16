<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_saved_tabled_rows_cols_order_bbox(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_saved_tabled_rows_cols_order_table(): array
{
    return [
        'pnum' => 0,
        'tnum' => 0,
        'coordinate_space' => 'page_image',
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => markerpdf_saved_tabled_rows_cols_order_bbox(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1, 'bbox' => markerpdf_saved_tabled_rows_cols_order_bbox(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => markerpdf_saved_tabled_rows_cols_order_bbox(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => markerpdf_saved_tabled_rows_cols_order_bbox(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 1, 'bbox' => markerpdf_saved_tabled_rows_cols_order_bbox(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => markerpdf_saved_tabled_rows_cols_order_bbox(342.0, 150.0, 362.0, 230.0)],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale saved row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale saved col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
    ];
}

function markerpdf_saved_tabled_rows_cols_order_page(array $lines): array
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

function markerpdf_saved_tabled_rows_cols_order_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-saved-tabled-rows-cols-order-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% saved tabled rows-cols order boundary fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_saved_tabled_rows_cols_order_page([
                    ['text' => 'Saved tabled rows columns order boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale saved tabled table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                    ['text' => 'After saved tabled table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                'recognized_tables' => [markerpdf_saved_tabled_rows_cols_order_table()],
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
    'defaults saved tabled row and column bands to x1 x2 y1 y2 order before assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_saved_tabled_rows_cols_order_table()],
            [['width' => 240, 'height' => 80]]
        );

        $coordinateReview = $formatted['coordinate_space_reviews'][0] ?? [];
        $localized = $formatted['recognized_tables'][0] ?? [];
        $assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');
        $gridReview = $recognizer->spanningGridReview(
            $formatted['assigned_cells'][0] ?? [],
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('bbox_array', $coordinateReview['table_bbox_source'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'converts saved tabled rows columns order boundary through supplied WordPress tables' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_saved_tabled_rows_cols_order_convert();
        $metadata = $result['metadata'];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
        $bandReview = $metadata['table_assigned_band_boundary_reviews'][0] ?? [];
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
        $cropRowsByText = [];
        foreach (($cropReview['cells'] ?? []) as $cell) {
            if (is_array($cell)) {
                $cropRowsByText[(string) ($cell['text'] ?? '')] = $cell;
            }
        }

        $t->contains('# Saved Tabled Rows Columns Order Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After saved tabled table.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale saved tabled table line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Stale saved row'));
        $t->true(!str_contains($result['text'], 'Stale saved col'));
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
        $t->same(3, $coordinateReview['translated_row_band_count'] ?? null);
        $t->same(3, $coordinateReview['translated_col_band_count'] ?? null);
        $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same(4, $bandReview['active_cell_count'] ?? null);
        $t->same(0, $bandReview['excluded_cell_count'] ?? null);
        $t->same('excluded_outside_table_image', $cropRowsByText['Stale saved row']['status'] ?? null);
        $t->same('excluded_outside_table_image', $cropRowsByText['Stale saved col']['status'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $gridReview['geometry_boundary_review']['row_bands'][0]['original_bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $gridReview['geometry_boundary_review']['col_bands'][0]['original_bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same(false, $metadata['context']['filetype'] !== 'pdf');
    },
];
