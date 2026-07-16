<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_record_coordinate_boundary_page(array $lines): array
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

function markerpdf_record_coordinate_boundary_table(): array
{
    return [
        'table_bbox' => [72.0, 150.0, 312.0, 230.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0], 'coordinate_space' => 'page_image'],
            ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0], 'coordinate_space' => 'page_image'],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0], 'coordinate_space' => 'page_image'],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0], 'coordinate_space' => 'page_image'],
            ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0], 'coordinate_space' => 'page_image'],
            ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0], 'coordinate_space' => 'page_image'],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1], 'coordinate_space' => 'page_image'],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1], 'coordinate_space' => 'page_image'],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale row', 'row_ids' => [99], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale column', 'row_ids' => [1], 'col_ids' => [99], 'coordinate_space' => 'page_image'],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide page-image OCR',
            'bbox' => [72.0, 150.0, 312.0, 215.0],
            'candidate_cell_indexes' => [0, 1],
            'candidate_cell_bboxes' => [
                [82.0, 155.0, 162.0, 170.0],
                [202.0, 155.0, 302.0, 170.0],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
            'coordinate_space' => 'page_image',
        ]],
    ];
}

return [
    'localizes per-record page-image table geometry before assigned-cell crop filtering' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_record_coordinate_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $cropBoundary = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $bandBoundary = $formatted['assigned_band_boundary_reviews'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];

        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );
        $gridByPosition = [];
        foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same(['page_image' => 3], $review['source_record_coordinate_spaces']['rows'] ?? null);
        $t->same(['page_image' => 6], $review['source_record_coordinate_spaces']['cells'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('page_image', $localized['cells'][0]['source_coordinate_space'] ?? null);
        $t->same('table_crop', $localized['cells'][0]['coordinate_space'] ?? null);
        $t->same([0.0, 0.0, 240.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same('table_crop', $conflict['coordinate_space'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale row', $assignedTexts, true));
        $t->true(!in_array('Stale column', $assignedTexts, true));
        $t->same(6, $cropBoundary['cell_count'] ?? null);
        $t->same(4, $cropBoundary['active_cell_count'] ?? null);
        $t->same(2, $cropBoundary['excluded_cell_count'] ?? null);
        $t->same(4, $bandBoundary['active_cell_count'] ?? null);
        $t->same(0, $bandBoundary['excluded_cell_count'] ?? null);
        $t->same([0.0, 0.0, 100.0, 32.0], $gridByPosition['0:0']['grid_bbox'] ?? null);
        $t->same([120.0, 40.0, 240.0, 70.0], $gridByPosition['1:1']['grid_bbox'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces per-record page-image table geometry through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-record-coordinate-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table record coordinate boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_record_coordinate_boundary_page([
                        ['text' => 'Record coordinate table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale record-coordinate table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After record coordinate geometry review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_record_coordinate_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];

            $t->contains('# Record Coordinate Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After record coordinate geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale record-coordinate table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale row'));
            $t->true(!str_contains($result['text'], 'Stale column'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same(['page_image' => 6], $coordinateReview['source_record_coordinate_spaces']['cells'] ?? null);
            $t->same(4, $result['metadata']['table_assigned_crop_boundary_reviews'][0]['active_cell_count'] ?? null);
            $t->same(2, $result['metadata']['table_assigned_crop_boundary_reviews'][0]['excluded_cell_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same('page_image', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
