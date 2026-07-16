<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_center_point_boundary_page(array $lines): array
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

function markerpdf_center_point_boundary_table(bool $includeTableBbox = true): array
{
    $table = [
        'coordinate_space' => 'page_image',
        'image_bbox' => ['center' => [306.0, 396.0], 'size' => [612.0, 792.0]],
        'rows' => [
            ['row_id' => 10, 'bbox' => ['center' => [192.0, 166.0], 'extent' => [240.0, 32.0]]],
            ['row_id' => 20, 'center' => ['x' => 192.0, 'y' => 205.0], 'width' => 240.0, 'height' => 30.0],
            ['row_id' => 99, 'bbox' => ['center' => [192.0, 260.0], 'size' => [240.0, 20.0]]],
        ],
        'cols' => [
            ['col_id' => 30, 'bbox' => ['center' => [122.0, 190.0], 'extent' => ['w' => 100.0, 'h' => 80.0]]],
            ['col_id' => 40, 'center' => [252.0, 190.0], 'size' => ['width' => 120.0, 'height' => 80.0]],
            ['col_id' => 99, 'bbox' => ['center' => [350.0, 190.0], 'extent' => [20.0, 80.0]]],
        ],
        'cells' => [
            ['bbox' => ['center' => [122.0, 162.5], 'extent' => [80.0, 15.0]], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
            ['center' => ['x' => 252.0, 'y' => 162.5], 'width' => 100.0, 'height' => 15.0, 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
            ['bbox' => ['center' => [122.0, 205.0], 'size' => [80.0, 20.0]], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
            ['center' => [252.0, 205.0], 'size' => ['width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
            ['bbox' => ['center' => [122.0, 259.0], 'extent' => [80.0, 18.0]], 'text' => 'Stale center point row', 'row_ids' => [99], 'col_ids' => [30]],
            ['bbox' => ['center' => [370.0, 205.0], 'extent' => [22.0, 20.0]], 'text' => 'Stale center point column', 'row_ids' => [20], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Center point conflict',
            'bbox' => ['center' => [192.0, 190.0], 'size' => [220.0, 60.0]],
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                ['center' => [122.0, 162.5], 'extent' => [80.0, 15.0]],
                ['center' => ['x' => 252.0, 'y' => 162.5], 'width' => 100.0, 'height' => 15.0],
                ['center' => [122.0, 205.0], 'size' => [80.0, 20.0]],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];

    if ($includeTableBbox) {
        $table['bbox'] = ['center' => ['x' => 192.0, 'y' => 190.0], 'extent' => ['width' => 240.0, 'height' => 80.0]];
    }

    return $table;
}

return [
    'localizes center point and extent table geometry before assigned-cell filtering' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_center_point_boundary_table()],
            [[]]
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
        $t->same('table_crop_bbox_extent', $review['image_size_source'] ?? null);
        $t->same('bbox_center_extent_fields', $review['table_bbox_source'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $review['table_crop_size'] ?? null);
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
        $t->same('bbox_center_extent_fields', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_center_width_height_fields', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_center_size_fields', $localized['cells'][2]['source_coordinate_source'] ?? null);
        $t->same('bbox_center_size_fields', $localized['cells'][3]['source_coordinate_source'] ?? null);

        $t->same([10.0, 10.0, 230.0, 70.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same('bbox_center_size_fields', $conflict['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale center point row', $assignedTexts, true));
        $t->true(!in_array('Stale center point column', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('bbox_center_extent_fields', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_center_extent_fields', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_center_width_height_fields', $gridReview['render_cells'][1]['source_coordinate_source'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces center point table geometry through supplied WordPress conversion' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-center-point-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table center point geometry boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_center_point_boundary_page([
                        ['text' => 'Center point table geometry', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale center-point table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After center point table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_center_point_boundary_table(false)],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $cropReview = $result['metadata']['table_assigned_crop_boundary_reviews'][0] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Center Point Table Geometry', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After center point table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale center-point table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale center point row'));
            $t->true(!str_contains($result['text'], 'Stale center point column'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
            $t->same(1, $coordinateReview['translated_conflict_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same('bbox_center_extent_fields', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_center_width_height_fields', $gridReview['geometry_boundary_review']['row_bands'][1]['source_coordinate_source'] ?? null);
            $t->same('bbox_center_extent_fields', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_center_size_fields', $gridReview['render_cells'][3]['source_coordinate_source'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
