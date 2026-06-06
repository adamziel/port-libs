<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_coordinate_order_boundary_page(array $lines): array
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

function markerpdf_coordinate_order_boundary_bbox(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_coordinate_order_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'bbox_order' => 'x1_x2_y1_y2',
        'bbox' => markerpdf_coordinate_order_boundary_bbox(72.0, 150.0, 312.0, 230.0),
        'rows' => [
            ['row_id' => 0, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 1, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(342.0, 150.0, 362.0, 230.0)],
        ],
        'cells' => [
            ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale ordered row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => markerpdf_coordinate_order_boundary_bbox(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale ordered column', 'row_ids' => [1], 'col_ids' => [99]],
        ],
    ];
}

return [
    'honors explicit x1 x2 y1 y2 coordinate order before table crop localization' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_coordinate_order_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $review['table_bbox_source'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same(false, $localized['cells'][0]['source_endpoint_order_normalized'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale ordered row', $assignedTexts, true));
        $t->true(!in_array('Stale ordered column', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces explicit coordinate order table geometry through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-coordinate-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table coordinate order boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_coordinate_order_boundary_page([
                        ['text' => 'Coordinate order table boundary', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale coordinate-order table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After coordinate order table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_coordinate_order_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $cropReview = $result['metadata']['table_assigned_crop_boundary_reviews'][0] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Coordinate Order Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After coordinate order table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale coordinate-order table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale ordered row'));
            $t->true(!str_contains($result['text'], 'Stale ordered column'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
