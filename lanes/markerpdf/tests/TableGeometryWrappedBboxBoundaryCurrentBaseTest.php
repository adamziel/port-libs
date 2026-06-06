<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_wrapped_bbox_boundary_page(array $lines): array
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

function markerpdf_wrapped_bbox_boundary_box(float $x1, float $y1, float $x2, float $y2): array
{
    return [
        'bbox' => [$x1, $y1, $x2, $y2],
        'source' => 'tabled.Bbox.model_dump',
    ];
}

function markerpdf_wrapped_bbox_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'bbox' => markerpdf_wrapped_bbox_boundary_box(72.0, 150.0, 312.0, 230.0),
        'image_bbox' => markerpdf_wrapped_bbox_boundary_box(0.0, 0.0, 612.0, 792.0),
        'rows' => [
            ['row_id' => 0, 'bbox' => markerpdf_wrapped_bbox_boundary_box(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1, 'bbox' => markerpdf_wrapped_bbox_boundary_box(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => markerpdf_wrapped_bbox_boundary_box(72.0, 250.0, 312.0, 270.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => markerpdf_wrapped_bbox_boundary_box(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 1, 'bbox' => markerpdf_wrapped_bbox_boundary_box(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => markerpdf_wrapped_bbox_boundary_box(342.0, 150.0, 362.0, 230.0)],
        ],
        'cells' => [
            ['bbox' => markerpdf_wrapped_bbox_boundary_box(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => markerpdf_wrapped_bbox_boundary_box(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => markerpdf_wrapped_bbox_boundary_box(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => markerpdf_wrapped_bbox_boundary_box(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => markerpdf_wrapped_bbox_boundary_box(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale wrapped row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => markerpdf_wrapped_bbox_boundary_box(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale wrapped col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide wrapped bbox OCR',
            'bbox' => markerpdf_wrapped_bbox_boundary_box(82.0, 155.0, 302.0, 215.0),
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                markerpdf_wrapped_bbox_boundary_box(82.0, 155.0, 162.0, 170.0),
                markerpdf_wrapped_bbox_boundary_box(202.0, 155.0, 302.0, 170.0),
                markerpdf_wrapped_bbox_boundary_box(82.0, 195.0, 162.0, 215.0),
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

return [
    'unwraps Bbox-shaped supplied table geometry before table crop localization' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_wrapped_bbox_boundary_table()],
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
        $t->same('bbox.bbox_array', $review['table_bbox_source'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $review['table_crop_size'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same('page_image', $review['source_coordinate_spaces']['rows'] ?? null);
        $t->same('page_image', $review['source_coordinate_spaces']['cells'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('bbox.bbox_array', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same('page_image', $localized['cells'][0]['source_coordinate_space'] ?? null);
        $t->same([10.0, 5.0, 230.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same('bbox.bbox_array', $conflict['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale wrapped row', $assignedTexts, true));
        $t->true(!in_array('Stale wrapped col', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('bbox.bbox_array', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox.bbox_array', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces wrapped Bbox table geometry through supplied WordPress conversion metadata' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-wrapped-bbox-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table wrapped bbox boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_wrapped_bbox_boundary_page([
                        ['text' => 'Wrapped bbox table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale wrapped-bbox table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After wrapped bbox table review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_wrapped_bbox_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

            $t->contains('# Wrapped Bbox Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After wrapped bbox table review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale wrapped-bbox table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale wrapped row'));
            $t->true(!str_contains($result['text'], 'Stale wrapped col'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('page_image', $coordinateReview['source_coordinate_spaces']['cells'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same('bbox.bbox_array', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
