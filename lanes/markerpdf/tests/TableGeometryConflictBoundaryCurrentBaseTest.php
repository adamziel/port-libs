<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$conflictBoundaryPdftextPage = static function (array $lines): array {
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
};

$conflictBoundaryRows = static fn (): array => [
    ['row_id' => 0, 'bbox' => [-8.0, -6.0, 210.0, 26.0]],
    ['row_id' => 1, 'bbox' => [0.0, 36.0, 210.0, 70.0]],
    ['row_id' => 2, 'bbox' => [0.0, 96.0, 210.0, 124.0]],
];

$conflictBoundaryCols = static fn (): array => [
    ['col_id' => 0, 'bbox' => [-6.0, 0.0, 90.0, 96.0]],
    ['col_id' => 1, 'bbox' => [96.0, 0.0, 206.0, 96.0]],
    ['col_id' => 2, 'bbox' => [230.0, 0.0, 260.0, 80.0]],
];

$conflictBoundaryAssigned = static fn (): array => [
    ['bbox' => [2.0, 4.0, 198.0, 20.0], 'text' => 'Header', 'row_ids' => [0], 'col_ids' => [0, 1]],
    ['bbox' => [6.0, 42.0, 84.0, 62.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
    ['bbox' => [102.0, 42.0, 196.0, 62.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
];

return [
    'propagates clipped table grid boundary into OCR grid-border conflict rows' => static function (
        TestRunner $t
    ) use ($conflictBoundaryRows, $conflictBoundaryCols, $conflictBoundaryAssigned): void {
        $recognizer = new TableRecognizer();
        $review = $recognizer->gridBorderConflictReview(
            [[
                'ocr_index' => 0,
                'text' => 'Wide OCR header',
                'bbox' => [-4.0, -2.0, 204.0, 64.0],
                'candidate_cell_indexes' => [0, 1, 2],
                'candidate_overlaps' => [1.0, 0.42, 0.38],
                'assigned_cell_index' => 0,
                'spans_grid_border' => true,
            ]],
            $conflictBoundaryAssigned(),
            $conflictBoundaryRows(),
            $conflictBoundaryCols(),
            ['width' => 200, 'height' => 80]
        );

        $conflict = $review[0] ?? [];
        $boundary = $conflict['geometry_boundary_review'] ?? [];

        $t->same('table_grid_geometry_boundary', $boundary['review_target'] ?? null);
        $t->same(['width' => 200, 'height' => 80], $boundary['image_size'] ?? null);
        $t->same(3, $boundary['row_band_count'] ?? null);
        $t->same(3, $boundary['col_band_count'] ?? null);
        $t->same(2, $boundary['active_row_band_count'] ?? null);
        $t->same(2, $boundary['active_col_band_count'] ?? null);
        $t->same(4, $boundary['clipped_band_count'] ?? null);
        $t->same(2, $boundary['excluded_band_count'] ?? null);
        $t->same('clipped_to_table_image', $boundary['row_bands'][0]['status'] ?? null);
        $t->same([0.0, 0.0, 200.0, 26.0], $boundary['row_bands'][0]['bounded_bbox'] ?? null);
        $t->same('excluded_outside_table_image', $boundary['row_bands'][2]['status'] ?? null);
        $t->same('clipped_to_table_image', $boundary['col_bands'][1]['status'] ?? null);
        $t->same([96.0, 0.0, 200.0, 80.0], $boundary['col_bands'][1]['bounded_bbox'] ?? null);
        $t->same('excluded_outside_table_image', $boundary['col_bands'][2]['status'] ?? null);

        $t->same('both', $conflict['grid_border_axis'] ?? null);
        $t->same(['column', 'row'], $conflict['grid_border_axes'] ?? null);
        $t->same([0, 1], $conflict['candidate_row_ids'] ?? null);
        $t->same([0, 1], $conflict['candidate_col_ids'] ?? null);
        $t->same([0.0, 0.0, 200.0, 26.0], $conflict['candidate_grid_cells'][0]['grid_bbox'] ?? null);
        $t->same([0.0, 36.0, 90.0, 70.0], $conflict['candidate_grid_cells'][1]['grid_bbox'] ?? null);
        $t->same([96.0, 36.0, 200.0, 70.0], $conflict['candidate_grid_cells'][2]['grid_bbox'] ?? null);
        $t->same([0.0, 0.0, 200.0, 26.0], $conflict['assigned_grid_cell']['grid_bbox'] ?? null);
        $t->same(2, count($conflict['assigned_grid_cell']['grid_cell_bboxes'] ?? []));
        $t->same('h-r0-c0', $conflict['assigned_grid_render_cell']['render_cell']['header_id'] ?? null);
        $t->same('Header', $conflict['assigned_grid_render_cell']['render_cell']['text'] ?? null);
    },
    'surfaces OCR grid-border crop boundary metadata through supplied WordPress conversion' => static function (
        TestRunner $t
    ) use ($conflictBoundaryPdftextPage, $conflictBoundaryRows, $conflictBoundaryCols): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-conflict-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table conflict geometry boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $conflictBoundaryPdftextPage([
                        ['text' => 'Table conflict boundary review', 'bbox' => [72.0, 48.0, 450.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale OCR conflict table text should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                        ['text' => 'After conflict geometry review.', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 450.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 272.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => $conflictBoundaryRows(),
                        'cols' => $conflictBoundaryCols(),
                    ]],
                    'table_detect_boxes' => true,
                    'table_detector_cells' => [[
                        ['bbox' => [2.0, 4.0, 198.0, 20.0], 'text' => ''],
                        ['bbox' => [6.0, 42.0, 84.0, 62.0], 'text' => ''],
                        ['bbox' => [102.0, 42.0, 196.0, 62.0], 'text' => ''],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Header', 'bbox' => [0.0, 4.0, 198.0, 20.0]],
                            ['text' => 'Images', 'bbox' => [0.0, 42.0, 198.0, 62.0]],
                            ['text' => 'Ready', 'bbox' => [0.0, 42.0, 198.0, 62.0]],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $conflict = $result['metadata']['table_ocr_grid_border_conflicts'][0][0] ?? [];
            $boundary = $conflict['geometry_boundary_review'] ?? [];

            $t->contains('# Table Conflict Boundary Review', $result['text']);
            $t->contains('| Header |       |', $result['text']);
            $t->contains('| Images | Ready |', $result['text']);
            $t->contains('After conflict geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale OCR conflict table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('table_grid_geometry_boundary', $boundary['review_target'] ?? null);
            $t->same(['width' => 200, 'height' => 80], $boundary['image_size'] ?? null);
            $t->same(4, $boundary['clipped_band_count'] ?? null);
            $t->same(2, $boundary['excluded_band_count'] ?? null);
            $t->same('source_order_grid_border', $result['metadata']['table_ocr_grid_border_conflicts'][0][0]['assignment_mode'] ?? null);
            $t->same('column', $conflict['grid_border_axis'] ?? null);
            $t->same([0.0, 36.0, 90.0, 70.0], $conflict['assigned_grid_cell']['grid_bbox'] ?? null);
            $t->same('Images', $conflict['assigned_grid_render_cell']['render_cell']['text'] ?? null);
        } finally {
            unlink($path);
        }
    },
];
