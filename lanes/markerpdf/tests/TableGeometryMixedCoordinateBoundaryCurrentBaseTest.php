<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_mixed_coordinate_boundary_page(array $lines): array
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

function markerpdf_mixed_coordinate_boundary_norm_page_bbox(array $bbox): array
{
    return [
        round(((float) $bbox[0] / 612.0) * 1000.0, 6),
        round(((float) $bbox[1] / 792.0) * 1000.0, 6),
        round(((float) $bbox[2] / 612.0) * 1000.0, 6),
        round(((float) $bbox[3] / 792.0) * 1000.0, 6),
    ];
}

function markerpdf_mixed_coordinate_boundary_norm_table_bbox(array $bbox): array
{
    return [
        round(((float) $bbox[0] / 240.0) * 1000.0, 6),
        round(((float) $bbox[1] / 80.0) * 1000.0, 6),
        round(((float) $bbox[2] / 240.0) * 1000.0, 6),
        round(((float) $bbox[3] / 80.0) * 1000.0, 6),
    ];
}

function markerpdf_mixed_coordinate_boundary_round_bbox(array $bbox): array
{
    return array_map(
        static fn (mixed $value): float => round((float) $value, 1),
        $bbox
    );
}

function markerpdf_mixed_coordinate_boundary_table(): array
{
    return [
        'table_bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0], 'coordinate_space' => 'page_image'],
            ['row_id' => 1, 'bbox' => markerpdf_mixed_coordinate_boundary_norm_page_bbox([72.0, 190.0, 312.0, 220.0]), 'coordinate_space' => 'normalized_page_image'],
            ['row_id' => 99, 'bbox' => markerpdf_mixed_coordinate_boundary_norm_table_bbox([0.0, 100.0, 240.0, 118.0]), 'coordinate_space' => 'normalized_table'],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => markerpdf_mixed_coordinate_boundary_norm_table_bbox([0.0, 0.0, 100.0, 80.0]), 'coordinate_space' => 'normalized_table'],
            ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0], 'coordinate_space' => 'page_image'],
            ['col_id' => 99, 'bbox' => markerpdf_mixed_coordinate_boundary_norm_page_bbox([340.0, 150.0, 360.0, 230.0]), 'coordinate_space' => 'normalized_page_image'],
        ],
        'cells' => [
            ['bbox' => markerpdf_mixed_coordinate_boundary_norm_table_bbox([10.0, 5.0, 90.0, 20.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0], 'coordinate_space' => 'normalized_table'],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1], 'coordinate_space' => 'page_image'],
            ['bbox' => markerpdf_mixed_coordinate_boundary_norm_page_bbox([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0], 'coordinate_space' => 'normalized_page_image'],
            ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale page row', 'row_ids' => [99], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
            ['bbox' => markerpdf_mixed_coordinate_boundary_norm_table_bbox([288.0, 45.0, 310.0, 65.0]), 'text' => 'Stale normalized col', 'row_ids' => [1], 'col_ids' => [99], 'coordinate_space' => 'normalized_table'],
        ],
        'ocr_grid_border_conflicts' => [
            [
                'ocr_index' => 0,
                'text' => 'Wide normalized page OCR',
                'bbox' => markerpdf_mixed_coordinate_boundary_norm_page_bbox([82.0, 155.0, 302.0, 215.0]),
                'candidate_cell_indexes' => [0, 1, 2],
                'candidate_cell_bboxes' => [
                    markerpdf_mixed_coordinate_boundary_norm_page_bbox([82.0, 155.0, 162.0, 170.0]),
                    markerpdf_mixed_coordinate_boundary_norm_page_bbox([202.0, 155.0, 302.0, 170.0]),
                    markerpdf_mixed_coordinate_boundary_norm_page_bbox([82.0, 195.0, 162.0, 215.0]),
                ],
                'assigned_cell_index' => 0,
                'spans_grid_border' => true,
                'coordinate_space' => 'normalized_page_image',
            ],
            [
                'ocr_index' => 1,
                'text' => 'Wide normalized crop OCR',
                'bbox' => markerpdf_mixed_coordinate_boundary_norm_table_bbox([10.0, 5.0, 230.0, 20.0]),
                'candidate_cell_indexes' => [0, 1],
                'candidate_cell_bboxes' => [
                    markerpdf_mixed_coordinate_boundary_norm_table_bbox([10.0, 5.0, 90.0, 20.0]),
                    markerpdf_mixed_coordinate_boundary_norm_table_bbox([130.0, 5.0, 230.0, 20.0]),
                ],
                'assigned_cell_index' => 1,
                'spans_grid_border' => true,
                'coordinate_space' => 'normalized_table',
            ],
            [
                'ocr_index' => 2,
                'text' => 'Wide page OCR',
                'bbox' => [82.0, 195.0, 302.0, 215.0],
                'candidate_cell_indexes' => [2, 3],
                'candidate_cell_bboxes' => [
                    [82.0, 195.0, 162.0, 215.0],
                    [202.0, 195.0, 302.0, 215.0],
                ],
                'assigned_cell_index' => 2,
                'spans_grid_border' => true,
                'coordinate_space' => 'page_image',
            ],
        ],
    ];
}

