<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_nested_source_crop_coordinate_space_page(array $lines): array
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

function markerpdf_nested_source_crop_coordinate_space_norm_page(array $bbox): array
{
    return [
        round(((float) $bbox[0] / 612.0) * 1000.0, 6),
        round(((float) $bbox[1] / 792.0) * 1000.0, 6),
        round(((float) $bbox[2] / 612.0) * 1000.0, 6),
        round(((float) $bbox[3] / 792.0) * 1000.0, 6),
    ];
}

function markerpdf_nested_source_crop_coordinate_space_round(array $bbox): array
{
    return array_map(
        static fn (mixed $value): float => round((float) $value, 1),
        $bbox
    );
}

function markerpdf_nested_source_crop_coordinate_space_table(): array
{
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'table_image' => [
            'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([72.0, 150.0, 312.0, 230.0]),
            'source_coordinate_space' => 'normalized_page_image',
            'crop_width' => 240,
            'crop_height' => 80,
        ],
        'rows' => [
            ['row_id' => 0, 'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([72.0, 150.0, 312.0, 182.0]), 'source_coordinate_space' => 'normalized_page_image'],
            ['row_id' => 1, 'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([72.0, 190.0, 312.0, 220.0]), 'source_coordinate_space' => 'normalized_page_image'],
            ['row_id' => 99, 'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([72.0, 250.0, 312.0, 270.0]), 'source_coordinate_space' => 'normalized_page_image'],
        ],
        'cols' => [
            ['col_id' => 0, 'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([72.0, 150.0, 172.0, 230.0]), 'source_coordinate_space' => 'normalized_page_image'],
            ['col_id' => 1, 'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([192.0, 150.0, 312.0, 230.0]), 'source_coordinate_space' => 'normalized_page_image'],
            ['col_id' => 99, 'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([342.0, 150.0, 362.0, 230.0]), 'source_coordinate_space' => 'normalized_page_image'],
        ],
        'cells' => [
            ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([82.0, 155.0, 162.0, 170.0]), 'source_coordinate_space' => 'normalized_page_image', 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([202.0, 155.0, 302.0, 170.0]), 'source_coordinate_space' => 'normalized_page_image', 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([82.0, 195.0, 162.0, 215.0]), 'source_coordinate_space' => 'normalized_page_image', 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([202.0, 195.0, 302.0, 215.0]), 'source_coordinate_space' => 'normalized_page_image', 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([82.0, 250.0, 162.0, 268.0]), 'source_coordinate_space' => 'normalized_page_image', 'text' => 'Stale normalized source row', 'row_ids' => [99], 'col_ids' => [0]],
            ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([360.0, 195.0, 382.0, 215.0]), 'source_coordinate_space' => 'normalized_page_image', 'text' => 'Stale normalized source col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide normalized source crop OCR',
            'source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([82.0, 155.0, 302.0, 215.0]),
            'source_coordinate_space' => 'normalized_page_image',
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([82.0, 155.0, 162.0, 170.0])],
                ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([202.0, 155.0, 302.0, 170.0])],
                ['source_bbox' => markerpdf_nested_source_crop_coordinate_space_norm_page([82.0, 195.0, 162.0, 215.0])],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

return [
    'unwraps normalized page-image source bbox from nested saved table crop metadata' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_nested_source_crop_coordinate_space_table()],
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

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_and_normalized_to_table_crop', $review['status'] ?? null);
        $t->same('table_image.source_bbox', $review['table_bbox_source'] ?? null);
        $t->same('normalized_page_image', $review['table_bbox_source_coordinate_space'] ?? null);
        $t->same(markerpdf_nested_source_crop_coordinate_space_table()['table_image']['source_bbox'], $review['source_table_bbox'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], markerpdf_nested_source_crop_coordinate_space_round($review['table_bbox'] ?? []));
        $t->same(['width' => 612, 'height' => 792], $review['table_bbox_page_image_normalization_size'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], [
            'x' => round((float) ($review['translation']['x'] ?? 0.0), 1),
            'y' => round((float) ($review['translation']['y'] ?? 0.0), 1),
        ]);
        $t->same(['normalized_page_image' => 3], $review['source_record_coordinate_spaces']['rows'] ?? null);
        $t->same(['normalized_page_image' => 6], $review['source_record_coordinate_spaces']['cells'] ?? null);
        $t->same(3, $review['normalized_row_band_count'] ?? null);
        $t->same(3, $review['normalized_col_band_count'] ?? null);
        $t->same(6, $review['normalized_cell_count'] ?? null);
        $t->same(1, $review['normalized_conflict_count'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], markerpdf_nested_source_crop_coordinate_space_round($localized['rows'][0]['bbox'] ?? []));
        $t->same([120.0, 0.0, 240.0, 80.0], markerpdf_nested_source_crop_coordinate_space_round($localized['cols'][1]['bbox'] ?? []));
        $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_nested_source_crop_coordinate_space_round($localized['cells'][0]['bbox'] ?? []));
        $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_nested_source_crop_coordinate_space_round($localized['cells'][0]['source_page_image_bbox'] ?? []));
        $t->same('source_bbox', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same('normalized_page_image', $localized['cells'][0]['source_coordinate_space'] ?? null);
        $t->same([10.0, 5.0, 230.0, 65.0], markerpdf_nested_source_crop_coordinate_space_round($conflict['bbox'] ?? []));
        $t->same([82.0, 155.0, 302.0, 215.0], markerpdf_nested_source_crop_coordinate_space_round($conflict['source_page_image_bbox'] ?? []));
        $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_nested_source_crop_coordinate_space_round($conflict['candidate_cell_bboxes'][0] ?? []));
        $t->same('source_bbox', $conflict['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale normalized source row', $assignedTexts, true));
        $t->true(!in_array('Stale normalized source col', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['active_row_band_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['active_col_band_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
        $t->same('normalized_page_image', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_nested_source_crop_coordinate_space_round($gridReview['render_cells'][0]['source_page_image_bbox'] ?? []));
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces nested source crop coordinate-space review through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-nested-source-crop-coordinate-space-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% nested source crop coordinate-space boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_nested_source_crop_coordinate_space_page([
                        ['text' => 'Nested Source Crop Coordinate Space', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale nested-source crop table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After nested source crop table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_nested_source_crop_coordinate_space_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Nested Source Crop Coordinate Space', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After nested source crop table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale nested-source crop table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale normalized source row'));
            $t->true(!str_contains($result['text'], 'Stale normalized source col'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_and_normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same(['normalized_page_image' => 3], $coordinateReview['source_record_coordinate_spaces']['rows'] ?? null);
            $t->same(['normalized_page_image' => 6], $coordinateReview['source_record_coordinate_spaces']['cells'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], markerpdf_nested_source_crop_coordinate_space_round($coordinateReview['table_bbox'] ?? []));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same('source_bbox', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same('normalized_page_image', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_nested_source_crop_coordinate_space_round($gridReview['render_cells'][0]['source_page_image_bbox'] ?? []));
            $t->same([[72.0, 150.0, 312.0, 230.0]], $metadata['table_plan']['table_bboxes'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
