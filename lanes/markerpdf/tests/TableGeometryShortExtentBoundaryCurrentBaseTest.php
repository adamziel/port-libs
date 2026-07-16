<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_short_extent_boundary_page(array $lines): array
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

function markerpdf_short_extent_boundary_table(): array
{
    return [
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
}

return [
    'localizes compact w h table geometry aliases before assigned-cell filtering' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_short_extent_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('bbox_xy_w_h_fields', $review['table_bbox_source'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([202.0, 155.0, 302.0, 170.0], $localized['cells'][1]['source_bbox'] ?? null);
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same('bbox_xy_w_h_fields', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_left_top_w_h_fields', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_x0_y0_w_h_fields', $localized['cells'][2]['source_coordinate_source'] ?? null);
        $t->same('bbox_cx_cy_w_h_fields', $localized['cells'][3]['source_coordinate_source'] ?? null);
        $t->same(false, $localized['cells'][0]['source_endpoint_order_normalized'] ?? null);

        $t->same([10.0, 5.0, 230.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same('bbox_xy_w_h_fields', $conflict['source_coordinate_source'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale compact row', $assignedTexts, true));
        $t->true(!in_array('Stale compact column', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('bbox_xy_w_h_fields', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_left_top_w_h_fields', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_xy_w_h_fields', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_cx_cy_w_h_fields', $gridReview['render_cells'][3]['source_coordinate_source'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces compact w h table geometry through supplied WordPress conversion' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-short-extent-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table compact extent boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_short_extent_boundary_page([
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
                    'recognized_tables' => [markerpdf_short_extent_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $cropReview = $result['metadata']['table_assigned_crop_boundary_reviews'][0] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Compact Extent Table Geometry', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After compact extent table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale compact-extent table line should be replaced.'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
            $t->same(1, $coordinateReview['translated_conflict_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->true(!in_array('Stale compact row', $assignedTexts, true));
            $t->true(!in_array('Stale compact column', $assignedTexts, true));
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same('bbox_xy_w_h_fields', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_left_top_w_h_fields', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_cx_cy_w_h_fields', $gridReview['render_cells'][3]['source_coordinate_source'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
