<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_polygon_alias_boundary_page(array $lines): array
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

function markerpdf_polygon_alias_boundary_points(float $x1, float $y1, float $x2, float $y2): array
{
    return [
        ['x' => (string) $x1, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y2],
        ['x' => (string) $x1, 'y' => (string) $y2],
    ];
}

function markerpdf_polygon_alias_boundary_flat(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $y1, $x2, $y1, $x2, $y2, $x1, $y2];
}

function markerpdf_polygon_alias_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'vertices' => markerpdf_polygon_alias_boundary_points(72.0, 150.0, 312.0, 230.0),
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'points' => markerpdf_polygon_alias_boundary_points(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1, 'vertices' => markerpdf_polygon_alias_boundary_points(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'quad' => markerpdf_polygon_alias_boundary_flat(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'quadrilateral' => markerpdf_polygon_alias_boundary_points(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 1, 'quad' => markerpdf_polygon_alias_boundary_flat(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'points' => markerpdf_polygon_alias_boundary_points(340.0, 150.0, 360.0, 230.0)],
        ],
        'cells' => [
            ['points' => markerpdf_polygon_alias_boundary_points(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['vertices' => markerpdf_polygon_alias_boundary_points(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['quad' => markerpdf_polygon_alias_boundary_flat(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['quadrilateral' => markerpdf_polygon_alias_boundary_points(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['points' => markerpdf_polygon_alias_boundary_points(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale alias row', 'row_ids' => [99], 'col_ids' => [0]],
            ['vertices' => markerpdf_polygon_alias_boundary_points(342.0, 195.0, 358.0, 215.0), 'text' => 'Stale alias col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [
            [
                'ocr_index' => 0,
                'text' => 'Wide alias OCR',
                'points' => markerpdf_polygon_alias_boundary_points(82.0, 155.0, 302.0, 170.0),
                'candidate_cell_indexes' => [0, 1],
                'candidate_cell_bboxes' => [
                    ['points' => markerpdf_polygon_alias_boundary_points(82.0, 155.0, 162.0, 170.0)],
                    ['vertices' => markerpdf_polygon_alias_boundary_points(202.0, 155.0, 302.0, 170.0)],
                ],
                'assigned_cell_index' => 0,
                'spans_grid_border' => true,
            ],
        ],
    ];
}

return [
    'normalizes supplied table polygon aliases before crop localization and cell assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_polygon_alias_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $localized = $formatted['recognized_tables'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }

        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same('polygon_vertices', $review['table_bbox_source'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same('polygon_points', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same([130.0, 45.0, 230.0, 65.0], $localized['cells'][3]['bbox'] ?? null);
        $t->same('polygon_quadrilateral', $localized['cells'][3]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 230.0, 20.0], $localized['ocr_grid_border_conflicts'][0]['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0]], $localized['ocr_grid_border_conflicts'][0]['candidate_cell_bboxes'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_values(array_column($assigned, 'text')));
        $t->true(!isset($assignedByText['Stale alias row']));
        $t->true(!isset($assignedByText['Stale alias col']));
        $t->same([1], $assignedByText['Ready']['row_ids'] ?? null);
        $t->same([1], $assignedByText['Ready']['col_ids'] ?? null);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces supplied polygon alias table geometry through WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-polygon-alias-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table polygon alias boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_polygon_alias_boundary_page([
                        ['text' => 'Polygon alias table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale polygon alias table text should be replaced.', 'bbox' => [72.0, 176.0, 500.0, 196.0]],
                        ['text' => 'After polygon alias table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_polygon_alias_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];

            $t->contains('# Polygon Alias Table Boundary', $result['text']);
            $t->contains('After polygon alias table.', $result['text']);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['Feature', 'Status'], array_column($metadata['table_spanning_grid_review'][0]['header_cells'] ?? [], 'text'));
            $t->same(['Images', 'Ready'], array_column($metadata['table_spanning_grid_review'][0]['data_cells'] ?? [], 'text'));
            $t->same([10.0, 5.0, 90.0, 20.0], $metadata['table_spanning_grid_review'][0]['render_cells'][0]['cell_bbox'] ?? null);
            $t->same('polygon_points', $metadata['table_spanning_grid_review'][0]['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same(1, $metadata['block_stats']['table'] ?? null);
            $t->same(0, $metadata['inserted_tables'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
