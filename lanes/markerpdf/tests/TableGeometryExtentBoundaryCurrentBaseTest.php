<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$extentBoundaryPdftextPage = static function (array $lines): array {
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

$extentRecognizedTable = static function (): array {
    return [
        'coordinate_space' => 'page_image',
        'bbox' => ['x' => '72', 'y' => '150', 'width' => '240', 'height' => '80'],
        'rows' => [
            ['row_id' => 0, 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 32.0]],
            ['row_id' => 1, 'bbox' => ['x' => 72.0, 'y' => 190.0, 'width' => 240.0, 'height' => 30.0]],
            ['row_id' => 99, 'bbox' => ['x' => 72.0, 'y' => 250.0, 'width' => 240.0, 'height' => 20.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 100.0, 'height' => 80.0]],
            ['col_id' => 1, 'bbox' => ['x' => 192.0, 'y' => 150.0, 'width' => 120.0, 'height' => 80.0]],
            ['col_id' => 99, 'bbox' => ['x' => 340.0, 'y' => 150.0, 'width' => 20.0, 'height' => 80.0]],
        ],
        'cells' => [
            ['bbox' => ['x' => 82.0, 'y' => 155.0, 'width' => 80.0, 'height' => 15.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => ['x' => 202.0, 'y' => 155.0, 'width' => 100.0, 'height' => 15.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => ['x' => 82.0, 'y' => 195.0, 'width' => 80.0, 'height' => 20.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => ['x' => 202.0, 'y' => 195.0, 'width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => ['x' => 342.0, 'y' => 195.0, 'width' => 16.0, 'height' => 20.0], 'text' => 'Stale extent edge', 'row_ids' => [1], 'col_ids' => [99]],
            ['bbox' => ['x' => 82.0, 'y' => 252.0, 'width' => 80.0, 'height' => 18.0], 'text' => 'Stale extent row', 'row_ids' => [99], 'col_ids' => [0]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Extent conflict',
            'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 230.0, 'height' => 65.0],
            'candidate_cell_indexes' => [0, 1],
            'candidate_cell_bboxes' => [
                ['x' => 82.0, 'y' => 155.0, 'width' => 80.0, 'height' => 15.0],
                ['x' => 202.0, 'y' => 155.0, 'width' => 100.0, 'height' => 15.0],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
};

return [
    'translates page-image extent table geometry into crop-local assigned cells' => static function (
        TestRunner $t
    ) use ($extentRecognizedTable): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [$extentRecognizedTable()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $grid = $recognizer->spanningGridReview($assigned, $localized['rows'] ?? [], $localized['cols'] ?? [], ['width' => 240, 'height' => 80]);

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('page_image', $localized['cells'][0]['source_coordinate_space'] ?? null);
        $t->same([0.0, 0.0, 230.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($assigned, 'text'));
        $t->true(!isset($assignedByText['Stale extent edge']));
        $t->true(!isset($assignedByText['Stale extent row']));
        $t->same([10.0, 5.0, 90.0, 20.0], $assignedByText['Feature']['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $assignedByText['Feature']['source_bbox'] ?? null);
        $t->same(4, $bandReview['cell_count'] ?? null);
        $t->same(4, $bandReview['active_cell_count'] ?? null);
        $t->same(0, $bandReview['excluded_cell_count'] ?? null);
        $t->same(2, $grid['geometry_boundary_review']['excluded_band_count'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces page-image extent table geometry through supplied WordPress conversion metadata' => static function (
        TestRunner $t
    ) use ($extentBoundaryPdftextPage, $extentRecognizedTable): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-extent-geometry-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table extent geometry boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $extentBoundaryPdftextPage([
                        ['text' => 'Extent table geometry review', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale extent table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After extent geometry review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 80.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [$extentRecognizedTable()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Extent Table Geometry Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After extent geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale extent table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale extent edge'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
            $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
            $t->same(1, $coordinateReview['translated_conflict_count'] ?? null);
            $t->same(4, $result['metadata']['table_assigned_band_boundary_reviews'][0]['active_cell_count'] ?? null);
            $t->same(0, $result['metadata']['table_assigned_band_boundary_reviews'][0]['excluded_cell_count'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same('page_image', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
        } finally {
            unlink($path);
        }
    },
];
