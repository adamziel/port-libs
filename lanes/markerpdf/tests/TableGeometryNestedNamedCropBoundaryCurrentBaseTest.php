<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_nested_named_crop_page(array $lines): array
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

function markerpdf_nested_named_crop_round(array $bbox): array
{
    return array_map(
        static fn (mixed $value): float => round((float) $value, 1),
        $bbox
    );
}

function markerpdf_nested_named_crop_table(): array
{
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'coordinate_space' => 'page_image',
        'table_image' => [
            'left' => 72.0,
            'top' => 150.0,
            'width' => 240.0,
            'height' => 80.0,
            'coordinate_space' => 'page_image',
            'crop_width' => 240,
            'crop_height' => 80,
        ],
        'rows' => [
            ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
            ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
            ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale named row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale named col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide named crop OCR',
            'bbox' => [82.0, 155.0, 302.0, 215.0],
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                [82.0, 155.0, 162.0, 170.0],
                [202.0, 155.0, 302.0, 170.0],
                [82.0, 195.0, 162.0, 215.0],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

return [
    'unwraps nested named crop fields before supplied table assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_nested_named_crop_table()],
            [[]]
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

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('table_image.bbox_left_top_width_height_fields', $review['table_bbox_source'] ?? null);
        $t->same('page_image', $review['table_bbox_source_coordinate_space'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], markerpdf_nested_named_crop_round($review['table_bbox'] ?? []));
        $t->same(['width' => 240, 'height' => 80], $review['table_crop_size'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], markerpdf_nested_named_crop_round($localized['rows'][0]['bbox'] ?? []));
        $t->same([120.0, 0.0, 240.0, 80.0], markerpdf_nested_named_crop_round($localized['cols'][1]['bbox'] ?? []));
        $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_nested_named_crop_round($localized['cells'][0]['bbox'] ?? []));
        $t->same([10.0, 5.0, 230.0, 65.0], markerpdf_nested_named_crop_round($conflict['bbox'] ?? []));
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], array_map('markerpdf_nested_named_crop_round', $conflict['candidate_cell_bboxes'] ?? []));
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale named row', $assignedTexts, true));
        $t->true(!in_array('Stale named col', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['active_row_band_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['active_col_band_count'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces nested named crop table geometry through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-nested-named-crop-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% nested named crop table geometry boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_nested_named_crop_page([
                        ['text' => 'Nested Named Crop Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale named crop table line should be replaced.', 'bbox' => [82.0, 176.0, 340.0, 196.0]],
                        ['text' => 'After nested named crop table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_nested_named_crop_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Nested Named Crop Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After nested named crop table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale named crop table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale named row'));
            $t->true(!str_contains($result['text'], 'Stale named col'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], markerpdf_nested_named_crop_round($coordinateReview['table_bbox'] ?? []));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $metadata['table_plan']['table_bboxes'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
