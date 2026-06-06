<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableFormatter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableFormatter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_corner_point_boundary_page(array $lines): array
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

function markerpdf_corner_point_boundary_point(float $x, float $y): array
{
    return ['x' => (string) $x, 'y' => (string) $y];
}

function markerpdf_corner_point_boundary_box(
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    string $style = 'top-left-bottom-right'
): array {
    return match ($style) {
        'upper-left-lower-right' => [
            'upper_left' => markerpdf_corner_point_boundary_point($x1, $y1),
            'lower_right' => markerpdf_corner_point_boundary_point($x2, $y2),
        ],
        'top-right-bottom-left' => [
            'top_right' => markerpdf_corner_point_boundary_point($x2, $y1),
            'bottom_left' => markerpdf_corner_point_boundary_point($x1, $y2),
        ],
        'tl-br-list' => [
            'tl' => [$x1, $y1],
            'br' => [$x2, $y2],
        ],
        'reversed' => [
            'top_left' => markerpdf_corner_point_boundary_point($x2, $y2),
            'bottom_right' => markerpdf_corner_point_boundary_point($x1, $y1),
        ],
        default => [
            'top_left' => markerpdf_corner_point_boundary_point($x1, $y1),
            'bottom_right' => markerpdf_corner_point_boundary_point($x2, $y2),
        ],
    };
}

function markerpdf_corner_point_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'image_bbox' => markerpdf_corner_point_boundary_box(0.0, 0.0, 612.0, 792.0, 'upper-left-lower-right'),
        'bbox' => markerpdf_corner_point_boundary_box(72.0, 150.0, 312.0, 230.0),
        'rows' => [
            ['row_id' => 0, 'bbox' => markerpdf_corner_point_boundary_box(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1] + markerpdf_corner_point_boundary_box(72.0, 190.0, 312.0, 220.0),
            ['row_id' => 99, 'bbox' => markerpdf_corner_point_boundary_box(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => markerpdf_corner_point_boundary_box(72.0, 150.0, 172.0, 230.0, 'upper-left-lower-right')],
            ['col_id' => 1, 'bbox' => markerpdf_corner_point_boundary_box(192.0, 150.0, 312.0, 230.0, 'tl-br-list')],
            ['col_id' => 99] + markerpdf_corner_point_boundary_box(342.0, 150.0, 362.0, 230.0, 'top-right-bottom-left'),
        ],
        'cells' => [
            ['bbox' => markerpdf_corner_point_boundary_box(82.0, 155.0, 162.0, 170.0, 'reversed'), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => markerpdf_corner_point_boundary_box(202.0, 155.0, 302.0, 170.0, 'tl-br-list'), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => markerpdf_corner_point_boundary_box(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => markerpdf_corner_point_boundary_box(202.0, 195.0, 302.0, 215.0, 'upper-left-lower-right'), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => markerpdf_corner_point_boundary_box(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale corner row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => markerpdf_corner_point_boundary_box(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale corner column', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [
            [
                'ocr_index' => 0,
                'text' => 'Wide corner OCR',
                'bbox' => markerpdf_corner_point_boundary_box(82.0, 155.0, 302.0, 170.0),
                'candidate_cell_indexes' => [0, 1],
                'candidate_cell_bboxes' => [
                    ['bbox' => markerpdf_corner_point_boundary_box(82.0, 155.0, 162.0, 170.0)],
                    ['bbox' => markerpdf_corner_point_boundary_box(202.0, 155.0, 302.0, 170.0, 'tl-br-list')],
                ],
                'assigned_cell_index' => 0,
                'spans_grid_border' => true,
            ],
        ],
    ];
}

return [
    'normalizes two-corner point bboxes before crop localization and cell assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_corner_point_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $localized = $formatted['recognized_tables'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }

        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same('bbox_top_left_bottom_right_points', $review['table_bbox_source'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same('bbox_top_left_bottom_right_points', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_top_left_bottom_right_points', $localized['rows'][1]['source_coordinate_source'] ?? null);
        $t->same('bbox_upper_left_lower_right_points', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_tl_br_points', $localized['cols'][1]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same('bbox_top_left_bottom_right_points', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same(true, $localized['cells'][0]['source_endpoint_order_normalized'] ?? null);
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same('bbox_tl_br_points', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 230.0, 20.0], $localized['ocr_grid_border_conflicts'][0]['bbox'] ?? null);
        $t->same('bbox_top_left_bottom_right_points', $localized['ocr_grid_border_conflicts'][0]['source_coordinate_source'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0]], $localized['ocr_grid_border_conflicts'][0]['candidate_cell_bboxes'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!isset($assignedByText['Stale corner row']));
        $t->true(!isset($assignedByText['Stale corner column']));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'normalizes two-corner point bboxes before table layout crop planning' => static function (TestRunner $t): void {
        $planned = (new TableFormatter())->getTableBoxes(
            [[
                'pnum' => 0,
                'bbox' => [0.0, 0.0, 612.0, 792.0],
                'layout' => [
                    'image_bbox' => markerpdf_corner_point_boundary_box(0.0, 0.0, 612.0, 792.0),
                    'bboxes' => [
                        ['label' => 'Table', 'bbox' => markerpdf_corner_point_boundary_box(72.0, 150.0, 312.0, 230.0)],
                        ['label' => 'Table', 'bbox' => markerpdf_corner_point_boundary_box(350.0, 150.0, 470.0, 230.0, 'upper-left-lower-right')],
                    ],
                ],
                'blocks' => [],
            ]],
            [['blocks' => []]],
            [['width' => 612, 'height' => 792]]
        );

        $t->same([2], $planned['table_counts']);
        $t->same([[72.0, 150.0, 312.0, 230.0], [350.0, 150.0, 470.0, 230.0]], $planned['table_bboxes']);
        $t->same([240.0, 120.0], array_map(static fn (array $image): float => $image['crop_width'], $planned['table_images']));
        $t->same([80.0, 80.0], array_map(static fn (array $image): float => $image['crop_height'], $planned['table_images']));
    },
    'surfaces two-corner point table geometry through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-corner-point-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table corner point boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_corner_point_boundary_page([
                        ['text' => 'Corner point table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale corner point table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                        ['text' => 'After corner point table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => markerpdf_corner_point_boundary_box(0.0, 0.0, 612.0, 792.0),
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                            ['label' => 'Table', 'bbox' => markerpdf_corner_point_boundary_box(72.0, 150.0, 312.0, 230.0)],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_corner_point_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

            $t->contains('# Corner Point Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After corner point table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale corner point table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale corner row'));
            $t->true(!str_contains($result['text'], 'Stale corner column'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same('bbox_top_left_bottom_right_points', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same(true, $gridReview['render_cells'][0]['source_endpoint_order_normalized'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $metadata['table_plan']['table_bboxes'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
