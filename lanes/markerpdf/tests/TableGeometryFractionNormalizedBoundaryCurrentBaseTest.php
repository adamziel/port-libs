<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_fraction_normalized_boundary_table(): array
{
    return [
        'coordinate_space' => 'normalized_table',
        'rows' => [
            ['id' => 1, 'bbox' => [0.0, 0.0, 1.0, 0.3]],
            ['id' => 2, 'bbox' => [0.0, 0.4, 1.0, 0.75]],
            ['id' => 99, 'bbox' => [0.0, 1.15, 1.0, 1.3]],
        ],
        'cols' => [
            ['id' => 1, 'bbox' => [0.0, 0.0, 0.4, 1.0]],
            ['id' => 2, 'bbox' => [0.5, 0.0, 0.9, 1.0]],
            ['id' => 99, 'bbox' => [1.1, 0.0, 1.2, 1.0]],
        ],
        'cells' => [
            [
                'row_id' => 1,
                'col_id' => 1,
                'bbox' => [0.04, 0.05, 0.36, 0.24],
                'text' => 'Feature',
            ],
            [
                'row_id' => 1,
                'col_id' => 2,
                'bbox' => [0.52, 0.05, 0.88, 0.24],
                'text' => 'Status',
            ],
            [
                'row_id' => 2,
                'col_id' => 1,
                'bbox' => [0.04, 0.45, 0.36, 0.66],
                'text' => 'Images',
            ],
            [
                'row_id' => 2,
                'col_id' => 2,
                'bbox' => [0.52, 0.45, 0.88, 0.66],
                'text' => 'Ready',
            ],
            [
                'row_id' => 2,
                'col_id' => 99,
                'bbox' => [1.11, 0.45, 1.2, 0.66],
                'text' => 'Stale X',
            ],
            [
                'row_id' => 99,
                'col_id' => 1,
                'bbox' => [0.04, 1.16, 0.36, 1.3],
                'text' => 'Stale Y',
            ],
        ],
        'ocr_grid_border_conflicts' => [
            [
                'bbox' => [0.0, 0.0, 0.9, 0.75],
                'source' => 'fractional-normalized-grid',
                'candidate_cell_bboxes' => [
                    [0.04, 0.05, 0.36, 0.24],
                    [0.52, 0.45, 0.88, 0.66],
                ],
            ],
        ],
    ];
}

function markerpdf_fraction_normalized_boundary_pdftext_page(array $lines): array
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

return [
    'table geometry boundary current base maps fraction normalized table crop bboxes' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $result = $recognizer->formatRecognizedTables(
            [
                markerpdf_fraction_normalized_boundary_table(),
            ],
            [
                ['width' => 250, 'height' => 100],
            ]
        );

        $localized = $result['recognized_tables'][0];
        $review = $result['coordinate_space_reviews'][0] ?? [];
        $assigned = $result['assigned_cells'][0] ?? [];
        $grid = $recognizer->spanningGridReview($assigned, $localized['rows'], $localized['cols'], ['width' => 250, 'height' => 100]);
        $boundary = $grid['geometry_boundary_review'] ?? [];

        $t->same('table_crop', $localized['coordinate_space']);
        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('normalized_to_table_crop', $review['status'] ?? null);
        $t->same([1.0], $review['normalization_denominators'] ?? null);
        $t->same(['x' => 250.0, 'y' => 100.0], $review['normalization_scale'] ?? null);

        $t->same([0.0, 0.0, 250.0, 30.0], $localized['rows'][0]['bbox']);
        $t->same([125.0, 0.0, 225.0, 100.0], $localized['cols'][1]['bbox']);
        $t->same([10.0, 5.0, 90.0, 24.0], $localized['cells'][0]['bbox']);
        $t->same([277.5, 45.0, 300.0, 66.0], $localized['cells'][4]['bbox']);
        $t->same([0.0, 0.0, 225.0, 75.0], $localized['ocr_grid_border_conflicts'][0]['bbox']);
        $t->same([10.0, 5.0, 90.0, 24.0], $localized['ocr_grid_border_conflicts'][0]['candidate_cell_bboxes'][0]);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($assigned, 'text'));
        $t->contains('| Feature | Status |', $result['markdown_tables'][0]);
        $t->contains('| Images  | Ready  |', $result['markdown_tables'][0]);
        $t->true(!str_contains($result['markdown_tables'][0], 'Stale X'));
        $t->true(!str_contains($result['markdown_tables'][0], 'Stale Y'));
        $t->same(2, $boundary['active_row_band_count'] ?? null);
        $t->same(2, $boundary['active_col_band_count'] ?? null);
        $t->same(2, $boundary['excluded_band_count'] ?? null);
    },

    'supplied converter boundary current base renders fraction normalized table cells' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-fraction-normalized-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table fraction normalized geometry boundary supplied pipeline\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_fraction_normalized_boundary_pdftext_page([
                        ['text' => 'Fraction normalized table geometry review', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale fraction normalized table line should be replaced.', 'bbox' => [72.0, 226.0, 370.0, 246.0]],
                        ['text' => 'After fraction normalized geometry review.', 'bbox' => [72.0, 336.0, 500.0, 354.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [50.0, 200.0, 300.0, 300.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 336.0, 500.0, 354.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_fraction_normalized_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $boundary = $gridReview['geometry_boundary_review'] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Fraction Normalized Table Geometry Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After fraction normalized geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale fraction normalized table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale X'));
            $t->true(!str_contains($result['text'], 'Stale Y'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('table_recognition_coordinate_space_boundary', $coordinateReview['review_target'] ?? null);
            $t->same('normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same([1.0], $coordinateReview['normalization_denominators'] ?? null);
            $t->same(['x' => 250.0, 'y' => 100.0], $coordinateReview['normalization_scale'] ?? null);
            $t->same(6, $coordinateReview['normalized_cell_count'] ?? null);
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
