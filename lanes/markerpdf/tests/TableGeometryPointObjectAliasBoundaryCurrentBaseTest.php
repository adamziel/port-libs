<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_point_object_alias_boundary_page(array $lines): array
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

function markerpdf_point_object_alias_boundary_point(float $x, float $y, string $style = 'left-top'): array
{
    return match ($style) {
        'right-bottom' => ['bottom' => (string) $y, 'right' => (string) $x],
        'right-top' => ['top' => (string) $y, 'right' => (string) $x],
        'left-bottom' => ['bottom' => (string) $y, 'left' => (string) $x],
        'x0-y0' => ['y0' => (string) $y, 'x0' => (string) $x],
        'x1-y1' => ['y1' => (string) $y, 'x1' => (string) $x],
        'xmin-ymin' => ['ymin' => (string) $y, 'xmin' => (string) $x],
        'xmax-ymax' => ['ymax' => (string) $y, 'xmax' => (string) $x],
        'x-min-y-min' => ['y_min' => (string) $y, 'x_min' => (string) $x],
        'x-max-y-max' => ['y_max' => (string) $y, 'x_max' => (string) $x],
        default => ['top' => (string) $y, 'left' => (string) $x],
    };
}

function markerpdf_point_object_alias_boundary_box(
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    string $style = 'top-left-bottom-right'
): array {
    return match ($style) {
        'upper-left-lower-right' => [
            'upper_left' => markerpdf_point_object_alias_boundary_point($x1, $y1),
            'lower_right' => markerpdf_point_object_alias_boundary_point($x2, $y2, 'right-bottom'),
        ],
        'top-right-bottom-left' => [
            'top_right' => markerpdf_point_object_alias_boundary_point($x2, $y1, 'right-top'),
            'bottom_left' => markerpdf_point_object_alias_boundary_point($x1, $y2, 'left-bottom'),
        ],
        'start-end-x0-x1' => [
            'start' => markerpdf_point_object_alias_boundary_point($x1, $y1, 'x0-y0'),
            'end' => markerpdf_point_object_alias_boundary_point($x2, $y2, 'x1-y1'),
        ],
        'p1-p2-min-max' => [
            'p1' => markerpdf_point_object_alias_boundary_point($x1, $y1, 'xmin-ymin'),
            'p2' => markerpdf_point_object_alias_boundary_point($x2, $y2, 'xmax-ymax'),
        ],
        'point1-point2-underscored' => [
            'point1' => markerpdf_point_object_alias_boundary_point($x1, $y1, 'x-min-y-min'),
            'point2' => markerpdf_point_object_alias_boundary_point($x2, $y2, 'x-max-y-max'),
        ],
        'reversed' => [
            'top_left' => markerpdf_point_object_alias_boundary_point($x2, $y2, 'right-bottom'),
            'bottom_right' => markerpdf_point_object_alias_boundary_point($x1, $y1),
        ],
        default => [
            'top_left' => markerpdf_point_object_alias_boundary_point($x1, $y1),
            'bottom_right' => markerpdf_point_object_alias_boundary_point($x2, $y2, 'right-bottom'),
        ],
    };
}

