<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_wrapped_alias_boundary_page(array $lines): array
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

function markerpdf_wrapped_alias_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'bbox' => ['stale' => 'not-a-geometry'],
        'box' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => ['box' => [0.0, 0.0, 612.0, 792.0]],
        'rows' => [
            ['row_id' => 0, 'bbox' => ['stale' => 'not-a-geometry'], 'box' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 1, 'rect' => ['left' => 72.0, 'top' => 190.0, 'right' => 312.0, 'bottom' => 220.0]],
            ['row_id' => 99, 'bounds' => ['x' => 72.0, 'y' => 250.0, 'width' => 240.0, 'height' => 20.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bounds' => ['x' => 72.0, 'y' => 150.0, 'width' => 100.0, 'height' => 80.0]],
            ['col_id' => 1, 'bounding_box' => ['center' => ['x' => 252.0, 'y' => 190.0], 'size' => ['width' => 120.0, 'height' => 80.0]]],
            ['col_id' => 99, 'rectangle' => [342.0, 150.0, 362.0, 230.0]],
        ],
        'cells' => [
            ['bbox' => ['stale' => 'not-a-geometry'], 'box' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['rect' => ['left' => 202.0, 'top' => 155.0, 'right' => 302.0, 'bottom' => 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bounds' => ['x' => 82.0, 'y' => 195.0, 'width' => 80.0, 'height' => 20.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bounding_box' => ['center' => ['x' => 252.0, 'y' => 205.0], 'width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['box' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale alias row', 'row_ids' => [99], 'col_ids' => [0]],
            ['rectangle' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale alias col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide alias OCR',
            'rect' => ['left' => 82.0, 'top' => 155.0, 'right' => 302.0, 'bottom' => 215.0],
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                ['box' => [82.0, 155.0, 162.0, 170.0]],
                ['rect' => ['left' => 202.0, 'top' => 155.0, 'right' => 302.0, 'bottom' => 170.0]],
                ['bounds' => ['x' => 82.0, 'y' => 195.0, 'width' => 80.0, 'height' => 20.0]],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

return [
    'localizes record-level wrapped alias table geometry before table crop review' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_wrapped_alias_boundary_table()],
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
        $t->same('table_crop_bbox_extent', $review['image_size_source'] ?? null);
        $t->same('box.bbox_array', $review['table_bbox_source'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $review['table_crop_size'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same([10.0, 45.0, 90.0, 65.0], $localized['cells'][2]['bbox'] ?? null);
        $t->same([130.0, 45.0, 230.0, 65.0], $localized['cells'][3]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('box.bbox_array', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same('rect.bbox_left_top_right_bottom_fields', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same('bounds.bbox_xy_width_height_fields', $localized['cells'][2]['source_coordinate_source'] ?? null);
        $t->same('bounding_box.bbox_center_width_height_fields', $localized['cells'][3]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 230.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same('rect.bbox_left_top_right_bottom_fields', $conflict['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale alias row', $assignedTexts, true));
        $t->true(!in_array('Stale alias col', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('box.bbox_array', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('rect.bbox_left_top_right_bottom_fields', $gridReview['geometry_boundary_review']['row_bands'][1]['source_coordinate_source'] ?? null);
        $t->same('bounds.bbox_xy_width_height_fields', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bounding_box.bbox_center_size_fields', $gridReview['geometry_boundary_review']['col_bands'][1]['source_coordinate_source'] ?? null);
        $t->same('bounding_box.bbox_center_width_height_fields', $gridReview['render_cells'][3]['source_coordinate_source'] ?? null);
        $t->same([202.0, 195.0, 302.0, 215.0], $gridReview['render_cells'][3]['source_cell_bbox'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces record-level wrapped alias geometry through supplied WordPress conversion metadata' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-wrapped-alias-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table wrapped alias boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_wrapped_alias_boundary_page([
                        ['text' => 'Wrapped alias table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale wrapped-alias table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After wrapped alias table review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_wrapped_alias_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

            $t->contains('# Wrapped Alias Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After wrapped alias table review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale wrapped-alias table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale alias row'));
            $t->true(!str_contains($result['text'], 'Stale alias col'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('page_image', $coordinateReview['source_coordinate_spaces']['cells'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same('bounding_box.bbox_center_width_height_fields', $gridReview['render_cells'][3]['source_coordinate_source'] ?? null);
            $t->same([202.0, 195.0, 302.0, 215.0], $gridReview['render_cells'][3]['source_cell_bbox'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
