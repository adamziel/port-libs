<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_point_pair_alias_boundary_page(array $lines): array
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

function markerpdf_point_pair_alias_boundary_point(float $x, float $y): array
{
    return ['x' => (string) $x, 'y' => (string) $y];
}

function markerpdf_point_pair_alias_boundary_box(
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    string $style = 'start-end'
): array {
    return match ($style) {
        'from-to' => [
            'from' => markerpdf_point_pair_alias_boundary_point($x1, $y1),
            'to' => markerpdf_point_pair_alias_boundary_point($x2, $y2),
        ],
        'p1-p2' => [
            'p1' => [$x1, $y1],
            'p2' => [$x2, $y2],
        ],
        'point1-point2' => [
            'point1' => markerpdf_point_pair_alias_boundary_point($x1, $y1),
            'point2' => markerpdf_point_pair_alias_boundary_point($x2, $y2),
        ],
        'start-point-end-point' => [
            'start_point' => [$x1, $y1],
            'end_point' => [$x2, $y2],
        ],
        'reversed' => [
            'start' => markerpdf_point_pair_alias_boundary_point($x2, $y2),
            'end' => markerpdf_point_pair_alias_boundary_point($x1, $y1),
        ],
        default => [
            'start' => markerpdf_point_pair_alias_boundary_point($x1, $y1),
            'end' => markerpdf_point_pair_alias_boundary_point($x2, $y2),
        ],
    };
}

function markerpdf_point_pair_alias_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'image_bbox' => markerpdf_point_pair_alias_boundary_box(0.0, 0.0, 612.0, 792.0, 'start-point-end-point'),
        'bbox' => markerpdf_point_pair_alias_boundary_box(72.0, 150.0, 312.0, 230.0),
        'rows' => [
            ['row_id' => 10, 'bbox' => markerpdf_point_pair_alias_boundary_box(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 20] + markerpdf_point_pair_alias_boundary_box(72.0, 190.0, 312.0, 220.0, 'from-to'),
            ['row_id' => 99, 'bbox' => markerpdf_point_pair_alias_boundary_box(72.0, 250.0, 312.0, 268.0, 'p1-p2')],
        ],
        'cols' => [
            ['col_id' => 30, 'bbox' => markerpdf_point_pair_alias_boundary_box(72.0, 150.0, 172.0, 230.0, 'p1-p2')],
            ['col_id' => 40, 'bbox' => markerpdf_point_pair_alias_boundary_box(192.0, 150.0, 312.0, 230.0, 'point1-point2')],
            ['col_id' => 99] + markerpdf_point_pair_alias_boundary_box(340.0, 150.0, 360.0, 230.0),
        ],
        'cells' => [
            ['bbox' => markerpdf_point_pair_alias_boundary_box(82.0, 155.0, 162.0, 170.0, 'reversed'), 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
            ['bbox' => markerpdf_point_pair_alias_boundary_box(202.0, 155.0, 302.0, 170.0, 'from-to'), 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
            ['p1' => [82.0, 195.0], 'p2' => [162.0, 215.0], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
            ['bbox' => markerpdf_point_pair_alias_boundary_box(202.0, 195.0, 302.0, 215.0, 'point1-point2'), 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
            ['bbox' => markerpdf_point_pair_alias_boundary_box(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale point row', 'row_ids' => [99], 'col_ids' => [30]],
            ['bbox' => markerpdf_point_pair_alias_boundary_box(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale point column', 'row_ids' => [20], 'col_ids' => [99]],
        ],
    ];
}

return [
    'normalizes neutral point-pair aliases before table crop localization' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_point_pair_alias_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];

        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('bbox_start_end_points', $review['table_bbox_source'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same([10.0, 45.0, 90.0, 65.0], $localized['cells'][2]['bbox'] ?? null);
        $t->same('bbox_start_end_points', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_from_to_points', $localized['rows'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_p1_p2_points', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_point1_point2_points', $localized['cols'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_start_end_points', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same(true, $localized['cells'][0]['source_endpoint_order_normalized'] ?? null);
        $t->same('bbox_from_to_points', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_p1_p2_points', $localized['cells'][2]['source_coordinate_source'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale point row', $assignedTexts, true));
        $t->true(!in_array('Stale point column', $assignedTexts, true));
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
        $t->same('bbox_start_end_points', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_p1_p2_points', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_start_end_points', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
    },
    'surfaces neutral point-pair table geometry through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-point-pair-alias-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table point pair alias boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_point_pair_alias_boundary_page([
                        ['text' => 'Point pair table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale point-pair table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                        ['text' => 'After point pair table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_point_pair_alias_boundary_table()],
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

            $t->contains('# Point Pair Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After point pair table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale point-pair table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale point row'));
            $t->true(!str_contains($result['text'], 'Stale point column'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same('bbox_start_end_points', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_point1_point2_points', $gridReview['geometry_boundary_review']['col_bands'][1]['source_coordinate_source'] ?? null);
            $t->same('bbox_start_end_points', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same(true, $gridReview['render_cells'][0]['source_endpoint_order_normalized'] ?? null);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