function markerpdf_point_object_alias_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'image_bbox' => markerpdf_point_object_alias_boundary_box(0.0, 0.0, 612.0, 792.0, 'upper-left-lower-right'),
        'bbox' => markerpdf_point_object_alias_boundary_box(72.0, 150.0, 312.0, 230.0),
        'rows' => [
            ['row_id' => 10, 'bbox' => markerpdf_point_object_alias_boundary_box(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 20] + markerpdf_point_object_alias_boundary_box(72.0, 190.0, 312.0, 220.0, 'upper-left-lower-right'),
            ['row_id' => 99, 'bbox' => markerpdf_point_object_alias_boundary_box(72.0, 250.0, 312.0, 268.0, 'top-right-bottom-left')],
        ],
        'cols' => [
            ['col_id' => 30, 'bbox' => markerpdf_point_object_alias_boundary_box(72.0, 150.0, 172.0, 230.0, 'p1-p2-min-max')],
            ['col_id' => 40, 'bbox' => markerpdf_point_object_alias_boundary_box(192.0, 150.0, 312.0, 230.0, 'start-end-x0-x1')],
            ['col_id' => 99] + markerpdf_point_object_alias_boundary_box(340.0, 150.0, 360.0, 230.0, 'point1-point2-underscored'),
        ],
        'cells' => [
            ['bbox' => markerpdf_point_object_alias_boundary_box(82.0, 155.0, 162.0, 170.0, 'reversed'), 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
            ['bbox' => markerpdf_point_object_alias_boundary_box(202.0, 155.0, 302.0, 170.0, 'start-end-x0-x1'), 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
            ['bbox' => markerpdf_point_object_alias_boundary_box(82.0, 195.0, 162.0, 215.0, 'p1-p2-min-max'), 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
            ['bbox' => markerpdf_point_object_alias_boundary_box(202.0, 195.0, 302.0, 215.0, 'point1-point2-underscored'), 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
            ['bbox' => markerpdf_point_object_alias_boundary_box(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale point-object row', 'row_ids' => [99], 'col_ids' => [30]],
            ['bbox' => markerpdf_point_object_alias_boundary_box(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale point-object column', 'row_ids' => [20], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Point object conflict',
            'bbox' => markerpdf_point_object_alias_boundary_box(82.0, 155.0, 302.0, 170.0),
            'candidate_cell_indexes' => [0, 1],
            'candidate_cell_bboxes' => [
                ['bbox' => markerpdf_point_object_alias_boundary_box(82.0, 155.0, 162.0, 170.0, 'reversed')],
                ['bbox' => markerpdf_point_object_alias_boundary_box(202.0, 155.0, 302.0, 170.0, 'start-end-x0-x1')],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

return [
    'normalizes named point-object aliases before table crop localization' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_point_object_alias_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];

        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('bbox_top_left_bottom_right_points', $review['table_bbox_source'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same([10.0, 45.0, 90.0, 65.0], $localized['cells'][2]['bbox'] ?? null);
        $t->same([10.0, 5.0, 230.0, 20.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0]], $conflict['candidate_cell_bboxes'] ?? null);

        $t->same('bbox_top_left_bottom_right_points', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_upper_left_lower_right_points', $localized['rows'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_p1_p2_points', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_start_end_points', $localized['cols'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_top_left_bottom_right_points', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same(true, $localized['cells'][0]['source_endpoint_order_normalized'] ?? null);
        $t->same('bbox_start_end_points', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_p1_p2_points', $localized['cells'][2]['source_coordinate_source'] ?? null);
        $t->same('bbox_point1_point2_points', $localized['cells'][3]['source_coordinate_source'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale point-object row', $assignedTexts, true));
        $t->true(!in_array('Stale point-object column', $assignedTexts, true));
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
        $t->same('bbox_top_left_bottom_right_points', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
        $t->same(true, $gridReview['render_cells'][0]['source_endpoint_order_normalized'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
    },
    'surfaces named point-object aliases through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-point-object-alias-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table point object alias boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_point_object_alias_boundary_page([
                        ['text' => 'Point object alias table geometry', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale point-object table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After point object alias table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => markerpdf_point_object_alias_boundary_box(0.0, 0.0, 612.0, 792.0),
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                            ['label' => 'Table', 'bbox' => markerpdf_point_object_alias_boundary_box(72.0, 150.0, 312.0, 230.0)],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_point_object_alias_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
            $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];

            $t->contains('# Point Object Alias Table Geometry', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After point object alias table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale point-object table line should be replaced.'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->true(!in_array('Stale point-object row', $assignedTexts, true));
            $t->true(!in_array('Stale point-object column', $assignedTexts, true));
            $t->same('bbox_top_left_bottom_right_points', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same(true, $gridReview['render_cells'][0]['source_endpoint_order_normalized'] ?? null);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same('pdf', $metadata['context']['filetype'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