return [
    'accumulates mixed table conflict coordinate-space counters while localizing records' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_mixed_coordinate_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $conflicts = $localized['ocr_grid_border_conflicts'] ?? [];
        $cropBoundary = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_and_normalized_to_table_crop', $review['status'] ?? null);
        $t->same(2, $review['translated_row_band_count'] ?? null);
        $t->same(2, $review['normalized_row_band_count'] ?? null);
        $t->same(2, $review['translated_col_band_count'] ?? null);
        $t->same(2, $review['normalized_col_band_count'] ?? null);
        $t->same(3, $review['translated_cell_count'] ?? null);
        $t->same(3, $review['normalized_cell_count'] ?? null);
        $t->same(2, $review['translated_conflict_count'] ?? null);
        $t->same(2, $review['normalized_conflict_count'] ?? null);
        $t->same(['normalized_page_image' => 1, 'normalized_table' => 1, 'page_image' => 1], $review['source_record_coordinate_spaces']['conflicts'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], markerpdf_mixed_coordinate_boundary_round_bbox($localized['rows'][1]['bbox'] ?? []));
        $t->same([0.0, 0.0, 100.0, 80.0], markerpdf_mixed_coordinate_boundary_round_bbox($localized['cols'][0]['bbox'] ?? []));
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_mixed_coordinate_boundary_round_bbox($localized['cells'][0]['bbox'] ?? []));
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same([10.0, 45.0, 90.0, 65.0], markerpdf_mixed_coordinate_boundary_round_bbox($localized['cells'][2]['bbox'] ?? []));
        $t->same('table_crop', $localized['coordinate_space'] ?? null);
        $t->same('table_crop', $conflicts[0]['coordinate_space'] ?? null);
        $t->same('table_crop', $conflicts[1]['coordinate_space'] ?? null);
        $t->same('table_crop', $conflicts[2]['coordinate_space'] ?? null);
        $t->same([10.0, 5.0, 230.0, 65.0], markerpdf_mixed_coordinate_boundary_round_bbox($conflicts[0]['bbox'] ?? []));
        $t->same([10.0, 5.0, 230.0, 20.0], markerpdf_mixed_coordinate_boundary_round_bbox($conflicts[1]['bbox'] ?? []));
        $t->same([10.0, 45.0, 230.0, 65.0], $conflicts[2]['bbox'] ?? null);
        $t->same([82.0, 155.0, 302.0, 215.0], markerpdf_mixed_coordinate_boundary_round_bbox($conflicts[0]['source_page_image_bbox'] ?? []));
        $t->same([82.0, 195.0, 302.0, 215.0], $conflicts[2]['source_bbox'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale page row', $assignedTexts, true));
        $t->true(!in_array('Stale normalized col', $assignedTexts, true));
        $t->same(6, $cropBoundary['cell_count'] ?? null);
        $t->same(4, $cropBoundary['active_cell_count'] ?? null);
        $t->same(2, $cropBoundary['excluded_cell_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces mixed table coordinate-space review through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-mixed-coordinate-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% mixed table coordinate boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_mixed_coordinate_boundary_page([
                        ['text' => 'Mixed coordinate table geometry review', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale mixed-coordinate table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After mixed coordinate table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_mixed_coordinate_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];

            $t->contains('# Mixed Coordinate Table Geometry Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After mixed coordinate table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale mixed-coordinate table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale page row'));
            $t->true(!str_contains($result['text'], 'Stale normalized col'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same('translated_and_normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same(2, $coordinateReview['translated_conflict_count'] ?? null);
            $t->same(2, $coordinateReview['normalized_conflict_count'] ?? null);
            $t->same(['normalized_page_image' => 1, 'normalized_table' => 1, 'page_image' => 1], $coordinateReview['source_record_coordinate_spaces']['conflicts'] ?? null);
            $t->same(4, $result['metadata']['table_assigned_crop_boundary_reviews'][0]['active_cell_count'] ?? null);
            $t->same(2, $result['metadata']['table_assigned_crop_boundary_reviews'][0]['excluded_cell_count'] ?? null);
            $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
            $t->same('normalized_table', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
            $t->same('page_image', $gridReview['render_cells'][1]['source_coordinate_space'] ?? null);
            $t->same('normalized_page_image', $gridReview['render_cells'][2]['source_coordinate_space'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
