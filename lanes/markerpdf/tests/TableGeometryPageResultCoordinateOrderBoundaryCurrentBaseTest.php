<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

function markerpdf_page_result_coordinate_order_page(array $lines): array
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

function markerpdf_page_result_coordinate_order_bbox(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_page_result_coordinate_order_extract_page_result(): array
{
    return [
        'pnum' => 0,
        'coordinate_space' => 'page_image',
        'bbox_order' => 'x1_x2_y1_y2',
        'rows_bbox_order' => 'x1_x2_y1_y2',
        'cols_bbox_order' => 'x1_x2_y1_y2',
        'cells_bbox_order' => 'x1_x2_y1_y2',
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'cells' => [[
            ['bbox' => markerpdf_page_result_coordinate_order_bbox(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_coordinate_order_bbox(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => markerpdf_page_result_coordinate_order_bbox(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_coordinate_order_bbox(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => markerpdf_page_result_coordinate_order_bbox(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale page-result row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_coordinate_order_bbox(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale page-result col', 'row_ids' => [1], 'col_ids' => [99]],
        ]],
        'rows_cols' => [[
            'rows' => [
                ['row_id' => 0, 'bbox' => markerpdf_page_result_coordinate_order_bbox(72.0, 150.0, 312.0, 182.0)],
                ['row_id' => 1, 'bbox' => markerpdf_page_result_coordinate_order_bbox(72.0, 190.0, 312.0, 220.0)],
                ['row_id' => 99, 'bbox' => markerpdf_page_result_coordinate_order_bbox(72.0, 250.0, 312.0, 268.0)],
            ],
            'cols' => [
                ['col_id' => 0, 'bbox' => markerpdf_page_result_coordinate_order_bbox(72.0, 150.0, 172.0, 230.0)],
                ['col_id' => 1, 'bbox' => markerpdf_page_result_coordinate_order_bbox(192.0, 150.0, 312.0, 230.0)],
                ['col_id' => 99, 'bbox' => markerpdf_page_result_coordinate_order_bbox(342.0, 150.0, 362.0, 230.0)],
            ],
        ]],
        'bboxes' => [
            ['bbox' => markerpdf_page_result_coordinate_order_bbox(72.0, 150.0, 312.0, 230.0)],
        ],
    ];
}

return [
    'propagates page result bbox order metadata before table geometry localization' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-result-coordinate-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page result coordinate order table boundary fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_page_result_coordinate_order_page([
                        ['text' => 'Page result coordinate order boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale page-result coordinate-order table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After page result coordinate order table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_page_result_coordinate_order_extract_page_result()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
            $assignedByText = [];
            foreach (($metadata['table_assigned_cells'][0] ?? []) as $cell) {
                $assignedByText[$cell['text'] ?? ''] = $cell;
            }
            $assignedTexts = array_keys($assignedByText);

            $t->contains('# Page Result Coordinate Order Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After page result coordinate order table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale page-result coordinate-order table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale page-result row'));
            $t->true(!str_contains($result['text'], 'Stale page-result col'));
            $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
            $t->same(1, $pageResultReview['table_count'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same('page_image', $coordinateReview['source_coordinate_spaces']['rows'] ?? null);
            $t->same(3, $coordinateReview['translated_row_band_count'] ?? null);
            $t->same(3, $coordinateReview['translated_col_band_count'] ?? null);
            $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
            $t->same([0.0, 0.0, 240.0, 32.0], $gridReview['geometry_boundary_review']['row_bands'][0]['original_bbox'] ?? null);
            $t->same([0.0, 0.0, 100.0, 80.0], $gridReview['geometry_boundary_review']['col_bands'][0]['original_bbox'] ?? null);
            $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same([10.0, 5.0, 90.0, 20.0], $assignedByText['Feature']['bbox'] ?? null);
            $t->same([202.0, 195.0, 302.0, 215.0], $assignedByText['Ready']['source_bbox'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(6, $cropReview['cell_count'] ?? null);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
