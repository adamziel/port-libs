<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_generic_wrapper_boundary_page(array $lines): array
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

function markerpdf_generic_wrapper_boundary_points(float $x1, float $y1, float $x2, float $y2): array
{
    return [
        ['x' => (string) $x1, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y2],
        ['x' => (string) $x1, 'y' => (string) $y2],
    ];
}

function markerpdf_generic_wrapper_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'geometry' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'geometry' => ['left' => 72.0, 'top' => 150.0, 'right' => 312.0, 'bottom' => 182.0]],
            ['row_id' => 1, 'coordinates' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'geometry' => [72.0, 250.0, 312.0, 268.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'coordinates' => ['x' => 72.0, 'y' => 150.0, 'width' => 100.0, 'height' => 80.0]],
            ['col_id' => 1, 'geometry' => ['center' => ['x' => 252.0, 'y' => 190.0], 'size' => ['width' => 120.0, 'height' => 80.0]]],
            ['col_id' => 99, 'coordinates' => [342.0, 150.0, 362.0, 230.0]],
        ],
        'cells' => [
            ['geometry' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['coordinates' => ['left' => 202.0, 'top' => 155.0, 'right' => 302.0, 'bottom' => 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['geometry' => markerpdf_generic_wrapper_boundary_points(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['coordinates' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['geometry' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale wrapper row', 'row_ids' => [99], 'col_ids' => [0]],
            ['coordinates' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale wrapper col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide generic wrapper OCR',
            'geometry' => markerpdf_generic_wrapper_boundary_points(82.0, 155.0, 302.0, 215.0),
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                ['geometry' => [82.0, 155.0, 162.0, 170.0]],
                ['coordinates' => ['left' => 202.0, 'top' => 155.0, 'right' => 302.0, 'bottom' => 170.0]],
                ['geometry' => markerpdf_generic_wrapper_boundary_points(82.0, 195.0, 162.0, 215.0)],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

function markerpdf_generic_wrapper_boundary_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-table-generic-wrapper-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% table generic wrapper geometry boundary current-base fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_generic_wrapper_boundary_page([
                    ['text' => 'Generic wrapper table geometry boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale generic-wrapper table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                    ['text' => 'After generic wrapper table review.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                'recognized_tables' => [markerpdf_generic_wrapper_boundary_table()],
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
    'localizes generic geometry and coordinates wrappers before table assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_generic_wrapper_boundary_table()],
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

        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('geometry.bbox_array', $review['table_bbox_source'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same('geometry.bbox_left_top_right_bottom_fields', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('coordinates.bbox_array', $localized['rows'][1]['source_coordinate_source'] ?? null);
        $t->same('coordinates.bbox_xy_width_height_fields', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same('geometry.bbox_center_size_fields', $localized['cols'][1]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same([10.0, 45.0, 90.0, 65.0], $localized['cells'][2]['bbox'] ?? null);
        $t->same([130.0, 45.0, 230.0, 65.0], $localized['cells'][3]['bbox'] ?? null);
        $t->same('geometry.bbox_array', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same('coordinates.bbox_left_top_right_bottom_fields', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same('geometry.bbox_polygon_points', $localized['cells'][2]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 230.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same('geometry.bbox_polygon_points', $conflict['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale wrapper row', $assignedTexts, true));
        $t->true(!in_array('Stale wrapper col', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('geometry.bbox_left_top_right_bottom_fields', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('coordinates.bbox_xy_width_height_fields', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('geometry.bbox_polygon_points', $gridReview['render_cells'][2]['source_coordinate_source'] ?? null);
        $t->same([82.0, 195.0, 162.0, 215.0], $gridReview['render_cells'][2]['source_cell_bbox'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces generic wrapper geometry through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_generic_wrapper_boundary_convert();
        $metadata = $result['metadata'];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

        $t->contains('# Generic Wrapper Table Geometry Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After generic wrapper table review.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale generic-wrapper table line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Stale wrapper row'));
        $t->true(!str_contains($result['text'], 'Stale wrapper col'));
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same('geometry.bbox_polygon_points', $gridReview['render_cells'][2]['source_coordinate_source'] ?? null);
        $t->same([82.0, 195.0, 162.0, 215.0], $gridReview['render_cells'][2]['source_cell_bbox'] ?? null);
        $t->same(false, $metadata['context']['filetype'] !== 'pdf');
    },
];
