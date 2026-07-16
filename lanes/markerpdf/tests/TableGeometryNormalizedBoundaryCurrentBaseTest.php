<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$normalizedBoundaryPdftextPage = static function (array $lines): array {
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

$normalizedRecognizedTable = static function (): array {
    return [
        'coordinate_space' => 'normalized_table',
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 1000.0, 300.0]],
            ['row_id' => 1, 'bbox' => [0.0, 400.0, 1000.0, 700.0]],
            ['row_id' => 99, 'bbox' => [0.0, 1150.0, 1000.0, 1300.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 400.0, 1000.0]],
            ['col_id' => 1, 'bbox' => [500.0, 0.0, 900.0, 1000.0]],
            ['col_id' => 99, 'bbox' => [1100.0, 0.0, 1200.0, 1000.0]],
        ],
        'cells' => [
            ['bbox' => [40.0, 50.0, 360.0, 240.0], 'text' => 'Feature'],
            ['bbox' => [520.0, 50.0, 880.0, 240.0], 'text' => 'Status'],
            ['bbox' => [40.0, 450.0, 360.0, 650.0], 'text' => 'Images'],
            ['bbox' => [520.0, 450.0, 880.0, 650.0], 'text' => 'Ready'],
            ['bbox' => [1120.0, 450.0, 1200.0, 650.0], 'text' => 'Stale normalized edge'],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide normalized OCR',
            'bbox' => [0.0, 0.0, 900.0, 650.0],
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_overlaps' => [1.0, 0.44, 0.36],
            'candidate_cell_bboxes' => [
                [40.0, 50.0, 360.0, 240.0],
                [520.0, 50.0, 880.0, 240.0],
                [40.0, 450.0, 360.0, 650.0],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
};

return [
    'normalizes supplied table recognition geometry from 1000-unit crop space before assignment' => static function (
        TestRunner $t
    ) use ($normalizedRecognizedTable): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [$normalizedRecognizedTable()],
            [['width' => 250, 'height' => 100]]
        );

        $localized = $formatted['recognized_tables'][0];
        $assigned = $formatted['assigned_cells'][0];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $grid = $recognizer->spanningGridReview($assigned, $localized['rows'], $localized['cols'], ['width' => 250, 'height' => 100]);
        $boundary = $grid['geometry_boundary_review'] ?? [];

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('normalized_to_table_crop', $review['status'] ?? null);
        $t->same(['width' => 250, 'height' => 100], $review['table_crop_size'] ?? null);
        $t->same(['x' => 0.25, 'y' => 0.1], $review['normalization_scale'] ?? null);
        $t->same(3, $review['normalized_row_band_count'] ?? null);
        $t->same(3, $review['normalized_col_band_count'] ?? null);
        $t->same(5, $review['normalized_cell_count'] ?? null);
        $t->same(1, $review['normalized_conflict_count'] ?? null);
        $t->same('table_crop', $localized['coordinate_space'] ?? null);
        $t->same([0.0, 0.0, 250.0, 30.0], $localized['rows'][0]['bbox']);
        $t->same([125.0, 0.0, 225.0, 100.0], $localized['cols'][1]['bbox']);
        $t->same([10.0, 5.0, 90.0, 24.0], $localized['cells'][0]['bbox']);
        $t->same([280.0, 45.0, 300.0, 65.0], $localized['cells'][4]['bbox']);
        $t->same([0.0, 0.0, 225.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 24.0], $conflict['candidate_cell_bboxes'][0] ?? null);
        $t->same([1], $assignedByText['Ready']['row_ids']);
        $t->same([1], $assignedByText['Ready']['col_ids']);
        $t->true(!isset($assignedByText['Stale normalized edge']));
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0]);
        $t->same(2, $boundary['active_row_band_count'] ?? null);
        $t->same(2, $boundary['active_col_band_count'] ?? null);
        $t->same(2, $boundary['excluded_band_count'] ?? null);
    },
    'surfaces normalized supplied table geometry through WordPress conversion metadata' => static function (
        TestRunner $t
    ) use ($normalizedBoundaryPdftextPage, $normalizedRecognizedTable): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-normalized-geometry-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table normalized geometry boundary supplied pipeline\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $normalizedBoundaryPdftextPage([
                        ['text' => 'Normalized table geometry review', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale normalized table line should be replaced.', 'bbox' => [72.0, 226.0, 360.0, 246.0]],
                        ['text' => 'After normalized geometry review.', 'bbox' => [72.0, 336.0, 480.0, 354.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [50.0, 200.0, 300.0, 300.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 336.0, 480.0, 354.0]],
                        ],
                    ]],
                    'recognized_tables' => [$normalizedRecognizedTable()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $boundary = $gridReview['geometry_boundary_review'] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Normalized Table Geometry Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After normalized geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale normalized table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale normalized edge'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('table_recognition_coordinate_space_boundary', $coordinateReview['review_target'] ?? null);
            $t->same('normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same(['width' => 250, 'height' => 100], $coordinateReview['table_crop_size'] ?? null);
            $t->same(['x' => 0.25, 'y' => 0.1], $coordinateReview['normalization_scale'] ?? null);
            $t->same(5, $coordinateReview['normalized_cell_count'] ?? null);
            $t->same(1, $coordinateReview['normalized_conflict_count'] ?? null);
            $t->same('table_grid_geometry_boundary', $boundary['review_target'] ?? null);
            $t->same(2, $boundary['active_row_band_count'] ?? null);
            $t->same(2, $boundary['active_col_band_count'] ?? null);
            $t->same(2, $boundary['excluded_band_count'] ?? null);
            $t->same([[50.0, 200.0, 300.0, 300.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
        } finally {
            unlink($path);
        }
    },
];
