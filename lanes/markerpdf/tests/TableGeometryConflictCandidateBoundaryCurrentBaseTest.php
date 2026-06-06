<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_conflict_candidate_boundary_page(array $lines): array
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

function markerpdf_conflict_candidate_norm_page_bbox(array $bbox): array
{
    return [
        round(((float) $bbox[0] / 612.0) * 1000.0, 6),
        round(((float) $bbox[1] / 792.0) * 1000.0, 6),
        round(((float) $bbox[2] / 612.0) * 1000.0, 6),
        round(((float) $bbox[3] / 792.0) * 1000.0, 6),
    ];
}

function markerpdf_conflict_candidate_norm_table_bbox(array $bbox): array
{
    return [
        round(((float) $bbox[0] / 240.0) * 1000.0, 6),
        round(((float) $bbox[1] / 80.0) * 1000.0, 6),
        round(((float) $bbox[2] / 240.0) * 1000.0, 6),
        round(((float) $bbox[3] / 80.0) * 1000.0, 6),
    ];
}

function markerpdf_conflict_candidate_round_bboxes(array $bboxes): array
{
    return array_map(
        static fn (array $bbox): array => array_map(static fn (mixed $value): float => round((float) $value, 1), $bbox),
        $bboxes
    );
}

function markerpdf_conflict_candidate_table(): array
{
    return [
        'table_bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 5.0, 90.0, 24.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 5.0, 230.0, 24.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Mixed candidate OCR',
            'bbox' => [0.0, 0.0, 240.0, 65.0],
            'coordinate_space' => 'table_crop',
            'candidate_cell_indexes' => [0, 1, 2, 3],
            'candidate_cell_bboxes' => [
                ['bbox' => [82.0, 155.0, 162.0, 174.0], 'coordinate_space' => 'page_image'],
                ['bbox' => [130.0, 5.0, 230.0, 24.0], 'coordinate_space' => 'table_crop'],
                ['bbox' => markerpdf_conflict_candidate_norm_page_bbox([82.0, 195.0, 162.0, 215.0]), 'coordinate_space' => 'normalized_page_image'],
                ['bbox' => markerpdf_conflict_candidate_norm_table_bbox([130.0, 45.0, 230.0, 65.0]), 'coordinate_space' => 'normalized_table'],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

return [
    'localizes per-candidate OCR conflict geometry before table grid review' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_conflict_candidate_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $gridReview = $recognizer->gridBorderConflictReview(
            $localized['ocr_grid_border_conflicts'] ?? [],
            $formatted['assigned_cells'][0] ?? [],
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );
        $gridConflict = $gridReview[0] ?? [];

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_and_normalized_to_table_crop', $review['status'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same(1, $review['normalized_conflict_count'] ?? null);
        $t->same([
            'normalized_page_image' => 1,
            'normalized_table' => 1,
            'page_image' => 1,
            'table_crop' => 2,
        ], $review['source_record_coordinate_spaces']['conflicts'] ?? null);
        $t->same([
            [10.0, 5.0, 90.0, 24.0],
            [130.0, 5.0, 230.0, 24.0],
            [10.0, 45.0, 90.0, 65.0],
            [130.0, 45.0, 230.0, 65.0],
        ], markerpdf_conflict_candidate_round_bboxes($conflict['candidate_cell_bboxes'] ?? []));
        $t->same([
            'page_image',
            'table_crop',
            'normalized_page_image',
            'normalized_table',
        ], $conflict['source_candidate_coordinate_spaces'] ?? null);
        $t->same([
            'bbox_array',
            'bbox_array',
            'bbox_array',
            'bbox_array',
        ], $conflict['source_candidate_coordinate_sources'] ?? null);
        $t->same([0.0, 0.0, 240.0, 65.0], $conflict['bbox'] ?? null);
        $t->same('table_crop', $conflict['coordinate_space'] ?? null);
        $t->same('both', $gridConflict['grid_border_axis'] ?? null);
        $t->same([0, 1], $gridConflict['candidate_row_ids'] ?? null);
        $t->same([0, 1], $gridConflict['candidate_col_ids'] ?? null);
        $t->same([130.0, 45.0, 230.0, 65.0], markerpdf_conflict_candidate_round_bboxes([$gridConflict['candidate_grid_cells'][3]['bbox'] ?? []])[0]);
    },
    'surfaces per-candidate OCR conflict geometry through supplied WordPress conversion' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-conflict-candidate-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table conflict candidate geometry current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_conflict_candidate_boundary_page([
                        ['text' => 'Candidate table geometry review', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale candidate table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                        ['text' => 'After candidate geometry review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_conflict_candidate_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $conflict = $result['metadata']['table_ocr_grid_border_conflicts'][0][0] ?? [];

            $t->contains('# Candidate Table Geometry Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After candidate geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale candidate table line should be replaced.'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('translated_and_normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same([
                'page_image',
                'table_crop',
                'normalized_page_image',
                'normalized_table',
            ], $conflict['source_candidate_coordinate_spaces'] ?? null);
            $t->same([130.0, 45.0, 230.0, 65.0], markerpdf_conflict_candidate_round_bboxes([$conflict['candidate_grid_cells'][3]['bbox'] ?? []])[0]);
            $t->same('Ready', $conflict['candidate_grid_cells'][3]['text'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
