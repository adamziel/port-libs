<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_saved_image_bbox_boundary_table(): array
{
    return [
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
}

return [
    'derives saved tabled result table image size from top-level bbox extent' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_saved_image_bbox_boundary_table()],
            [[]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $cropBoundary = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('table_crop_bbox_extent', $review['image_size_source'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $review['table_crop_size'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $localized['image_bbox'] ?? null);
        $t->same('table_crop', $localized['coordinate_space'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $localized['table_crop_bbox'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale page row', $assignedTexts, true));
        $t->true(!in_array('Stale page edge', $assignedTexts, true));
        $t->same(6, $cropBoundary['cell_count'] ?? null);
        $t->same(4, $cropBoundary['active_cell_count'] ?? null);
        $t->same(2, $cropBoundary['excluded_cell_count'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
];
